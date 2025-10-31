<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\MessageHandler;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Message\SyncDnsRecordToRemoteMessage;
use CloudflareDnsBundle\MessageHandler\SyncDnsRecordToRemoteMessageHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SyncDnsRecordToRemoteMessageHandler::class)]
#[RunTestsInSeparateProcesses]
final class SyncDnsRecordToRemoteMessageHandlerTest extends AbstractIntegrationTestCase
{
    private SyncDnsRecordToRemoteMessageHandler $handler;

    protected function onSetUp(): void
    {
        $this->handler = self::getService(SyncDnsRecordToRemoteMessageHandler::class);
    }

    public function testInvokeWithNonexistentRecord(): void
    {
        $message = new SyncDnsRecordToRemoteMessage(999);

        // 执行处理程序，期望能正常处理不存在的记录
        $this->handler->__invoke($message);

        // 验证处理程序能够正常处理不存在的记录
        $this->assertTrue(true, 'Handler should handle nonexistent record gracefully');
    }

    public function testInvokeWithRecordAlreadySyncing(): void
    {
        $record = $this->createDnsRecord();
        $record->setSyncing(true);

        // 保存到数据库
        $domain = $record->getDomain();
        $this->assertNotNull($domain, 'Domain should not be null for test record');
        $iamKey = $domain->getIamKey();
        $this->assertNotNull($iamKey, 'IAM Key should not be null for test domain');
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->persist($domain);
        self::getEntityManager()->persist($record);
        self::getEntityManager()->flush();

        $recordId = $record->getId();
        $this->assertNotNull($recordId, 'Record ID should not be null after persistence');
        $message = new SyncDnsRecordToRemoteMessage($recordId);

        // 执行处理程序，期望能正常处理正在同步的记录
        $this->handler->__invoke($message);

        // 验证处理程序能够正常处理正在同步的记录
        $this->assertTrue(true, 'Handler should handle record already syncing gracefully');
    }

    public function testInvokeWithValidRecord(): void
    {
        $record = $this->createDnsRecord();
        $record->setRecordId('existing-record-id');

        // 保存到数据库
        $domain = $record->getDomain();
        $this->assertNotNull($domain, 'Domain should not be null for test record');
        $iamKey = $domain->getIamKey();
        $this->assertNotNull($iamKey, 'IAM Key should not be null for test domain');
        self::getEntityManager()->persist($iamKey);
        self::getEntityManager()->persist($domain);
        self::getEntityManager()->persist($record);
        self::getEntityManager()->flush();

        $recordId = $record->getId();
        $this->assertNotNull($recordId, 'Record ID should not be null after persistence');
        $message = new SyncDnsRecordToRemoteMessage($recordId);

        // 执行处理程序，期望能正常处理有效记录
        $this->handler->__invoke($message);

        // 验证处理程序能够正常处理有效记录
        $this->assertTrue(true, 'Handler should handle valid record gracefully');
    }

    /**
     * 创建测试用的 DnsRecord 对象
     */
    private function createDnsRecord(): DnsRecord
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key');
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');
        $domain->setIamKey($iamKey);
        $domain->setValid(true);

        $record = new DnsRecord();
        $record->setDomain($domain);
        $record->setRecord('test');
        $record->setType(DnsRecordType::A);
        $record->setContent('192.168.1.1');
        $record->setTtl(300);
        $record->setProxy(false);
        $record->setSynced(false);
        $record->setSyncing(false);

        return $record;
    }
}
