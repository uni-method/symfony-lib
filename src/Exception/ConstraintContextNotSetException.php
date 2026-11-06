<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Exception;

use LogicException;
use UniMethod\Frame\Exception\FrameConfigurationException;

/**
 * A bundle constraint validator was called outside ConstraintCheckerInterface::check().
 *
 * Валидатор констрейнта бандла вызван вне ConstraintCheckerInterface::check().
 */
final class ConstraintContextNotSetException extends LogicException implements
    FrameConfigurationException
{
    public function __construct()
    {
        parent::__construct(
            "ConstraintContext is not set: the validator was invoked outside of ConstraintCheckerInterface::check().",
        );
    }
}
