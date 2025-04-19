<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Service\CloudflareHttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CloudflareHttpClientTest extends TestCase
{
    private string $accessKey = 'test-access-key';
    private string $secretKey = 'test-secret-key';
    private CloudflareHttpClient $client;
    private MockObject|HttpClientInterface $httpClient;

    protected function setUp(): void
    {
        // 创建 HttpClientInterface 的模拟对象
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        // 创建 CloudflareHttpClient 实例
        $this->client = new CloudflareHttpClient($this->accessKey, $this->secretKey);

        // 使用反射替换内部的 client 属性
        $reflectionClass = new ReflectionClass(CloudflareHttpClient::class);
        $reflectionProperty = $reflectionClass->getProperty('client');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->client, $this->httpClient);
    }

    public function testDeleteDnsRecord(): void
    {
        $zoneId = 'test-zone-id';
        $recordId = 'test-record-id';
        $expectedUrl = sprintf('/client/v4/zones/%s/dns_records/%s', $zoneId, $recordId);

        // 创建 ResponseInterface 的模拟对象
        $responseMock = $this->createMock(ResponseInterface::class);

        // 配置 httpClient 的预期行为
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('DELETE', $expectedUrl)
            ->willReturn($responseMock);

        // 执行被测试方法
        $result = $this->client->deleteDnsRecord($zoneId, $recordId);

        // 验证结果
        $this->assertSame($responseMock, $result);
    }

    public function testCreateDnsRecord(): void
    {
        $zoneId = 'test-zone-id';
        $record = $this->createDnsRecord();
        $expectedUrl = sprintf('/client/v4/zones/%s/dns_records', $zoneId);
        $expectedJson = [
            'type' => $record->getType()->value,
            'name' => $record->getRecord(),
            'content' => $record->getContent(),
            'ttl' => $record->getTtl(),
            'proxied' => $record->isProxy(),
        ];

        // 创建 ResponseInterface 的模拟对象
        $responseMock = $this->createMock(ResponseInterface::class);

        // 配置 httpClient 的预期行为
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', $expectedUrl, ['json' => $expectedJson])
            ->willReturn($responseMock);

        // 执行被测试方法
        $result = $this->client->createDnsRecord($zoneId, $record);

        // 验证结果
        $this->assertSame($responseMock, $result);
    }

    public function testUpdateDnsRecord(): void
    {
        $zoneId = 'test-zone-id';
        $record = $this->createDnsRecord();
        $record->setRecordId('test-record-id');
        $expectedUrl = sprintf('/client/v4/zones/%s/dns_records/%s', $zoneId, $record->getRecordId());
        $expectedJson = [
            'type' => $record->getType()->value,
            'name' => $record->getRecord(),
            'content' => $record->getContent(),
            'ttl' => $record->getTtl(),
            'proxied' => $record->isProxy(),
        ];

        // 创建 ResponseInterface 的模拟对象
        $responseMock = $this->createMock(ResponseInterface::class);

        // 配置 httpClient 的预期行为
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('PUT', $expectedUrl, ['json' => $expectedJson])
            ->willReturn($responseMock);

        // 执行被测试方法
        $result = $this->client->updateDnsRecord($zoneId, $record);

        // 验证结果
        $this->assertSame($responseMock, $result);
    }

    public function testListDnsRecords(): void
    {
        $zoneId = 'test-zone-id';
        $params = ['page' => 1, 'per_page' => 20];
        $expectedUrl = sprintf('/client/v4/zones/%s/dns_records', $zoneId);

        // 创建 ResponseInterface 的模拟对象
        $responseMock = $this->createMock(ResponseInterface::class);

        // 配置 httpClient 的预期行为
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $expectedUrl, ['query' => $params])
            ->willReturn($responseMock);

        // 执行被测试方法
        $result = $this->client->listDnsRecords($zoneId, $params);

        // 验证结果
        $this->assertSame($responseMock, $result);
    }

    /**
     * 创建测试用的 DnsRecord 对象
     */
    private function createDnsRecord(): DnsRecord
    {
        $record = new DnsRecord();
        $record->setType(DnsRecordType::A);
        $record->setRecord('test.example.com');
        $record->setContent('192.0.2.1');
        $record->setTtl(120);
        $record->setProxy(false);
        return $record;
    }
}
