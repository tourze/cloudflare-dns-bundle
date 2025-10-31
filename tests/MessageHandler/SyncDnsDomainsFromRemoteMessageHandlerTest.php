<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\MessageHandler;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Message\SyncDnsDomainsFromRemoteMessage;
use CloudflareDnsBundle\MessageHandler\SyncDnsDomainsFromRemoteMessageHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SyncDnsDomainsFromRemoteMessageHandler::class)]
#[RunTestsInSeparateProcesses]
final class SyncDnsDomainsFromRemoteMessageHandlerTest extends AbstractIntegrationTestCase
{
    private SyncDnsDomainsFromRemoteMessageHandler $handler;

    protected function onSetUp(): void
    {
        $this->handler = self::getService(SyncDnsDomainsFromRemoteMessageHandler::class);
    }

    public function testInvokeWithNonexistentDomain(): void
    {
        $message = new SyncDnsDomainsFromRemoteMessage(999);

        // 执行处理程序，期望能正常处理不存在的域名
        // 这种情况下应该不抛出异常
        $this->handler->__invoke($message);

        // 验证处理程序能够正常处理不存在的域名ID
        $this->assertTrue(true, 'Handler should handle nonexistent domain gracefully');
    }

    public function testInvokeWithInvalidDomain(): void
    {
        $iamKey = $this->createIamKey();
        self::getEntityManager()->persist($iamKey);

        $domain = $this->createDnsDomain();
        $domain->setValid(false);
        $domain->setIamKey($iamKey);

        // 保存到数据库
        self::getEntityManager()->persist($domain);
        self::getEntityManager()->flush();

        $domainId = $domain->getId();
        $this->assertNotNull($domainId, 'Domain ID should not be null after persistence');
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        // 执行处理程序，期望能正常处理无效域名
        $this->handler->__invoke($message);

        // 验证处理程序能够正常处理无效域名
        $this->assertTrue(true, 'Handler should handle invalid domain gracefully');
    }

    public function testInvokeWithDomainMissingZoneId(): void
    {
        $iamKey = $this->createIamKey();
        self::getEntityManager()->persist($iamKey);

        $domain = $this->createDnsDomain();
        $domain->setZoneId(null);
        $domain->setIamKey($iamKey);

        // 保存到数据库
        self::getEntityManager()->persist($domain);
        self::getEntityManager()->flush();

        $domainId = $domain->getId();
        $this->assertNotNull($domainId, 'Domain ID should not be null after persistence');
        $message = new SyncDnsDomainsFromRemoteMessage($domainId);

        // 执行处理程序，期望能正常处理缺少Zone ID的域名
        $this->handler->__invoke($message);

        // 验证处理程序能够正常处理缺少Zone ID的域名
        $this->assertTrue(true, 'Handler should handle domain missing zone ID gracefully');
    }

    private function createIamKey(): IamKey
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test IAM Key');
        $iamKey->setAccessKey('test@example.com');
        $iamKey->setSecretKey('test-secret-key');
        $iamKey->setAccountId('test-account-id');
        $iamKey->setValid(true);

        return $iamKey;
    }

    private function createDnsDomain(): DnsDomain
    {
        $domain = new DnsDomain();
        $domain->setName('example.com');
        $domain->setZoneId('test-zone-id');
        $domain->setValid(true);

        return $domain;
    }
}
