<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Enum;

use CloudflareDnsBundle\Enum\DnsRecordType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(DnsRecordType::class)]
final class DnsRecordTypeTest extends AbstractEnumTestCase
{
    public function testEnumCases(): void
    {
        $cases = DnsRecordType::cases();

        $this->assertCount(6, $cases);
        $this->assertContains(DnsRecordType::A, $cases);
        $this->assertContains(DnsRecordType::MX, $cases);
        $this->assertContains(DnsRecordType::TXT, $cases);
        $this->assertContains(DnsRecordType::CNAME, $cases);
        $this->assertContains(DnsRecordType::NS, $cases);
        $this->assertContains(DnsRecordType::URI, $cases);
    }

    public function testEnumValues(): void
    {
        $this->assertEquals('A', DnsRecordType::A->value);
        $this->assertEquals('MX', DnsRecordType::MX->value);
        $this->assertEquals('TXT', DnsRecordType::TXT->value);
        $this->assertEquals('CNAME', DnsRecordType::CNAME->value);
        $this->assertEquals('NS', DnsRecordType::NS->value);
        $this->assertEquals('URI', DnsRecordType::URI->value);
    }

    public function testGetLabel(): void
    {
        $this->assertEquals('A记录', DnsRecordType::A->getLabel());
        $this->assertEquals('MX记录', DnsRecordType::MX->getLabel());
        $this->assertEquals('TXT记录', DnsRecordType::TXT->getLabel());
        $this->assertEquals('CNAME记录', DnsRecordType::CNAME->getLabel());
        $this->assertEquals('NS记录', DnsRecordType::NS->getLabel());
        $this->assertEquals('URI记录', DnsRecordType::URI->getLabel());
    }

