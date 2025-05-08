<?php

namespace CloudflareDnsBundle\Entity;

use CloudflareDnsBundle\Repository\DnsDomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Attribute\CreateTimeColumn;
use Tourze\DoctrineTimestampBundle\Attribute\UpdateTimeColumn;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;
use Tourze\EasyAdmin\Attribute\Action\Creatable;
use Tourze\EasyAdmin\Attribute\Action\CurdAction;
use Tourze\EasyAdmin\Attribute\Action\Deletable;
use Tourze\EasyAdmin\Attribute\Action\Editable;
use Tourze\EasyAdmin\Attribute\Column\BoolColumn;
use Tourze\EasyAdmin\Attribute\Column\ExportColumn;
use Tourze\EasyAdmin\Attribute\Column\ListColumn;
use Tourze\EasyAdmin\Attribute\Field\FormField;
use Tourze\EasyAdmin\Attribute\Filter\Filterable;
use Tourze\EasyAdmin\Attribute\Filter\Keyword;
use Tourze\EasyAdmin\Attribute\Permission\AsPermission;

#[AsPermission(title: '域名管理')]
#[Creatable]
#[Editable]
#[Deletable]
#[ORM\Entity(repositoryClass: DnsDomainRepository::class)]
#[ORM\Table(name: 'ims_cloudflare_dns_domain', options: ['comment' => 'CF域名'])]
#[ORM\UniqueConstraint(name: 'ims_cloudflare_dns_domain_idx_uniq', columns: ['iam_key_id', 'name'])]
class DnsDomain implements \Stringable
{
    #[ListColumn(order: -1)]
    #[ExportColumn]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = 0;

