<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Entity;

/**
 * An entity that is not served by any Doctrine entity manager.
 *
 * Сущность, которую не обслуживает ни один entity manager Doctrine.
 */
final class Ghost
{
    public function __construct(public ?string $id = null)
    {
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
