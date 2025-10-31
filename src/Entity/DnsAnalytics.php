<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Entity;

use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: DnsAnalyticsRepository::class)]
#[ORM\Table(name: 'ims_cloudflare_dns_analytics', options: ['comment' => 'DNS分析数据'])]
class DnsAnalytics implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private int $id = 0;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '域名不能为空')]
    private ?DnsDomain $domain = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: false, options: ['comment' => '查询名称'])]
    #[Assert\NotBlank(message: '查询名称不能为空')]
    #[Assert\Length(max: 64, maxMessage: '查询名称长度不能超过 {{ limit }} 个字符')]
    private ?string $queryName = null;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: false, options: ['comment' => '查询类型'])]
    #[Assert\NotBlank(message: '查询类型不能为空')]
    #[Assert\Length(max: 32, maxMessage: '查询类型长度不能超过 {{ limit }} 个字符')]
    private ?string $queryType = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['comment' => '查询次数'])]
    #[Assert\NotNull(message: '查询次数不能为空')]
    #[Assert\PositiveOrZero(message: '查询次数不能为负数')]
    private ?int $queryCount = 0;

    #[ORM\Column(type: Types::FLOAT, nullable: false, options: ['comment' => '平均响应时间(ms)'])]
    #[Assert\NotNull(message: '平均响应时间不能为空')]
    #[Assert\PositiveOrZero(message: '平均响应时间不能为负数')]
    private ?float $responseTimeAvg = 0.0;

    #[IndexColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['comment' => '统计时间'])]
    #[Assert\NotNull(message: '统计时间不能为空')]
    private ?\DateTimeInterface $statTime = null;

    public function getId(): int
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

    public function getQueryName(): ?string
    {
        return $this->queryName;
    }

    public function setQueryName(?string $queryName): void
    {
        $this->queryName = $queryName;
    }

    public function getQueryType(): ?string
    {
        return $this->queryType;
    }

    public function setQueryType(?string $queryType): void
    {
        $this->queryType = $queryType;
    }

    public function getQueryCount(): ?int
    {
        return $this->queryCount;
    }

    public function setQueryCount(?int $queryCount): void
    {
        $this->queryCount = $queryCount;
    }

    public function getResponseTimeAvg(): ?float
    {
        return $this->responseTimeAvg;
    }

    public function setResponseTimeAvg(?float $responseTimeAvg): void
    {
        $this->responseTimeAvg = $responseTimeAvg;
    }

    public function getStatTime(): ?\DateTimeInterface
    {
        return $this->statTime;
    }

    public function setStatTime(?\DateTimeInterface $statTime): void
    {
        $this->statTime = $statTime;
    }

    public function __toString(): string
    {
        return $this->getId() > 0 ? "{$this->getQueryName()} ({$this->getQueryType()})" : '';
    }
}
