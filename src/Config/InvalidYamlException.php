<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Config;

use RuntimeException;
use Throwable;
use UniMethod\Frame\Exception\FrameConfigurationException;

final class InvalidYamlException extends RuntimeException implements
    FrameConfigurationException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
