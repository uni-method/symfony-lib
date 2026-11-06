<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Persistence;

use UniMethod\Frame\Persistence\EntityLocatorInterface;
use UniMethod\SymfonyLib\Tests\Fixtures\App\Entity\Post;

/**
 * Knows one post with id "1".
 *
 * Знает один пост с id "1".
 */
final class InMemoryLocator implements EntityLocatorInterface
{
    public function find(
        string $className,
        int|string $id,
        array $include = [],
        ?object $object = null,
    ): object|null {
        return (string) $id === "1" ? new Post("1", "First") : null;
    }
}
