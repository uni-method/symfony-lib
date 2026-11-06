<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Integration;

use Doctrine\Persistence\{ManagerRegistry, ObjectManager};
use UniMethod\Frame\Persistence\EntityLocatorInterface;
use UniMethod\SymfonyLib\Exception\EntityManagerNotFoundException;

final readonly class DoctrineLocator implements EntityLocatorInterface
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    /**
     * @param class-string $className
     */
    public function find(
        string $className,
        int|string $id,
        array $include = [],
        ?object $object = null,
    ): ?object {
        $em = $this->registry->getManagerForClass($className);

        if (!$em instanceof ObjectManager) {
            throw new EntityManagerNotFoundException($className);
        }

        /** @var class-string $className */
        return $em->find($className, $id);
    }
}
