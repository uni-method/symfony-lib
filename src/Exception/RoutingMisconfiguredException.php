<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Exception;

use RuntimeException;
use UniMethod\Frame\Exception\FrameConfigurationException;

/**
 * The API routes cannot be built: the loader is connected again, ApiHandler is not
 * registered or is registered with the wrong class.
 *
 * Роуты API не собрать: загрузчик подключён повторно, ApiHandler не
 * зарегистрирован или зарегистрирован не тем классом.
 */
final class RoutingMisconfiguredException extends RuntimeException implements
    FrameConfigurationException
{
}
