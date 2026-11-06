<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Exception;

use RuntimeException;
use UniMethod\Frame\Exception\FrameConfigurationException;

/**
 * A service from the impl dictionary does not implement the interface required for this dictionary.
 *
 * Сервис из словаря impl не реализует интерфейс, нужный для этого словаря.
 */
final class ImplServiceTypeMismatchException extends RuntimeException implements
    FrameConfigurationException
{
    public function __construct(
        string $section,
        string $alias,
        string $class,
        string $expectedInterface,
        string $givenType,
    ) {
        parent::__construct(
            sprintf(
                'impl.%s alias "%s": service "%s" must implement %s, %s given.',
                $section,
                $alias,
                $class,
                $expectedInterface,
                $givenType,
            ),
        );
    }
}
