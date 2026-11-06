<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use UniMethod\Frame\Integration\{ConstraintContext, ConstraintPhase};

/**
 * The bridge contract: phase + action → Symfony validation groups.
 *
 *   AfterLocate  → [<action>]            object from the DB, input not applied
 *   AfterInput   → ['Default', <action>] object after input is applied
 *   ListQuery    → ['Default']           list request DTO
 *
 * Контракт моста: фаза + действие → группы валидации Symfony.
 *
 *   AfterLocate  → [<action>]            объект из БД, вход не применён
 *   AfterInput   → ['Default', <action>] объект после применения входа
 *   ListQuery    → ['Default']           DTO list-запроса
 */
final class PhaseGroups
{
    public const string DEFAULT = "Default";

    /**
     * @return list<string>
     */
    public static function for(ConstraintContext $context): array
    {
        return match ($context->phase) {
            ConstraintPhase::AfterLocate => [$context->actionType->value],
            ConstraintPhase::AfterInput => [self::DEFAULT, $context->actionType->value],
            ConstraintPhase::ListQuery => [self::DEFAULT],
        };
    }
}