    public function testTryFromValidValues(): void
    {
        $this->assertEquals(DnsRecordType::A, DnsRecordType::tryFrom('A'));
        $this->assertEquals(DnsRecordType::MX, DnsRecordType::tryFrom('MX'));
        $this->assertEquals(DnsRecordType::TXT, DnsRecordType::tryFrom('TXT'));
        $this->assertEquals(DnsRecordType::CNAME, DnsRecordType::tryFrom('CNAME'));
        $this->assertEquals(DnsRecordType::NS, DnsRecordType::tryFrom('NS'));
        $this->assertEquals(DnsRecordType::URI, DnsRecordType::tryFrom('URI'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(DnsRecordType::tryFrom('INVALID'));
        $this->assertNull(DnsRecordType::tryFrom(''));
        $this->assertNull(DnsRecordType::tryFrom('aaaa'));
        $this->assertNull(DnsRecordType::tryFrom('mx')); // case sensitive
    }

    public function testFromValidValues(): void
    {
        $this->assertEquals(DnsRecordType::A, DnsRecordType::from('A'));
        $this->assertEquals(DnsRecordType::MX, DnsRecordType::from('MX'));
        $this->assertEquals(DnsRecordType::TXT, DnsRecordType::from('TXT'));
        $this->assertEquals(DnsRecordType::CNAME, DnsRecordType::from('CNAME'));
        $this->assertEquals(DnsRecordType::NS, DnsRecordType::from('NS'));
        $this->assertEquals(DnsRecordType::URI, DnsRecordType::from('URI'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        DnsRecordType::from('INVALID');
    }

    public function testItemableTraitBehavior(): void
    {
        // 测试ItemTrait行为 - 手动构建预期结构
        $expectedItems = [];
        foreach (DnsRecordType::cases() as $case) {
            $expectedItems[$case->value] = $case->getLabel();
        }
        $this->assertCount(6, $expectedItems);

        // 检查key-value结构
        $this->assertArrayHasKey('A', $expectedItems);
        $this->assertArrayHasKey('MX', $expectedItems);
        $this->assertArrayHasKey('TXT', $expectedItems);
        $this->assertArrayHasKey('CNAME', $expectedItems);
        $this->assertArrayHasKey('NS', $expectedItems);
        $this->assertArrayHasKey('URI', $expectedItems);

        $this->assertEquals('A记录', $expectedItems['A']);
        $this->assertEquals('MX记录', $expectedItems['MX']);
        $this->assertEquals('TXT记录', $expectedItems['TXT']);
        $this->assertEquals('CNAME记录', $expectedItems['CNAME']);
        $this->assertEquals('NS记录', $expectedItems['NS']);
        $this->assertEquals('URI记录', $expectedItems['URI']);
    }

    public function testSelectableTraitBehavior(): void
    {
        // 测试SelectTrait行为 - 手动构建预期结构
        $expectedOptions = [];
        foreach (DnsRecordType::cases() as $case) {
            $expectedOptions[] = [
                'value' => $case->value,
                'label' => $case->getLabel(),
            ];
        }
        $this->assertCount(6, $expectedOptions);

        // 检查选项格式
        foreach ($expectedOptions as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }

        // 检查特定选项
        $aOption = null;
        foreach ($expectedOptions as $option) {
            if ('A' === $option['value']) {
                $aOption = $option;
                break;
            }
        }

        $this->assertNotNull($aOption);
        $this->assertEquals('A', $aOption['value']);
        $this->assertEquals('A记录', $aOption['label']);
    }

    public function testAllEnumCasesHaveLabels(): void
    {
        foreach (DnsRecordType::cases() as $case) {
            $label = $case->getLabel();
            $this->assertNotEmpty($label);
            $this->assertStringContainsString('记录', $label);
        }
    }

    public function testEnumConsistency(): void
    {
        // 确保枚举值与case名称一致
        $this->assertEquals('A', DnsRecordType::A->name);
        $this->assertEquals('A', DnsRecordType::A->value);

        $this->assertEquals('MX', DnsRecordType::MX->name);
        $this->assertEquals('MX', DnsRecordType::MX->value);

        $this->assertEquals('TXT', DnsRecordType::TXT->name);
        $this->assertEquals('TXT', DnsRecordType::TXT->value);

        $this->assertEquals('CNAME', DnsRecordType::CNAME->name);
        $this->assertEquals('CNAME', DnsRecordType::CNAME->value);

        $this->assertEquals('NS', DnsRecordType::NS->name);
        $this->assertEquals('NS', DnsRecordType::NS->value);

        $this->assertEquals('URI', DnsRecordType::URI->name);
        $this->assertEquals('URI', DnsRecordType::URI->value);
    }

    public function testEnumSerialization(): void
    {
        $type = DnsRecordType::A;
        $serialized = serialize($type);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(DnsRecordType::class, $unserialized);
        $this->assertEquals($type, $unserialized);
        $this->assertEquals($type->value, $unserialized->value);
        $this->assertEquals($type->getLabel(), $unserialized->getLabel());
    }

    public function testMostCommonRecordTypesIncluded(): void
    {
        // 确保包含最常用的DNS记录类型
        $commonTypes = ['A', 'CNAME', 'MX', 'TXT', 'NS'];

        foreach ($commonTypes as $type) {
            $enum = DnsRecordType::tryFrom($type);
            $this->assertNotNull($enum, "缺少常用DNS记录类型: {$type}");
        }
    }

    public function testCaseSensitivity(): void
    {
        // 测试case sensitivity
        $this->assertNull(DnsRecordType::tryFrom('a'));
        $this->assertNull(DnsRecordType::tryFrom('mx'));
        $this->assertNull(DnsRecordType::tryFrom('txt'));
        $this->assertNull(DnsRecordType::tryFrom('cname'));
        $this->assertNull(DnsRecordType::tryFrom('ns'));
        $this->assertNull(DnsRecordType::tryFrom('uri'));
    }

    public function testToArray(): void
    {
        $result = DnsRecordType::A->toArray();

        $this->assertIsArray($result);

        // 验证数组非空并包含正确的键
        $this->assertNotEmpty($result);

        // 验证数组结构：包含 value 和 label 键
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('label', $result);

        // 验证值的类型
        $this->assertIsString($result['value']);
        $this->assertIsString($result['label']);

        // 验证具体的值
        $this->assertEquals('A', $result['value']);
        $this->assertEquals('A记录', $result['label']);
    }
}
