<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Controller\Admin;

use CloudflareDnsBundle\Controller\Admin\DnsAnalyticsCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(DnsAnalyticsCrudController::class)]
#[RunTestsInSeparateProcesses]
class DnsAnalyticsCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function getControllerService(): DnsAnalyticsCrudController
    {
        return self::getService(DnsAnalyticsCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        // ID字段设置了onlyOnDetail()，不在索引页面显示
        yield '所属根域名' => ['所属根域名'];
        yield '查询名称' => ['查询名称'];
        yield '查询类型' => ['查询类型'];
        yield '查询次数' => ['查询次数'];
        yield '平均响应时间(ms)' => ['平均响应时间(ms)'];
        yield '统计时间' => ['统计时间'];
        yield '创建时间' => ['创建时间'];
        // updateTime字段设置了hideOnIndex()，不在索引页面显示
    }

    /**
     * NEW 操作已禁用，但必须提供数据以满足基类验证要求
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // 提供最小数据集以满足基类的非空检查，但实际操作被禁用
        yield 'disabled_field' => ['disabled_field'];
    }

    /**
     * EDIT 操作已禁用，但必须提供数据以满足基类验证要求
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        // 提供最小数据集以满足基类的非空检查，但实际操作被禁用
        yield 'disabled_field' => ['disabled_field'];
    }
}
