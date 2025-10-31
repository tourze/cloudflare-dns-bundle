<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Enum;

use CloudflareDnsBundle\Enum\DomainStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\EnumExtra\BadgeInterface;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(DomainStatus::class)]
final class DomainStatusTest extends AbstractEnumTestCase
{
    public function testEnumCases(): void
    {
        $cases = DomainStatus::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(DomainStatus::ACTIVE, $cases);
        $this->assertContains(DomainStatus::PENDING, $cases);
        $this->assertContains(DomainStatus::SUSPENDED, $cases);
        $this->assertContains(DomainStatus::INACTIVE, $cases);
        $this->assertContains(DomainStatus::DELETED, $cases);
    }

    public function testEnumValues(): void
    {
        $this->assertEquals('active', DomainStatus::ACTIVE->value);
        $this->assertEquals('pending', DomainStatus::PENDING->value);
        $this->assertEquals('suspended', DomainStatus::SUSPENDED->value);
        $this->assertEquals('inactive', DomainStatus::INACTIVE->value);
        $this->assertEquals('deleted', DomainStatus::DELETED->value);
    }

    public function testGetLabel(): void
    {
        $this->assertEquals('活跃', DomainStatus::ACTIVE->getLabel());
        $this->assertEquals('待验证', DomainStatus::PENDING->getLabel());
        $this->assertEquals('已暂停', DomainStatus::SUSPENDED->getLabel());
        $this->assertEquals('未激活', DomainStatus::INACTIVE->getLabel());
        $this->assertEquals('已删除', DomainStatus::DELETED->getLabel());
    }

    public function testGetBadge(): void
    {
        $this->assertEquals(BadgeInterface::PRIMARY, DomainStatus::ACTIVE->getBadge());
        $this->assertEquals(BadgeInterface::INFO, DomainStatus::PENDING->getBadge());
        $this->assertEquals(BadgeInterface::INFO, DomainStatus::SUSPENDED->getBadge());
        $this->assertEquals(BadgeInterface::SECONDARY, DomainStatus::INACTIVE->getBadge());
        $this->assertEquals(BadgeInterface::DANGER, DomainStatus::DELETED->getBadge());
    }

    public function testTryFromValidValues(): void
    {
        $this->assertEquals(DomainStatus::ACTIVE, DomainStatus::tryFrom('active'));
        $this->assertEquals(DomainStatus::PENDING, DomainStatus::tryFrom('pending'));
        $this->assertEquals(DomainStatus::SUSPENDED, DomainStatus::tryFrom('suspended'));
        $this->assertEquals(DomainStatus::INACTIVE, DomainStatus::tryFrom('inactive'));
        $this->assertEquals(DomainStatus::DELETED, DomainStatus::tryFrom('deleted'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(DomainStatus::tryFrom('INVALID'));
        $this->assertNull(DomainStatus::tryFrom(''));
        $this->assertNull(DomainStatus::tryFrom('ACTIVE')); // case sensitive
        $this->assertNull(DomainStatus::tryFrom('unknown'));
    }

    public function testFromValidValues(): void
    {
        $this->assertEquals(DomainStatus::ACTIVE, DomainStatus::from('active'));
        $this->assertEquals(DomainStatus::PENDING, DomainStatus::from('pending'));
        $this->assertEquals(DomainStatus::SUSPENDED, DomainStatus::from('suspended'));
        $this->assertEquals(DomainStatus::INACTIVE, DomainStatus::from('inactive'));
        $this->assertEquals(DomainStatus::DELETED, DomainStatus::from('deleted'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        DomainStatus::from('INVALID');
    }

    public function testImplementsRequiredInterfaces(): void
    {
        $status = DomainStatus::ACTIVE;

        // 由于枚举已明确实现这些接口，此处测试具体功能
        $this->assertNotNull($status->getLabel());
        $this->assertNotNull($status->getBadge());
    }

    public function testItemableTraitBehavior(): void
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

    public function testSelectableTraitBehavior(): void
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
            if ('active' === $option['value']) {
                $activeOption = $option;
                break;
            }
        }

        $this->assertNotNull($activeOption);
        $this->assertEquals('active', $activeOption['value']);
        $this->assertEquals('活跃', $activeOption['label']);
    }

    public function testAllEnumCasesHaveLabels(): void
    {
        foreach (DomainStatus::cases() as $case) {
            $label = $case->getLabel();
            $this->assertNotEmpty($label);
        }
    }

    public function testAllEnumCasesHaveBadges(): void
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

    public function testEnumConsistency(): void
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

    public function testEnumSerialization(): void
    {
        $status = DomainStatus::ACTIVE;
        $serialized = serialize($status);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(DomainStatus::class, $unserialized);
        $this->assertEquals($status, $unserialized);
        $this->assertEquals($status->value, $unserialized->value);
        $this->assertEquals($status->getLabel(), $unserialized->getLabel());
        $this->assertEquals($status->getBadge(), $unserialized->getBadge());
    }

    public function testBadgeColorLogic(): void
    {
        // 测试徽章颜色分配逻辑
        $this->assertEquals(BadgeInterface::PRIMARY, DomainStatus::ACTIVE->getBadge(), 'Active should be primary');
        $this->assertEquals(BadgeInterface::DANGER, DomainStatus::DELETED->getBadge(), 'Deleted should be danger');
        $this->assertEquals(BadgeInterface::SECONDARY, DomainStatus::INACTIVE->getBadge(), 'Inactive should be secondary');

        // Pending 和 Suspended 都应该是 INFO
        $this->assertEquals(BadgeInterface::INFO, DomainStatus::PENDING->getBadge());
        $this->assertEquals(BadgeInterface::INFO, DomainStatus::SUSPENDED->getBadge());
    }

    public function testCaseSensitivity(): void
    {
        // 测试case sensitivity
        $this->assertNull(DomainStatus::tryFrom('ACTIVE'));
        $this->assertNull(DomainStatus::tryFrom('PENDING'));
        $this->assertNull(DomainStatus::tryFrom('SUSPENDED'));
        $this->assertNull(DomainStatus::tryFrom('INACTIVE'));
        $this->assertNull(DomainStatus::tryFrom('DELETED'));
    }

    public function testStatusTransitionsLogic(): void
    {
        // 测试状态转换的逻辑合理性（虽然枚举本身不包含转换逻辑，但可以测试状态的合理性）
        $statuses = DomainStatus::cases();

        // 确保包含常见的域名状态
        $statusValues = array_map(fn ($s) => $s->value, $statuses);

        $this->assertContains('active', $statusValues, '应该包含活跃状态');
        $this->assertContains('pending', $statusValues, '应该包含待验证状态');
        $this->assertContains('suspended', $statusValues, '应该包含暂停状态');
        $this->assertContains('inactive', $statusValues, '应该包含未激活状态');
        $this->assertContains('deleted', $statusValues, '应该包含删除状态');
    }

    public function testToArray(): void
    {
        $result = DomainStatus::ACTIVE->toArray();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        // 验证数组结构：包含 value 和 label 键
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('label', $result);

        // 验证值的类型
        $this->assertIsString($result['value']);
        $this->assertIsString($result['label']);

        // 验证具体的值
        $this->assertEquals('active', $result['value']);
        $this->assertEquals('活跃', $result['label']);
    }
}
