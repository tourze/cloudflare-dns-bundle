<?php

namespace CloudflareDnsBundle\Controller\Admin;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Enum\DomainStatus;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * 域名管理控制器
 */
#[AdminCrud(routePath: '/cf-dns/domain', routeName: 'cf_dns_domain')]
class DnsDomainCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
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
        $syncRecordsAction = Action::new('syncRecords', '同步远程到本地')
            ->setIcon('fa fa-cloud-download-alt')
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

        yield ChoiceField::new('status', '状态')
            ->setChoices(array_combine(
                array_map(fn($case) => $case->getLabel(), DomainStatus::cases()),
                DomainStatus::cases()
            ))
            ->formatValue(function (DomainStatus $value) {
                return "<span class=\"badge badge-{$value->getBadge()}\">{$value->getLabel()}</span>";
            });

        yield DateTimeField::new('expiresTime', '过期时间')
            ->setColumns(6);
        yield DateTimeField::new('lockedUntilTime', '锁定截止时间')
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
            // 创建同步消息并发送到消息队列
            $message = new \CloudflareDnsBundle\Message\SyncDnsDomainsFromRemoteMessage($domain->getId());
            $this->messageBus->dispatch($message);

            $this->logger->info('已将域名DNS记录同步请求加入队列', [
                'domain' => $domain->getName(),
                'zoneId' => $domain->getZoneId(),
            ]);

            $this->addFlash('success', sprintf(
                '已将域名 [%s] 的解析记录同步请求加入处理队列，稍后将自动处理', 
                $domain->getName()
            ));
        } catch  (\Throwable $e) {
            $this->logger->error('将DNS记录同步请求加入队列时发生错误', [
                'domain' => $domain->getName(),
                'error' => $e->getMessage(),
            ]);

            $this->addFlash('danger', sprintf('将域名 [%s] 的解析记录同步请求加入队列时发生错误: %s', $domain->getName(), $e->getMessage()));
        }

        return $this->redirect($this->adminUrlGenerator->setAction(Action::DETAIL)->setEntityId($domain->getId())->generateUrl());
    }
}
