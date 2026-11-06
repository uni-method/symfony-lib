<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Persistence;

use UniMethod\Frame\Persistence\EntitySaverInterface;

final class InMemorySaver implements EntitySaverInterface
{
    public function save(object $objectToSave): object
    {
        return $objectToSave;
    }
}
