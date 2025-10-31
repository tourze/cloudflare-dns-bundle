<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Exception;

use CloudflareDnsBundle\Exception\DnsAnalyticsException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(DnsAnalyticsException::class)]
final class DnsAnalyticsExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(DnsAnalyticsException::class);
        $this->expectExceptionMessage('Test message');

        throw new DnsAnalyticsException('Test message');
    }

    public function testExceptionExtendsInvalidArgumentException(): void
    {
        $exception = new DnsAnalyticsException('Test message');

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
