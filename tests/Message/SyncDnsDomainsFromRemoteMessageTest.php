<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Message;

use CloudflareDnsBundle\Message\SyncDnsDomainsFromRemoteMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SyncDnsDomainsFromRemoteMessage::class)]
final class SyncDnsDomainsFromRemoteMessageTest extends TestCase
{
    public function testConstructWithValidDomainId(): void
    {
        $domainId = 123;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function testConstructWithZeroDomainId(): void
    {
        $domainId = 0;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function testConstructWithNegativeDomainId(): void
    {
        $domainId = -1;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function testConstructWithLargeDomainId(): void
    {
        $domainId = 999999999;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function testGetDomainIdReturnsSameValue(): void
    {
        $domainId = 456;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        // 多次调用应该返回相同的值
        $this->assertEquals($domainId, $message->getDomainId());
        $this->assertEquals($domainId, $message->getDomainId());
        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function testMessageImmutability(): void
    {
        $originalDomainId = 789;
        $message = new SyncDnsDomainsFromRemoteMessage($originalDomainId);

        // 获取域名ID
        $retrievedDomainId = $message->getDomainId();

        // 确保原始值没有被修改
        $this->assertEquals($originalDomainId, $retrievedDomainId);
        $this->assertEquals($originalDomainId, $message->getDomainId());
    }

    public function testMessageSerialization(): void
    {
        $domainId = 321;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        $serialized = serialize($message);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(SyncDnsDomainsFromRemoteMessage::class, $unserialized);
        $this->assertEquals($domainId, $unserialized->getDomainId());
    }

    public function testMessageEquality(): void
    {
        $domainId = 555;
        $message1 = new SyncDnsDomainsFromRemoteMessage($domainId);
        $message2 = new SyncDnsDomainsFromRemoteMessage($domainId);

        // 虽然是不同的对象实例，但包含相同的数据
        $this->assertEquals($message1->getDomainId(), $message2->getDomainId());
        $this->assertNotSame($message1, $message2);
    }

    public function testDifferentDomainIdsCreateDifferentMessages(): void
    {
        $domainId1 = 111;
        $domainId2 = 222;

        $message1 = new SyncDnsDomainsFromRemoteMessage($domainId1);
        $message2 = new SyncDnsDomainsFromRemoteMessage($domainId2);

        $this->assertNotEquals($message1->getDomainId(), $message2->getDomainId());
        $this->assertEquals($domainId1, $message1->getDomainId());
        $this->assertEquals($domainId2, $message2->getDomainId());
    }

    public function testMessageReadonlyProperty(): void
    {
        $domainId = 777;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        // 使用反射来验证属性是否为只读
        $reflection = new \ReflectionClass($message);
        $property = $reflection->getProperty('domainId');

        $this->assertTrue($property->isReadOnly(), 'domainId property should be readonly');
        $this->assertTrue($property->isPrivate(), 'domainId property should be private');
    }

    public function testConstructorParameterType(): void
    {
        $reflection = new \ReflectionClass(SyncDnsDomainsFromRemoteMessage::class);
        $constructor = $reflection->getConstructor();
        if (null === $constructor) {
            self::fail('Constructor not found');
        }
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('domainId', $parameters[0]->getName());
        $this->assertEquals('int', (string) $parameters[0]->getType());
    }

    public function testGetterMethodReturnType(): void
    {
        $reflection = new \ReflectionClass(SyncDnsDomainsFromRemoteMessage::class);
        $method = $reflection->getMethod('getDomainId');

        $this->assertEquals('int', (string) $method->getReturnType());
        $this->assertTrue($method->isPublic());
    }

    #[DataProvider('domainIdProvider')]
    public function testVariousDomainIdValues(int $domainId): void
    {
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        $this->assertEquals($domainId, $message->getDomainId());
    }

    /**
     * @return array<string, array<int>>
     */
    public static function domainIdProvider(): array
    {
        return [
            'positive small' => [1],
            'positive medium' => [100],
            'positive large' => [999999],
            'zero' => [0],
            'negative' => [-1],
            'negative large' => [-999999],
            'max int' => [PHP_INT_MAX],
            'min int' => [PHP_INT_MIN],
        ];
    }

    public function testClassHasCorrectNamespace(): void
    {
        $reflection = new \ReflectionClass(SyncDnsDomainsFromRemoteMessage::class);
        $this->assertEquals(
            'CloudflareDnsBundle\Message\SyncDnsDomainsFromRemoteMessage',
            $reflection->getName()
        );
    }

    public function testMessageCanBeJsonEncoded(): void
    {
        $domainId = 888;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        // 虽然消息对象本身可能不直接JSON编码，但我们可以测试其数据
        $data = ['domainId' => $message->getDomainId()];
        $json = json_encode($data);
        if (false === $json) {
            self::fail('JSON encoding failed');
        }

        $this->assertJson($json);
        $this->assertEquals('{"domainId":888}', $json);
    }
}
