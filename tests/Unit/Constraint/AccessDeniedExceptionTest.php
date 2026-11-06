<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use PHPUnit\Framework\TestCase;
use UniMethod\Frame\Exception\FrameDomainException;
use UniMethod\SymfonyLib\Exception\AccessDeniedException;

final class AccessDeniedExceptionTest extends TestCase
{
    public function testIsDomain403(): void
    {
        $exception = new AccessDeniedException("nope");

        self::assertInstanceOf(FrameDomainException::class, $exception);
        self::assertSame(403, $exception->getHttpErrorCode());
        self::assertSame("nope", $exception->getMessage());
    }
}
