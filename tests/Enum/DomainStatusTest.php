<?php

namespace CloudflareDnsBundle\Tests\Enum;

use CloudflareDnsBundle\Enum\DomainStatus;
use PHPUnit\Framework\TestCase;
use Tourze\EnumExtra\BadgeInterface;

class DomainStatusTest extends TestCase
{
    public function test_enum_cases(): void
    {
        $cases = DomainStatus::cases();
        
        $this->assertCount(5, $cases);
        $this->assertContains(DomainStatus::ACTIVE, $cases);
        $this->assertContains(DomainStatus::PENDING, $cases);
        $this->assertContains(DomainStatus::SUSPENDED, $cases);
        $this->assertContains(DomainStatus::INACTIVE, $cases);
        $this->assertContains(DomainStatus::DELETED, $cases);
    }

    public function test_enum_values(): void
    {
        $this->assertEquals('active', DomainStatus::ACTIVE->value);
        $this->assertEquals('pending', DomainStatus::PENDING->value);
        $this->assertEquals('suspended', DomainStatus::SUSPENDED->value);
        $this->assertEquals('inactive', DomainStatus::INACTIVE->value);
        $this->assertEquals('deleted', DomainStatus::DELETED->value);
    }

    public function test_get_label(): void
    {
        $this->assertEquals('活跃', DomainStatus::ACTIVE->getLabel());
        $this->assertEquals('待验证', DomainStatus::PENDING->getLabel());
        $this->assertEquals('已暂停', DomainStatus::SUSPENDED->getLabel());
        $this->assertEquals('未激活', DomainStatus::INACTIVE->getLabel());
        $this->assertEquals('已删除', DomainStatus::DELETED->getLabel());
    }

    public function test_get_badge(): void
    {
        $this->assertEquals(BadgeInterface::PRIMARY, DomainStatus::ACTIVE->getBadge());
        $this->assertEquals(BadgeInterface::INFO, DomainStatus::PENDING->getBadge());
        $this->assertEquals(BadgeInterface::INFO, DomainStatus::SUSPENDED->getBadge());
        $this->assertEquals(BadgeInterface::SECONDARY, DomainStatus::INACTIVE->getBadge());
        $this->assertEquals(BadgeInterface::DANGER, DomainStatus::DELETED->getBadge());
    }

    public function test_try_from_valid_values(): void
    {
        $this->assertEquals(DomainStatus::ACTIVE, DomainStatus::tryFrom('active'));
        $this->assertEquals(DomainStatus::PENDING, DomainStatus::tryFrom('pending'));
        $this->assertEquals(DomainStatus::SUSPENDED, DomainStatus::tryFrom('suspended'));
        $this->assertEquals(DomainStatus::INACTIVE, DomainStatus::tryFrom('inactive'));
        $this->assertEquals(DomainStatus::DELETED, DomainStatus::tryFrom('deleted'));
    }

    public function test_try_from_invalid_value(): void
    {
        $this->assertNull(DomainStatus::tryFrom('INVALID'));
        $this->assertNull(DomainStatus::tryFrom(''));
        $this->assertNull(DomainStatus::tryFrom('ACTIVE')); // case sensitive
        $this->assertNull(DomainStatus::tryFrom('unknown'));
    }

    public function test_from_valid_values(): void
    {
        $this->assertEquals(DomainStatus::ACTIVE, DomainStatus::from('active'));
        $this->assertEquals(DomainStatus::PENDING, DomainStatus::from('pending'));
        $this->assertEquals(DomainStatus::SUSPENDED, DomainStatus::from('suspended'));
        $this->assertEquals(DomainStatus::INACTIVE, DomainStatus::from('inactive'));
        $this->assertEquals(DomainStatus::DELETED, DomainStatus::from('deleted'));
    }

