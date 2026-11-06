<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Integration;

use Doctrine\Persistence\{ManagerRegistry, ObjectManager};
use UniMethod\Frame\Persistence\EntitySaverInterface;
use UniMethod\SymfonyLib\Exception\EntityManagerNotFoundException;

final readonly class DoctrineSaver implements EntitySaverInterface
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function save(object $objectToSave): object
    {
        $className = get_class($objectToSave);
        $em = $this->registry->getManagerForClass($className);

        if (!$em instanceof ObjectManager) {
            throw new EntityManagerNotFoundException($className);
        }

        $em->persist($objectToSave);
        $em->flush();

        return $objectToSave;
    }
}
