<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use DateTimeInterface;
use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use UniMethod\Frame\Config\ActionType;
use UniMethod\Frame\Integration\ConstraintPhase;
use UniMethod\SymfonyLib\Exception\ConstraintPathMisconfiguredException;

final class UnchangedOnUpdateValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ConstraintContextHolder $contextHolder,
        private readonly PropertyPathReader $pathReader,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UnchangedOnUpdate) {
            throw new UnexpectedTypeException($constraint, UnchangedOnUpdate::class);
        }

        $context = $this->contextHolder->get();

        if (
            $context->actionType !== ActionType::Update ||
            $context->phase !== ConstraintPhase::AfterInput ||
            $context->original === null
        ) {
            return;
        }

        $path = $this->context->getPropertyPath();

        if ($path === "") {
            return;
        }

        [$reachable, $before] = $this->pathReader->read($context->original, $path);

        // The path came from the validator, so it exists on the object: unreachability
        // on the snapshot is a configuration error, not a reason to silently skip the rule
        //
        // Путь пришёл от валидатора, значит на объекте он есть: недостижимость
        // на снимке — ошибка конфигурации, а не повод молча пропустить правило
        if (!$reachable) {
            throw ConstraintPathMisconfiguredException::notReachable(
                UnchangedOnUpdate::class,
                $context->original,
                $path,
            );
        }

        if ($this->same($before, $value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }

    private function same(mixed $a, mixed $b): bool
    {
        if ($a instanceof DateTimeInterface && $b instanceof DateTimeInterface) {
            return $a == $b;
        }

        if (is_object($a) && is_object($b)) {
            if ($a === $b) {
                return true;
            }

            if (method_exists($a, "getId") && method_exists($b, "getId")) {
                return $a::class === $b::class && $a->getId() === $b->getId();
            }

            return false;
        }

        return $a === $b;
    }
}
