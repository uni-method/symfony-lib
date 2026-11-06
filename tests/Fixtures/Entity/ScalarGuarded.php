<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\Entity;

use UniMethod\SymfonyLib\Constraint\OwnedByCurrentUser;

/**
 * A constraint path continuing past a scalar type.
 *
 * Путь констрейнта, продолжающийся за скалярным типом.
 */
#[OwnedByCurrentUser(path: "title.length", groups: ["read"])]
final class ScalarGuarded
{
    public ?string $id = null;

    public string $title = "";
}
