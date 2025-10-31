<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum DomainStatus: string implements Labelable, Itemable, Selectable, BadgeInterface
{
    use ItemTrait;
    use SelectTrait;
    case ACTIVE = 'active';
    case PENDING = 'pending';
    case SUSPENDED = 'suspended';
    case INACTIVE = 'inactive';
    case DELETED = 'deleted';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => '活跃',
            self::PENDING => '待验证',
            self::SUSPENDED => '已暂停',
            self::INACTIVE => '未激活',
            self::DELETED => '已删除',
        };
    }

    public function getBadge(): string
    {
        return match ($this) {
            self::ACTIVE => self::PRIMARY,
            self::PENDING, self::SUSPENDED => self::INFO,
            self::INACTIVE => self::SECONDARY,
            self::DELETED => self::DANGER,
        };
    }
}
