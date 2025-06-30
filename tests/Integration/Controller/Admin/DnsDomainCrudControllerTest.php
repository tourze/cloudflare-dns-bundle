<?php

namespace CloudflareDnsBundle\Tests\Integration\Controller\Admin;

use CloudflareDnsBundle\Controller\Admin\DnsDomainCrudController;
use CloudflareDnsBundle\Entity\DnsDomain;
use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DnsDomainCrudControllerTest extends TestCase
{
    private DnsDomainCrudController $controller;
    private AdminUrlGenerator $adminUrlGenerator;
    private LoggerInterface&MockObject $logger;
    private MessageBusInterface&MockObject $messageBus;

    protected function setUp(): void
    {
        // Use reflection to create AdminUrlGenerator without constructor
        $reflectionClass = new \ReflectionClass(AdminUrlGenerator::class);
        $this->adminUrlGenerator = $reflectionClass->newInstanceWithoutConstructor();
        
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        
        $this->controller = new DnsDomainCrudController(
            $this->adminUrlGenerator,
            $this->logger,
            $this->messageBus
        );
    }

    public function test_extends_abstract_crud_controller(): void
    {
        $this->assertInstanceOf(AbstractCrudController::class, $this->controller);
    }

    public function test_getEntityFqcn_returns_correct_entity_class(): void
    {
        $this->assertEquals(DnsDomain::class, $this->controller::getEntityFqcn());
    }

    public function test_configureFields_returns_fields_array(): void
    {
        $fieldsArray = iterator_to_array($this->controller->configureFields('index'));
        
        $this->assertNotEmpty($fieldsArray);
    }

    public function test_configureFields_on_detail_page(): void
    {
        $fields = iterator_to_array($this->controller->configureFields('detail'));
        
        $this->assertNotEmpty($fields);
    }

    public function test_configureFields_on_index_page(): void
    {
        $fields = iterator_to_array($this->controller->configureFields('index'));
        
        $this->assertNotEmpty($fields);
    }

    public function test_controller_has_correct_dependencies(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        
        $this->assertTrue($reflection->hasProperty('adminUrlGenerator'));
        $this->assertTrue($reflection->hasProperty('logger'));
        $this->assertTrue($reflection->hasProperty('messageBus'));
    }
}