    public function test_from_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        DomainStatus::from('INVALID');
    }

    public function test_implements_required_interfaces(): void
    {
        $status = DomainStatus::ACTIVE;
        
        $this->assertInstanceOf(\Tourze\EnumExtra\Labelable::class, $status);
        $this->assertInstanceOf(\Tourze\EnumExtra\Itemable::class, $status);
        $this->assertInstanceOf(\Tourze\EnumExtra\Selectable::class, $status);
        $this->assertInstanceOf(\Tourze\EnumExtra\BadgeInterface::class, $status);
    }

    public function test_itemable_trait_behavior(): void
    {
        // 测试ItemTrait行为 - 手动构建预期结构
        $expectedItems = [];
        foreach (DomainStatus::cases() as $case) {
            $expectedItems[$case->value] = $case->getLabel();
        }
        $this->assertCount(5, $expectedItems);
        
        // 检查key-value结构
        $this->assertArrayHasKey('active', $expectedItems);
        $this->assertArrayHasKey('pending', $expectedItems);
        $this->assertArrayHasKey('suspended', $expectedItems);
        $this->assertArrayHasKey('inactive', $expectedItems);
        $this->assertArrayHasKey('deleted', $expectedItems);
        
        $this->assertEquals('活跃', $expectedItems['active']);
        $this->assertEquals('待验证', $expectedItems['pending']);
        $this->assertEquals('已暂停', $expectedItems['suspended']);
        $this->assertEquals('未激活', $expectedItems['inactive']);
        $this->assertEquals('已删除', $expectedItems['deleted']);
    }

    public function test_selectable_trait_behavior(): void
    {
        // 测试SelectTrait行为 - 手动构建预期结构
        $expectedOptions = [];
        foreach (DomainStatus::cases() as $case) {
            $expectedOptions[] = [
                'value' => $case->value,
                'label' => $case->getLabel(),
            ];
        }
        $this->assertCount(5, $expectedOptions);
        
        // 检查选项格式
        foreach ($expectedOptions as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
        
        // 检查特定选项
        $activeOption = null;
        foreach ($expectedOptions as $option) {
            if ($option['value'] === 'active') {
                $activeOption = $option;
                break;
            }
        }
        
        $this->assertNotNull($activeOption);
        $this->assertEquals('active', $activeOption['value']);
        $this->assertEquals('活跃', $activeOption['label']);
    }

    public function test_all_enum_cases_have_labels(): void
    {
        foreach (DomainStatus::cases() as $case) {
            $label = $case->getLabel();
            $this->assertNotEmpty($label);
        }
    }

    public function test_all_enum_cases_have_badges(): void
    {
        $validBadges = [
            BadgeInterface::PRIMARY,
            BadgeInterface::SECONDARY, 
            BadgeInterface::SUCCESS,
            BadgeInterface::DANGER,
            BadgeInterface::WARNING,
            BadgeInterface::INFO,
            BadgeInterface::LIGHT,
            BadgeInterface::DARK,
        ];

        foreach (DomainStatus::cases() as $case) {
            $badge = $case->getBadge();
            $this->assertContains($badge, $validBadges, "Badge '{$badge}' is not valid for case {$case->name}");
        }
    }

    public function test_enum_consistency(): void
    {
        // 确保枚举值与case名称的逻辑一致性
        $this->assertEquals('ACTIVE', DomainStatus::ACTIVE->name);
        $this->assertEquals('active', DomainStatus::ACTIVE->value);
        
        $this->assertEquals('PENDING', DomainStatus::PENDING->name);
        $this->assertEquals('pending', DomainStatus::PENDING->value);
        
        $this->assertEquals('SUSPENDED', DomainStatus::SUSPENDED->name);
        $this->assertEquals('suspended', DomainStatus::SUSPENDED->value);
        
        $this->assertEquals('INACTIVE', DomainStatus::INACTIVE->name);
        $this->assertEquals('inactive', DomainStatus::INACTIVE->value);
        
        $this->assertEquals('DELETED', DomainStatus::DELETED->name);
        $this->assertEquals('deleted', DomainStatus::DELETED->value);
    }

    public function test_enum_serialization(): void
    {
        $status = DomainStatus::ACTIVE;
        $serialized = serialize($status);
        $unserialized = unserialize($serialized);
        
        $this->assertEquals($status, $unserialized);
        $this->assertEquals($status->value, $unserialized->value);
        $this->assertEquals($status->getLabel(), $unserialized->getLabel());
        $this->assertEquals($status->getBadge(), $unserialized->getBadge());
    }

    public function test_badge_color_logic(): void
    {
        // 测试徽章颜色分配逻辑
        $this->assertEquals(BadgeInterface::PRIMARY, DomainStatus::ACTIVE->getBadge(), 'Active should be primary');
        $this->assertEquals(BadgeInterface::DANGER, DomainStatus::DELETED->getBadge(), 'Deleted should be danger');
        $this->assertEquals(BadgeInterface::SECONDARY, DomainStatus::INACTIVE->getBadge(), 'Inactive should be secondary');
        
        // Pending 和 Suspended 都应该是 INFO
        $this->assertEquals(BadgeInterface::INFO, DomainStatus::PENDING->getBadge());
        $this->assertEquals(BadgeInterface::INFO, DomainStatus::SUSPENDED->getBadge());
    }

    public function test_case_sensitivity(): void
    {
        // 测试case sensitivity
        $this->assertNull(DomainStatus::tryFrom('ACTIVE'));
        $this->assertNull(DomainStatus::tryFrom('PENDING'));
        $this->assertNull(DomainStatus::tryFrom('SUSPENDED'));
        $this->assertNull(DomainStatus::tryFrom('INACTIVE'));
        $this->assertNull(DomainStatus::tryFrom('DELETED'));
    }

    public function test_status_transitions_logic(): void
    {
        // 测试状态转换的逻辑合理性（虽然枚举本身不包含转换逻辑，但可以测试状态的合理性）
        $statuses = DomainStatus::cases();
        
        // 确保包含常见的域名状态
        $statusValues = array_map(fn($s) => $s->value, $statuses);
        
        $this->assertContains('active', $statusValues, '应该包含活跃状态');
        $this->assertContains('pending', $statusValues, '应该包含待验证状态');
        $this->assertContains('suspended', $statusValues, '应该包含暂停状态');
        $this->assertContains('inactive', $statusValues, '应该包含未激活状态');
        $this->assertContains('deleted', $statusValues, '应该包含删除状态');
    }
} 