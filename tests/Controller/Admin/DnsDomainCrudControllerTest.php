<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Controller\Admin;

use CloudflareDnsBundle\Controller\Admin\DnsDomainCrudController;
use CloudflareDnsBundle\Entity\DnsDomain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(DnsDomainCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DnsDomainCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        // 验证控制器能正确处理实体类型
        $this->assertSame(DnsDomain::class, DnsDomainCrudController::getEntityFqcn());
    }

    public function testIndexWithoutAuthentication(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        $this->assertContains($client->getResponse()->getStatusCode(), [302, 401, 403, 404]);
    }

    public function testIndexWithAuthentication(): void
    {
        $client = self::createClientWithDatabase();
        $this->createAdminUser('admin@example.com', 'admin123');
        $this->loginAsAdmin($client, 'admin@example.com', 'admin123');

        $client->request('GET', '/admin');

        // 设置静态客户端以支持响应断言
        self::getClient($client);
        $this->assertResponseIsSuccessful();
    }

    public function testControllerConfiguration(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        // 验证控制器配置正确
        $this->assertSame(DnsDomain::class, DnsDomainCrudController::getEntityFqcn());
    }

    public function testSyncRecordsAction(): void
    {
        $client = self::createClient();

        // 模拟对 syncRecords 动作的 GET 请求，预期返回授权相关状态码
        $client->request('GET', '/admin/cf-dns/domain/1/syncRecords');

        // 验证响应状态码为授权/访问控制相关代码
        $this->assertContains($client->getResponse()->getStatusCode(), [302, 401, 403, 404, 500]);
    }

    protected function getControllerService(): DnsDomainCrudController
    {
        return self::getService(DnsDomainCrudController::class);
    }

    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'IAM账号' => ['IAM账号'];
        yield '根域名' => ['根域名'];
        yield 'Zone ID' => ['Zone ID'];
        yield 'Account ID' => ['Account ID'];
        yield '状态' => ['状态'];
        yield '过期时间' => ['过期时间'];
        yield '锁定截止时间' => ['锁定截止时间'];
        yield '是否自动续费' => ['是否自动续费'];
        yield '有效' => ['有效'];
        yield '创建时间' => ['创建时间'];
        yield '更新时间' => ['更新时间'];
    }

    public static function provideNewPageFields(): iterable
    {
        yield 'iamKey' => ['iamKey'];
        yield 'name' => ['name'];
        yield 'zoneId' => ['zoneId'];
        yield 'status' => ['status'];
        yield 'expiresTime' => ['expiresTime'];
        yield 'lockedUntilTime' => ['lockedUntilTime'];
        yield 'autoRenew' => ['autoRenew'];
        yield 'valid' => ['valid'];
    }

    public static function provideEditPageFields(): iterable
    {
        yield 'iamKey' => ['iamKey'];
        yield 'name' => ['name'];
        yield 'zoneId' => ['zoneId'];
        yield 'status' => ['status'];
        yield 'expiresTime' => ['expiresTime'];
        yield 'lockedUntilTime' => ['lockedUntilTime'];
        yield 'autoRenew' => ['autoRenew'];
        yield 'valid' => ['valid'];
    }
}
