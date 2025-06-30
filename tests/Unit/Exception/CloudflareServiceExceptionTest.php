<?php

namespace CloudflareDnsBundle\Tests\Unit\Exception;

use CloudflareDnsBundle\Exception\CloudflareServiceException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CloudflareServiceExceptionTest extends TestCase
{
    public function test_extends_runtime_exception(): void
    {
        $exception = new CloudflareServiceException();
        
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function test_constructor_with_message(): void
    {
        $message = 'Cloudflare API error';
        $exception = new CloudflareServiceException($message);
        
        $this->assertEquals($message, $exception->getMessage());
    }

    public function test_constructor_with_message_and_code(): void
    {
        $message = 'Cloudflare API error';
        $code = 500;
        $exception = new CloudflareServiceException($message, $code);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function test_constructor_with_previous_exception(): void
    {
        $previous = new RuntimeException('Previous error');
        $message = 'Cloudflare API error';
        $exception = new CloudflareServiceException($message, 0, $previous);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_constructor_with_all_parameters(): void
    {
        $previous = new RuntimeException('Previous error');
        $message = 'Cloudflare API error';
        $code = 404;
        $exception = new CloudflareServiceException($message, $code, $previous);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $message = 'Test exception';
        
        $this->expectException(CloudflareServiceException::class);
        $this->expectExceptionMessage($message);
        
        throw new CloudflareServiceException($message);
    }

    public function test_can_be_caught_as_runtime_exception(): void
    {
        $message = 'Test exception';
        
        try {
            throw new CloudflareServiceException($message);
        } catch (RuntimeException $e) {
            $this->assertInstanceOf(CloudflareServiceException::class, $e);
            $this->assertEquals($message, $e->getMessage());
        }
    }
}