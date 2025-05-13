# Cloudflare DNS Bundle 数据填充

本目录包含用于填充测试数据的 Fixtures 类，可用于开发和测试环境。

## 可用的数据填充类

- `IamKeyFixtures`: 创建基本的 IAM 密钥记录
- `DnsDomainFixtures`: 创建域名记录（依赖于 IamKeyFixtures）
- `DnsRecordFixtures`: 创建 DNS 解析记录（依赖于 DnsDomainFixtures）
- `DnsAnalyticsFixtures`: 创建 DNS 分析数据（依赖于 DnsDomainFixtures）

## 使用方法

### 加载所有数据

在项目根目录执行以下命令加载所有测试数据：

```bash
php bin/console doctrine:fixtures:load --group=cloudflare_dns
```

> 注意：此命令会清空数据库中所有相关表的数据。如需保留现有数据，请添加 `--append` 参数。

### 加载特定数据

如果只想加载特定的数据填充类，可以使用以下命令：

```bash
php bin/console doctrine:fixtures:load --group=cloudflare_dns_iam_key
php bin/console doctrine:fixtures:load --group=cloudflare_dns_domain
php bin/console doctrine:fixtures:load --group=cloudflare_dns_record
php bin/console doctrine:fixtures:load --group=cloudflare_dns_analytics
```

## 数据说明

### IAM密钥

创建了两个IAM密钥：
- Cloudflare API (主密钥)
- Cloudflare DNS API (DNS专用密钥)

### 域名

创建了三个域名：
- example.com (使用主API密钥)
- test.com (使用DNS专用密钥)
- demo.com (使用DNS专用密钥，状态为pending)

### DNS记录

为每个域名创建了多个不同类型的DNS记录：
- A记录
- CNAME记录
- MX记录
- TXT记录

### DNS分析数据

为各个域名创建了DNS查询分析数据，包含：
- 不同记录类型的查询统计
- 多天的历史数据
- 平均响应时间

## 开发环境与生产环境

此数据填充仅用于开发和测试环境。请勿在生产环境中使用，以免覆盖实际数据。 