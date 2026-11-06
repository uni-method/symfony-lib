<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use LogicException;
use UniMethod\Frame\Integration\ConstraintContext;
use UniMethod\SymfonyLib\Exception\ConstraintContextNotSetException;

/**
 * The context of the current check for custom validators: action, phase,
 * user, the "before" snapshot. SymfonyConstraintChecker sets it for the duration of
 * the Symfony Validator call and removes it afterwards. A stack: a check started inside
 * another check does not overwrite the outer context.
 *
 * Контекст текущей проверки для кастомных валидаторов: действие, фаза,
 * пользователь, снимок «до». SymfonyConstraintChecker кладёт его на время
 * вызова Symfony Validator и снимает после. Стек: проверка, запущенная внутри
 * другой проверки, не затирает внешний контекст.
 */
final class ConstraintContextHolder
{
    /** @var list<ConstraintContext> */
    private array $stack = [];

    public function push(ConstraintContext $context): void
    {
        $this->stack[] = $context;
    }

    /**
     * @throws LogicException pop() without a paired push()
     */
    public function pop(): void
    {
        if ($this->stack === []) {
            throw new LogicException("ConstraintContextHolder::pop() without a matching push().");
        }

        array_pop($this->stack);
    }

    public function has(): bool
    {
        return $this->stack !== [];
    }

    public function get(): ConstraintContext
    {
        if ($this->stack === []) {
            throw new ConstraintContextNotSetException();
        }

        return $this->stack[array_key_last($this->stack)];
    }
}
