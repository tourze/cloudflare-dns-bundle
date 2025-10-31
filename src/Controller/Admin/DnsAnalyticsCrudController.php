<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Controller\Admin;

use CloudflareDnsBundle\Entity\DnsAnalytics;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * DNS统计分析控制器
 *
 * @extends AbstractCrudController<DnsAnalytics>
 */
#[AdminCrud(routePath: '/cf-dns/analytics', routeName: 'cf_dns_analytics')]
#[Autoconfigure(public: true)]
final class DnsAnalyticsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DnsAnalytics::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('DNS分析数据')
            ->setEntityLabelInPlural('DNS分析数据')
            ->setPageTitle('index', 'DNS分析数据列表')
            ->setPageTitle('detail', fn (DnsAnalytics $analytics) => sprintf('DNS分析数据详情: %s', $analytics->getQueryName()))
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'queryName', 'queryType'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL])
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('domain'))
            ->add('queryName')
            ->add('queryType')
            ->add('statTime')
            ->add('createTime')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->setMaxLength(9999)->onlyOnDetail();
        yield AssociationField::new('domain', '所属根域名');
        yield TextField::new('queryName', '查询名称');
        yield TextField::new('queryType', '查询类型');
        yield IntegerField::new('queryCount', '查询次数');
        yield NumberField::new('responseTimeAvg', '平均响应时间(ms)')
            ->setNumDecimals(2)
        ;
        yield DateTimeField::new('statTime', '统计时间');
        yield DateTimeField::new('createTime', '创建时间');
        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnIndex()
        ;
    }
}
