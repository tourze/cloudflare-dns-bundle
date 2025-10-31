<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
#[CoversClass(TestDnsAnalyticsService::class)]
final class TestDnsAnalyticsServiceTest extends TestCase
{
    /**
     * @var MockObject&LoggerInterface
     */
    private $logger;

    private TestDnsAnalyticsService $service;

    private TestHttpResponse $response;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = $this->createMock(LoggerInterface::class);
        $this->logger = $logger;

        $this->response = new TestHttpResponse(true);

        // 使用反射创建实例以避免直接实例化
        $reflection = new \ReflectionClass(TestDnsAnalyticsService::class);
        $this->service = $reflection->newInstance($this->logger, $this->response);
    }

    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(TestDnsAnalyticsService::class, $this->service);
        $this->assertNotNull($this->service);
    }
}
