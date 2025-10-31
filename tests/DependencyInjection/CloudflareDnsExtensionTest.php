<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\DependencyInjection;

use CloudflareDnsBundle\DependencyInjection\CloudflareDnsExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(CloudflareDnsExtension::class)]
final class CloudflareDnsExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
