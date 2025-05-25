<?php

namespace CloudflareDnsBundle\Tests\Entity;

use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Entity\IamKey;
use PHPUnit\Framework\TestCase;

/**
 * IAM密钥实体测试
 */
class IamKeyTest extends TestCase
{
    public function test_constructor_initializes_default_values(): void
    {
        $iamKey = new IamKey();

        $this->assertEquals(0, $iamKey->getId());
        $this->assertNull($iamKey->getAccessKey());
        $this->assertNull($iamKey->getAccountId());
        $this->assertNull($iamKey->getSecretKey());
        $this->assertNull($iamKey->getNote());
        $this->assertEmpty($iamKey->getDomains());
        $this->assertFalse($iamKey->isValid());
        $this->assertNull($iamKey->getCreatedBy());
        $this->assertNull($iamKey->getUpdatedBy());
        $this->assertNull($iamKey->getCreateTime());
        $this->assertNull($iamKey->getUpdateTime());
    }

    public function test_setName_and_getName(): void
    {
        $iamKey = new IamKey();
        $name = 'Test API Key';

        $result = $iamKey->setName($name);

        $this->assertSame($iamKey, $result);
        $this->assertEquals($name, $iamKey->getName());
    }

    public function test_setAccessKey_and_getAccessKey(): void
    {
        $iamKey = new IamKey();
        $accessKey = 'test@example.com';

        $result = $iamKey->setAccessKey($accessKey);

        $this->assertSame($iamKey, $result);
        $this->assertEquals($accessKey, $iamKey->getAccessKey());
    }

    public function test_setAccessKey_with_null(): void
    {
        $iamKey = new IamKey();

        $result = $iamKey->setAccessKey(null);

        $this->assertSame($iamKey, $result);
        $this->assertNull($iamKey->getAccessKey());
    }

    public function test_setAccountId_and_getAccountId(): void
    {
        $iamKey = new IamKey();
        $accountId = 'account123456';

        $result = $iamKey->setAccountId($accountId);

        $this->assertSame($iamKey, $result);
        $this->assertEquals($accountId, $iamKey->getAccountId());
    }

    public function test_setAccountId_with_null(): void
    {
        $iamKey = new IamKey();

        $result = $iamKey->setAccountId(null);

        $this->assertSame($iamKey, $result);
        $this->assertNull($iamKey->getAccountId());
    }

    public function test_setSecretKey_and_getSecretKey(): void
    {
        $iamKey = new IamKey();
        $secretKey = 'secret-api-key-123';

        $result = $iamKey->setSecretKey($secretKey);

        $this->assertSame($iamKey, $result);
        $this->assertEquals($secretKey, $iamKey->getSecretKey());
    }

    public function test_setNote_and_getNote(): void
    {
        $iamKey = new IamKey();
        $note = 'This is a test API key for development';

        $result = $iamKey->setNote($note);

        $this->assertSame($iamKey, $result);
        $this->assertEquals($note, $iamKey->getNote());
    }

    public function test_setNote_with_null(): void
    {
        $iamKey = new IamKey();

        $result = $iamKey->setNote(null);

        $this->assertSame($iamKey, $result);
        $this->assertNull($iamKey->getNote());
    }

    public function test_addDomain_and_getDomains(): void
    {
        $iamKey = new IamKey();
        $domain = new DnsDomain();

        $result = $iamKey->addDomain($domain);

        $this->assertSame($iamKey, $result);
        $this->assertCount(1, $iamKey->getDomains());
        $this->assertTrue($iamKey->getDomains()->contains($domain));
        $this->assertSame($iamKey, $domain->getIamKey());
    }

    public function test_addDomain_duplicate_domain(): void
    {
        $iamKey = new IamKey();
        $domain = new DnsDomain();

        $iamKey->addDomain($domain);
        $iamKey->addDomain($domain); // 添加相同域名

        $this->assertCount(1, $iamKey->getDomains());
    }

    public function test_removeDomain(): void
    {
        $iamKey = new IamKey();
        $domain = new DnsDomain();
        $iamKey->addDomain($domain);

        $result = $iamKey->removeDomain($domain);

        $this->assertSame($iamKey, $result);
        $this->assertCount(0, $iamKey->getDomains());
        $this->assertFalse($iamKey->getDomains()->contains($domain));
        $this->assertNull($domain->getIamKey());
    }

    public function test_removeDomain_not_existing(): void
    {
        $iamKey = new IamKey();
        $domain = new DnsDomain();

        $result = $iamKey->removeDomain($domain);

        $this->assertSame($iamKey, $result);
        $this->assertCount(0, $iamKey->getDomains());
    }

