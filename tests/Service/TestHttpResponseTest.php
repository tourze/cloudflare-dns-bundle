<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TestHttpResponse::class)]
final class TestHttpResponseTest extends TestCase
{
    private TestHttpResponse $successResponse;

    private TestHttpResponse $failureResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->successResponse = new TestHttpResponse(true);
        $this->failureResponse = new TestHttpResponse(false);
    }

    public function testResponseInstantiation(): void
    {
        $this->assertInstanceOf(TestHttpResponse::class, $this->successResponse);
        $this->assertInstanceOf(TestHttpResponse::class, $this->failureResponse);
        $this->assertNotNull($this->successResponse);
        $this->assertNotNull($this->failureResponse);
    }

    public function testToArray(): void
    {
        $successResult = $this->successResponse->toArray();
        $this->assertTrue($successResult['success']);
        $this->assertArrayHasKey('result', $successResult);
        $this->assertIsArray($successResult['result']);
        $this->assertEquals('test-record-id', $successResult['result']['id']);

        $failureResult = $this->failureResponse->toArray();
        $this->assertFalse($failureResult['success']);
        $this->assertArrayHasKey('errors', $failureResult);
        $this->assertIsArray($failureResult['errors']);
        $this->assertIsArray($failureResult['errors'][0]);
        $this->assertEquals(1003, $failureResult['errors'][0]['code']);
    }

    public function testCancel(): void
    {
        // cancel() method should not throw any exception
        $this->successResponse->cancel();
        $this->failureResponse->cancel();

        // The test passes if no exception is thrown
        $this->assertTrue(true);
    }
}
