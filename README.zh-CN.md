# Cloudflare DNS Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)
[![License](https://img.shields.io/packagist/l/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)

一个全面的 Symfony Bundle，用于管理 Cloudflare DNS 记录、域名和分析数据，支持完整的 ORM、CLI 工具和 EasyAdmin 集成。

## 目录

- [功能特性](#功能特性)
- [系统要求](#系统要求)
- [安装说明](#安装说明)
- [配置说明](#配置说明)
- [快速开始](#快速开始)
  - [1. 添加 IAM 密钥](#1-添加-iam-密钥)
  - [2. 同步域名](#2-同步域名)
  - [3. 管理 DNS 记录](#3-管理-dns-记录)
  - [4. 分析数据](#4-分析数据)
- [服务使用](#服务使用)
- [实体说明](#实体说明)
- [CLI 命令](#cli-命令)
- [管理界面](#管理界面)
- [事件系统](#事件系统)
- [高级功能](#高级功能)
- [测试](#测试)
- [贡献指南](#贡献指南)
- [许可证](#许可证)

## 功能特性

- **完整的 DNS 管理**：通过 Cloudflare API 创建、更新、删除和同步 DNS 记录
- **域名同步**：在 Cloudflare 和本地数据库之间同步域名
- **分析集成**：获取并存储 DNS 查询分析数据
- **多账户支持**：通过 IAM 密钥管理多个 Cloudflare 账户
- **EasyAdmin 集成**：完整的 CRUD 操作和管理界面
- **批量操作**：支持批量 DNS 记录操作
- **CLI 工具**：为所有操作提供全面的命令行工具
- **事件系统**：同步前/后事件，支持自定义业务逻辑
- **审计跟踪**：自动跟踪创建/更新时间戳

## 安装说明

### 系统要求

- PHP >= 8.1
- Symfony >= 7.3
- Doctrine ORM >= 3.0
- Cloudflare 账号及 API 凭证

### 通过 Composer 安装

```bash
composer require tourze/cloudflare-dns-bundle
```

## 配置说明

### Bundle 注册

如果使用 Symfony Flex，Bundle 及其依赖将自动注册。否则，请添加到 `config/bundles.php`：

```php
// config/bundles.php
return [
    // 依赖（通过 BundleDependencyInterface 自动注册）
    Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle::class => ['all' => true],
    Tourze\DoctrineUserBundle\DoctrineUserBundle::class => ['all' => true],
    Tourze\DoctrineTrackBundle\DoctrineTrackBundle::class => ['all' => true],
    Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle::class => ['all' => true],
    // 主 Bundle
    CloudflareDnsBundle\CloudflareDnsBundle::class => ['all' => true],
];
```

### 数据库设置

1. 更新数据库结构：
```bash
php bin/console doctrine:schema:update --force
```

2. 加载初始数据（可选）：
```bash
php bin/console doctrine:fixtures:load --group=cf
```

## 快速开始

### 1. 添加 IAM 密钥

首先，将您的 Cloudflare API 凭证添加到数据库：

```php
use CloudflareDnsBundle\Entity\IamKey;

$iamKey = new IamKey();
$iamKey->setName('主账户');
$iamKey->setEmail('your@email.com');
$iamKey->setAccessKey('your-api-key');
$iamKey->setAccountId('your-account-id');

$entityManager->persist($iamKey);
$entityManager->flush();
```

### 2. 同步域名

```bash
# 从 Cloudflare 同步所有域名
php bin/console cloudflare:sync-domains <iamKeyId>

# 同步特定域名
php bin/console cloudflare:sync-domains <iamKeyId> --domain=example.com
```

### 3. 管理 DNS 记录

```bash
# 从 Cloudflare 同步 DNS 记录到本地数据库
php bin/console cloudflare:sync-domain-record-to-local <domainId>

# 从本地数据库同步 DNS 记录到 Cloudflare
php bin/console cloudflare:sync-dns-domain-record-to-remote <dnsRecordId>

# 同步域名的所有 DNS 记录
php bin/console cloudflare:sync-dns-domain-record-to-remote --all --domain=<domainId>
```

### 4. 分析数据

```bash
# 同步最近 24 小时的 DNS 分析数据
php bin/console cloudflare:sync-dns-analytics

# 同步特定时间范围的分析数据
php bin/console cloudflare:sync-dns-analytics --since="2024-01-01" --until="2024-01-31"
```

## 服务使用

### DNS 记录服务

```php
use CloudflareDnsBundle\Service\DnsRecordService;
use CloudflareDnsBundle\Entity\DnsRecord;
use CloudflareDnsBundle\Enum\DnsRecordType;

class MyService
{
    public function __construct(
        private DnsRecordService $dnsRecordService
    ) {}

    public function createRecord(): void
    {
        $record = new DnsRecord();
        $record->setDomain($domain);
        $record->setType(DnsRecordType::A);
        $record->setRecord('subdomain.example.com');
        $record->setContent('192.168.1.1');
        $record->setTtl(3600);
        $record->setProxy(true);

        $this->dnsRecordService->syncToRemote($record);
    }
}
```

### 域名服务

```php
use CloudflareDnsBundle\Service\DnsDomainService;

$domains = $dnsDomainService->syncFromRemote($iamKey);
foreach ($domains as $domain) {
    echo $domain->getName() . ' - ' . $domain->getStatus()->value . PHP_EOL;
}
```

## 实体说明

### DnsDomain
表示在 Cloudflare 中管理的域名：
- `name`：域名（例如：example.com）
- `zoneId`：Cloudflare Zone ID
- `status`：域名状态（active、pending 等）
- `iamKey`：关联的 IAM 密钥

### DnsRecord
表示 DNS 记录：
- `domain`：关联的域名
- `type`：记录类型（A、AAAA、CNAME、MX、TXT、NS、URI）
- `record`：完整的记录名称
- `content`：记录值
- `ttl`：生存时间
- `proxy`：是否启用 Cloudflare 代理
- `recordId`：Cloudflare 记录 ID

### IamKey
存储 Cloudflare API 凭证：
- `name`：描述性名称
- `email`：Cloudflare 账户邮箱
- `accessKey`：API 密钥
- `accountId`：Cloudflare 账户 ID

### DnsAnalytics
存储 DNS 查询分析数据：
- `domain`：关联的域名
- `queryCount`：查询数量
- `queryName`：查询的主机名
- `queryType`：DNS 查询类型
- `responseCode`：DNS 响应代码
- `datetime`：分析时间戳

## CLI 命令

| 命令 | 描述 |
|---------|-------------|
| `cloudflare:sync-domains` | 从 Cloudflare 同步域名 |
| `cloudflare:sync-domain-info` | 更新域名 Zone 信息 |
| `cloudflare:sync-domain-record-to-local` | 从 Cloudflare 同步 DNS 记录到本地数据库 |
| `cloudflare:sync-dns-domain-record-to-remote` | 从本地数据库同步 DNS 记录到 Cloudflare |
| `cloudflare:sync-dns-analytics` | 同步 DNS 分析数据 |

## 管理界面

该 Bundle 为所有实体提供 EasyAdmin CRUD 控制器：

- **DnsDomainCrudController**：管理域名
- **DnsRecordCrudController**：管理 DNS 记录
- **IamKeyCrudController**：管理 API 凭证
- **DnsAnalyticsCrudController**：查看分析数据

通过您的 EasyAdmin 仪表板配置访问。

## 事件系统

该 Bundle 在同步操作期间分发事件：

```php
use CloudflareDnsBundle\EventListener\DnsRecordSyncListener;
use Doctrine\ORM\Events;

class MyListener
{
    public function postPersist($args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof DnsRecord) {
            // DNS 记录创建后的自定义逻辑
        }
    }
}
```

## 高级功能

### 批量操作

该 Bundle 通过 Cloudflare API 支持批量操作：

```php
use CloudflareDnsBundle\Client\CloudflareHttpClient;

$operations = [
    'posts' => [$newRecord1, $newRecord2],
    'puts' => [['id' => $recordId, 'record' => $updatedRecord]],
    'deletes' => [$recordIdToDelete]
];

$client->batchDnsRecords($zoneId, $operations);
```

### 消息队列集成

通过 Symfony Messenger 支持异步操作：

- `SyncDnsDomainsFromRemoteMessage`：异步域名同步
- `SyncDnsRecordToRemoteMessage`：异步 DNS 记录同步

### 服务层架构

- **BaseCloudflareService**：所有服务的抽象基类
- **DomainSynchronizer**：处理单个域名同步
- **DomainBatchSynchronizer**：处理批量域名操作
- **CloudflareHttpClient**：带有日志记录的底层 API 客户端

## 测试

该 Bundle 包含全面的测试覆盖：

```bash
# 运行所有测试
vendor/bin/phpunit

# 运行特定测试套件
vendor/bin/phpunit tests/Service/

# 运行并生成覆盖率报告
vendor/bin/phpunit --coverage-html coverage/
```

## 贡献指南

欢迎贡献！请：

1. 遵循 PSR-12 编码标准
2. 为新功能添加测试
3. 根据需要更新文档
4. 提交 Pull Request

## 许可证

MIT 许可证。详见 [LICENSE](LICENSE) 文件。
