<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\Entity;

use UniMethod\SymfonyLib\Constraint\ScopedToCurrentUser;

/**
 * The root DTO of a list request: identityPath refers to a property that Account does not have.
 *
 * Корневой DTO list-запроса: identityPath ссылается на свойство, которого у Account нет.
 */
#[ScopedToCurrentUser(path: "filter", identityPath: "uuid")]
final class PostListRequest
{
    public ?object $filter = null;
}
