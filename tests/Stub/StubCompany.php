<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Stub;

final class StubCompany
{
    public function __construct(public int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
