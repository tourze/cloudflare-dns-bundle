<?php

namespace CloudflareDnsBundle\Controller\Admin;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * 域名管理控制器
 */
class DnsDomainCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DnsRecordRepository $recordRepository,
        private readonly DnsRecordService $dnsService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return DnsDomain::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('域名')
            ->setEntityLabelInPlural('域名')
            ->setPageTitle('index', '域名列表')
            ->setPageTitle('new', '新增域名')
            ->setPageTitle('edit', fn(DnsDomain $domain) => sprintf('编辑域名: %s', $domain->getName()))
            ->setPageTitle('detail', fn(DnsDomain $domain) => sprintf('域名详情: %s', $domain->getName()))
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'name', 'zoneId', 'accountId', 'registrar', 'status']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $syncRecordsAction = Action::new('syncRecords', '从远程同步解析记录')
            ->setIcon('fa fa-cloud-download-alt')
            ->setCssClass('btn btn-primary')
            ->linkToCrudAction('syncRecords')
            ->displayIf(static function (DnsDomain $domain): bool {
                // 只有有效且有Zone ID的域名才能同步解析记录
                return $domain->isValid() && $domain->getZoneId() !== null;
            });

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $syncRecordsAction)
            ->add(Crud::PAGE_DETAIL, $syncRecordsAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'syncRecords', Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('iamKey'))
            ->add('name')
            ->add('zoneId')
            ->add('status')
            ->add('valid')
            ->add('createTime')
            ->add('updateTime');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setMaxLength(9999)->hideOnForm();
        yield AssociationField::new('iamKey', 'IAM账号');
        yield TextField::new('name', '根域名');
        yield TextField::new('zoneId', 'Zone ID');
        yield TextField::new('accountId', 'Account ID');
        yield TextField::new('status', '状态');

        yield DateTimeField::new('expiresAt', '过期时间')
            ->setColumns(6);
        yield DateTimeField::new('lockedUntil', '锁定截止时间')
            ->setColumns(6);
        yield BooleanField::new('autoRenew', '是否自动续费');

        yield BooleanField::new('valid', '有效');
        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm();
        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm();
    }

    /**
     * 从Cloudflare同步域名的DNS解析记录到本地
     */
    #[AdminAction('{entityId}/syncRecords', 'syncRecords')]
    public function syncRecords(AdminContext $context): Response
    {
        /** @var DnsDomain $domain */
        $domain = $context->getEntity()->getInstance();

        // 验证域名是否有效
        if (!$domain->isValid()) {
            $this->addFlash('danger', sprintf('域名 [%s] 无效，无法同步解析记录', $domain->getName()));
            return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($domain->getId())->generateUrl());
        }

        // 验证域名是否有Zone ID
        if (!$domain->getZoneId()) {
            $this->addFlash('danger', sprintf('域名 [%s] 未设置Zone ID，无法同步解析记录', $domain->getName()));
            return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($domain->getId())->generateUrl());
        }

        try {
            // 从Cloudflare获取该域名下的所有DNS记录
            $this->logger->info('开始从Cloudflare获取域名解析记录', [
                'domain' => $domain->getName(),
                'zoneId' => $domain->getZoneId(),
            ]);

            $response = $this->dnsService->listRecords($domain);

            if (empty($response['result'])) {
                $this->addFlash('warning', sprintf('域名 [%s] 在Cloudflare上没有任何解析记录', $domain->getName()));
                return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($domain->getId())->generateUrl());
            }

            $remoteRecords = $response['result'];
            $this->logger->info('从Cloudflare获取到解析记录', [
                'count' => count($remoteRecords),
            ]);

            // 获取本地已存在的记录，并建立索引
            $localRecords = $this->recordRepository->findBy(['domain' => $domain]);
            $localRecordMap = [];
            $localRecordByCloudflareId = [];

            foreach ($localRecords as $record) {
                $key = $record->getType()->value . '_' . $record->getRecord();
                $localRecordMap[$key] = $record;

                if ($record->getRecordId()) {
                    $localRecordByCloudflareId[$record->getRecordId()] = $record;
                }
            }

            $createCount = 0;
            $updateCount = 0;
            $skipCount = 0;
            $errorCount = 0;

            // 处理远程记录
            foreach ($remoteRecords as $remoteRecord) {
                try {
                    $recordType = $remoteRecord['type'] ?? null;
                    $recordName = $remoteRecord['name'] ?? '';
                    $recordContent = $remoteRecord['content'] ?? '';
                    $recordId = $remoteRecord['id'] ?? '';

                    // 去除域名后缀，获取子域名部分
                    $domainSuffix = '.' . $domain->getName();
                    if (str_ends_with($recordName, $domainSuffix)) {
                        $recordName = substr($recordName, 0, -strlen($domainSuffix));
                    }
                    // 处理根域名的情况
                    if ($recordName === $domain->getName()) {
                        $recordName = '@';
                    }

                    // 构建记录键名用于查找
                    $key = $recordType . '_' . $recordName;

                    // 如果本地已有此记录，则更新
                    if (isset($localRecordMap[$key]) || isset($localRecordByCloudflareId[$recordId])) {
                        // 优先使用ID匹配，其次使用类型和名称匹配
                        $localRecord = isset($localRecordByCloudflareId[$recordId]) 
                            ? $localRecordByCloudflareId[$recordId] 
                            : $localRecordMap[$key];

                        // 检查是否需要更新
                        $needUpdate = false;

                        // 更新记录ID（可能在本地已有记录但没有ID的情况）
                        if (!$localRecord->getRecordId() && $recordId) {
                            $localRecord->setRecordId($recordId);
                            $needUpdate = true;
                        }

                        // 检查内容是否有变化
                        if ($localRecord->getContent() !== $recordContent) {
                            $localRecord->setContent($recordContent);
                            $needUpdate = true;
                        }

                        // 更新其他字段
                        if (isset($remoteRecord['ttl']) && $localRecord->getTtl() != $remoteRecord['ttl']) {
                            $localRecord->setTtl($remoteRecord['ttl']);
                            $needUpdate = true;
                        }

                        if (isset($remoteRecord['proxied']) && $localRecord->isProxy() !== $remoteRecord['proxied']) {
                            $localRecord->setProxy($remoteRecord['proxied']);
                            $needUpdate = true;
                        }

                        if ($needUpdate) {
                            $localRecord->setSynced(true); // 设置为已同步
                            $localRecord->setLastSyncedAt(new \DateTime());
                            $this->entityManager->persist($localRecord);
                            $updateCount++;

                            $this->logger->info('更新本地DNS记录', [
                                'record' => $recordName,
                                'type' => $recordType,
                                'content' => $recordContent,
                            ]);
                        } else {
                            $skipCount++;
                        }
                    } else {
                        // 本地没有此记录，创建新记录
                        // 首先尝试将字符串类型转换为枚举类型
                        $enumType = null;
                        foreach (\CloudflareDnsBundle\Enum\DnsRecordType::cases() as $case) {
                            if ($case->value === $recordType) {
                                $enumType = $case;
                                break;
                            }
                        }

                        if ($enumType) {
                            $newRecord = new \CloudflareDnsBundle\Entity\DnsRecord();
                            $newRecord->setDomain($domain);
                            $newRecord->setType($enumType);
                            $newRecord->setRecord($recordName);
                            $newRecord->setRecordId($recordId);
                            $newRecord->setContent($recordContent);
                            $newRecord->setTtl($remoteRecord['ttl'] ?? 60);
                            $newRecord->setProxy($remoteRecord['proxied'] ?? false);
                            $newRecord->setSynced(true); // 设置为已同步

                            $this->entityManager->persist($newRecord);
                            $createCount++;

                            $this->logger->info('创建本地DNS记录', [
                                'record' => $recordName,
                                'type' => $recordType,
                                'content' => $recordContent,
                            ]);
                        } else {
                            $this->logger->warning('未知的DNS记录类型，跳过创建', [
                                'type' => $recordType,
                                'record' => $recordName,
                            ]);
                            $skipCount++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error('处理DNS记录时出错', [
                        'record' => $remoteRecord['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $errorCount++;
                }
            }

            // 提交所有变更
            $this->entityManager->flush();

            if ($createCount > 0 || $updateCount > 0) {
                $this->addFlash('success', sprintf(
                    '域名 [%s] 的解析记录同步完成，新增: %d，更新: %d，跳过: %d，失败: %d', 
                    $domain->getName(), $createCount, $updateCount, $skipCount, $errorCount
                ));
            } else if ($errorCount > 0) {
                $this->addFlash('danger', sprintf(
                    '域名 [%s] 的解析记录同步失败，新增: %d，更新: %d，跳过: %d，失败: %d', 
                    $domain->getName(), $createCount, $updateCount, $skipCount, $errorCount
                ));
            } else {
                $this->addFlash('warning', sprintf(
                    '域名 [%s] 的解析记录没有需要同步的变更，新增: %d，更新: %d，跳过: %d，失败: %d', 
                    $domain->getName(), $createCount, $updateCount, $skipCount, $errorCount
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('同步DNS记录时发生错误', [
                'domain' => $domain->getName(),
                'error' => $e->getMessage(),
            ]);

            $this->addFlash('danger', sprintf('同步域名 [%s] 的解析记录时发生错误: %s', $domain->getName(), $e->getMessage()));
        }

        return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($domain->getId())->generateUrl());
    }
}
