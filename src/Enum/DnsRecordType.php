<?php

namespace CloudflareDnsBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum DnsRecordType: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case A = 'A';
    case MX = 'MX';
    case TXT = 'TXT';
    case CNAME = 'CNAME';
    case NS = 'NS';
    case URI = 'URI';

    public function getLabel(): string
    {
        return match ($this) {
            self::A => 'A记录',
            self::MX => 'MX记录',
            self::TXT => 'TXT记录',
            self::CNAME => 'CNAME记录',
            self::NS => 'NS记录',
            self::URI => 'URI记录',
        };
    }
}
