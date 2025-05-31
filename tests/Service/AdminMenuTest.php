<?php

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Service\AdminMenu;
use Knp\Menu\ItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;

class AdminMenuTest extends TestCase
{
    private AdminMenu $service;
    private LinkGeneratorInterface&MockObject $linkGenerator;

    protected function setUp(): void
    {
        $this->linkGenerator = $this->createMock(LinkGeneratorInterface::class);
        $this->service = new AdminMenu($this->linkGenerator);
    }

    public function test_invoke_creates_cloudflare_menu(): void
    {
        $rootMenu = $this->createMock(ItemInterface::class);
        $cloudflareMenu = $this->createMock(ItemInterface::class);

        // 第一次调用返回null（不存在），第二次调用返回子菜单对象
        $rootMenu->expects($this->exactly(2))
            ->method('getChild')
            ->with('Cloudflare DNS')
            ->willReturnOnConsecutiveCalls(null, $cloudflareMenu);

        $rootMenu->expects($this->once())
            ->method('addChild')
            ->with('Cloudflare DNS')
            ->willReturn($cloudflareMenu);

        // 设置子菜单的添加期望
        $cloudflareMenu->expects($this->exactly(4))
            ->method('addChild')
            ->willReturnCallback(function() {
                return $this->createMock(ItemInterface::class);
            });

        // 设置链接生成器期望
        $this->linkGenerator->expects($this->exactly(4))
            ->method('getCurdListPage')
            ->willReturn('/admin/list');

        $this->service->__invoke($rootMenu);
    }

    public function test_invoke_handles_existing_cloudflare_menu(): void
    {
        $rootMenu = $this->createMock(ItemInterface::class);
        $cloudflareMenu = $this->createMock(ItemInterface::class);

        // 第一次和第二次调用都返回已存在的子菜单
        $rootMenu->expects($this->exactly(2))
            ->method('getChild')
            ->with('Cloudflare DNS')
            ->willReturn($cloudflareMenu);

        $rootMenu->expects($this->never())
            ->method('addChild');

        // 设置子菜单的添加期望
        $cloudflareMenu->expects($this->exactly(4))
            ->method('addChild')
            ->willReturnCallback(function() {
                return $this->createMock(ItemInterface::class);
            });

        // 设置链接生成器期望
        $this->linkGenerator->expects($this->exactly(4))
            ->method('getCurdListPage')
            ->willReturn('/admin/list');

        $this->service->__invoke($rootMenu);
    }

    public function test_invoke_handles_link_generation_failure(): void
    {
        $rootMenu = $this->createMock(ItemInterface::class);
        $cloudflareMenu = $this->createMock(ItemInterface::class);

        // 第一次调用返回null（不存在），第二次调用返回子菜单对象
        $rootMenu->expects($this->exactly(2))
            ->method('getChild')
            ->with('Cloudflare DNS')
            ->willReturnOnConsecutiveCalls(null, $cloudflareMenu);

        $rootMenu->expects($this->once())
            ->method('addChild')
            ->with('Cloudflare DNS')
            ->willReturn($cloudflareMenu);

        // 模拟链接生成失败 - 只调用一次就抛出异常
        $this->linkGenerator->expects($this->once())
            ->method('getCurdListPage')
            ->willThrowException(new \Exception('Link generation failed'));

        // 即使链接生成失败，菜单仍应创建 - 但只创建一个就失败了
        $cloudflareMenu->expects($this->once())
            ->method('addChild')
            ->willReturnCallback(function() {
                return $this->createMock(ItemInterface::class);
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Link generation failed');

        $this->service->__invoke($rootMenu);
    }

    public function test_invoke_with_null_parent_item(): void
    {
        $parentItem = $this->createMock(ItemInterface::class);
        $dnsItem = $this->createMock(ItemInterface::class);

        $parentItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('Cloudflare DNS')
            ->willReturnOnConsecutiveCalls(null, $dnsItem);

        $parentItem->expects($this->once())
            ->method('addChild')
            ->with('Cloudflare DNS')
            ->willReturn($dnsItem);

        $this->linkGenerator->expects($this->atLeast(1))
            ->method('getCurdListPage')
            ->willReturn('/admin/test');

        // 模拟4个子菜单的创建
        $subItem = $this->createMock(ItemInterface::class);
        $subItem->method('setUri')->willReturnSelf();
        $subItem->method('setAttribute')->willReturnSelf();

        $dnsItem->method('addChild')->willReturn($subItem);

        $this->service->__invoke($parentItem);
    }

    public function test_service_is_callable(): void
    {
        $this->assertTrue(is_callable($this->service));
    }

    public function test_constructor_sets_dependencies(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('linkGenerator');
        $property->setAccessible(true);

        $this->assertSame($this->linkGenerator, $property->getValue($this->service));
    }

    public function test_invoke_menu_structure(): void
    {
        $rootMenu = $this->createMock(ItemInterface::class);
        $cloudflareMenu = $this->createMock(ItemInterface::class);

        // 第一次调用返回null（不存在），第二次调用返回子菜单对象
        $rootMenu->expects($this->exactly(2))
            ->method('getChild')
            ->with('Cloudflare DNS')
            ->willReturnOnConsecutiveCalls(null, $cloudflareMenu);

        $rootMenu->expects($this->once())
            ->method('addChild')
            ->with('Cloudflare DNS')
            ->willReturn($cloudflareMenu);

        // 创建具体的子菜单项Mock
        $iamMenuItem = $this->createMock(ItemInterface::class);
        $domainMenuItem = $this->createMock(ItemInterface::class);
        $recordMenuItem = $this->createMock(ItemInterface::class);
        $analyticsMenuItem = $this->createMock(ItemInterface::class);

        // 配置子菜单的addChild调用 - 使用willReturnMap代替withConsecutive
        $cloudflareMenu->expects($this->exactly(4))
            ->method('addChild')
            ->willReturnMap([
                ['IAM密钥', $iamMenuItem],
                ['域名管理', $domainMenuItem],
                ['DNS解析', $recordMenuItem],
                ['DNS分析', $analyticsMenuItem],
            ]);

        // 配置每个菜单项的setUri调用
        $iamMenuItem->expects($this->once())
            ->method('setUri')
            ->with('/admin/list')
            ->willReturnSelf();
        
        $iamMenuItem->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fas fa-key')
            ->willReturnSelf();

        $domainMenuItem->expects($this->once())
            ->method('setUri')
            ->with('/admin/list')
            ->willReturnSelf();
        
        $domainMenuItem->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fas fa-globe')
            ->willReturnSelf();

        $recordMenuItem->expects($this->once())
            ->method('setUri')
            ->with('/admin/list')
            ->willReturnSelf();
        
        $recordMenuItem->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fas fa-server')
            ->willReturnSelf();

        $analyticsMenuItem->expects($this->once())
            ->method('setUri')
            ->with('/admin/list')
            ->willReturnSelf();
        
        $analyticsMenuItem->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fas fa-chart-line')
            ->willReturnSelf();

        // 设置链接生成器期望
        $this->linkGenerator->expects($this->exactly(4))
            ->method('getCurdListPage')
            ->willReturn('/admin/list');

        $this->service->__invoke($rootMenu);
    }
} 