<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Entity;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * IAM密钥实体测试
 *
 * @internal
 */
#[CoversClass(IamKey::class)]
final class IamKeyTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new IamKey();
    }

    public function testNameSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(IamKey::class, $entity);
        $entity->setName('Test API Key');
        $this->assertEquals('Test API Key', $entity->getName(), 'Getter should return the set value');
    }

    public function testAccessKeySetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(IamKey::class, $entity);
        $entity->setAccessKey('test@example.com');
        $this->assertEquals('test@example.com', $entity->getAccessKey(), 'Getter should return the set value');
    }

    public function testAccountIdSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(IamKey::class, $entity);
        $entity->setAccountId('account123456');
        $this->assertEquals('account123456', $entity->getAccountId(), 'Getter should return the set value');
    }

    public function testSecretKeySetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(IamKey::class, $entity);
        $entity->setSecretKey('secret-api-key-123');
        $this->assertEquals('secret-api-key-123', $entity->getSecretKey(), 'Getter should return the set value');
    }

    public function testNoteSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(IamKey::class, $entity);
        $entity->setNote('This is a test API key for development');
        $this->assertEquals('This is a test API key for development', $entity->getNote(), 'Getter should return the set value');
    }

    public function testValidSetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(IamKey::class, $entity);
        $entity->setValid(true);
        $this->assertTrue($entity->isValid(), 'Getter should return the set value');
    }

    public function testCreatedBySetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(IamKey::class, $entity);
        $entity->setCreatedBy('admin');
        $this->assertEquals('admin', $entity->getCreatedBy(), 'Getter should return the set value');
    }

    public function testUpdatedBySetter(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(IamKey::class, $entity);
        $entity->setUpdatedBy('admin');
        $this->assertEquals('admin', $entity->getUpdatedBy(), 'Getter should return the set value');
    }

    public function testDomainRelationship(): void
    {
        $iamKey = new IamKey();
        $domain = new DnsDomain();

        // 添加域名
        $iamKey->addDomain($domain);
        $this->assertCount(1, $iamKey->getDomains());
        $this->assertTrue($iamKey->getDomains()->contains($domain));
        $this->assertSame($iamKey, $domain->getIamKey());

        // 移除域名
        $iamKey->removeDomain($domain);
        $this->assertCount(0, $iamKey->getDomains());
        $this->assertFalse($iamKey->getDomains()->contains($domain));
        $this->assertNull($domain->getIamKey());
    }

    public function testToStringMethod(): void
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test API Key');

        // ID为0时返回空字符串
        $this->assertEquals('', (string) $iamKey);

        $iamKey->setName('');
        $this->assertEquals('', (string) $iamKey);
    }

    public function testEdgeCaseWithLongStrings(): void
    {
        $iamKey = new IamKey();
        $longName = str_repeat('a', 120);
        $longAccessKey = str_repeat('b', 250) . '@example.com';
        $longAccountId = str_repeat('c', 60);
        $longSecretKey = str_repeat('d', 500);
        $longNote = str_repeat('e', 1000);

        $iamKey->setName($longName);
        $iamKey->setAccessKey($longAccessKey);
        $iamKey->setAccountId($longAccountId);
        $iamKey->setSecretKey($longSecretKey);
        $iamKey->setNote($longNote);

        $this->assertEquals($longName, $iamKey->getName());
        $this->assertEquals($longAccessKey, $iamKey->getAccessKey());
        $this->assertEquals($longAccountId, $iamKey->getAccountId());
        $this->assertEquals($longSecretKey, $iamKey->getSecretKey());
        $this->assertEquals($longNote, $iamKey->getNote());
    }

    public function testEmailFormatAccessKey(): void
    {
        $iamKey = new IamKey();

        $validEmails = [
            'user@example.com',
            'test.email@domain.co.uk',
            'admin+test@cloudflare.com',
            'user123@test-domain.org',
        ];

        foreach ($validEmails as $email) {
            $iamKey->setAccessKey($email);
            $this->assertEquals($email, $iamKey->getAccessKey());
        }
    }

    public function testNoteWithMultilineText(): void
    {
        $iamKey = new IamKey();
        $multilineNote = "This is line 1\nThis is line 2\nThis is line 3";

        $iamKey->setNote($multilineNote);
        $this->assertEquals($multilineNote, $iamKey->getNote());
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'name' => ['name', 'Test API Key'];
        yield 'accessKey' => ['accessKey', 'test@example.com'];
        yield 'accountId' => ['accountId', 'account123456'];
        yield 'secretKey' => ['secretKey', 'secret-api-key-123'];
        yield 'note' => ['note', 'This is a test API key for development'];
        yield 'valid' => ['valid', true];
        yield 'createdBy' => ['createdBy', 'admin'];
        yield 'updatedBy' => ['updatedBy', 'admin'];
    }
}