    public function test_setValid_and_isValid(): void
    {
        $iamKey = new IamKey();

        $result = $iamKey->setValid(true);

        $this->assertSame($iamKey, $result);
        $this->assertTrue($iamKey->isValid());

        $iamKey->setValid(false);
        $this->assertFalse($iamKey->isValid());

        $iamKey->setValid(null);
        $this->assertNull($iamKey->isValid());
    }

    public function test_setCreatedBy_and_getCreatedBy(): void
    {
        $iamKey = new IamKey();
        $createdBy = 'admin';

        $result = $iamKey->setCreatedBy($createdBy);

        $this->assertSame($iamKey, $result);
        $this->assertEquals($createdBy, $iamKey->getCreatedBy());
    }

    public function test_setCreatedBy_with_null(): void
    {
        $iamKey = new IamKey();

        $result = $iamKey->setCreatedBy(null);

        $this->assertSame($iamKey, $result);
        $this->assertNull($iamKey->getCreatedBy());
    }

    public function test_setUpdatedBy_and_getUpdatedBy(): void
    {
        $iamKey = new IamKey();
        $updatedBy = 'admin';

        $result = $iamKey->setUpdatedBy($updatedBy);

        $this->assertSame($iamKey, $result);
        $this->assertEquals($updatedBy, $iamKey->getUpdatedBy());
    }

    public function test_setUpdatedBy_with_null(): void
    {
        $iamKey = new IamKey();

        $result = $iamKey->setUpdatedBy(null);

        $this->assertSame($iamKey, $result);
        $this->assertNull($iamKey->getUpdatedBy());
    }

    public function test_setCreateTime_and_getCreateTime(): void
    {
        $iamKey = new IamKey();
        $createTime = new \DateTime('2023-01-01 10:00:00');

        $iamKey->setCreateTime($createTime);

        $this->assertSame($createTime, $iamKey->getCreateTime());
    }

    public function test_setCreateTime_with_null(): void
    {
        $iamKey = new IamKey();

        $iamKey->setCreateTime(null);

        $this->assertNull($iamKey->getCreateTime());
    }

    public function test_setUpdateTime_and_getUpdateTime(): void
    {
        $iamKey = new IamKey();
        $updateTime = new \DateTime('2023-01-01 11:00:00');

        $iamKey->setUpdateTime($updateTime);

        $this->assertSame($updateTime, $iamKey->getUpdateTime());
    }

    public function test_setUpdateTime_with_null(): void
    {
        $iamKey = new IamKey();

        $iamKey->setUpdateTime(null);

        $this->assertNull($iamKey->getUpdateTime());
    }

    public function test_toString_with_name(): void
    {
        $iamKey = new IamKey();
        $iamKey->setName('Test API Key');

        // ID为0时返回空字符串
        $this->assertEquals('', (string) $iamKey);
    }

    public function test_toString_without_name(): void
    {
        $iamKey = new IamKey();

        $this->assertEquals('', (string) $iamKey);
    }

    public function test_complex_scenario_with_multiple_domains(): void
    {
        $iamKey = new IamKey();
        $iamKey->setName('Multi-Domain Key');

        $domain1 = new DnsDomain();
        $domain1->setName('example.com');

        $domain2 = new DnsDomain();
        $domain2->setName('test.com');

        $iamKey->addDomain($domain1);
        $iamKey->addDomain($domain2);

        $this->assertCount(2, $iamKey->getDomains());
        $this->assertTrue($iamKey->getDomains()->contains($domain1));
        $this->assertTrue($iamKey->getDomains()->contains($domain2));
        $this->assertSame($iamKey, $domain1->getIamKey());
        $this->assertSame($iamKey, $domain2->getIamKey());
    }

    public function test_complex_scenario_with_all_properties(): void
    {
        $iamKey = new IamKey();

        $name = 'Production API Key';
        $accessKey = 'prod@example.com';
        $accountId = 'account789';
        $secretKey = 'very-secret-production-key';
        $note = 'Production environment API key with full permissions';
        $valid = true;
        $createdBy = 'system';
        $updatedBy = 'admin';
        $createTime = new \DateTime('2023-06-01 10:00:00');
        $updateTime = new \DateTime('2023-06-01 15:00:00');

        $iamKey->setName($name)
            ->setAccessKey($accessKey)
            ->setAccountId($accountId)
            ->setSecretKey($secretKey)
            ->setNote($note)
            ->setValid($valid)
            ->setCreatedBy($createdBy)
            ->setUpdatedBy($updatedBy);
        $iamKey->setCreateTime($createTime);
        $iamKey->setUpdateTime($updateTime);

        $this->assertEquals($name, $iamKey->getName());
        $this->assertEquals($accessKey, $iamKey->getAccessKey());
        $this->assertEquals($accountId, $iamKey->getAccountId());
        $this->assertEquals($secretKey, $iamKey->getSecretKey());
        $this->assertEquals($note, $iamKey->getNote());
        $this->assertTrue($iamKey->isValid());
        $this->assertEquals($createdBy, $iamKey->getCreatedBy());
        $this->assertEquals($updatedBy, $iamKey->getUpdatedBy());
        $this->assertSame($createTime, $iamKey->getCreateTime());
        $this->assertSame($updateTime, $iamKey->getUpdateTime());
        $this->assertEquals('', (string) $iamKey); // ID为0时返回空字符串
    }

