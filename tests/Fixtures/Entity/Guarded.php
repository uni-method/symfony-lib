<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\Entity;

use UniMethod\SymfonyLib\Constraint\OwnedByCurrentUser;

/**
 * A constraint path whose segment does not exist on the getter's type.
 *
 * Путь констрейнта, сегмента которого нет на типе геттера.
 */
#[OwnedByCurrentUser(path: "author.owner", groups: ["read"])]
final class Guarded
{
    public ?string $id = null;

    private ?Writer $author = null;

    public function getAuthor(): ?Writer
    {
        return $this->author;
    }
}
