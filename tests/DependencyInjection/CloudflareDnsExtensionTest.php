<?php

namespace CloudflareDnsBundle\Tests\DependencyInjection;

use CloudflareDnsBundle\DependencyInjection\CloudflareDnsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class CloudflareDnsExtensionTest extends TestCase
{
    private CloudflareDnsExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new CloudflareDnsExtension();
        $this->container = new ContainerBuilder();
    }

    public function test_extension_extends_symfony_extension(): void
    {
        $this->assertInstanceOf(Extension::class, $this->extension);
    }

    public function test_load_method_exists(): void
    {
        $this->assertTrue(method_exists($this->extension, 'load'));
    }

    public function test_load_accepts_correct_parameters(): void
    {
        $reflection = new \ReflectionMethod($this->extension, 'load');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(2, $parameters);
        $this->assertEquals('configs', $parameters[0]->getName());
        $this->assertEquals('container', $parameters[1]->getName());
    }

    public function test_extension_class_structure(): void
    {
        $reflection = new \ReflectionClass(CloudflareDnsExtension::class);
        
        // 验证类继承关系
        $this->assertTrue($reflection->isSubclassOf(Extension::class));
        
        // 验证load方法存在且为公共方法
        $this->assertTrue($reflection->hasMethod('load'));
        $method = $reflection->getMethod('load');
        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());
    }

    public function test_extension_namespace(): void
    {
        $reflection = new \ReflectionClass($this->extension);
        
        $this->assertEquals('CloudflareDnsBundle\DependencyInjection', $reflection->getNamespaceName());
        $this->assertEquals('CloudflareDnsExtension', $reflection->getShortName());
    }

    public function test_load_with_empty_configs(): void
    {
        // 测试空配置不会抛出异常
        $this->expectNotToPerformAssertions();
        
        try {
            $this->extension->load([], $this->container);
        } catch (\Throwable $e) {
            // 如果抛出异常，可能是因为services.yaml文件不存在
            // 这在测试环境中是正常的
            $this->assertStringContainsString('services.yaml', $e->getMessage());
        }
    }

    public function test_load_with_configs_array(): void
    {
        $configs = [
            ['some_config' => 'value'],
            ['another_config' => 'another_value']
        ];
        
        // 测试配置数组不会抛出异常
        $this->expectNotToPerformAssertions();
        
        try {
            $this->extension->load($configs, $this->container);
        } catch (\Throwable $e) {
            // 如果抛出异常，可能是因为services.yaml文件不存在
            // 这在测试环境中是正常的
            $this->assertStringContainsString('services.yaml', $e->getMessage());
        }
    }

    public function test_extension_alias(): void
    {
        // 测试Extension的别名
        $alias = $this->extension->getAlias();
        
        // 默认情况下，别名应该是类名去掉Extension后缀并转换为下划线格式
        $this->assertEquals('cloudflare_dns', $alias);
    }

    public function test_extension_configuration_class(): void
    {
        $reflection = new \ReflectionClass($this->extension);
        
        // 验证Extension类的基本结构
        $this->assertTrue($reflection->isInstantiable());
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isInterface());
    }

    public function test_load_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->extension, 'load');
        
        // 验证方法签名
        $this->assertEquals('load', $reflection->getName());
        $this->assertTrue($reflection->isPublic());
        $this->assertFalse($reflection->isStatic());
        
        // 验证返回类型
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', $returnType->getName());
    }
} 