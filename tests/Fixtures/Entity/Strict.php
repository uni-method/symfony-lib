<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\Entity;

/**
 * A constructor with a required parameter.
 *
 * Конструктор с обязательным параметром.
 */
final class Strict
{
    public ?string $id = null;

    public function __construct(public string $name)
    {
    }
}
