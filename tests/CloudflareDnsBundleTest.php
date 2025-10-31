<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests;

use CloudflareDnsBundle\CloudflareDnsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(CloudflareDnsBundle::class)]
#[RunTestsInSeparateProcesses]
final class CloudflareDnsBundleTest extends AbstractBundleTestCase
{
}
