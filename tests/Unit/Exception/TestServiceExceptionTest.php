<?php

namespace CloudflareDnsBundle\Tests\Unit\Exception;

use CloudflareDnsBundle\Exception\TestServiceException;
use Exception;
use PHPUnit\Framework\TestCase;

class TestServiceExceptionTest extends TestCase
{
    public function test_extends_exception(): void
    {
        $exception = new TestServiceException();
        
        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_constructor_with_message(): void
    {
        $message = 'Test service error';
        $exception = new TestServiceException($message);
        
        $this->assertEquals($message, $exception->getMessage());
    }

    public function test_constructor_with_message_and_code(): void
    {
        $message = 'Test service error';
        $code = 500;
        $exception = new TestServiceException($message, $code);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function test_constructor_with_previous_exception(): void
    {
        $previous = new Exception('Previous error');
        $message = 'Test service error';
        $exception = new TestServiceException($message, 0, $previous);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_constructor_with_all_parameters(): void
    {
        $previous = new Exception('Previous error');
        $message = 'Test service error';
        $code = 404;
        $exception = new TestServiceException($message, $code, $previous);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $message = 'Test exception';
        
        $this->expectException(TestServiceException::class);
        $this->expectExceptionMessage($message);
        
        throw new TestServiceException($message);
    }

    public function test_can_be_caught_as_exception(): void
    {
        $message = 'Test exception';
        
        try {
            throw new TestServiceException($message);
        } catch (Exception $e) {
            $this->assertInstanceOf(TestServiceException::class, $e);
            $this->assertEquals($message, $e->getMessage());
        }
    }
}