<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Exception;

use RuntimeException;
use UniMethod\Frame\Exception\FrameConfigurationException;

/**
 * The path in the constraint does not match the class it is put on: the segment
 * does not exist, or the value at the path is of the wrong kind. A constraint configuration error.
 *
 * Путь в констрейнте не соответствует классу, на который он навешен: сегмента
 * нет, или значение по пути не того вида. Ошибка конфигурации констрейнта.
 */
final class ConstraintPathMisconfiguredException extends RuntimeException implements
    FrameConfigurationException
{
    public static function notReachable(
        string $constraint,
        object $root,
        string $path,
    ): self {
        return new self(
            sprintf(
                'Constraint %s: path "%s" is not reachable on %s.',
                $constraint,
                $path,
                get_debug_type($root),
            ),
        );
    }

    public static function unexpectedValue(
        string $constraint,
        object $root,
        string $path,
        string $expected,
        mixed $given,
    ): self {
        return new self(
            sprintf(
                'Constraint %s: path "%s" on %s must resolve to %s, %s given.',
                $constraint,
                $path,
                get_debug_type($root),
                $expected,
                get_debug_type($given),
            ),
        );
    }
}
