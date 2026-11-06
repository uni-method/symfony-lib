<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\Entity;

final class Writer
{
    public ?string $id = null;

    public ?string $name = null;

    public ?Account $user = null;
}
