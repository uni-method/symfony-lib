<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Exception;

use RuntimeException;
use UniMethod\Frame\Exception\FrameDomainException;

/**
 * An interrupting outcome of a constraint: the object exists, but it is not available
 * to the current user. Domain → 403, one error in the response.
 *
 * Прерывающий исход ограничения: объект есть, но текущему пользователю
 * недоступен. Доменное → 403, одна ошибка в ответе.
 */
final class AccessDeniedException extends RuntimeException implements FrameDomainException
{
    public function getHttpErrorCode(): int
    {
        return 403;
    }
}
