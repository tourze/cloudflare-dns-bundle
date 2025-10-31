<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Entity;

use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;

#[ORM\Entity(repositoryClass: DnsRecordRepository::class)]
#[ORM\Table(name: 'ims_cloudflare_dns_record', options: ['comment' => 'CF解析记录'])]
#[ORM\UniqueConstraint(name: 'ims_cloudflare_dns_record_idx_uniq', columns: ['domain_id', 'type', 'record', 'record_id'])]
class DnsRecord implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'records', cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '域名不能为空')]
    private ?DnsDomain $domain = null;

    #[TrackColumn]
    #[ORM\Column(length: 64, enumType: DnsRecordType::class, options: ['comment' => '记录类型'])]
    #[Assert\Choice(callback: [DnsRecordType::class, 'cases'], message: '请选择有效的DNS记录类型')]
    private DnsRecordType $type = DnsRecordType::A;

    #[TrackColumn]
    #[ORM\Column(type: Types::STRING, length: 64, options: ['comment' => '域名记录'])]
    #[Assert\NotBlank(message: '域名记录不能为空')]
    #[Assert\Length(max: 64, maxMessage: '域名记录长度不能超过 {{ limit }} 个字符')]
    private ?string $record = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '记录ID'])]
    #[Assert\Length(max: 64, maxMessage: '记录ID长度不能超过 {{ limit }} 个字符')]
    private ?string $recordId = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::TEXT, options: ['comment' => '记录值'])]
    #[Assert\NotBlank(message: '记录值不能为空')]
    #[Assert\Length(max: 65535, maxMessage: '记录值长度不能超过 {{ limit }} 个字符')]
    private ?string $content = null;

    #[TrackColumn]
    #[ORM\Column(options: ['comment' => 'TTL'])]
    #[Assert\PositiveOrZero(message: 'TTL不能为负数')]
    #[Assert\Range(min: 1, max: 86400, notInRangeMessage: 'TTL值必须在 {{ min }} 到 {{ max }} 之间')]
    private int $ttl = 60;

    #[TrackColumn]
    #[ORM\Column(options: ['comment' => '是否代理'])]
    #[Assert\Type(type: 'bool', message: '代理状态必须是布尔值')]
    private bool $proxy = false;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否已同步到远程', 'default' => false])]
    #[Assert\Type(type: 'bool', message: '同步状态必须是布尔值')]
    private bool $synced = false;

    #[TrackColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后同步时间'])]
    #[Assert\Type(type: \DateTimeInterface::class, message: '最后同步时间必须是有效的日期时间')]
    private ?\DateTimeInterface $lastSyncedTime = null;

    /**
     * 是否正在同步中,用于防止循环执行
     */
    #[Assert\Type(type: 'bool', message: '同步中状态必须是布尔值')]
    private bool $syncing = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomain(): ?DnsDomain
    {
        return $this->domain;
    }

    public function setDomain(?DnsDomain $domain): void
    {
        $this->domain = $domain;
    }

    public function getRecord(): ?string
    {
        return $this->record;
    }

    public function setRecord(string $record): void
    {
        $this->record = $record;
    }

    public function getRecordId(): ?string
    {
        return $this->recordId;
    }

    public function setRecordId(?string $recordId): void
    {
        $this->recordId = $recordId;
    }

    public function getType(): DnsRecordType
    {
        return $this->type;
    }

    public function setType(DnsRecordType $type): void
    {
        $this->type = $type;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    public function isProxy(): bool
    {
        return $this->proxy;
    }

    public function setProxy(bool $proxy): void
    {
        $this->proxy = $proxy;
    }

    public function isSynced(): bool
    {
        return $this->synced;
    }

    public function setSynced(bool $synced): void
    {
        $this->synced = $synced;

        if ($synced) {
            $this->setLastSyncedTime(new \DateTimeImmutable());
        }
    }

    public function getLastSyncedTime(): ?\DateTimeInterface
    {
        return $this->lastSyncedTime;
    }

    public function setLastSyncedTime(?\DateTimeInterface $lastSyncedTime): void
    {
        $this->lastSyncedTime = $lastSyncedTime;
    }

    public function isSyncing(): bool
    {
        return $this->syncing;
    }

    public function setSyncing(bool $syncing): void
    {
        $this->syncing = $syncing;
    }

    public function __toString(): string
    {
        if (null !== $this->getRecord()) {
            return "({$this->getRecord()})";
        }

        return '';
    }

    public function getFullName(): string
    {
        $domain = $this->getDomain();
        if (null === $domain) {
            return $this->getRecord() ?? '';
        }

        return "{$this->getRecord()}.{$domain->getName()}";
    }
}
