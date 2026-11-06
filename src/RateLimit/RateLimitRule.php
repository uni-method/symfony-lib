<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\RateLimit;

/**
 * A limit rule for a "model + action" pair and a user type.
 *
 * id is unique within the application (api, user type, model, action):
 * the implementation stores state under the id and the client key.
 *
 * Правило лимита для пары «модель + действие» и типа пользователя.
 *
 * id уникален в приложении (api, тип пользователя, модель, действие):
 * реализация хранит состояние под id и ключом клиента.
 */
final readonly class RateLimitRule
{
    public function __construct(
        public string $id,
        public RateLimitPolicy $policy,
        public ?int $limit = null,
        public ?string $interval = null,
        public ?string $rateInterval = null,
        public int $rateAmount = 1,
    ) {
    }
}
