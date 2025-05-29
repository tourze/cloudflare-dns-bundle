<?php

namespace CloudflareDnsBundle\Entity;

use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
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
    private ?int $id = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?DnsDomain $domain = null;

    #[ORM\Column(type: Types::STRING, length: 64, options: ['comment' => '查询名称'])]
    private ?string $queryName = null;

    #[ORM\Column(type: Types::STRING, length: 32, options: ['comment' => '查询类型'])]
    private ?string $queryType = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '查询次数'])]
    private ?int $queryCount = 0;

    #[ORM\Column(type: Types::FLOAT, options: ['comment' => '平均响应时间(ms)'])]
    private ?float $responseTimeAvg = 0.0;

    #[IndexColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['comment' => '统计时间'])]
    private ?\DateTimeInterface $statTime = null;

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

    public function getQueryName(): ?string
    {
        return $this->queryName;
    }

    public function setQueryName(?string $queryName): static
    {
        $this->queryName = $queryName;
        return $this;
    }

    public function getQueryType(): ?string
    {
        return $this->queryType;
    }

    public function setQueryType(?string $queryType): static
    {
        $this->queryType = $queryType;
        return $this;
    }

    public function getQueryCount(): ?int
    {
        return $this->queryCount;
    }

    public function setQueryCount(?int $queryCount): static
    {
        $this->queryCount = $queryCount;
        return $this;
    }

    public function getResponseTimeAvg(): ?float
    {
        return $this->responseTimeAvg;
    }

    public function setResponseTimeAvg(?float $responseTimeAvg): static
    {
        $this->responseTimeAvg = $responseTimeAvg;
        return $this;
    }

    public function getStatTime(): ?\DateTimeInterface
    {
        return $this->statTime;
    }

    public function setStatTime(?\DateTimeInterface $statTime): static
    {
        $this->statTime = $statTime;
        return $this;
    }

    public function __toString(): string
    {
        return $this->getId() ? "{$this->getQueryName()} ({$this->getQueryType()})" : '';
    }
}
