<?php

namespace CloudflareDnsBundle\Entity;

use CloudflareDnsBundle\Repository\DnsAnalyticsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Attribute\CreateTimeColumn;
use Tourze\DoctrineTimestampBundle\Attribute\UpdateTimeColumn;
use Tourze\EasyAdmin\Attribute\Column\ExportColumn;
use Tourze\EasyAdmin\Attribute\Column\ListColumn;
use Tourze\EasyAdmin\Attribute\Filter\Filterable;
use Tourze\EasyAdmin\Attribute\Permission\AsPermission;

#[AsPermission(title: 'DNS分析数据')]
#[ORM\Entity(repositoryClass: DnsAnalyticsRepository::class)]
#[ORM\Table(name: 'ims_cloudflare_dns_analytics', options: ['comment' => 'DNS分析数据'])]
class DnsAnalytics
{
    #[ListColumn(order: -1)]
    #[ExportColumn]
    #[Groups(['restful_read', 'api_tree', 'admin_curd', 'api_list'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = 0;

    #[ListColumn(title: '所属根域名')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?DnsDomain $domain = null;

    #[Filterable]
    #[ListColumn]
    #[ORM\Column(type: Types::STRING, length: 64, options: ['comment' => '查询名称'])]
    private ?string $queryName = null;

    #[ListColumn]
    #[ORM\Column(type: Types::STRING, length: 32, options: ['comment' => '查询类型'])]
    private ?string $queryType = null;

    #[ListColumn]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '查询次数'])]
    private ?int $queryCount = 0;

    #[ListColumn]
    #[ORM\Column(type: Types::FLOAT, options: ['comment' => '平均响应时间(ms)'])]
    private ?float $responseTimeAvg = 0.0;

    #[ListColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['comment' => '统计时间'])]
    private ?\DateTimeInterface $statTime = null;

    #[Filterable]
    #[IndexColumn]
    #[ListColumn(order: 98, sorter: true)]
    #[ExportColumn]
    #[CreateTimeColumn]
    #[Groups(['restful_read', 'admin_curd', 'restful_read'])]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '创建时间'])]
    private ?\DateTimeInterface $createTime = null;

    #[UpdateTimeColumn]
    #[ListColumn(order: 99, sorter: true)]
    #[Groups(['restful_read', 'admin_curd', 'restful_read'])]
    #[Filterable]
    #[ExportColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '更新时间'])]
    private ?\DateTimeInterface $updateTime = null;

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
