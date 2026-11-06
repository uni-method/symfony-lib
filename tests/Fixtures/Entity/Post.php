<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\Entity;

use UniMethod\SymfonyLib\Constraint\OwnedByCurrentUser;

/**
 * A correct model: everything is read and written via public properties.
 *
 * Корректная модель: всё читается и пишется через публичные свойства.
 */
#[OwnedByCurrentUser(path: "author.user", groups: ["read"])]
final class Post
{
    public ?string $id = null;

    public ?string $title = null;

    public ?Writer $author = null;
}
