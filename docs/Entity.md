# Entity Design: Cloudflare DNS Bundle

This document details the entity design for the Cloudflare DNS Bundle, including fields, relationships, and design rationale for each entity.

## DnsDomain

Represents a root domain managed via Cloudflare.

| Field         | Type                  | Description                |
|-------------- |----------------------|----------------------------|
| id            | int                   | Primary key                |
| valid         | bool                  | Whether the domain is valid|
| createdBy     | string|null           | Creator                    |
| updatedBy     | string|null           | Last updater               |
| createTime    | DateTime|null         | Creation time              |
| updateTime    | DateTime|null         | Last update time           |
| iamKey        | IamKey|null           | Linked IAM key             |
| name          | string                | Root domain name           |
| zoneId        | string|null           | Cloudflare Zone ID         |
| accountId     | string|null           | Cloudflare Account ID      |
| registrar     | string|null           | Registrar                  |
| status        | string|null           | Domain status              |
| expiresAt     | DateTime|null         | Expiry date                |
| lockedUntil   | DateTime|null         | Lock expiry                |
| autoRenew     | bool                  | Auto-renewal enabled       |
| records       | DnsRecord[]           | DNS records (1:N)          |
| tlsCertPath   | string|null           | TLS cert path              |
| tlsKeyPath    | string|null           | TLS key path               |
| tlsFullchainPath | string|null        | TLS fullchain path         |
| tlsChainPath  | string|null           | TLS chain path             |
| tlsExpireTime | DateTime|null         | TLS expiry                 |

**Design Notes:**

- Linked to `IamKey` for multi-account support.
- Holds all DNS records via `records` (1:N).
- Tracks certificate and registrar info for automation.

## DnsRecord

Represents a DNS record under a domain.

| Field         | Type                  | Description                |
|-------------- |----------------------|----------------------------|
| id            | int                   | Primary key                |
| createdBy     | string|null           | Creator                    |
| updatedBy     | string|null           | Last updater               |
| createTime    | DateTime|null         | Creation time              |
| updateTime    | DateTime|null         | Last update time           |
| domain        | DnsDomain             | Linked domain (N:1)        |
| type          | DnsRecordType         | Record type (A, MX, etc.)  |
| record        | string                | Subdomain/record name      |
| recordId      | string|null           | Cloudflare record ID       |
| content       | string                | Record value               |
| ttl           | int                   | TTL                        |
| proxy         | bool                  | Proxy enabled              |
| syncing       | bool                  | Internal sync flag         |

**Design Notes:**

- Linked to `DnsDomain` (N:1).
- Uses PHP 8.1+ enum for type safety.
- Tracks audit info and supports admin UI.

## IamKey

Represents a Cloudflare API credential set.

| Field         | Type                  | Description                |
|-------------- |----------------------|----------------------------|
| id            | int                   | Primary key                |
| valid         | bool                  | Whether the key is valid   |
| createdBy     | string|null           | Creator                    |
| updatedBy     | string|null           | Last updater               |
| createTime    | DateTime|null         | Creation time              |
| updateTime    | DateTime|null         | Last update time           |
| name          | string                | Key name                   |
| accessKey     | string|null           | Cloudflare email           |
| secretKey     | string|null           | Cloudflare API key/token   |
| note          | string|null           | Note/description           |
| domains       | DnsDomain[]           | Linked domains (1:N)       |

**Design Notes:**

- Supports multiple Cloudflare accounts.
- Linked to domains for credential scoping.

## DnsAnalytics

Represents DNS analytics/statistics for a domain.

| Field         | Type                  | Description                |
|-------------- |----------------------|----------------------------|
| id            | int                   | Primary key                |
| domain        | DnsDomain             | Linked domain (N:1)        |
| queryName     | string                | Queried name               |
| queryType     | string                | Query type (A, MX, etc.)   |
| queryCount    | int                   | Query count                |
| responseTimeAvg | float               | Avg response time (ms)     |
| statTime      | DateTime              | Statistic time             |
| createTime    | DateTime|null         | Creation time              |
| updateTime    | DateTime|null         | Last update time           |

**Design Notes:**

- Linked to `DnsDomain` (N:1).
- Used for analytics reporting and trend analysis.

## Relationships

- `IamKey` 1:N `DnsDomain`
- `DnsDomain` 1:N `DnsRecord`
- `DnsDomain` 1:N `DnsAnalytics`

## Design Principles

- All entities use strict type hints and PHP 8+ attributes.
- Full audit fields (created/updated by, timestamps).
- Designed for extensibility and admin UI integration.
- Enum types for safety and clarity.
