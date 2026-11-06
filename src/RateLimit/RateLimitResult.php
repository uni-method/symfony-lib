<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\RateLimit;

use DateTimeImmutable;

final readonly class RateLimitResult
{
    public function __construct(
        public bool $accepted,
        public ?DateTimeImmutable $retryAfter = null,
    ) {
    }
}
