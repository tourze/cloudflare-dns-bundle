<?php

namespace CloudflareDnsBundle\Tests\Repository;

use CloudflareDnsBundle\Repository\IamKeyRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class IamKeyRepositoryTest extends TestCase
{
    public function test_constructor_creates_repository_instance(): void
    {        $registry = $this->createMock(ManagerRegistry::class);
        $repository = new IamKeyRepository($registry);
        
        $this->assertInstanceOf(IamKeyRepository::class, $repository);
    }

    public function test_repository_extends_service_entity_repository(): void
    {        $registry = $this->createMock(ManagerRegistry::class);
        $repository = new IamKeyRepository($registry);
        
        $this->assertInstanceOf(
            \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository::class,
            $repository
        );
    }

    public function test_repository_has_standard_doctrine_methods(): void
    {        $registry = $this->createMock(ManagerRegistry::class);
        $repository = new IamKeyRepository($registry);
        
        $expectedMethods = ['find', 'findAll', 'findBy', 'findOneBy'];
        
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                method_exists($repository, $method),
                "Repository should have method: {$method}"
            );
        }
    }

    public function test_repository_class_structure(): void
    {
        $reflection = new \ReflectionClass(IamKeyRepository::class);
        
        // 验证类继承关系
        $this->assertTrue($reflection->isSubclassOf(
            \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository::class
        ));
        
        // 验证构造函数存在
        $this->assertTrue($reflection->hasMethod('__construct'));
        
        $constructor = $reflection->getMethod('__construct');
        $parameters = $constructor->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertEquals('registry', $parameters[0]->getName());
    }
} 