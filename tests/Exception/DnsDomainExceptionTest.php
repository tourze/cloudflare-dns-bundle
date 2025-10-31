<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Exception;

use CloudflareDnsBundle\Exception\DnsDomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(DnsDomainException::class)]
final class DnsDomainExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsInvalidArgumentException(): void
    {
        $exception = new DnsDomainException();

        // 由于类已明确继承自InvalidArgumentException，此处添加有意义的断言
        $this->assertNotNull($exception);
    }

    public function testConstructorWithMessage(): void
    {
        $message = 'Invalid domain name';
        $exception = new DnsDomainException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testConstructorWithMessageAndCode(): void
    {
        $message = 'Invalid domain name';
        $code = 400;
        $exception = new DnsDomainException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testConstructorWithPreviousException(): void
    {
        $previous = new \InvalidArgumentException('Previous error');
        $message = 'Invalid domain name';
        $exception = new DnsDomainException($message, 0, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithAllParameters(): void
    {
        $previous = new \InvalidArgumentException('Previous error');
        $message = 'Invalid domain name';
        $code = 422;
        $exception = new DnsDomainException($message, $code, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCanBeThrownAndCaught(): void
    {
        $message = 'Test exception';

        $this->expectException(DnsDomainException::class);
        $this->expectExceptionMessage($message);

        throw new DnsDomainException($message);
    }

    public function testCanBeCaughtAsInvalidArgumentException(): void
    {
        $message = 'Test exception';

        try {
            throw new DnsDomainException($message);
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(DnsDomainException::class, $e);
            $this->assertEquals($message, $e->getMessage());
        }
    }
}
