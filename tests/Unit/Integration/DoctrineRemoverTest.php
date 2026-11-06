<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Integration;

use Doctrine\Persistence\{ManagerRegistry, ObjectManager};
use PHPUnit\Framework\TestCase;
use stdClass;
use UniMethod\SymfonyLib\Exception\EntityManagerNotFoundException;
use UniMethod\SymfonyLib\Integration\DoctrineRemover;

final class DoctrineRemoverTest extends TestCase
{
    public function testRemovesAndFlushes(): void
    {
        $entity = new stdClass();
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects($this->once())->method("remove")->with($entity);
        $manager->expects($this->once())->method("flush");

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method("getManagerForClass")->willReturn($manager);

        (new DoctrineRemover($registry))->remove($entity);
    }

    public function testClassWithoutEntityManagerIsConfigurationError(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method("getManagerForClass")->willReturn(null);

        $this->expectException(EntityManagerNotFoundException::class);

        (new DoctrineRemover($registry))->remove(new stdClass());
    }
}
