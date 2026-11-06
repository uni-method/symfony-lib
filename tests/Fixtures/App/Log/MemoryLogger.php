<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Log;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that records entries for checks.
 *
 * Логгер, запоминающий записи для проверок.
 */
final class MemoryLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ["level" => $level, "message" => (string) $message];
    }
}
