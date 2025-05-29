<?php

namespace CloudflareDnsBundle\Entity;

use CloudflareDnsBundle\Enum\DomainStatus;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;

#[ORM\Entity(repositoryClass: DnsDomainRepository::class)]
#[ORM\Table(name: 'ims_cloudflare_dns_domain', options: ['comment' => 'CF域名'])]
#[ORM\UniqueConstraint(name: 'ims_cloudflare_dns_domain_idx_uniq', columns: ['iam_key_id', 'name'])]
class DnsDomain implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'domains')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?IamKey $iamKey = null;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(length: 128, unique: true, options: ['comment' => '根域名记录'])]
    private ?string $name = null;

    #[ORM\Column(length: 64, nullable: true, options: ['comment' => 'Zone ID'])]
    private ?string $zoneId = null;

    #[ORM\Column(length: 32, nullable: true, enumType: DomainStatus::class, options: ['comment' => '状态'])]
    private ?DomainStatus $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '过期时间'])]
    private ?\DateTimeInterface $expiresTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '锁定截止时间'])]
    private ?\DateTimeInterface $lockedUntilTime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否自动续费'])]
    private bool $autoRenew = false;

    #[Ignore]
    #[ORM\OneToMany(targetEntity: DnsRecord::class, mappedBy: 'domain')]
    private Collection $records;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '有效'])]
    private ?bool $valid = false;

    #[CreatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '创建人'])]
    private ?string $createdBy = null;

    #[UpdatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '更新人'])]
    private ?string $updatedBy = null;

    public function __construct()
    {
        $this->records = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * 从关联的IamKey获取AccountId
     */
    public function getAccountId(): ?string
    {
        return $this->iamKey?->getAccountId();
    }

    public function getStatus(): ?DomainStatus
    {
        return $this->status;
    }

    public function setStatus(?DomainStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getExpiresTime(): ?\DateTimeInterface
    {
        return $this->expiresTime;
    }

    public function setExpiresTime(?\DateTimeInterface $expiresTime): static
    {
        $this->expiresTime = $expiresTime;

        return $this;
    }

    public function getLockedUntilTime(): ?\DateTimeInterface
    {
        return $this->lockedUntilTime;
    }

    public function setLockedUntilTime(?\DateTimeInterface $lockedUntilTime): static
    {
        $this->lockedUntilTime = $lockedUntilTime;

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

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(?bool $valid): self
    {
        $this->valid = $valid;

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
}
