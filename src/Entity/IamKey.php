<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Entity;

use CloudflareDnsBundle\Repository\IamKeyRepository;
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

#[ORM\Entity(repositoryClass: IamKeyRepository::class)]
#[ORM\Table(name: 'ims_cloudflare_iam_key', options: ['comment' => 'IAM密钥'])]
class IamKey implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\Column(length: 128, unique: true, options: ['comment' => '名称'])]
    #[Assert\NotBlank(message: '名称不能为空')]
    #[Assert\Length(max: 128, maxMessage: '名称长度不能超过 {{ limit }} 个字符')]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true, options: ['comment' => '邮箱'])]
    #[Assert\Length(max: 255, maxMessage: '邮箱长度不能超过 {{ limit }} 个字符')]
    #[Assert\Email(message: '邮箱格式不正确')]
    private ?string $accessKey = null;

    #[ORM\Column(length: 64, nullable: true, options: ['comment' => 'Account ID'])]
    #[Assert\Length(max: 64, maxMessage: 'Account ID长度不能超过 {{ limit }} 个字符')]
    private ?string $accountId = null;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => 'API Key'])]
    #[Assert\NotBlank(message: 'API Key不能为空')]
    #[Assert\Length(max: 65535, maxMessage: 'API Key长度不能超过 {{ limit }} 个字符')]
    private ?string $secretKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '备注'])]
    #[Assert\Length(max: 65535, maxMessage: '备注长度不能超过 {{ limit }} 个字符')]
    private ?string $note = null;

    /**
     * @var Collection<int, DnsDomain>
     */
    #[Ignore]
    #[ORM\OneToMany(targetEntity: DnsDomain::class, mappedBy: 'iamKey')]
    private Collection $domains;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '有效', 'default' => 0])]
    #[Assert\Type(type: 'bool', message: '有效性必须是布尔值')]
    private ?bool $valid = false;

    public function __construct()
    {
        $this->domains = new ArrayCollection();
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

    public function getAccessKey(): ?string
    {
        return $this->accessKey;
    }

    public function setAccessKey(?string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getAccountId(): ?string
    {
        return $this->accountId;
    }

    public function setAccountId(?string $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    public function setSecretKey(string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    /**
     * @return Collection<int, DnsDomain>
     */
    public function getDomains(): Collection
    {
        return $this->domains;
    }

    public function addDomain(DnsDomain $domain): static
    {
        if (!$this->domains->contains($domain)) {
            $this->domains->add($domain);
            $domain->setIamKey($this);
        }

        return $this;
    }

    public function removeDomain(DnsDomain $domain): static
    {
        if ($this->domains->removeElement($domain)) {
            // set the owning side to null (unless already changed)
            if ($domain->getIamKey() === $this) {
                $domain->setIamKey(null);
            }
        }

        return $this;
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
