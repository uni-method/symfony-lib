<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Exception;

use RuntimeException;
use UniMethod\Frame\Exception\FrameConfigurationException;

/**
 * A class from the impl dictionary (locators/savers/removers/mutators/relationMutators)
 * is not registered as a service in the container.
 *
 * Класс из словаря impl (locators/savers/removers/mutators/relationMutators)
 * не зарегистрирован сервисом в контейнере.
 */
final class ImplServiceNotFoundException extends RuntimeException implements
    FrameConfigurationException
{
    public function __construct(string $section, string $alias, string $class)
    {
        parent::__construct(
            sprintf(
                'impl.%s alias "%s" refers to "%s", which is not a service in the container. Register it as a service.',
                $section,
                $alias,
                $class,
            ),
        );
    }
}
