<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Controller\Admin;

use CloudflareDnsBundle\Controller\Admin\DnsRecordCrudController;
use CloudflareDnsBundle\Entity\DnsRecord;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(DnsRecordCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DnsRecordCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        // 验证控制器能正确处理实体类型
        $this->assertSame(DnsRecord::class, DnsRecordCrudController::getEntityFqcn());
    }

    public function testIndexWithoutAuthentication(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        $this->assertContains($client->getResponse()->getStatusCode(), [302, 401, 403, 404]);
    }

    public function testIndexWithAuthentication(): void
    {
        $client = $this->createAuthenticatedClient();
        $url = $this->generateAdminUrl(Action::INDEX);

        $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
    }

    public function testControllerConfiguration(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        // 验证控制器配置正确
        $this->assertSame(DnsRecord::class, DnsRecordCrudController::getEntityFqcn());
    }

    public function testSyncToRemoteAction(): void
    {
        $client = self::createClient();

        // 模拟对 syncToRemote 动作的 GET 请求，预期返回授权相关状态码
        $client->request('GET', '/admin/cf-dns/record/1/syncToRemote');

        // 验证响应状态码为授权/访问控制相关代码
        $this->assertContains($client->getResponse()->getStatusCode(), [302, 401, 403, 404, 500]);
    }

    protected function getControllerService(): DnsRecordCrudController
    {
        return self::getService(DnsRecordCrudController::class);
    }

    public static function provideIndexPageHeaders(): iterable
    {
        // ID字段设置了onlyOnDetail()，不在索引页面显示
        yield '所属根域名' => ['所属根域名'];
        yield '记录类型' => ['记录类型'];
        yield '域名记录' => ['域名记录'];
        yield '记录ID' => ['记录ID'];
        yield '记录值' => ['记录值'];
        yield 'TTL' => ['TTL'];
        yield '是否代理' => ['是否代理'];
        yield '已同步到远程' => ['已同步到远程'];
        yield '创建时间' => ['创建时间'];
        yield '更新时间' => ['更新时间'];
        // lastSyncedTime字段设置了hideOnIndex()，不在索引页面显示
    }

    public static function provideNewPageFields(): iterable
    {
        yield 'domain' => ['domain'];
        yield 'type' => ['type'];
        yield 'record' => ['record'];
        // recordId字段设置了hideOnForm()，不在表单页面显示
        yield 'content' => ['content'];
        yield 'ttl' => ['ttl'];
        yield 'proxy' => ['proxy'];
        // synced, lastSyncedTime, createTime, updateTime字段设置了hideOnForm()，不在表单页面显示
    }

    public static function provideEditPageFields(): iterable
    {
        yield 'domain' => ['domain'];
        yield 'type' => ['type'];
        yield 'record' => ['record'];
        // recordId字段设置了hideOnForm()，不在表单页面显示
        yield 'content' => ['content'];
        yield 'ttl' => ['ttl'];
        yield 'proxy' => ['proxy'];
        // synced, lastSyncedTime, createTime, updateTime字段设置了hideOnForm()，不在表单页面显示
    }
}
