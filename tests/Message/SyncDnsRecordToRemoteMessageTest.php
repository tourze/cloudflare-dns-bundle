<?php

namespace CloudflareDnsBundle\Tests\Message;

use CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage;
use PHPUnit\Framework\TestCase;

class SyncDnsRecordToRemoteMessageTest extends TestCase
{
    public function test_construct_with_valid_dns_record_id(): void
    {
        $dnsRecordId = 456;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function test_construct_with_zero_dns_record_id(): void
    {
        $dnsRecordId = 0;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function test_construct_with_negative_dns_record_id(): void
    {
        $dnsRecordId = -1;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function test_construct_with_large_dns_record_id(): void
    {
        $dnsRecordId = 999999999;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function test_get_dns_record_id_returns_same_value(): void
    {
        $dnsRecordId = 789;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        // 多次调用应该返回相同的值
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

    public function test_message_immutability(): void
    {
        $originalDnsRecordId = 123;
        $message = new SyncDnsRecordToRemoteMessage($originalDnsRecordId);
        
        // 获取DNS记录ID
        $retrievedDnsRecordId = $message->getDnsRecordId();
        
        // 确保原始值没有被修改
        $this->assertEquals($originalDnsRecordId, $retrievedDnsRecordId);
        $this->assertEquals($originalDnsRecordId, $message->getDnsRecordId());
    }

    public function test_message_serialization(): void
    {
        $dnsRecordId = 654;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        $serialized = serialize($message);
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(SyncDnsRecordToRemoteMessage::class, $unserialized);
        $this->assertEquals($dnsRecordId, $unserialized->getDnsRecordId());
    }

    public function test_message_equality(): void
    {
        $dnsRecordId = 888;
        $message1 = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        $message2 = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        // 虽然是不同的对象实例，但包含相同的数据
        $this->assertEquals($message1->getDnsRecordId(), $message2->getDnsRecordId());
        $this->assertNotSame($message1, $message2);
    }

    public function test_different_dns_record_ids_create_different_messages(): void
    {
        $dnsRecordId1 = 111;
        $dnsRecordId2 = 222;
        
        $message1 = new SyncDnsRecordToRemoteMessage($dnsRecordId1);
        $message2 = new SyncDnsRecordToRemoteMessage($dnsRecordId2);
        
        $this->assertNotEquals($message1->getDnsRecordId(), $message2->getDnsRecordId());
        $this->assertEquals($dnsRecordId1, $message1->getDnsRecordId());
        $this->assertEquals($dnsRecordId2, $message2->getDnsRecordId());
    }

    public function test_message_readonly_property(): void
    {
        $dnsRecordId = 999;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        // 使用反射来验证属性是否为只读
        $reflection = new \ReflectionClass($message);
        $property = $reflection->getProperty('dnsRecordId');
        
        $this->assertTrue($property->isReadOnly(), 'dnsRecordId property should be readonly');
        $this->assertTrue($property->isPrivate(), 'dnsRecordId property should be private');
    }

    public function test_constructor_parameter_type(): void
    {
        $reflection = new \ReflectionClass(SyncDnsRecordToRemoteMessage::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertEquals('dnsRecordId', $parameters[0]->getName());
        $this->assertEquals('int', $parameters[0]->getType()->getName());
    }

    public function test_getter_method_return_type(): void
    {
        $reflection = new \ReflectionClass(SyncDnsRecordToRemoteMessage::class);
        $method = $reflection->getMethod('getDnsRecordId');
        
        $this->assertEquals('int', $method->getReturnType()->getName());
        $this->assertTrue($method->isPublic());
    }

    /**
     * @dataProvider dnsRecordIdProvider
     */
    public function test_various_dns_record_id_values(int $dnsRecordId): void
    {
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        $this->assertEquals($dnsRecordId, $message->getDnsRecordId());
    }

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

    public function test_class_has_correct_namespace(): void
    {
        $reflection = new \ReflectionClass(SyncDnsRecordToRemoteMessage::class);
        $this->assertEquals(
            'CloudflareDnsBundle\\Message\\SyncDnsRecordToRemoteMessage',
            $reflection->getName()
        );
    }

    public function test_message_can_be_json_encoded(): void
    {
        $dnsRecordId = 777;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        // 虽然消息对象本身可能不直接JSON编码，但我们可以测试其数据
        $data = ['dnsRecordId' => $message->getDnsRecordId()];
        $json = json_encode($data);
        
        $this->assertJson($json);
        $this->assertEquals('{"dnsRecordId":777}', $json);
    }

    public function test_message_vs_sync_domains_message(): void
    {
        // 这个测试确保两个消息类是不同的
        $recordId = 123;
        $recordMessage = new SyncDnsRecordToRemoteMessage($recordId);
        
        $this->assertInstanceOf(SyncDnsRecordToRemoteMessage::class, $recordMessage);
        $this->assertEquals($recordId, $recordMessage->getDnsRecordId());
        
        // 确保这个类有正确的方法名（不是getDomainId）
        $this->assertTrue(method_exists($recordMessage, 'getDnsRecordId'));
        $this->assertFalse(method_exists($recordMessage, 'getDomainId'));
    }

    public function test_message_construction_with_edge_cases(): void
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
            $this->assertEquals($caseValue, $message->getDnsRecordId(), "Failed for edge case: $caseValue");
        }
    }

    public function test_message_property_accessibility(): void
    {
        $dnsRecordId = 500;
        $message = new SyncDnsRecordToRemoteMessage($dnsRecordId);
        
        // 测试属性只能通过getter访问
        $reflection = new \ReflectionClass($message);
        $property = $reflection->getProperty('dnsRecordId');
        
        $this->assertTrue($property->isPrivate());
        $this->assertTrue($property->isReadOnly());
        
        // 确保没有setter方法
        $this->assertFalse(method_exists($message, 'setDnsRecordId'));
    }

    public function test_object_comparison(): void
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