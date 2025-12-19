<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use CloudflareDnsBundle\Service\RecordSyncProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RecordSyncProcessor::class)]
#[RunTestsInSeparateProcesses]
final class RecordSyncProcessorTest extends AbstractIntegrationTestCase
{
    private RecordSyncProcessor $processor;

    private DnsRecordRepository $recordRepository;

    protected function onSetUp(): void
    {
        $this->processor = self::getService(RecordSyncProcessor::class);
        $this->recordRepository = self::getService(DnsRecordRepository::class);
    }

    public function testProcessRemoteRecordCreatesNewRecord(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $persistedDomain = $this->persistAndFlush($domain);
        self::assertInstanceOf(DnsDomain::class, $persistedDomain);

        $remoteRecord = [
            'id' => 'remote-id-123',
            'type' => 'A',
            'name' => 'test.example.com',
            'content' => '192.168.1.1',
            'ttl' => 300,
            'proxied' => false,
        ];

        $localRecordMaps = [[], []];
        $counters = ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

        // 获取此域名当前的记录数
        $countBefore = count($this->recordRepository->findBy(['domain' => $persistedDomain]));

        $result = $this->processor->processRemoteRecord($persistedDomain, $remoteRecord, $localRecordMaps, $counters);

        self::assertSame(1, $result['create']);
        self::assertSame(0, $result['update']);
        self::assertSame(0, $result['skip']);

        // 刷新EntityManager以确保数据被持久化
        self::getEntityManager()->flush();

        // 验证新增了1条记录
        $records = $this->recordRepository->findBy(['domain' => $persistedDomain]);
        self::assertCount($countBefore + 1, $records);
        // 验证最后一条记录的内容
        $lastRecord = end($records);
        self::assertInstanceOf(DnsRecord::class, $lastRecord);
        self::assertSame('test', $lastRecord->getRecord());
        self::assertSame('192.168.1.1', $lastRecord->getContent());
    }

    public function testProcessRemoteRecordUpdatesExistingRecord(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $persistedDomain = $this->persistAndFlush($domain);
        self::assertInstanceOf(DnsDomain::class, $persistedDomain);

        $existingRecord = new DnsRecord();
        $existingRecord->setDomain($persistedDomain);
        $existingRecord->setType(DnsRecordType::A);
        $existingRecord->setRecord('test');
        $existingRecord->setContent('192.168.1.1');
        $existingRecord->setTtl(300);
        $persistedRecord = $this->persistAndFlush($existingRecord);
        self::assertInstanceOf(DnsRecord::class, $persistedRecord);

        $remoteRecord = [
            'id' => 'remote-id-123',
            'type' => 'A',
            'name' => 'test.example.com',
            'content' => '192.168.1.2',
            'ttl' => 600,
            'proxied' => false,
        ];

        $localRecordMaps = [['A_test' => $persistedRecord], []];
        $counters = ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

        $result = $this->processor->processRemoteRecord($persistedDomain, $remoteRecord, $localRecordMaps, $counters);

        self::assertSame(0, $result['create']);
        self::assertSame(1, $result['update']);
        self::assertSame(0, $result['skip']);

        // 刷新EntityManager以确保数据被持久化
        self::getEntityManager()->flush();
        self::getEntityManager()->clear();

        // 验证记录被更新
        $updatedRecord = $this->recordRepository->find($persistedRecord->getId());
        self::assertNotNull($updatedRecord);
        self::assertSame('192.168.1.2', $updatedRecord->getContent());
        self::assertSame(600, $updatedRecord->getTtl());
    }

    public function testProcessRemoteRecordSkipsWhenNoChanges(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $persistedDomain = $this->persistAndFlush($domain);
        self::assertInstanceOf(DnsDomain::class, $persistedDomain);

        $existingRecord = new DnsRecord();
        $existingRecord->setDomain($persistedDomain);
        $existingRecord->setType(DnsRecordType::A);
        $existingRecord->setRecord('test');
        $existingRecord->setContent('192.168.1.1');
        $existingRecord->setTtl(300);
        $existingRecord->setRecordId('remote-id-123');
        $persistedRecord = $this->persistAndFlush($existingRecord);
        self::assertInstanceOf(DnsRecord::class, $persistedRecord);

        $remoteRecord = [
            'id' => 'remote-id-123',
            'type' => 'A',
            'name' => 'test.example.com',
            'content' => '192.168.1.1',
            'ttl' => 300,
            'proxied' => false,
        ];

        $localRecordMaps = [['A_test' => $persistedRecord], []];
        $counters = ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

        $result = $this->processor->processRemoteRecord($persistedDomain, $remoteRecord, $localRecordMaps, $counters);

        self::assertSame(0, $result['create']);
        self::assertSame(0, $result['update']);
        self::assertSame(1, $result['skip']);
    }

    public function testProcessRemoteRecordSkipsUnknownType(): void
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $persistedDomain = $this->persistAndFlush($domain);
        self::assertInstanceOf(DnsDomain::class, $persistedDomain);

        $remoteRecord = [
            'id' => 'remote-id-123',
            'type' => 'UNKNOWN_TYPE',
            'name' => 'test.example.com',
            'content' => '192.168.1.1',
        ];

        $localRecordMaps = [[], []];
        $counters = ['create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

        // 获取此域名当前的记录数
        $countBefore = count($this->recordRepository->findBy(['domain' => $persistedDomain]));

        $result = $this->processor->processRemoteRecord($persistedDomain, $remoteRecord, $localRecordMaps, $counters);

        self::assertSame(0, $result['create']);
        self::assertSame(1, $result['skip']);
        // 验证没有新增记录
        $countAfter = count($this->recordRepository->findBy(['domain' => $persistedDomain]));
        self::assertSame($countBefore, $countAfter);
    }
}
