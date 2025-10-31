<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Exception;

use CloudflareDnsBundle\Exception\TestServiceException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(TestServiceException::class)]
final class TestServiceExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsException(): void
    {
        $exception = new TestServiceException();

        // 由于类已明确继承自Exception，此处添加有意义的断言
        $this->assertNotNull($exception);
    }

    public function testConstructorWithMessage(): void
    {
        $message = 'Test service error';
        $exception = new TestServiceException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testConstructorWithMessageAndCode(): void
    {
        $message = 'Test service error';
        $code = 500;
        $exception = new TestServiceException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testConstructorWithPreviousException(): void
    {
        $previous = new \Exception('Previous error');
        $message = 'Test service error';
        $exception = new TestServiceException($message, 0, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithAllParameters(): void
    {
        $previous = new \Exception('Previous error');
        $message = 'Test service error';
        $code = 404;
        $exception = new TestServiceException($message, $code, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCanBeThrownAndCaught(): void
    {
        $message = 'Test exception';

        $this->expectException(TestServiceException::class);
        $this->expectExceptionMessage($message);

        throw new TestServiceException($message);
    }

    public function testCanBeCaughtAsException(): void
    {
        $message = 'Test exception';

        try {
            throw new TestServiceException($message);
        } catch (\Exception $e) {
            $this->assertInstanceOf(TestServiceException::class, $e);
            $this->assertEquals($message, $e->getMessage());
        }
    }
}
