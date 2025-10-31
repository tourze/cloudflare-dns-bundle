<?php

declare(strict_types=1);

namespace CloudflareDnsBundle\Tests\Service;

use CloudflareDnsBundle\Service\AdminMenu;
use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    protected function onSetUp(): void
    {
        // Tests don't require special setup
    }

    public function testServiceIsCallable(): void
    {
        $service = self::getService(AdminMenu::class);
        // Verify the service implements __invoke method
        $reflection = new \ReflectionClass($service);
        $this->assertTrue($reflection->hasMethod('__invoke'));
        $this->assertTrue($reflection->getMethod('__invoke')->isPublic());
    }

    public function testInvokeCreatesCloudflareMenu(): void
    {
        $service = self::getService(AdminMenu::class);
        $rootMenu = $this->createMock(ItemInterface::class);
        $cloudflareMenu = $this->createMock(ItemInterface::class);

        // 第一次调用返回null（不存在），第二次调用返回子菜单对象
        $rootMenu->expects($this->exactly(2))
            ->method('getChild')
            ->with('Cloudflare DNS')
            ->willReturnOnConsecutiveCalls(null, $cloudflareMenu)
        ;

        $rootMenu->expects($this->once())
            ->method('addChild')
            ->with('Cloudflare DNS')
            ->willReturn($cloudflareMenu)
        ;

        // 设置子菜单的添加期望
        $cloudflareMenu->expects($this->exactly(4))
            ->method('addChild')
            ->willReturnCallback(function () {
                return $this->createMock(ItemInterface::class);
            })
        ;

        $service->__invoke($rootMenu);
    }

    public function testInvokeHandlesExistingCloudflareMenu(): void
    {
        $service = self::getService(AdminMenu::class);
        $rootMenu = $this->createMock(ItemInterface::class);
        $cloudflareMenu = $this->createMock(ItemInterface::class);

        // 第一次和第二次调用都返回已存在的子菜单
        $rootMenu->expects($this->exactly(2))
            ->method('getChild')
            ->with('Cloudflare DNS')
            ->willReturn($cloudflareMenu)
        ;

        $rootMenu->expects($this->never())
            ->method('addChild')
        ;

        // 设置子菜单的添加期望
        $cloudflareMenu->expects($this->exactly(4))
            ->method('addChild')
            ->willReturnCallback(function () {
                return $this->createMock(ItemInterface::class);
            })
        ;

        $service->__invoke($rootMenu);
    }
}
