<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Command;

use CloudflareDnsBundle\Command\SyncDomainRecordToLocalCommand;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Client\CloudflareHttpClient;
use CloudflareDnsBundle\Service\DnsRecordService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(SyncDomainRecordToLocalCommand::class)]
final class SyncDomainRecordToLocalCommandTest extends AbstractCommandTestCase
{
    private SyncDomainRecordToLocalCommand $command;

    private DnsDomainRepository $domainRepository;

    private DnsRecordRepository $recordRepository;

    private CommandTester $commandTester;

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    protected function onSetUp(): void
    {
        // 从容器获取真实服务实例
        $this->domainRepository = self::getContainer()->get(DnsDomainRepository::class);
        $this->recordRepository = self::getContainer()->get(DnsRecordRepository::class);
    }

    public function testExecuteSuccessWithSpecificDomain(): void
    {
        // 创建测试数据
        $domain = $this->createAndPersistDnsDomain();

        // Mock HttpClient 来模拟 Cloudflare API 响应
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => [
                [
                    'id' => 'remote-record-1',
                    'name' => 'test.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ]);

        // 注入 Mock HttpClient 到服务
        $this->injectMockHttpClient($mockHttpClient);

        $result = $this->commandTester->execute([
            'domainId' => (string) $domain->getId(),
        ]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);

        // 验证记录已创建
        $record = $this->recordRepository->findOneBy([
            'domain' => $domain,
            'recordId' => 'remote-record-1',
        ]);
        $this->assertNotNull($record);
        $this->assertEquals('test', $record->getRecord());
        $this->assertEquals('192.168.1.1', $record->getContent());
    }

    public function testExecuteAllDomains(): void
    {
        // 创建测试数据
        $domain1 = $this->createAndPersistDnsDomain('example1.com');
        $domain2 = $this->createAndPersistDnsDomain('example2.com');

        // Mock HttpClient 来模拟 Cloudflare API 响应
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => [],
            'result_info' => [
                'total_pages' => 1,
            ],
        ]);

        $this->injectMockHttpClient($mockHttpClient);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example1.com', $output);
        $this->assertStringContainsString('开始处理域名：example2.com', $output);
    }

    public function testExecuteDomainNotFound(): void
    {
        // 创建一个空的 Mock HttpClient（不会被使用）
        $mockHttpClient = $this->createMockHttpClient([]);
        $this->injectMockHttpClient($mockHttpClient);

        // 不创建任何域名，直接查询不存在的ID
        $result = $this->commandTester->execute([
            'domainId' => '999999',
        ]);

        $this->assertEquals(0, $result);
    }

    public function testExecuteUpdatesExistingRecord(): void
    {
        // 创建测试数据
        $domain = $this->createAndPersistDnsDomain();

        // 创建已存在的记录
        $existingRecord = new DnsRecord();
        $existingRecord->setDomain($domain);
        $existingRecord->setRecordId('remote-record-id-1');
        $existingRecord->setRecord('test');
        $existingRecord->setType(DnsRecordType::A);
        $existingRecord->setContent('192.168.1.1');
        $existingRecord->setTtl(300);
        $existingRecord->setProxy(false);
        $this->persistAndFlush($existingRecord);

        // Mock HttpClient 返回更新的内容
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => [
                [
                    'id' => 'remote-record-id-1',
                    'name' => 'test.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.2',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ]);

        $this->injectMockHttpClient($mockHttpClient);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);

