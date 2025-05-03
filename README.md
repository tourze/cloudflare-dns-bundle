# Cloudflare DNS Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)
[![Build Status](https://img.shields.io/travis/tourze/cloudflare-dns-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/cloudflare-dns-bundle)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/cloudflare-dns-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/cloudflare-dns-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/cloudflare-dns-bundle)

Cloudflare DNS Bundle is a Symfony bundle for managing Cloudflare DNS, domains, analytics, and IAM keys with full ORM support, CLI tools, and advanced admin features.

## Features

- Manage Cloudflare DNS records and domains via ORM entities
- Sync DNS/domain/analytics data between Cloudflare and local DB
- Full CRUD and admin UI support (EasyAdmin attributes)
- Command-line tools for batch sync, analytics, and import/export
- IAM key management for multi-account support
- Extensible service layer for custom business logic
- Comprehensive test coverage

## Installation

```bash
composer require tourze/cloudflare-dns-bundle
```

### Requirements

- PHP >= 8.1
- Symfony >= 6.4
- Doctrine ORM >= 2.20
- Cloudflare account and API credentials

## Quick Start

1. Register the bundle in your Symfony config (if not auto-discovered):

```php
// config/bundles.php
return [
    CloudflareDnsBundle\CloudflareDnsBundle::class => ['all' => true],
];
```

2. Configure your database and run migrations for the provided entities.

3. Add your IAM keys and domains via admin UI or directly in the database.

4. Use CLI commands to sync and manage DNS:

```bash
php bin/console cloudflare:sync-domain-info
php bin/console cloudflare:sync-domain-record-to-local [domainId]
php bin/console cloudflare:sync-dns-domain-record <dnsRecordId>
php bin/console cloudflare:sync-dns-analytics [--since -24h] [--until now] [--time-delta 1h]
```

5. Use the service layer in your own code:

```php
use CloudflareDnsBundle\Service\DnsRecordService;

// Inject DnsRecordService and use its methods
$service->listRecords($domain, ['type' => 'A']);
```

## Documentation

- **Entities:** DnsDomain, DnsRecord, IamKey, DnsAnalytics (see `docs/Entity.md`)
- **CLI Commands:** See `src/Command/` for all available commands
- **Service Layer:** See `src/Service/` for extensible business logic
- **Enum Types:** DnsRecordType (A, MX, TXT, CNAME, NS, URI)
- **Testing:** PHPUnit tests in `tests/Service/`

## Advanced Features

- Batch import/export DNS records (BIND format)
- Analytics sync and reporting
- Full audit and tracking (created/updated by, timestamps)
- Admin UI integration (EasyAdmin attributes)
- Extensible with your own services and event subscribers

## Contributing

Feel free to submit issues or pull requests. Please follow PSR-12 and project code style. Run tests with:

```bash
phpunit
```

## License

MIT License. See [LICENSE](../LICENSE).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release notes and upgrade guide.
