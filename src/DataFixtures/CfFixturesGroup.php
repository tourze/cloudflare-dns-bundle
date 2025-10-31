<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\DataFixtures;

/**
 * Cloudflare DNS Bundle数据填充分组
 */
final class CfFixturesGroup
{
    /**
     * 所有Cloudflare DNS相关的数据
     */
    public const CLOUDFLARE_DNS = 'cloudflare_dns';

    /**
     * IAM密钥数据
     */
    public const IAM_KEY = 'cloudflare_dns_iam_key';

    /**
     * 域名数据
     */
    public const DOMAIN = 'cloudflare_dns_domain';

    /**
     * DNS记录数据
     */
    public const RECORD = 'cloudflare_dns_record';

    /**
     * DNS分析数据
     */
    public const ANALYTICS = 'cloudflare_dns_analytics';
}
