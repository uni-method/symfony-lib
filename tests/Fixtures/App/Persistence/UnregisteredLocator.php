<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Persistence;

use UniMethod\Frame\Persistence\EntityLocatorInterface;

/**
 * The class exists but is not registered as a service in the container.
 *
 * Класс есть, но сервисом в контейнере не зарегистрирован.
 */
final class UnregisteredLocator implements EntityLocatorInterface
{
    public function find(
        string $className,
        int|string $id,
        array $include = [],
        ?object $object = null,
    ): object|null {
        return null;
    }
}
