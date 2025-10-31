<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Message;

use CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @internal
 */
#[CoversClass(SyncDnsRecordToRemoteMessage::class)]
final class SyncDnsRecordToRemoteMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Message tests don't require special setup
    }

    public function testConstructWithValidDnsRecordId(): void
    {
        $dnsRecordId = 456;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function testConstructWithZeroDnsRecordId(): void
    {
        $dnsRecordId = 0;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function testConstructWithNegativeDnsRecordId(): void
    {
        $dnsRecordId = -1;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function testConstructWithLargeDnsRecordId(): void
    {
        $dnsRecordId = 999999999;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function testGetDnsRecordIdReturnsSameValue(): void
    {
        $dnsRecordId = 789;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        // 多次调用应该返回相同的值
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function testMessageImmutability(): void
    {
        $originalDnsRecordId = 123;
        $message = new SyncDnsRecordToRemoteMessage($originalDnsRecordId);

        // 获取DNS记录ID
        $retrievedDnsRecordId = $message->getDnsRecordId();

        // 确保原始值没有被修改
        $this->assertEquals($originalDnsRecordId, $retrievedDnsRecordId);
        $this->assertEquals($originalDnsRecordId, $message->getDnsRecordId());
    }

    public function testMessageSerialization(): void
    {
        $dnsRecordId = 654;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        $serialized = serialize($message);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(SyncDnsRecordToRemoteMessage::class, $unserialized);
        $this->assertEquals($dnsRecordId, $unserialized->getDnsRecordId());
    }

    public function testMessageEquality(): void
    {
        $dnsRecordId = 888;
        $message1 = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        $message2 = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        // 虽然是不同的对象实例，但包含相同的数据
        $this->assertEquals($message1->getDnsRecordId(), $message2->getDnsRecordId());
        $this->assertNotSame($message1, $message2);
    }

    public function testDifferentDnsRecordIdsCreateDifferentMessages(): void
    {
        $dnsRecordId1 = 111;
        $dnsRecordId2 = 222;

        $message1 = new SyncDnsRecordToRemoteMessage($dnsRecordId1);
        $message2 = new SyncDnsRecordToRemoteMessage($dnsRecordId2);

        $this->assertNotEquals($message1->getDnsRecordId(), $message2->getDnsRecordId());
        $this->assertEquals($dnsRecordId1, $message1->getDnsRecordId());
        $this->assertEquals($dnsRecordId2, $message2->getDnsRecordId());
    }

    public function testMessageReadonlyProperty(): void
    {
        $dnsRecordId = 999;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        // 使用反射来验证属性是否为只读
        $reflection = new \ReflectionClass($message);
        $property = $reflection->getProperty('dnsRecordId');

        $this->assertTrue($property->isReadOnly(), 'dnsRecordId property should be readonly');
        $this->assertTrue($property->isPrivate(), 'dnsRecordId property should be private');
    }

    public function testConstructorParameterType(): void
    {
        $reflection = new \ReflectionClass(SyncDnsRecordToRemoteMessage::class);
        $constructor = $reflection->getConstructor();
        if (null === $constructor) {
            self::fail('Constructor not found');
        }
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('dnsRecordId', $parameters[0]->getName());
        $this->assertEquals('int', (string) $parameters[0]->getType());
    }

    public function testGetterMethodReturnType(): void
    {
        $reflection = new \ReflectionClass(SyncDnsRecordToRemoteMessage::class);
        $method = $reflection->getMethod('getDnsRecordId');

        $this->assertEquals('int', (string) $method->getReturnType());
        $this->assertTrue($method->isPublic());
    }

    #[DataProvider('dnsRecordIdProvider')]
    public function testVariousDnsRecordIdValues(int $dnsRecordId): void
    {
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    /**
     * @return array<string, array<int>>
     */
    public static function dnsRecordIdProvider(): array
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
        $reflection = new \ReflectionClass(SyncDnsRecordToRemoteMessage::class);
        $this->assertEquals(
            'CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage',
            $reflection->getName()
        );
    }

    public function testMessageCanBeJsonEncoded(): void
    {
        $dnsRecordId = 777;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        // 虽然消息对象本身可能不直接JSON编码，但我们可以测试其数据
        $data = ['dnsRecordId' => $message->getDnsRecordId()];
        $json = json_encode($data);
        if (false === $json) {
            self::fail('JSON encoding failed');
        }

        $this->assertJson($json);
        $this->assertEquals('{"dnsRecordId":777}', $json);
    }

    public function testMessageVsSyncDomainsMessage(): void
    {
        // 这个测试确保两个消息类是不同的
        $recordId = 123;
        $recordMessage = new SyncDnsRecordToRemoteMessage($recordId);

        // 由于类型已明确，此处测试消息对象功能
        $this->assertEquals($recordId, $recordMessage->getDnsRecordId());

        // 确保这个类有正确的方法名（不是getDomainId）
        $reflection = new \ReflectionClass($recordMessage);
        $this->assertTrue($reflection->hasMethod('getDnsRecordId'));
        $this->assertFalse($reflection->hasMethod('getDomainId'));
    }

    public function testMessageConstructionWithEdgeCases(): void
    {
        // 边界值测试
        $edgeCases = [
            0,
            1,
            -1,
            PHP_INT_MAX,
            PHP_INT_MIN,
        ];

        foreach ($edgeCases as $caseValue) {
            $message = new SyncDnsRecordToRemoteMessage($caseValue);
            $this->assertEquals($caseValue, $message->getDnsRecordId(), "Failed for edge case: {$caseValue}");
        }
    }

    public function testMessagePropertyAccessibility(): void
    {
        $dnsRecordId = 500;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);

        // 测试属性只能通过getter访问
        $reflection = new \ReflectionClass($message);
        $property = $reflection->getProperty('dnsRecordId');

        $this->assertTrue($property->isPrivate());
        $this->assertTrue($property->isReadOnly());

        // 确保没有setter方法
        $messageReflection = new \ReflectionClass($message);
        $this->assertFalse($messageReflection->hasMethod('setDnsRecordId'));
    }

    public function testObjectComparison(): void
    {
        $id1 = 100;
        $id2 = 200;

        $message1a = new SyncDnsRecordToRemoteMessage($id1);
        $message1b = new SyncDnsRecordToRemoteMessage($id1);
        $message2 = new SyncDnsRecordToRemoteMessage($id2);

        // 相同ID的消息应该有相同的数据但不是同一个对象
        $this->assertEquals($message1a->getDnsRecordId(), $message1b->getDnsRecordId());
        $this->assertNotSame($message1a, $message1b);

        // 不同ID的消息应该有不同的数据
        $this->assertNotEquals($message1a->getDnsRecordId(), $message2->getDnsRecordId());
    }
}
