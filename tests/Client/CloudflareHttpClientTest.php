<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Client;

use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Client\CloudflareHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * CloudflareHttpClient 单元测试
 * CloudflareHttpClient是值对象，不应被注册为服务，因此使用单元测试
 * 测试重点：HTTP客户端封装、API调用方法、错误处理
 * @internal
 */
#[CoversClass(CloudflareHttpClient::class)]
final class CloudflareHttpClientTest extends TestCase
{
    /**
     * 创建CloudflareHttpClient实例
     * 使用Mock的HTTP客户端来确保测试的可预测性
     *
     * CloudflareHttpClient是值对象，不是服务，因此直接实例化是合理的。
     */
    private function getCloudflareHttpClient(): CloudflareHttpClient
    {
        // 创建Mock HTTP客户端，始终抛出TransportException来模拟网络错误
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $transportException = $this->createMock(TransportExceptionInterface::class);
        $mockHttpClient->method('request')
            ->willThrowException($transportException)
        ;

        // CloudflareHttpClient是值对象，直接实例化而不是从容器获取
        return new CloudflareHttpClient(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            client: $mockHttpClient,
        );
    }

    public function testServiceCanBeInstantiated(): void
    {
        $client = $this->getCloudflareHttpClient();
        $this->assertInstanceOf(CloudflareHttpClient::class, $client);
    }

    public function testApiMethodsAreCallable(): void
    {
        $client = $this->getCloudflareHttpClient();

        // 验证主要API方法是可调用的 - 使用反射检查方法存在性
        $reflection = new \ReflectionClass($client);
        $this->assertTrue($reflection->hasMethod('deleteDnsRecord'));
        $this->assertTrue($reflection->hasMethod('createDnsRecord'));
        $this->assertTrue($reflection->hasMethod('updateDnsRecord'));
        $this->assertTrue($reflection->hasMethod('listDnsRecords'));
        $this->assertTrue($reflection->hasMethod('listDomains'));
        $this->assertTrue($reflection->hasMethod('lookupZoneId'));
    }

    public function testBatchDnsRecords(): void
    {
        $client = $this->getCloudflareHttpClient();
        $zoneId = 'test-zone-id';
        $operations = [
            'deletes' => ['record-id-1'],
            'posts' => [],
            'patches' => [],
            'puts' => [],
        ];

        $this->expectException(\Exception::class);
        $client->batchDnsRecords($zoneId, $operations);
    }

    public function testCreateDnsRecord(): void
    {
        $client = $this->getCloudflareHttpClient();
        $zoneId = 'test-zone-id';
        $record = new DnsRecord();
        $record->setType(DnsRecordType::A);
        $record->setRecord('test.example.com');
        $record->setContent('192.168.1.1');
        $record->setTtl(3600);

        $this->expectException(\Exception::class);
        $client->createDnsRecord($zoneId, $record);
    }

    public function testDeleteDnsRecord(): void
    {
        $client = $this->getCloudflareHttpClient();
        $zoneId = 'test-zone-id';
        $recordId = 'test-record-id';

        $this->expectException(\Exception::class);
        $client->deleteDnsRecord($zoneId, $recordId);
    }

    public function testExportDnsRecords(): void
    {
        $client = $this->getCloudflareHttpClient();
        $zoneId = 'test-zone-id';

        $this->expectException(\Exception::class);
        $client->exportDnsRecords($zoneId);
    }

    public function testImportDnsRecords(): void
    {
        $client = $this->getCloudflareHttpClient();
        $zoneId = 'test-zone-id';
        $bindConfig = 'test.example.com. IN A 192.168.1.1';

        $this->expectException(\Exception::class);
        $client->importDnsRecords($zoneId, $bindConfig);
    }

    public function testListDnsRecords(): void
    {
        $client = $this->getCloudflareHttpClient();
        $zoneId = 'test-zone-id';
        $params = ['type' => 'A', 'per_page' => 10];

        $this->expectException(\Exception::class);
        $client->listDnsRecords($zoneId, $params);
    }

    public function testListDomains(): void
    {
        $client = $this->getCloudflareHttpClient();
        $accountId = 'test-account-id';

        $this->expectException(\Exception::class);
        $client->listDomains($accountId);
    }

    public function testLookupZoneId(): void
    {
        $client = $this->getCloudflareHttpClient();
        $domainName = 'example.com';

        $this->expectException(\Exception::class);
        $client->lookupZoneId($domainName);
    }

    public function testScanDnsRecords(): void
    {
        $client = $this->getCloudflareHttpClient();
        $zoneId = 'test-zone-id';

        $this->expectException(\Exception::class);
        $client->scanDnsRecords($zoneId);
    }

    public function testUpdateDnsRecord(): void
    {
        $client = $this->getCloudflareHttpClient();
        $zoneId = 'test-zone-id';
        $record = new DnsRecord();
        $record->setRecordId('test-record-id');
        $record->setType(DnsRecordType::A);
        $record->setRecord('test.example.com');
        $record->setContent('192.168.1.2');
        $record->setTtl(3600);

        $this->expectException(\Exception::class);
        $client->updateDnsRecord($zoneId, $record);
    }
}
