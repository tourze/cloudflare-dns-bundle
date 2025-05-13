<?php

namespace CloudflareDnsBundle\Controller\Admin;

use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * IAM密钥管理控制器
 */
class IamKeyCrudController extends AbstractCrudController
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
            ->setSearchFields(['id', 'name', 'accessKey', 'accountId', 'note']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $syncDomainsAction = Action::new('syncDomains', '同步域名')
            ->setIcon('fa fa-sync')
            ->setCssClass('btn btn-primary')
            ->linkToCrudAction('syncDomains')
            ->displayIf(static function (IamKey $iamKey): bool {
                // 只有有效且有账户ID的密钥才能同步域名
                return $iamKey->isValid() && $iamKey->getAccountId() !== null;
            });

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $syncDomainsAction)
            ->add(Crud::PAGE_DETAIL, $syncDomainsAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'syncDomains', Action::EDIT, Action::DELETE]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')
            ->add('accessKey')
            ->add('accountId')
            ->add('valid')
            ->add('createTime')
            ->add('updateTime');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->setMaxLength(9999)->hideOnForm();
        yield TextField::new('name', '名称');
        yield TextField::new('accessKey', '邮箱');
        yield TextField::new('accountId', 'Account ID');
        yield TextField::new('secretKey', 'API Key')
            ->onlyOnForms()
            ->setRequired(true);
        yield TextareaField::new('note', '备注')
            ->hideOnIndex();
        yield BooleanField::new('valid', '有效');
        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm();
        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm();
    }

    /**
     * 同步IAM密钥下的所有域名
     */
    #[AdminAction('{entityId}/syncDomains', 'syncDomains')]
    public function syncDomains(AdminContext $context): Response
    {
        /** @var IamKey $iamKey */
        $iamKey = $context->getEntity()->getInstance();

        // 验证IAM Key
        if (!$iamKey->isValid()) {
            $this->addFlash('danger', sprintf('IAM Key [%s] 无效，无法同步域名', $iamKey->getName()));
            return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($iamKey->getId())->generateUrl());
        }

        // 验证Account ID
        if (!$iamKey->getAccountId()) {
            $this->addFlash('danger', sprintf('IAM Key [%s] 未设置Account ID，无法同步域名', $iamKey->getName()));
            return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($iamKey->getId())->generateUrl());
        }

        try {
            $this->logger->info(sprintf('开始同步IAM Key [%s] 的域名', $iamKey->getName()));

            // 获取账户下所有域名
            $domains = $this->batchSynchronizer->listAllDomains($iamKey);

            if (empty($domains) || empty($domains['result'])) {
                $this->addFlash('warning', sprintf('IAM Key [%s] 下没有可同步的域名', $iamKey->getName()));
                return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($iamKey->getId())->generateUrl());
            }

            // 使用所有域名，不进行过滤
            $domainsToSync = $domains['result'];

            if (empty($domainsToSync)) {
                $this->addFlash('warning', sprintf('IAM Key [%s] 下没有可同步的域名', $iamKey->getName()));
                return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($iamKey->getId())->generateUrl());
            }

            // 直接执行同步，适配Web界面场景
            $syncCount = 0;
            $errorCount = 0;
            $skippedCount = 0;

            foreach ($domainsToSync as $domainData) {
                try {
                    // 创建或更新域名，使用DomainSynchronizer服务
                    $domain = $this->domainSynchronizer->createOrUpdateDomain($iamKey, $domainData);

                    // 保存域名
                    $this->entityManager->persist($domain);
                    $syncCount++;
                } catch (\Exception $e) {
                    $this->logger->error('同步域名失败', [
                        'domainName' => $domainData['name'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $errorCount++;
                }
            }

            // 提交事务
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('域名同步完成，成功: %d，失败: %d，跳过: %d', $syncCount, $errorCount, $skippedCount));

        } catch (\Exception $e) {
            $this->logger->error('同步域名时发生错误', [
                'iamKey' => $iamKey->getName(),
                'error' => $e->getMessage(),
            ]);

            $this->addFlash('danger', sprintf('同步域名时发生错误: %s', $e->getMessage()));
        }

        return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($iamKey->getId())->generateUrl());
    }
}