    #[BoolColumn]
    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '有效', 'default' => 0])]
    #[ListColumn(order: 97)]
    #[FormField(order: 97)]
    private ?bool $valid = false;

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(?bool $valid): self
    {
        $this->valid = $valid;

        return $this;
    }

    #[CreatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '创建人'])]
    private ?string $createdBy = null;

    #[UpdatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '更新人'])]
    private ?string $updatedBy = null;

    #[Filterable]
    #[IndexColumn]
    #[ListColumn(order: 98, sorter: true)]
    #[ExportColumn]
    #[CreateTimeColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '创建时间'])]
    private ?\DateTimeInterface $createTime = null;

    #[UpdateTimeColumn]
    #[ListColumn(order: 99, sorter: true)]
    #[Filterable]
    #[ExportColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '更新时间'])]
    private ?\DateTimeInterface $updateTime = null;

    #[Filterable(label: 'IAM账号')]
    #[ListColumn(title: 'IAM账号')]
    #[FormField(title: 'IAM账号')]
    #[ORM\ManyToOne(inversedBy: 'domains')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?IamKey $iamKey = null;

    #[IndexColumn]
    #[Keyword]
    #[ListColumn]
    #[FormField]
    #[TrackColumn]
    #[ORM\Column(length: 128, unique: true, options: ['comment' => '根域名记录'])]
    private ?string $name = null;

    #[ListColumn]
    #[FormField]
    #[ORM\Column(length: 64, nullable: true, options: ['comment' => 'Zone ID'])]
    private ?string $zoneId = null;

    #[ListColumn]
    #[FormField]
    #[ORM\Column(length: 64, nullable: true, options: ['comment' => 'Account ID'])]
    private ?string $accountId = null;

    #[ListColumn]
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '注册商'])]
    private ?string $registrar = null;

    #[ListColumn]
    #[ORM\Column(type: Types::STRING, length: 32, nullable: true, options: ['comment' => '状态'])]
    private ?string $status = null;

    #[ListColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '过期时间'])]
    private ?\DateTimeInterface $expiresAt = null;

    #[ListColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '锁定截止时间'])]
    private ?\DateTimeInterface $lockedUntil = null;

    #[BoolColumn]
    #[ListColumn]
    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否自动续费'])]
    private bool $autoRenew = false;

    #[Ignore]
    #[CurdAction(label: '解析记录')]
    #[ORM\OneToMany(mappedBy: 'domain', targetEntity: DnsRecord::class)]
    private Collection $records;

    #[FormField]
    #[ORM\Column(length: 255, nullable: true, options: ['comment' => 'CERT'])]
    private ?string $tlsCertPath = null;

    #[FormField]
    #[ORM\Column(length: 255, nullable: true, options: ['comment' => 'KEY'])]
    private ?string $tlsKeyPath = null;

    #[FormField]
    #[ORM\Column(length: 255, nullable: true, options: ['comment' => '完整证书链'])]
    private ?string $tlsFullchainPath = null;

    #[FormField]
    #[ORM\Column(length: 255, nullable: true, options: ['comment' => '中间证书'])]
    private ?string $tlsChainPath = null;

    #[ListColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => 'TLS过期时间'])]
    private ?\DateTimeInterface $tlsExpireTime = null;

    public function __construct()
    {
        $this->records = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getZoneId(): ?string
    {
        return $this->zoneId;
    }

    public function setZoneId(?string $zoneId): static
    {
        $this->zoneId = $zoneId;

        return $this;
    }

    public function getIamKey(): ?IamKey
    {
        return $this->iamKey;
    }

    public function setIamKey(?IamKey $iamKey): static
    {
        $this->iamKey = $iamKey;

        return $this;
    }

    /**
     * @return Collection<int, DnsRecord>
     */
    public function getRecords(): Collection
    {
        return $this->records;
    }

    public function addRecord(DnsRecord $record): static
    {
        if (!$this->records->contains($record)) {
            $this->records->add($record);
            $record->setDomain($this);
        }

        return $this;
    }

    public function removeRecord(DnsRecord $record): static
    {
        if ($this->records->removeElement($record)) {
            // set the owning side to null (unless already changed)
            if ($record->getDomain() === $this) {
                $record->setDomain(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getId() ? $this->getName() : '';
    }

    public function getAccountId(): ?string
    {
        return $this->accountId;
    }

    public function setAccountId(?string $accountId): static
    {
        $this->accountId = $accountId;

        return $this;
    }

    public function getRegistrar(): ?string
    {
        return $this->registrar;
    }

    public function setRegistrar(?string $registrar): static
    {
        $this->registrar = $registrar;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getLockedUntil(): ?\DateTimeInterface
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeInterface $lockedUntil): static
    {
        $this->lockedUntil = $lockedUntil;

        return $this;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function setAutoRenew(bool $autoRenew): static
    {
        $this->autoRenew = $autoRenew;

        return $this;
    }

    public function getTlsCertPath(): ?string
    {
        return $this->tlsCertPath;
    }

    public function setTlsCertPath(?string $tlsCertPath): static
    {
        $this->tlsCertPath = $tlsCertPath;

        return $this;
    }

    public function getTlsKeyPath(): ?string
    {
        return $this->tlsKeyPath;
    }

    public function setTlsKeyPath(?string $tlsKeyPath): static
    {
        $this->tlsKeyPath = $tlsKeyPath;

        return $this;
    }

    public function getTlsFullchainPath(): ?string
    {
        return $this->tlsFullchainPath;
    }

    public function setTlsFullchainPath(?string $tlsFullchainPath): static
    {
        $this->tlsFullchainPath = $tlsFullchainPath;

        return $this;
    }

    public function getTlsChainPath(): ?string
    {
        return $this->tlsChainPath;
    }

    public function setTlsChainPath(?string $tlsChainPath): static
    {
        $this->tlsChainPath = $tlsChainPath;

        return $this;
    }

    public function getTlsExpireTime(): ?\DateTimeInterface
    {
        return $this->tlsExpireTime;
    }

    public function setTlsExpireTime(?\DateTimeInterface $tlsExpireTime): static
    {
        $this->tlsExpireTime = $tlsExpireTime;

        return $this;
    }

    public function setCreateTime(?\DateTimeInterface $createdAt): void
    {
        $this->createTime = $createdAt;
    }

    public function getCreateTime(): ?\DateTimeInterface
    {
        return $this->createTime;
    }

    public function setUpdateTime(?\DateTimeInterface $updateTime): void
    {
        $this->updateTime = $updateTime;
    }

    public function getUpdateTime(): ?\DateTimeInterface
    {
        return $this->updateTime;
    }
}
