<?php

namespace CloudflareDnsBundle\Tests;

use CloudflareDnsBundle\CloudflareDnsBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class CloudflareDnsBundleTest extends TestCase
{
    private CloudflareDnsBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new CloudflareDnsBundle();
    }

    public function test_bundle_extends_symfony_bundle(): void
    {
        $this->assertInstanceOf(Bundle::class, $this->bundle);
    }

    public function test_bundle_implements_bundle_dependency_interface(): void
    {
        $this->assertInstanceOf(BundleDependencyInterface::class, $this->bundle);
    }

    public function test_get_bundle_dependencies_returns_array(): void
    {
        $dependencies = CloudflareDnsBundle::getBundleDependencies();
        
        $this->assertNotEmpty($dependencies);
    }

    public function test_get_bundle_dependencies_contains_required_bundles(): void
    {
        $dependencies = CloudflareDnsBundle::getBundleDependencies();
        
        $expectedBundles = [
            \Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle::class,
            \Tourze\DoctrineUserBundle\DoctrineUserBundle::class,
            \Tourze\DoctrineTrackBundle\DoctrineTrackBundle::class,
        ];
        
        foreach ($expectedBundles as $expectedBundle) {
            $this->assertArrayHasKey($expectedBundle, $dependencies);
            $this->assertEquals(['all' => true], $dependencies[$expectedBundle]);
        }
    }

    public function test_get_bundle_dependencies_structure(): void
    {
        $dependencies = CloudflareDnsBundle::getBundleDependencies();
        
        foreach ($dependencies as $bundleClass => $config) {
            // 验证bundle类名是字符串
            $this->assertIsString($bundleClass);
            
            // 验证配置是数组
            $this->assertIsArray($config);
            
            // 验证配置包含'all'键
            $this->assertArrayHasKey('all', $config);
            $this->assertTrue($config['all']);
        }
    }

    public function test_bundle_class_structure(): void
    {
        $reflection = new \ReflectionClass(CloudflareDnsBundle::class);
        
        // 验证类继承关系
        $this->assertTrue($reflection->isSubclassOf(Bundle::class));
        
        // 验证实现的接口
        $interfaces = $reflection->getInterfaceNames();
        $this->assertContains(BundleDependencyInterface::class, $interfaces);
        
        // 验证getBundleDependencies方法存在且为静态
        $this->assertTrue($reflection->hasMethod('getBundleDependencies'));
        $method = $reflection->getMethod('getBundleDependencies');
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
    }

    public function test_bundle_dependencies_count(): void
    {
        $dependencies = CloudflareDnsBundle::getBundleDependencies();
        
        // 验证依赖数量符合预期
        $this->assertCount(4, $dependencies);
    }

    public function test_bundle_name_convention(): void
    {
        $bundleName = $this->bundle->getName();
        
        // Bundle名称应该是CloudflareDnsBundle
        $this->assertEquals('CloudflareDnsBundle', $bundleName);
    }

    public function test_bundle_namespace(): void
    {
        $reflection = new \ReflectionClass($this->bundle);
        
        $this->assertEquals('CloudflareDnsBundle', $reflection->getNamespaceName());
        $this->assertEquals('CloudflareDnsBundle', $reflection->getShortName());
    }
} 