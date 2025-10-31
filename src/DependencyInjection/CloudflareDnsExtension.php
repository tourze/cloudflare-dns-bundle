<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class CloudflareDnsExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
