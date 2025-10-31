<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Controller\Admin;

use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Response;

/**
 * IAM密钥管理控制器
 *
 * @extends AbstractCrudController<IamKey>
 */
#[AdminCrud(routePath: '/cf-dns/key', routeName: 'cf_dns_key')]
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'cloudflare_dns')]
final class IamKeyCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly DomainBatchSynchronizer $batchSynchronizer,
        private readonly DomainSynchronizer $domainSynchronizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return IamKey::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('IAM密钥')
            ->setEntityLabelInPlural('IAM密钥')
            ->setPageTitle('index', 'IAM密钥列表')
            ->setPageTitle('new', '新增IAM密钥')
            ->setPageTitle('edit', fn (IamKey $key) => sprintf('编辑密钥: %s', $key->getName()))
            ->setPageTitle('detail', fn (IamKey $key) => sprintf('密钥详情: %s', $key->getName()))
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'name', 'accessKey', 'accountId', 'note'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $syncDomainsAction = Action::new('syncDomains', '同步域名')
            ->setIcon('fa fa-sync')
            ->setCssClass('btn btn-primary')
            ->linkToCrudAction('syncDomains')
            ->displayIf(static function (IamKey $iamKey): bool {
                // 只有有效且有账户ID的密钥才能同步域名
                return true === $iamKey->isValid() && null !== $iamKey->getAccountId();
            })
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $syncDomainsAction)
            ->add(Crud::PAGE_DETAIL, $syncDomainsAction)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')
            ->add('accessKey')
            ->add('accountId')
            ->add('valid')
            ->add('createTime')
            ->add('updateTime')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->setMaxLength(9999)->hideOnForm();
        yield TextField::new('name', '名称');
        yield TextField::new('accessKey', '邮箱');
        yield TextField::new('accountId', 'Account ID');
        yield TextareaField::new('secretKey', 'API Key')
            ->onlyOnForms()
            ->setRequired(true)
            ->setNumOfRows(3)
            ->setHelp('输入Cloudflare API Key')
        ;
        yield TextareaField::new('note', '备注')
            ->hideOnIndex()
        ;
        yield BooleanField::new('valid', '有效');
        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
        ;
        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
        ;
    }

    /**
     * 同步IAM密钥下的所有域名
     */
    #[AdminAction(routePath: '{entityId}/syncDomains', routeName: 'syncDomains')]
    public function syncDomains(AdminContext $context): Response
    {
        $iamKey = $context->getEntity()->getInstance();
        assert($iamKey instanceof IamKey);

        $detailUrl = $this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($iamKey->getId())->generateUrl();

        if (!$this->isValidIamKey($iamKey)) {
            return $this->redirect($detailUrl);
        }

        try {
            $result = $this->processDomainSync($iamKey);
            $this->addFlash('success', sprintf('域名同步完成，成功: %d，失败: %d，跳过: %d', $result['success'], $result['error'], $result['skipped']));
        } catch (\Throwable $e) {
            $this->logger->error('同步域名时发生错误', [
                'iamKey' => $iamKey->getName(),
                'error' => $e->getMessage(),
            ]);
            $this->addFlash('danger', sprintf('同步域名时发生错误: %s', $e->getMessage()));
        }

        return $this->redirect($detailUrl);
    }

    /**
     * 验证IAM Key是否有效
     */
    private function isValidIamKey(IamKey $iamKey): bool
    {
        if (true !== $iamKey->isValid()) {
            $this->addFlash('danger', sprintf('IAM Key [%s] 无效，无法同步域名', $iamKey->getName()));

            return false;
        }

        if (null === $iamKey->getAccountId() || '' === $iamKey->getAccountId()) {
            $this->addFlash('danger', sprintf('IAM Key [%s] 未设置Account ID，无法同步域名', $iamKey->getName()));

            return false;
        }

        return true;
    }

    /**
     * 处理域名同步
     * @return array{success: int, error: int, skipped: int}
     */
    private function processDomainSync(IamKey $iamKey): array
    {
        $this->logger->info(sprintf('开始同步IAM Key [%s] 的域名', $iamKey->getName()));

        $domains = $this->batchSynchronizer->listAllDomains($iamKey);
        $domainsToSync = $domains['result'] ?? [];

        if (!is_array($domainsToSync) || [] === $domainsToSync) {
            $this->addFlash('warning', sprintf('IAM Key [%s] 下没有可同步的域名', $iamKey->getName()));

            return ['success' => 0, 'error' => 0, 'skipped' => 0];
        }

        return $this->syncDomainList($iamKey, $domainsToSync);
    }

    /**
     * 同步域名列表
     * @param array<mixed> $domainsToSync
     * @return array{success: int, error: int, skipped: int}
     */
    private function syncDomainList(IamKey $iamKey, array $domainsToSync): array
    {
        $syncCount = 0;
        $errorCount = 0;

        foreach ($domainsToSync as $domainData) {
            if (!is_array($domainData)) {
                ++$errorCount;
                continue;
            }

            /** @var array<string, mixed> $domainData */
            try {
                $domain = $this->domainSynchronizer->createOrUpdateDomain($iamKey, $domainData);
                $this->entityManager->persist($domain);
                ++$syncCount;
            } catch (\Throwable $e) {
                $this->logger->error('同步域名失败', [
                    'domainName' => $domainData['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                ++$errorCount;
            }
        }

        $this->entityManager->flush();

        return ['success' => $syncCount, 'error' => $errorCount, 'skipped' => 0];
    }
}
