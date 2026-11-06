<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Exception;

use RuntimeException;
use UniMethod\Frame\Exception\FrameConfigurationException;

/**
 * The model class is not served by any Doctrine entity manager: the entity
 * is not mapped, or a Doctrine locator/saver/remover is chosen for the model although
 * it is not an entity.
 *
 * Класс модели не обслуживается ни одним entity manager Doctrine: сущность
 * не замаплена, либо для модели выбран Doctrine-locator/saver/remover, хотя
 * это не сущность.
 */
final class EntityManagerNotFoundException extends RuntimeException implements
    FrameConfigurationException
{
    public function __construct(string $className)
    {
        parent::__construct(
            sprintf('No entity manager found for class "%s".', $className),
        );
    }
}
