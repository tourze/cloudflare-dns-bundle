<?php

namespace CloudflareDnsBundle\Tests\Integration\Controller\Admin;

use CloudflareDnsBundle\Controller\Admin\DnsAnalyticsCrudController;
use CloudflareDnsBundle\Entity\DnsAnalytics;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\TestCase;

class DnsAnalyticsCrudControllerTest extends TestCase
{
    private DnsAnalyticsCrudController $controller;

    protected function setUp(): void
    {
        $this->controller = new DnsAnalyticsCrudController();
    }

    public function test_extends_abstract_crud_controller(): void
    {
        $this->assertInstanceOf(AbstractCrudController::class, $this->controller);
    }

    public function test_getEntityFqcn_returns_correct_entity_class(): void
    {
        $this->assertEquals(DnsAnalytics::class, $this->controller::getEntityFqcn());
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
}