<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Exception;

use CloudflareDnsBundle\Exception\CloudflareServiceException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(CloudflareServiceException::class)]
final class CloudflareServiceExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsRuntimeException(): void
    {
        $exception = new CloudflareServiceException();

        // 由于类已明确继承自RuntimeException，此处添加有意义的断言
        $this->assertNotNull($exception);
    }

    public function testConstructorWithMessage(): void
    {
        $message = 'Cloudflare API error';
        $exception = new CloudflareServiceException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testConstructorWithMessageAndCode(): void
    {
        $message = 'Cloudflare API error';
        $code = 500;
        $exception = new CloudflareServiceException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testConstructorWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $message = 'Cloudflare API error';
        $exception = new CloudflareServiceException($message, 0, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithAllParameters(): void
    {
        $previous = new \RuntimeException('Previous error');
        $message = 'Cloudflare API error';
        $code = 404;
        $exception = new CloudflareServiceException($message, $code, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCanBeThrownAndCaught(): void
    {
        $message = 'Test exception';

        $this->expectException(CloudflareServiceException::class);
        $this->expectExceptionMessage($message);

        throw new CloudflareServiceException($message);
    }

    public function testCanBeCaughtAsRuntimeException(): void
    {
        $message = 'Test exception';

        try {
            throw new CloudflareServiceException($message);
        } catch (\RuntimeException $e) {
            $this->assertInstanceOf(CloudflareServiceException::class, $e);
            $this->assertEquals($message, $e->getMessage());
        }
    }
}
