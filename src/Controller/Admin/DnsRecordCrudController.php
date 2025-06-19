<?php

namespace CloudflareDnsBundle\Controller\Admin;

use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Service\DnsRecordService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\Response;

/**
 * DNS记录管理控制器
 */
#[AdminCrud(routePath: '/cf-dns/record', routeName: 'cf_dns_record')]
class DnsRecordCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly DnsRecordService $dnsRecordService,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return DnsRecord::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('DNS记录')
            ->setEntityLabelInPlural('DNS记录')
            ->setPageTitle('index', 'DNS记录列表')
            ->setPageTitle('new', '新增DNS记录')
            ->setPageTitle('edit', fn (DnsRecord $record) => sprintf('编辑DNS记录: %s', $record->getFullName()))
            ->setPageTitle('detail', fn (DnsRecord $record) => sprintf('DNS记录详情: %s', $record->getFullName()))
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'record', 'recordId', 'content']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $syncAction = Action::new('syncToRemote', '同步到远程')
            ->setIcon('fa fa-cloud-upload')
            ->linkToCrudAction('syncToRemote')
            ->displayIf(static function (DnsRecord $record): bool {
                // 只有域名有zoneId才能同步
                return $record->getDomain() !== null && $record->getDomain()->getZoneId() !== null;
            });

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $syncAction)
            ->add(Crud::PAGE_DETAIL, $syncAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'syncToRemote', Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $choices = [];
        foreach (DnsRecordType::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }

        return $filters
            ->add(EntityFilter::new('domain'))
            ->add(ChoiceFilter::new('type', '记录类型')->setChoices($choices))
            ->add('record')
            ->add('recordId')
            ->add(ChoiceFilter::new('synced', '同步状态')->setChoices([
                '已同步' => true,
                '未同步' => false,
            ]))
            ->add('createTime')
            ->add('updateTime');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setMaxLength(9999)->onlyOnDetail();
        yield AssociationField::new('domain', '所属根域名');

        yield ChoiceField::new('type', '记录类型')
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => DnsRecordType::class,
            ])
            ->formatValue(function ($value) {
                return $value instanceof DnsRecordType ? $value->getLabel() : '';
            });

        yield TextField::new('record', '域名记录');
        yield TextField::new('recordId', '记录ID')->hideOnForm()->setHelp('自动生成，不需要手动填写');
        yield TextareaField::new('content', '记录值');
        yield IntegerField::new('ttl', 'TTL');
        yield BooleanField::new('proxy', '是否代理');

        yield BooleanField::new('synced', '已同步到远程')
            ->renderAsSwitch(false)
            ->setHelp('表示该记录是否已同步到Cloudflare')
            ->hideOnForm();

        yield DateTimeField::new('lastSyncedTime', '最后同步时间')
            ->hideOnForm()
            ->hideOnIndex();

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm();
        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm();
    }

    /**
     * 同步DNS记录到远程
     */
    #[AdminAction('{entityId}/syncToRemote', 'syncToRemote')]
    public function syncToRemote(AdminContext $context): Response
    {
        /** @var DnsRecord $record */
        $record = $context->getEntity()->getInstance();

        if ($record->isSyncing()) {
            $this->addFlash('warning', '记录正在同步中，请稍后再试');
            return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($record->getId())->generateUrl());
        }

        try {
            $record->setSyncing(true);
            $this->entityManager->flush();

            $domain = $record->getDomain();

            // 如果没有记录ID，先尝试查找匹配的记录
            if ($record->getRecordId() === null || $record->getRecordId() === '') {
                $this->logger->info('尝试查找匹配的DNS记录', [
                    'domain' => $domain->getName(),
                    'record' => $record->getRecord(),
                ]);

                $response = $this->dnsRecordService->listRecords($domain, [
                    'type' => $record->getType()->value,
                    'name' => "{$record->getRecord()}.{$domain->getName()}",
                ]);

                if (!empty($response['result'])) {
                    $record->setRecordId($response['result'][0]['id']);
                    $this->entityManager->flush();
                    $this->logger->info('找到匹配的DNS记录', [
                        'recordId' => $record->getRecordId(),
                    ]);
                }
            }

            // 如果仍然没有记录ID，创建新记录
            if ($record->getRecordId() === null || $record->getRecordId() === '') {
                $this->logger->info('创建新DNS记录', [
                    'domain' => $domain->getName(),
                    'record' => $record->getRecord(),
                ]);

                $result = $this->dnsRecordService->createRecord($domain, $record);

                if (isset($result['result']['id'])) {
                    $record->setRecordId($result['result']['id']);
                    $this->entityManager->flush();
                    $this->logger->info('DNS记录创建成功', [
                        'recordId' => $record->getRecordId(),
                    ]);
                }
            } else {
                // 更新已有记录
                $this->logger->info('更新DNS记录', [
                    'recordId' => $record->getRecordId(),
                ]);

                $this->dnsRecordService->updateRecord($record);
            }

            // 更新同步状态
            $record->setSynced(true);
            $record->setSyncing(false);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('DNS记录 %s 同步成功', $record->getFullName()));

        } catch (\Throwable $e) {
            $record->setSyncing(false);
            $this->entityManager->flush();

            $this->logger->error('同步DNS记录失败', [
                'record' => $record->getFullName(),
                'error' => $e->getMessage(),
            ]);

            $this->addFlash('danger', sprintf('DNS记录 %s 同步失败: %s', $record->getFullName(), $e->getMessage()));
        }

        return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($record->getId())->generateUrl());
    }
}
