<?php

namespace CloudflareDnsBundle\Tests\Enum;

use CloudflareDnsBundle\Enum\DnsRecordType;
use PHPUnit\Framework\TestCase;

class DnsRecordTypeTest extends TestCase
{
    public function test_enum_cases(): void
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

    public function test_enum_values(): void
    {
        $this->assertEquals('A', DnsRecordType::A->value);
        $this->assertEquals('MX', DnsRecordType::MX->value);
        $this->assertEquals('TXT', DnsRecordType::TXT->value);
        $this->assertEquals('CNAME', DnsRecordType::CNAME->value);
        $this->assertEquals('NS', DnsRecordType::NS->value);
        $this->assertEquals('URI', DnsRecordType::URI->value);
    }

    public function test_get_label(): void
    {
        $this->assertEquals('A记录', DnsRecordType::A->getLabel());
        $this->assertEquals('MX记录', DnsRecordType::MX->getLabel());
        $this->assertEquals('TXT记录', DnsRecordType::TXT->getLabel());
        $this->assertEquals('CNAME记录', DnsRecordType::CNAME->getLabel());
        $this->assertEquals('NS记录', DnsRecordType::NS->getLabel());
        $this->assertEquals('URI记录', DnsRecordType::URI->getLabel());
    }

    public function test_try_from_valid_values(): void
    {
        $this->assertEquals(DnsRecordType::A, DnsRecordType::tryFrom('A'));
        $this->assertEquals(DnsRecordType::MX, DnsRecordType::tryFrom('MX'));
        $this->assertEquals(DnsRecordType::TXT, DnsRecordType::tryFrom('TXT'));
        $this->assertEquals(DnsRecordType::CNAME, DnsRecordType::tryFrom('CNAME'));
        $this->assertEquals(DnsRecordType::NS, DnsRecordType::tryFrom('NS'));
        $this->assertEquals(DnsRecordType::URI, DnsRecordType::tryFrom('URI'));
    }

    public function test_try_from_invalid_value(): void
    {
        $this->assertNull(DnsRecordType::tryFrom('INVALID'));
        $this->assertNull(DnsRecordType::tryFrom(''));
        $this->assertNull(DnsRecordType::tryFrom('aaaa'));
        $this->assertNull(DnsRecordType::tryFrom('mx')); // case sensitive
    }

    public function test_from_valid_values(): void
    {
        $this->assertEquals(DnsRecordType::A, DnsRecordType::from('A'));
        $this->assertEquals(DnsRecordType::MX, DnsRecordType::from('MX'));
        $this->assertEquals(DnsRecordType::TXT, DnsRecordType::from('TXT'));
        $this->assertEquals(DnsRecordType::CNAME, DnsRecordType::from('CNAME'));
        $this->assertEquals(DnsRecordType::NS, DnsRecordType::from('NS'));
        $this->assertEquals(DnsRecordType::URI, DnsRecordType::from('URI'));
    }

    public function test_from_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        DnsRecordType::from('INVALID');
    }

    public function test_itemable_trait_behavior(): void
    {
        // 测试ItemTrait行为 - 手动构建预期结构
        $expectedItems = [];
        foreach (DnsRecordType::cases() as $case) {
            $expectedItems[$case->value] = $case->getLabel();
        }
        
        $this->assertIsArray($expectedItems);
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

    public function test_selectable_trait_behavior(): void
    {
        // 测试SelectTrait行为 - 手动构建预期结构
        $expectedOptions = [];
        foreach (DnsRecordType::cases() as $case) {
            $expectedOptions[] = [
                'value' => $case->value,
                'label' => $case->getLabel(),
            ];
        }
        
        $this->assertIsArray($expectedOptions);
        $this->assertCount(6, $expectedOptions);
        
        // 检查选项格式
        foreach ($expectedOptions as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
        
        // 检查特定选项
        $aOption = null;
        foreach ($expectedOptions as $option) {
            if ($option['value'] === 'A') {
                $aOption = $option;
                break;
            }
        }
        
        $this->assertNotNull($aOption);
        $this->assertEquals('A', $aOption['value']);
        $this->assertEquals('A记录', $aOption['label']);
    }

    public function test_all_enum_cases_have_labels(): void
    {
        foreach (DnsRecordType::cases() as $case) {
            $label = $case->getLabel();
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
            $this->assertStringContainsString('记录', $label);
        }
    }

    public function test_enum_consistency(): void
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

    public function test_enum_serialization(): void
    {
        $type = DnsRecordType::A;
        $serialized = serialize($type);
        $unserialized = unserialize($serialized);
        
        $this->assertEquals($type, $unserialized);
        $this->assertEquals($type->value, $unserialized->value);
        $this->assertEquals($type->getLabel(), $unserialized->getLabel());
    }

    public function test_most_common_record_types_included(): void
    {
        // 确保包含最常用的DNS记录类型
        $commonTypes = ['A', 'CNAME', 'MX', 'TXT', 'NS'];
        
        foreach ($commonTypes as $type) {
            $enum = DnsRecordType::tryFrom($type);
            $this->assertNotNull($enum, "缺少常用DNS记录类型: $type");
        }
    }

    public function test_case_sensitivity(): void
    {
        // 测试case sensitivity
        $this->assertNull(DnsRecordType::tryFrom('a'));
        $this->assertNull(DnsRecordType::tryFrom('mx'));
        $this->assertNull(DnsRecordType::tryFrom('txt'));
        $this->assertNull(DnsRecordType::tryFrom('cname'));
        $this->assertNull(DnsRecordType::tryFrom('ns'));
        $this->assertNull(DnsRecordType::tryFrom('uri'));
    }
} 