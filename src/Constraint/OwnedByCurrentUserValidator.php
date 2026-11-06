<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use UniMethod\Frame\Integration\UserInterface;
use UniMethod\SymfonyLib\Exception\{
    AccessDeniedException,
    ConstraintPathMisconfiguredException,
};

final class OwnedByCurrentUserValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ConstraintContextHolder $contextHolder,
        private readonly PropertyPathReader $pathReader,
        private readonly RoleChecker $roleChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof OwnedByCurrentUser) {
            throw new UnexpectedTypeException($constraint, OwnedByCurrentUser::class);
        }

        if (!is_object($value)) {
            return;
        }

        [$reachable, $owner] = $this->pathReader->read($value, $constraint->path);

        if (!$reachable) {
            throw ConstraintPathMisconfiguredException::notReachable(
                OwnedByCurrentUser::class,
                $value,
                $constraint->path,
            );
        }

        if ($owner === null) {
            return;
        }

        if (!$owner instanceof UserInterface) {
            throw ConstraintPathMisconfiguredException::unexpectedValue(
                OwnedByCurrentUser::class,
                $value,
                $constraint->path,
                UserInterface::class,
                $owner,
            );
        }

        $user = $this->contextHolder->get()->user;

        if ($user instanceof UserInterface) {
            if ($user->getUserIdentifier() === $owner->getUserIdentifier()) {
                return;
            }

            if ($this->roleChecker->hasAny($user, $constraint->exceptRoles)) {
                return;
            }
        }

        throw new AccessDeniedException($constraint->message);
    }
}
