<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Integration;

use Doctrine\Persistence\{ManagerRegistry, ObjectManager};
use PHPUnit\Framework\TestCase;
use stdClass;
use UniMethod\SymfonyLib\Exception\EntityManagerNotFoundException;
use UniMethod\SymfonyLib\Integration\DoctrineLocator;

final class DoctrineLocatorTest extends TestCase
{
    public function testFindsThroughEntityManagerOfClass(): void
    {
        $entity = new stdClass();
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects($this->once())->method("find")->with(stdClass::class, "7")->willReturn($entity);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method("getManagerForClass")->willReturn($manager);

        self::assertSame($entity, (new DoctrineLocator($registry))->find(stdClass::class, "7"));
    }

    public function testClassWithoutEntityManagerIsConfigurationError(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method("getManagerForClass")->willReturn(null);

        $this->expectException(EntityManagerNotFoundException::class);

        (new DoctrineLocator($registry))->find(stdClass::class, "7");
    }
}
