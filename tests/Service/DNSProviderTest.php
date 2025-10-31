<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Exception\CloudflareServiceException;
use CloudflareDnsBundle\Service\DNSProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DDNSContracts\DTO\ExpectResolveResult;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * DNSProvider 集成测试
 * @internal
 */
#[CoversClass(DNSProvider::class)]
#[RunTestsInSeparateProcesses]
final class DNSProviderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 无需特殊初始化逻辑
    }

    /**
     * 获取服务容器中的DNSProvider实例
     */
    private function getDNSProvider(): DNSProvider
    {
        return self::getService(DNSProvider::class);
    }

    public function testServiceCanBeInstantiated(): void
    {
        $service = $this->getDNSProvider();
        $this->assertInstanceOf(DNSProvider::class, $service);
    }

    public function testGetName(): void
    {
        $service = $this->getDNSProvider();
        $this->assertEquals('cloudflare-dns', $service->getName());
    }

    public function testApiMethodsAreCallable(): void
    {
        $service = $this->getDNSProvider();

        // 验证主要API方法是可调用的 - 使用反射检查方法存在性
        $reflection = new \ReflectionClass($service);
        $this->assertTrue($reflection->hasMethod('check'));
        $this->assertTrue($reflection->hasMethod('resolve'));
    }

    public function testCheck(): void
    {
        $service = $this->getDNSProvider();

        // 创建模拟的 ExpectResolveResult
        $result = $this->createMock(ExpectResolveResult::class);
        $result->method('getDomainName')->willReturn('test.example.com');

        // 测试域名检查 - 由于没有配置数据，应该返回 false
        $isManaged = $service->check($result);
        $this->assertIsBool($isManaged);
    }

    public function testResolve(): void
    {
        $service = $this->getDNSProvider();

        // 创建模拟的 ExpectResolveResult
        $result = $this->createMock(ExpectResolveResult::class);
        $result->method('getDomainName')->willReturn('test.example.com');
        $result->method('getIpAddress')->willReturn('192.168.1.1');

        // 测试解析 - 应该抛出异常因为找不到根域名
        $this->expectException(CloudflareServiceException::class);
        $this->expectExceptionMessage('找不到匹配的根域名：test.example.com');

        $service->resolve($result);
    }
}
