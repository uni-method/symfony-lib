<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Integration;

use Doctrine\Persistence\{ManagerRegistry, ObjectManager};
use PHPUnit\Framework\TestCase;
use stdClass;
use UniMethod\SymfonyLib\Exception\EntityManagerNotFoundException;
use UniMethod\SymfonyLib\Integration\DoctrineSaver;

final class DoctrineSaverTest extends TestCase
{
    public function testPersistsAndFlushesSameInstance(): void
    {
        $entity = new stdClass();
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects($this->once())->method("persist")->with($entity);
        $manager->expects($this->once())->method("flush");

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method("getManagerForClass")->willReturn($manager);

        self::assertSame($entity, (new DoctrineSaver($registry))->save($entity));
    }

    public function testClassWithoutEntityManagerIsConfigurationError(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method("getManagerForClass")->willReturn(null);

        $this->expectException(EntityManagerNotFoundException::class);

        (new DoctrineSaver($registry))->save(new stdClass());
    }
}
