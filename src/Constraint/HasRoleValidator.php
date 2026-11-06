<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use UniMethod\Frame\Integration\UserInterface;
use UniMethod\SymfonyLib\Exception\AccessDeniedException;

final class HasRoleValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ConstraintContextHolder $contextHolder,
        private readonly RoleChecker $roleChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof HasRole) {
            throw new UnexpectedTypeException($constraint, HasRole::class);
        }

        if (in_array($value, [null, "", []], true)) {
            return;
        }

        $user = $this->contextHolder->get()->user;

        if ($user instanceof UserInterface && $this->roleChecker->hasAny($user, $constraint->roles)) {
            return;
        }

        throw new AccessDeniedException($constraint->message);
    }
}
