<?php

namespace CloudflareDnsBundle\Entity;

use CloudflareDnsBundle\Enum\DnsRecordType;
use CloudflareDnsBundle\Repository\DnsRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;

#[ORM\Entity(repositoryClass: DnsRecordRepository::class)]
#[ORM\Table(name: 'ims_cloudflare_dns_record', options: ['comment' => 'CF解析记录'])]
#[ORM\UniqueConstraint(name: 'ims_cloudflare_dns_record_idx_uniq', columns: ['domain_id', 'type', 'record', 'record_id'])]
class DnsRecord implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = 0;

    #[ORM\ManyToOne(inversedBy: 'records')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?DnsDomain $domain = null;

    #[TrackColumn]
    #[ORM\Column(length: 64, enumType: DnsRecordType::class, options: ['comment' => '记录类型'])]
    private ?DnsRecordType $type = DnsRecordType::A;

    #[TrackColumn]
    #[ORM\Column(type: Types::STRING, length: 64, options: ['comment' => '域名记录'])]
    private ?string $record = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '记录ID'])]
    private ?string $recordId = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::TEXT, options: ['comment' => '记录值'])]
    private ?string $content = null;

    #[TrackColumn]
    #[ORM\Column(options: ['comment' => 'TTL'])]
    private ?int $ttl = 60;

    #[TrackColumn]
    #[ORM\Column(options: ['comment' => '是否代理'])]
    private ?bool $proxy = false;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否已同步到远程', 'default' => false])]
    private bool $synced = false;

    #[TrackColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '最后同步时间'])]
    private ?\DateTimeInterface $lastSyncedTime = null;

    #[CreatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '创建人'])]
    private ?string $createdBy = null;

    #[UpdatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '更新人'])]
    private ?string $updatedBy = null;

    /**
     * 是否正在同步中,用于防止循环执行
     */
    private bool $syncing = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomain(): ?DnsDomain
    {
        return $this->domain;
    }

    public function setDomain(?DnsDomain $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getRecord(): ?string
    {
        return $this->record;
    }

    public function setRecord(string $record): static
    {
        $this->record = $record;

        return $this;
    }

    public function getRecordId(): ?string
    {
        return $this->recordId;
    }

    public function setRecordId(?string $recordId): static
    {
        $this->recordId = $recordId;

        return $this;
    }

    public function getType(): ?DnsRecordType
    {
        return $this->type;
    }

    public function setType(DnsRecordType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): static
    {
        $this->ttl = $ttl;

        return $this;
    }

    public function isProxy(): ?bool
    {
        return $this->proxy;
    }

    public function setProxy(bool $proxy): static
    {
        $this->proxy = $proxy;

        return $this;
    }

    public function isSynced(): bool
    {
        return $this->synced;
    }

    public function setSynced(bool $synced): static
    {
        $this->synced = $synced;

        if ($synced) {
            $this->setLastSyncedTime(new \DateTime());
        }

        return $this;
    }

    public function getLastSyncedTime(): ?\DateTimeInterface
    {
        return $this->lastSyncedTime;
    }

    public function setLastSyncedTime(?\DateTimeInterface $lastSyncedTime): static
    {
        $this->lastSyncedTime = $lastSyncedTime;

        return $this;
    }

    public function isSyncing(): bool
    {
        return $this->syncing;
    }

    public function setSyncing(bool $syncing): static
    {
        $this->syncing = $syncing;
        return $this;
    }

    public function setCreatedBy(?string $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setUpdatedBy(?string $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function __toString(): string
    {
        $rs = $this->getId() ? $this->getRecord() : '';
        if ($this->getRecord()) {
            return "{$rs}({$this->getRecord()})";
        }

        return $rs;
    }

    public function getFullName(): string
    {
        return "{$this->getRecord()}.{$this->getDomain()->getName()}";
    }
}
