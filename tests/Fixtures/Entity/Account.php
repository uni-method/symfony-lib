<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\Entity;

/**
 * The user model (userModel).
 *
 * Модель пользователя (userModel).
 */
final class Account
{
    public ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }
}