    public function test_edge_case_with_empty_strings(): void
    {
        $iamKey = new IamKey();

        $iamKey->setName('')
            ->setAccessKey('')
            ->setAccountId('')
            ->setSecretKey('')
            ->setNote('');

        $this->assertEquals('', $iamKey->getName());
        $this->assertEquals('', $iamKey->getAccessKey());
        $this->assertEquals('', $iamKey->getAccountId());
        $this->assertEquals('', $iamKey->getSecretKey());
        $this->assertEquals('', $iamKey->getNote());
        $this->assertEquals('', (string) $iamKey);
    }

    public function test_edge_case_with_long_strings(): void
    {
        $iamKey = new IamKey();
        $longName = str_repeat('a', 120);
        $longAccessKey = str_repeat('b', 250) . '@example.com';
        $longAccountId = str_repeat('c', 60);
        $longSecretKey = str_repeat('d', 500);
        $longNote = str_repeat('e', 1000);

        $iamKey->setName($longName)
            ->setAccessKey($longAccessKey)
            ->setAccountId($longAccountId)
            ->setSecretKey($longSecretKey)
            ->setNote($longNote);

        $this->assertEquals($longName, $iamKey->getName());
        $this->assertEquals($longAccessKey, $iamKey->getAccessKey());
        $this->assertEquals($longAccountId, $iamKey->getAccountId());
        $this->assertEquals($longSecretKey, $iamKey->getSecretKey());
        $this->assertEquals($longNote, $iamKey->getNote());
    }

    public function test_domain_relationship_integrity(): void
    {
        $iamKey = new IamKey();
        $domain = new DnsDomain();

        // 添加域名
        $iamKey->addDomain($domain);
        $this->assertSame($iamKey, $domain->getIamKey());

        // 从域名移除IAM密钥
        $domain->setIamKey(null);
        $this->assertTrue($iamKey->getDomains()->contains($domain));

        // 从IAM密钥移除域名
        $iamKey->removeDomain($domain);
        $this->assertNull($domain->getIamKey());
    }

    public function test_email_format_access_key(): void
    {
        $iamKey = new IamKey();

        $validEmails = [
            'user@example.com',
            'test.email@domain.co.uk',
            'admin+test@cloudflare.com',
            'user123@test-domain.org'
        ];

        foreach ($validEmails as $email) {
            $iamKey->setAccessKey($email);
            $this->assertEquals($email, $iamKey->getAccessKey());
        }
    }

    public function test_account_id_format(): void
    {
        $iamKey = new IamKey();

        $validAccountIds = [
            'account123',
            'acc-456-789',
            'cf_account_789',
            'ACCOUNT-ABC-123'
        ];

        foreach ($validAccountIds as $accountId) {
            $iamKey->setAccountId($accountId);
            $this->assertEquals($accountId, $iamKey->getAccountId());
        }
    }

    public function test_secret_key_format(): void
    {
        $iamKey = new IamKey();

        $validSecretKeys = [
            'sk-1234567890abcdef',
            'token_abcdef123456',
            'api-key-very-long-string-here',
            'cloudflare-secret-key-123'
        ];

        foreach ($validSecretKeys as $secretKey) {
            $iamKey->setSecretKey($secretKey);
            $this->assertEquals($secretKey, $iamKey->getSecretKey());
        }
    }

    public function test_note_with_multiline_text(): void
    {
        $iamKey = new IamKey();
        $multilineNote = "This is line 1\nThis is line 2\nThis is line 3";

        $iamKey->setNote($multilineNote);

        $this->assertEquals($multilineNote, $iamKey->getNote());
    }

    public function test_note_with_special_characters(): void
    {
        $iamKey = new IamKey();
        $specialNote = "Note with special chars: !@#$%^&*()_+-=[]{}|;':\",./<>?";

        $iamKey->setNote($specialNote);

        $this->assertEquals($specialNote, $iamKey->getNote());
    }
} 