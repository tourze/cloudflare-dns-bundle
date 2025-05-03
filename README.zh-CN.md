# Cloudflare DNS Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![最新版本](https://img.shields.io/packagist/v/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)
[![构建状态](https://img.shields.io/travis/tourze/cloudflare-dns-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/cloudflare-dns-bundle)
[![质量评分](https://img.shields.io/scrutinizer/g/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/cloudflare-dns-bundle)
[![下载次数](https://img.shields.io/packagist/dt/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)

Cloudflare DNS Bundle 是一个用于管理 Cloudflare DNS、域名、分析和 IAM 密钥的 Symfony Bundle，支持完整 ORM、命令行工具和高级后台特性。

## 功能特性

- 通过 ORM 实体管理 Cloudflare DNS 记录和域名
- 支持 Cloudflare 与本地数据库的 DNS/域名/分析数据同步
- 完整的 CRUD 和后台管理（EasyAdmin 属性）
- 提供批量同步、分析、导入导出等命令行工具
- 支持多账号 IAM 密钥管理
- 可扩展的服务层，便于自定义业务逻辑
- 覆盖全面的单元测试

## 安装说明

```bash
composer require tourze/cloudflare-dns-bundle
```

### 系统要求

- PHP >= 8.1
- Symfony >= 6.4
- Doctrine ORM >= 2.20
- Cloudflare 账号及 API 凭证

## 快速开始

1. 在 Symfony 配置中注册 Bundle（如未自动发现）：

```php
// config/bundles.php
return [
    CloudflareDnsBundle\CloudflareDnsBundle::class => ['all' => true],
];
```

2. 配置数据库并为实体执行迁移。

3. 通过后台 UI 或直接在数据库中添加 IAM 密钥和域名。

4. 使用命令行工具同步和管理 DNS：

```bash
php bin/console cloudflare:sync-domain-info
php bin/console cloudflare:sync-domain-record-to-local [domainId]
php bin/console cloudflare:sync-dns-domain-record <dnsRecordId>
php bin/console cloudflare:sync-dns-analytics [--since -24h] [--until now] [--time-delta 1h]
```

5. 在业务代码中调用服务层：

```php
use CloudflareDnsBundle\Service\DnsRecordService;

// 注入 DnsRecordService 并调用其方法
$service->listRecords($domain, ['type' => 'A']);
```

## 详细文档

- **实体设计：** DnsDomain、DnsRecord、IamKey、DnsAnalytics（详见 `docs/Entity.zh-CN.md`）
- **命令行工具：** 详见 `src/Command/` 目录
- **服务层：** 详见 `src/Service/` 目录
- **枚举类型：** DnsRecordType（A、MX、TXT、CNAME、NS、URI）
- **测试用例：** `tests/Service/` 目录

## 高级特性

- 支持 DNS 记录批量导入导出（BIND 格式）
- 分析数据同步与报表
- 完整的审计与追踪（创建/更新人、时间戳）
- 后台管理集成（EasyAdmin 属性）
- 可扩展服务与事件订阅

## 贡献指南

欢迎提交 Issue 或 PR。请遵循 PSR-12 及项目代码风格。运行测试：

```bash
phpunit
```

## 协议

MIT 协议，详见 [LICENSE](../LICENSE)。

## 更新日志

详见 [CHANGELOG.md](CHANGELOG.md) 获取版本记录与升级指南。
