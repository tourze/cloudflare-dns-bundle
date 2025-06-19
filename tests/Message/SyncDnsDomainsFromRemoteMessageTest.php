<?php

namespace CloudflareDnsBundle\Tests\Message;

use CloudflareDnsBundle\Message\SyncDnsDomainsFromRemoteMessage;
use PHPUnit\Framework\TestCase;

class SyncDnsDomainsFromRemoteMessageTest extends TestCase
{
    public function test_construct_with_valid_domain_id(): void
    {
        $domainId = 123;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        
        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function test_construct_with_zero_domain_id(): void
    {
        $domainId = 0;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        
        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function test_construct_with_negative_domain_id(): void
    {
        $domainId = -1;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        
        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function test_construct_with_large_domain_id(): void
    {
        $domainId = 999999999;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        
        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function test_get_domain_id_returns_same_value(): void
    {
        $domainId = 456;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        
        // 多次调用应该返回相同的值
        $this->assertEquals($domainId, $message->getDomainId());
        $this->assertEquals($domainId, $message->getDomainId());
        $this->assertEquals($domainId, $message->getDomainId());
    }

    public function test_message_immutability(): void
    {
        $originalDomainId = 789;
        $message = new SyncDnsDomainsFromRemoteMessage($originalDomainId);
        
        // 获取域名ID
        $retrievedDomainId = $message->getDomainId();
        
        // 确保原始值没有被修改
        $this->assertEquals($originalDomainId, $retrievedDomainId);
        $this->assertEquals($originalDomainId, $message->getDomainId());
    }

    public function test_message_serialization(): void
    {
        $domainId = 321;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        
        $serialized = serialize($message);
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(SyncDnsDomainsFromRemoteMessage::class, $unserialized);
        $this->assertEquals($domainId, $unserialized->getDomainId());
    }

    public function test_message_equality(): void
    {
        $domainId = 555;
        $message1 = new SyncDnsDomainsFromRemoteMessage($domainId);
        $message2 = new SyncDnsDomainsFromRemoteMessage($domainId);
        
        // 虽然是不同的对象实例，但包含相同的数据
        $this->assertEquals($message1->getDomainId(), $message2->getDomainId());
        $this->assertNotSame($message1, $message2);
    }

    public function test_different_domain_ids_create_different_messages(): void
    {
        $domainId1 = 111;
        $domainId2 = 222;
        
        $message1 = new SyncDnsDomainsFromRemoteMessage($domainId1);
        $message2 = new SyncDnsDomainsFromRemoteMessage($domainId2);
        
        $this->assertNotEquals($message1->getDomainId(), $message2->getDomainId());
        $this->assertEquals($domainId1, $message1->getDomainId());
        $this->assertEquals($domainId2, $message2->getDomainId());
    }

    public function test_message_readonly_property(): void
    {
        $domainId = 777;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        
        // 使用反射来验证属性是否为只读
        $reflection = new \ReflectionClass($message);
        $property = $reflection->getProperty('domainId');
        
        $this->assertTrue($property->isReadOnly(), 'domainId property should be readonly');
        $this->assertTrue($property->isPrivate(), 'domainId property should be private');
    }

    public function test_constructor_parameter_type(): void
    {
        $reflection = new \ReflectionClass(SyncDnsDomainsFromRemoteMessage::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertEquals('domainId', $parameters[0]->getName());
        $this->assertEquals('int', (string) $parameters[0]->getType());
    }

    public function test_getter_method_return_type(): void
    {
        $reflection = new \ReflectionClass(SyncDnsDomainsFromRemoteMessage::class);
        $method = $reflection->getMethod('getDomainId');
        
        $this->assertEquals('int', (string) $method->getReturnType());
        $this->assertTrue($method->isPublic());
    }

    /**
     * @dataProvider domainIdProvider
     */
    public function test_various_domain_id_values(int $domainId): void
    {
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        $this->assertEquals($domainId, $message->getDomainId());
    }

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

    public function test_class_has_correct_namespace(): void
    {
        $reflection = new \ReflectionClass(SyncDnsDomainsFromRemoteMessage::class);
        $this->assertEquals(
            'CloudflareDnsBundle\\Message\\SyncDnsDomainsFromRemoteMessage',
            $reflection->getName()
        );
    }

    public function test_message_can_be_json_encoded(): void
    {
        $domainId = 888;
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);
        
        // 虽然消息对象本身可能不直接JSON编码，但我们可以测试其数据
        $data = ['domainId' => $message->getDomainId()];
        $json = json_encode($data);
        
        $this->assertJson($json);
        $this->assertEquals('{"domainId":888}', $json);
    }
} 