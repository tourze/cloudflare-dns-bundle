<?php

namespace CloudflareDnsBundle\Tests\Integration\Controller\Admin;

use CloudflareDnsBundle\Controller\Admin\IamKeyCrudController;
use CloudflareDnsBundle\Entity\IamKey;
use CloudflareDnsBundle\Service\DomainBatchSynchronizer;
use CloudflareDnsBundle\Service\DomainSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IamKeyCrudControllerTest extends TestCase
{
    private IamKeyCrudController $controller;
    private DomainBatchSynchronizer&MockObject $batchSynchronizer;
    private DomainSynchronizer&MockObject $domainSynchronizer;
    private EntityManagerInterface&MockObject $entityManager;
    private AdminUrlGenerator $adminUrlGenerator;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->batchSynchronizer = $this->createMock(DomainBatchSynchronizer::class);
        $this->domainSynchronizer = $this->createMock(DomainSynchronizer::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        // Use reflection to create AdminUrlGenerator without constructor
        $reflectionClass = new \ReflectionClass(AdminUrlGenerator::class);
        $this->adminUrlGenerator = $reflectionClass->newInstanceWithoutConstructor();
        
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->controller = new IamKeyCrudController(
            $this->batchSynchronizer,
            $this->domainSynchronizer,
            $this->entityManager,
            $this->adminUrlGenerator,
            $this->logger
        );
    }

    public function test_extends_abstract_crud_controller(): void
    {
        $this->assertInstanceOf(AbstractCrudController::class, $this->controller);
    }

    public function test_getEntityFqcn_returns_correct_entity_class(): void
    {
        $this->assertEquals(IamKey::class, $this->controller::getEntityFqcn());
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
        
        $this->assertTrue($reflection->hasProperty('batchSynchronizer'));
        $this->assertTrue($reflection->hasProperty('domainSynchronizer'));
        $this->assertTrue($reflection->hasProperty('entityManager'));
        $this->assertTrue($reflection->hasProperty('adminUrlGenerator'));
        $this->assertTrue($reflection->hasProperty('logger'));
    }
}