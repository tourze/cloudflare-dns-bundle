<?php

namespace CloudflareDnsBundle\Tests\Service;

use PHPUnit\Framework\TestCase;

class TestDnsRecordServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(TestDnsRecordService::class));
    }
}
