<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Entity;

use CloudflareDnsBundle\Enum\DomainStatus;
use CloudflareDnsBundle\Repository\DnsDomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;

#[ORM\Entity(repositoryClass: DnsDomainRepository::class)]
#[ORM\Table(name: 'ims_cloudflare_dns_domain', options: ['comment' => 'CF域名'])]
#[ORM\UniqueConstraint(name: 'ims_cloudflare_dns_domain_idx_uniq', columns: ['iam_key_id', 'name'])]
class DnsDomain implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'domains', cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?IamKey $iamKey = null;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(length: 128, unique: true, options: ['comment' => '根域名记录'])]
    #[Assert\NotBlank(message: '域名不能为空')]
    #[Assert\Length(max: 128, maxMessage: '域名长度不能超过 {{ limit }} 个字符')]
    private ?string $name = null;

    #[ORM\Column(length: 64, nullable: true, options: ['comment' => 'Zone ID'])]
    #[Assert\Length(max: 64, maxMessage: 'Zone ID长度不能超过 {{ limit }} 个字符')]
    private ?string $zoneId = null;

    #[ORM\Column(length: 32, nullable: true, enumType: DomainStatus::class, options: ['comment' => '状态'])]
    #[Assert\Choice(callback: [DomainStatus::class, 'cases'], message: '请选择有效的域名状态')]
    private ?DomainStatus $status = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '过期时间'])]
    #[Assert\Type(type: \DateTimeInterface::class, message: '过期时间必须是有效的日期时间')]
    private ?\DateTimeInterface $expiresTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '锁定截止时间'])]
    #[Assert\Type(type: \DateTimeInterface::class, message: '锁定截止时间必须是有效的日期时间')]
    private ?\DateTimeInterface $lockedUntilTime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否自动续费'])]
    #[Assert\Type(type: 'bool', message: '自动续费必须是布尔值')]
    private bool $autoRenew = false;

    /**
     * @var Collection<int, DnsRecord>
     */
    #[Ignore]
    #[ORM\OneToMany(targetEntity: DnsRecord::class, mappedBy: 'domain')]
    private Collection $records;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '有效'])]
    #[Assert\Type(type: 'bool', message: '有效性必须是布尔值')]
    private ?bool $valid = false;

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

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getZoneId(): ?string
    {
        return $this->zoneId;
    }

    public function setZoneId(?string $zoneId): void
    {
        $this->zoneId = $zoneId;
    }

    public function getIamKey(): ?IamKey
    {
        return $this->iamKey;
    }

    public function setIamKey(?IamKey $iamKey): void
    {
        $this->iamKey = $iamKey;
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

    public function setStatus(?DomainStatus $status): void
    {
        $this->status = $status;
    }

    public function getExpiresTime(): ?\DateTimeInterface
    {
        return $this->expiresTime;
    }

    public function setExpiresTime(?\DateTimeInterface $expiresTime): void
    {
        $this->expiresTime = $expiresTime;
    }

    public function getLockedUntilTime(): ?\DateTimeInterface
    {
        return $this->lockedUntilTime;
    }

    public function setLockedUntilTime(?\DateTimeInterface $lockedUntilTime): void
    {
        $this->lockedUntilTime = $lockedUntilTime;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function setAutoRenew(bool $autoRenew): void
    {
        $this->autoRenew = $autoRenew;
    }

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(?bool $valid): void
    {
        $this->valid = $valid;
    }

    public function __toString(): string
    {
        return null !== $this->getId() ? $this->getName() ?? '' : '';
    }
}