        // 验证记录已更新
        self::getEntityManager()->refresh($existingRecord);
        $this->assertEquals('192.168.1.2', $existingRecord->getContent());
    }

    public function testExecuteWithApiError(): void
    {
        // 创建测试数据
        $domain = $this->createAndPersistDnsDomain();

        // Mock HttpClient 返回失败响应
        $mockHttpClient = $this->createMockHttpClient(['success' => false]);

        $this->injectMockHttpClient($mockHttpClient);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithEmptyRemoteRecords(): void
    {
        // 创建测试数据
        $domain = $this->createAndPersistDnsDomain();

        // Mock HttpClient 返回空结果
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => [],
            'result_info' => [
                'total_pages' => 1,
            ],
        ]);

        $this->injectMockHttpClient($mockHttpClient);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);
    }

    public function testExecuteWithMultipleRecordTypes(): void
    {
        // 创建测试数据
        $domain = $this->createAndPersistDnsDomain();

        // Mock HttpClient 返回多种类型的记录
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => [
                [
                    'id' => 'record-1',
                    'name' => 'www.example.com',
                    'type' => 'A',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
                [
                    'id' => 'record-2',
                    'name' => 'mail.example.com',
                    'type' => 'MX',
                    'content' => 'mail.example.com',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
                [
                    'id' => 'record-3',
                    'name' => 'api.example.com',
                    'type' => 'CNAME',
                    'content' => 'example.com',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ]);

        $this->injectMockHttpClient($mockHttpClient);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('开始处理域名：example.com', $output);

        // 验证记录已创建
        $records = $this->recordRepository->findBy(['domain' => $domain]);
        $this->assertCount(3, $records);
    }

    public function testExecuteWithDatabaseError(): void
    {
        // 创建测试数据
        $domain = $this->createAndPersistDnsDomain();

        // Mock HttpClient 抛出异常
        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')
            ->willThrowException(new \Exception('DNS service error'))
        ;

        $this->injectMockHttpClient($mockHttpClient);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('同步DNS发生错误', $output);
    }

    public function testExecuteHandlesInvalidRecordType(): void
    {
        // 创建测试数据
        $domain = $this->createAndPersistDnsDomain();

        // Mock HttpClient 返回无效的记录类型
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => [
                [
                    'id' => 'record-1',
                    'name' => 'test.example.com',
                    'type' => 'INVALID_TYPE',
                    'content' => '192.168.1.1',
                    'ttl' => 300,
                    'proxiable' => false,
                ],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ]);

        $this->injectMockHttpClient($mockHttpClient);

        $result = $this->commandTester->execute([]);

        $this->assertEquals(0, $result);

        // 验证记录被创建了（因为 DnsRecord 的 type 字段有默认值 DnsRecordType::A）
        // 即使传入的类型无效，记录仍然会使用默认类型
        $records = $this->recordRepository->findBy(['domain' => $domain]);
        $this->assertCount(1, $records);

        // 验证使用的是默认类型 A
        $this->assertEquals(DnsRecordType::A, $records[0]->getType());
    }

    /**
     * 创建并持久化 DnsDomain 测试数据
     */
    private function createAndPersistDnsDomain(string $domainName = 'example.com'): DnsDomain
    {
        // 创建 IAM Key，使用唯一名称避免重复
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key ' . uniqid());
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        // 创建 Domain
        $domain = new DnsDomain();
        $domain->setName($domainName);
        $domain->setZoneId('test-zone-id-' . uniqid());
        $domain->setIamKey($iamKey);
        $domain->setValid(true);

        // 持久化到数据库
        $this->persistAndFlush($iamKey);
        $this->persistAndFlush($domain);

        return $domain;
    }

    /**
     * 创建 Mock HttpClient 来模拟 Cloudflare API 响应
     *
     * @param array<string, mixed> $responseData
     */
    private function createMockHttpClient(array $responseData): HttpClientInterface
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('toArray')
            ->willReturn($responseData)
        ;
        $mockResponse->method('getStatusCode')
            ->willReturn(200)
        ;

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')
            ->willReturn($mockResponse)
        ;

        return $mockHttpClient;
    }

    /**
     * 注入 Mock HttpClient 到 DnsRecordService
     *
     * 由于 CloudflareHttpClient 是在 BaseCloudflareService 的 getCloudFlareClient 方法中动态创建的，
     * 我们需要通过替换 DnsRecordService 来注入 Mock HttpClient
     *
     * 注意：必须在任何服务被初始化之前调用此方法
     *
     * @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass, commandTest.noDirectInstantiation
     */
    private function injectMockHttpClient(HttpClientInterface $mockHttpClient): void
    {
        // 创建一个自定义的 DnsRecordService，重写 getCloudFlareClient 方法
        $dnsService = new class(self::getContainer()->get('monolog.logger.cloudflare_dns'), $this->domainRepository, $mockHttpClient) extends DnsRecordService {
            private HttpClientInterface $mockHttpClient;

            public function __construct($logger, $domainRepository, HttpClientInterface $mockHttpClient)
            {
                parent::__construct($logger, $domainRepository);
                $this->mockHttpClient = $mockHttpClient;
            }

            protected function getCloudFlareClient(DnsDomain $domain): CloudflareHttpClient
            {
                $iamKey = $domain->getIamKey();
                if (null === $iamKey) {
                    throw new \RuntimeException('Domain does not have an IAM key configured');
                }

                $accessKey = $iamKey->getAccessKey();
                $secretKey = $iamKey->getSecretKey();

                if (null === $accessKey || null === $secretKey) {
                    throw new \RuntimeException('IAM key is missing access key or secret key');
                }

                // 返回带有 Mock HttpClient 的 CloudflareHttpClient
                return new CloudflareHttpClient($accessKey, $secretKey, $this->mockHttpClient);
            }
        };

        // 将自定义服务注入容器，然后从容器获取 Command
        self::getContainer()->set(DnsRecordService::class, $dnsService);

        // 从容器获取 Command 实例
        $command = self::getService(SyncDomainRecordToLocalCommand::class);
        $this->command = $command;

        $application = new Application();
        $application->addCommand($this->command);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testArgumentDomainId(): void
    {
        // 创建测试数据
        $domain = $this->createAndPersistDnsDomain();

        // Mock HttpClient
        $mockHttpClient = $this->createMockHttpClient([
            'success' => true,
            'result' => [],
            'result_info' => ['total_pages' => 1],
        ]);

        $this->injectMockHttpClient($mockHttpClient);

        $result = $this->commandTester->execute(['domainId' => (string) $domain->getId()]);
        $this->assertEquals(0, $result);
    }
}
