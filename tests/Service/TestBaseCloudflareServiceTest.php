<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
#[CoversClass(TestBaseCloudflareService::class)]
final class TestBaseCloudflareServiceTest extends TestCase
{
    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;

    private TestBaseCloudflareService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        $this->service = new TestBaseCloudflareService($this->logger);
    }

    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(TestBaseCloudflareService::class, $this->service);
        $this->assertNotNull($this->service);
    }

    public function testExposedGetCloudFlareClient(): void
    {
        $domain = $this->createDnsDomain();

        $client = $this->service->exposedGetCloudFlareClient($domain);

        // 由于方法已有明确的返回类型声明，此处添加有意义的断言
        $this->assertNotNull($client);
    }

    public function testExposedHandleResponse(): void
    {
        $successResult = [
            'success' => true,
            'result' => ['id' => 'test-id'],
        ];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('操作成功', self::callback(function ($context) use ($successResult) {
                return is_array($context) && isset($context['result']) && $context['result'] === $successResult;
            }))
        ;

        $result = $this->service->exposedHandleResponse($successResult, '操作失败');
        $this->assertSame($successResult, $result);
    }

    /**
     * 创建测试用的 DnsDomain 对象
     */
    private function createDnsDomain(): DnsDomain
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');

        $iamKey = new IamKey();
        $iamKey->setAccessKey('test-access-key');
        $iamKey->setSecretKey('test-secret-key');
        $domain->setIamKey($iamKey);

        return $domain;
    }
}
