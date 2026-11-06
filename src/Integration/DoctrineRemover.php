<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Integration;

use Doctrine\Persistence\{ManagerRegistry, ObjectManager};
use UniMethod\Frame\Persistence\EntityRemoverInterface;
use UniMethod\SymfonyLib\Exception\EntityManagerNotFoundException;

final readonly class DoctrineRemover implements EntityRemoverInterface
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function remove(object $objectToRemove): void
    {
        $className = get_class($objectToRemove);
        $em = $this->registry->getManagerForClass($className);

        if (!$em instanceof ObjectManager) {
            throw new EntityManagerNotFoundException($className);
        }

        $em->remove($objectToRemove);
        $em->flush();
    }
}
