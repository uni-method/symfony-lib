<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\RateLimit;

enum RateLimitPolicy: string
{
    case FixedWindow = "fixed_window";
    case SlidingWindow = "sliding_window";
    case TokenBucket = "token_bucket";
    case NoLimit = "no_limit";
}
