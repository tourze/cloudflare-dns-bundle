# 实体设计说明：Cloudflare DNS Bundle

本文档详细说明 Cloudflare DNS Bundle 的实体设计，包括各实体的字段、关系及设计思路。

## DnsDomain

代表一个通过 Cloudflare 托管的根域名。

| 字段             | 类型            | 说明                   |
|------------------|-----------------|------------------------|
| id               | int             | 主键                   |
| valid            | bool            | 域名是否有效           |
| createdBy        | string|null     | 创建人                 |
| updatedBy        | string|null     | 更新人                 |
| createTime       | DateTime|null   | 创建时间               |
| updateTime       | DateTime|null   | 更新时间               |
| iamKey           | IamKey|null     | 关联的 IAM 密钥        |
| name             | string          | 根域名                 |
| zoneId           | string|null     | Cloudflare Zone ID     |
| accountId        | string|null     | Cloudflare 账号 ID     |
| registrar        | string|null     | 注册商                 |
| status           | string|null     | 域名状态               |
| expiresAt        | DateTime|null   | 过期时间               |
| lockedUntil      | DateTime|null   | 锁定截止时间           |
| autoRenew        | bool            | 是否自动续费           |
| records          | DnsRecord[]     | DNS 记录（1:N）        |
| tlsCertPath      | string|null     | TLS 证书路径           |
| tlsKeyPath       | string|null     | TLS 密钥路径           |
| tlsFullchainPath | string|null     | TLS 完整链路径         |
| tlsChainPath     | string|null     | TLS 中间证书路径       |
| tlsExpireTime    | DateTime|null   | TLS 证书过期时间       |

**设计说明：**

- 通过 `IamKey` 支持多账号。
- 通过 `records` 关联所有 DNS 记录（1:N）。
- 支持证书与注册商信息自动化管理。

## DnsRecord

代表某个域名下的 DNS 记录。

| 字段         | 类型            | 说明                   |
|--------------|-----------------|------------------------|
| id           | int             | 主键                   |
| createdBy    | string|null     | 创建人                 |
| updatedBy    | string|null     | 更新人                 |
| createTime   | DateTime|null   | 创建时间               |
| updateTime   | DateTime|null   | 更新时间               |
| domain       | DnsDomain       | 关联域名（N:1）        |
| type         | DnsRecordType   | 记录类型（A、MX等）    |
| record       | string          | 子域名/记录名          |
| recordId     | string|null     | Cloudflare 记录ID      |
| content      | string          | 记录值                 |
| ttl          | int             | TTL                    |
| proxy        | bool            | 是否代理               |
| syncing      | bool            | 内部同步标记           |

**设计说明：**

- 通过 `DnsDomain` 关联域名（N:1）。
- 记录类型使用 PHP 8.1+ 枚举，类型安全。
- 支持审计字段和后台管理。

## IamKey

代表一组 Cloudflare API 凭证。

| 字段         | 类型            | 说明                   |
|--------------|-----------------|------------------------|
| id           | int             | 主键                   |
| valid        | bool            | 密钥是否有效           |
| createdBy    | string|null     | 创建人                 |
| updatedBy    | string|null     | 更新人                 |
| createTime   | DateTime|null   | 创建时间               |
| updateTime   | DateTime|null   | 更新时间               |
| name         | string          | 密钥名称               |
| accessKey    | string|null     | Cloudflare 邮箱        |
| secretKey    | string|null     | Cloudflare API 密钥    |
| note         | string|null     | 备注                   |
| domains      | DnsDomain[]     | 关联域名（1:N）        |

**设计说明：**

- 支持多 Cloudflare 账号。
- 通过 domains 关联域名。

## DnsAnalytics

代表某个域名的 DNS 分析/统计数据。

| 字段             | 类型            | 说明                   |
|------------------|-----------------|------------------------|
| id               | int             | 主键                   |
| domain           | DnsDomain       | 关联域名（N:1）        |
| queryName        | string          | 查询名称               |
| queryType        | string          | 查询类型（A、MX等）    |
| queryCount       | int             | 查询次数               |
| responseTimeAvg  | float           | 平均响应时间（ms）     |
| statTime         | DateTime        | 统计时间               |
| createTime       | DateTime|null   | 创建时间               |
| updateTime       | DateTime|null   | 更新时间               |

**设计说明：**

- 通过 `DnsDomain` 关联域名（N:1）。
- 用于分析报表和趋势分析。

## 关系结构

- `IamKey` 1:N `DnsDomain`
- `DnsDomain` 1:N `DnsRecord`
- `DnsDomain` 1:N `DnsAnalytics`

## 设计原则

- 所有实体均使用严格类型和 PHP 8+ 属性。
- 完整的审计字段（创建/更新人、时间戳）。
- 便于扩展和后台集成。
- 枚举类型保证类型安全和可读性。
