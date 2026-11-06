<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Persistence;

use UniMethod\Frame\Persistence\EntityRemoverInterface;

final class InMemoryRemover implements EntityRemoverInterface
{
    public function remove(object $objectToRemove): void
    {
    }
}
