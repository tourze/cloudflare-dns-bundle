# Cloudflare DNS Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)
[![License](https://img.shields.io/packagist/l/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)

A comprehensive Symfony bundle for managing Cloudflare DNS records, domains, and analytics with full ORM support, CLI tools, and EasyAdmin integration.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
  - [1. Add IAM Key](#1-add-iam-key)
  - [2. Sync Domains](#2-sync-domains)
  - [3. Manage DNS Records](#3-manage-dns-records)
  - [4. Analytics](#4-analytics)
- [Service Usage](#service-usage)
- [Entities](#entities)
- [CLI Commands](#cli-commands)
- [Admin Interface](#admin-interface)
- [Event System](#event-system)
- [Advanced Features](#advanced-features)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Complete DNS Management**: Create, update, delete, and sync DNS records with Cloudflare API
- **Domain Synchronization**: Sync domains between Cloudflare and local database
- **Analytics Integration**: Fetch and store DNS query analytics data
- **Multi-Account Support**: Manage multiple Cloudflare accounts with IAM key management
- **EasyAdmin Integration**: Full CRUD operations with admin UI
- **Batch Operations**: Support for bulk DNS record operations
- **CLI Tools**: Comprehensive command-line tools for all operations
- **Event System**: Pre/post sync events for custom business logic
- **Audit Trail**: Automatic tracking of created/updated timestamps

## Installation

### Requirements

- PHP >= 8.1
- Symfony >= 7.3
- Doctrine ORM >= 3.0
- Cloudflare account with API credentials

### Install via Composer

```bash
composer require tourze/cloudflare-dns-bundle
```

## Configuration

### Bundle Registration

The bundle and its dependencies will be automatically registered if using Symfony Flex. Otherwise, add to your `config/bundles.php`:

```php
// config/bundles.php
return [
    // Dependencies (auto-registered via BundleDependencyInterface)
    Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle::class => ['all' => true],
    Tourze\DoctrineUserBundle\DoctrineUserBundle::class => ['all' => true],
    Tourze\DoctrineTrackBundle\DoctrineTrackBundle::class => ['all' => true],
    Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle::class => ['all' => true],
    // Main bundle
    CloudflareDnsBundle\CloudflareDnsBundle::class => ['all' => true],
];
```

### Database Setup

1. Update your database schema:
```bash
php bin/console doctrine:schema:update --force
```

2. Load initial data fixtures (optional):
```bash
php bin/console doctrine:fixtures:load --group=cf
```

## Quick Start

### 1. Add IAM Key

First, add your Cloudflare API credentials to the database:

```php
use CloudflareDnsBundle\Entity\IamKey;

$iamKey = new IamKey();
$iamKey->setName('Main Account');
$iamKey->setEmail('your@email.com');
$iamKey->setAccessKey('your-api-key');
$iamKey->setAccountId('your-account-id');

$entityManager->persist($iamKey);
$entityManager->flush();
```

### 2. Sync Domains

```bash
# Sync all domains from Cloudflare
php bin/console cloudflare:sync-domains <iamKeyId>

# Sync specific domain
php bin/console cloudflare:sync-domains <iamKeyId> --domain=example.com
```

### 3. Manage DNS Records

```bash
# Sync DNS records from Cloudflare to local DB
php bin/console cloudflare:sync-domain-record-to-local <domainId>

# Sync DNS records from local DB to Cloudflare
php bin/console cloudflare:sync-dns-domain-record-to-remote <dnsRecordId>

# Sync all DNS records for a domain
php bin/console cloudflare:sync-dns-domain-record-to-remote --all --domain=<domainId>
```

### 4. Analytics

```bash
# Sync DNS analytics for last 24 hours
php bin/console cloudflare:sync-dns-analytics

# Sync analytics for specific time range
php bin/console cloudflare:sync-dns-analytics --since="2024-01-01" --until="2024-01-31"
```

## Service Usage

### DNS Record Service

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

### Domain Service

```php
use CloudflareDnsBundle\Service\DnsDomainService;

$domains = $dnsDomainService->syncFromRemote($iamKey);
foreach ($domains as $domain) {
    echo $domain->getName() . ' - ' . $domain->getStatus()->value . PHP_EOL;
}
```

## Entities

### DnsDomain
Represents a domain managed in Cloudflare:
- `name`: Domain name (e.g., example.com)
- `zoneId`: Cloudflare Zone ID
- `status`: Domain status (active, pending, etc.)
- `iamKey`: Associated IAM key

### DnsRecord
Represents a DNS record:
- `domain`: Associated domain
- `type`: Record type (A, AAAA, CNAME, MX, TXT, NS, URI)
- `record`: Full record name
- `content`: Record value
- `ttl`: Time to live
- `proxy`: Whether Cloudflare proxy is enabled
- `recordId`: Cloudflare record ID

### IamKey
Stores Cloudflare API credentials:
- `name`: Descriptive name
- `email`: Cloudflare account email
- `accessKey`: API key
- `accountId`: Cloudflare account ID

### DnsAnalytics
Stores DNS query analytics:
- `domain`: Associated domain
- `queryCount`: Number of queries
- `queryName`: Queried hostname
- `queryType`: DNS query type
- `responseCode`: DNS response code
- `datetime`: Analytics timestamp

## CLI Commands

| Command | Description |
|---------|-------------|
| `cloudflare:sync-domains` | Sync domains from Cloudflare |
| `cloudflare:sync-domain-info` | Update domain zone information |
| `cloudflare:sync-domain-record-to-local` | Sync DNS records from Cloudflare to local DB |
| `cloudflare:sync-dns-domain-record-to-remote` | Sync DNS records from local DB to Cloudflare |
| `cloudflare:sync-dns-analytics` | Sync DNS analytics data |

## Admin Interface

The bundle provides EasyAdmin CRUD controllers for all entities:

- **DnsDomainCrudController**: Manage domains
- **DnsRecordCrudController**: Manage DNS records  
- **IamKeyCrudController**: Manage API credentials
- **DnsAnalyticsCrudController**: View analytics data

Access via your EasyAdmin dashboard configuration.

## Event System

The bundle dispatches events during sync operations:

```php
use CloudflareDnsBundle\EventListener\DnsRecordSyncListener;
use Doctrine\ORM\Events;

class MyListener
{
    public function postPersist($args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof DnsRecord) {
            // Custom logic after DNS record creation
        }
    }
}
```

## Advanced Features

### Batch Operations

The bundle supports batch operations via the Cloudflare API:

```php
use CloudflareDnsBundle\Service\CloudflareHttpClient;

$operations = [
    'posts' => [$newRecord1, $newRecord2],
    'puts' => [['id' => $recordId, 'record' => $updatedRecord]],
    'deletes' => [$recordIdToDelete]
];

$client->batchDnsRecords($zoneId, $operations);
```

### Message Queue Integration

Async operations are supported via Symfony Messenger:

- `SyncDnsDomainsFromRemoteMessage`: Async domain sync
- `SyncDnsRecordToRemoteMessage`: Async DNS record sync

### Service Layer Architecture

- **BaseCloudflareService**: Abstract base for all services
- **DomainSynchronizer**: Handles single domain sync
- **DomainBatchSynchronizer**: Handles bulk domain operations
- **CloudflareHttpClient**: Low-level API client with logging

## Testing

The bundle includes comprehensive test coverage:

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit tests/Service/

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

## Contributing

Contributions are welcome! Please:

1. Follow PSR-12 coding standards
2. Add tests for new features
3. Update documentation as needed
4. Submit a pull request

## License

MIT License. See [LICENSE](LICENSE) for more information.
