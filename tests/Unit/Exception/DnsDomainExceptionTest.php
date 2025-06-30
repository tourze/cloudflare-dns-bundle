<?php

namespace CloudflareDnsBundle\Tests\Unit\Exception;

use CloudflareDnsBundle\Exception\DnsDomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DnsDomainExceptionTest extends TestCase
{
    public function test_extends_invalid_argument_exception(): void
    {
        $exception = new DnsDomainException();
        
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
    }

    public function test_constructor_with_message(): void
    {
        $message = 'Invalid domain name';
        $exception = new DnsDomainException($message);
        
        $this->assertEquals($message, $exception->getMessage());
    }

    public function test_constructor_with_message_and_code(): void
    {
        $message = 'Invalid domain name';
        $code = 400;
        $exception = new DnsDomainException($message, $code);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function test_constructor_with_previous_exception(): void
    {
        $previous = new InvalidArgumentException('Previous error');
        $message = 'Invalid domain name';
        $exception = new DnsDomainException($message, 0, $previous);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_constructor_with_all_parameters(): void
    {
        $previous = new InvalidArgumentException('Previous error');
        $message = 'Invalid domain name';
        $code = 422;
        $exception = new DnsDomainException($message, $code, $previous);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $message = 'Test exception';
        
        $this->expectException(DnsDomainException::class);
        $this->expectExceptionMessage($message);
        
        throw new DnsDomainException($message);
    }

    public function test_can_be_caught_as_invalid_argument_exception(): void
    {
        $message = 'Test exception';
        
        try {
            throw new DnsDomainException($message);
        } catch (InvalidArgumentException $e) {
            $this->assertInstanceOf(DnsDomainException::class, $e);
            $this->assertEquals($message, $e->getMessage());
        }
    }
}