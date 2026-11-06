<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Entity;

final class Post
{
    public function __construct(
        public ?string $id = null,
        public ?string $title = null,
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
