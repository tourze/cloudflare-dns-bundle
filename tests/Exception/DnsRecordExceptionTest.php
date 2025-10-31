<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Exception;

use CloudflareDnsBundle\Exception\DnsRecordException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(DnsRecordException::class)]
final class DnsRecordExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(DnsRecordException::class);
        $this->expectExceptionMessage('Test message');

        throw new DnsRecordException('Test message');
    }

    public function testExceptionExtendsInvalidArgumentException(): void
    {
        $exception = new DnsRecordException('Test message');

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
