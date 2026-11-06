<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use Stringable;
use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use UniMethod\Frame\Integration\UserInterface;
use UniMethod\SymfonyLib\Exception\{
    AccessDeniedException,
    ConstraintPathMisconfiguredException,
};

final class ScopedToCurrentUserValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ConstraintContextHolder $contextHolder,
        private readonly PropertyPathReader $pathReader,
        private readonly RoleChecker $roleChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ScopedToCurrentUser) {
            throw new UnexpectedTypeException($constraint, ScopedToCurrentUser::class);
        }

        if (!is_object($value)) {
            return;
        }

        $user = $this->contextHolder->get()->user;

        if (!$user instanceof UserInterface) {
            throw new AccessDeniedException($constraint->message);
        }

        if ($this->roleChecker->hasAny($user, $constraint->exceptRoles)) {
            return;
        }

        [$reachable, $filtered] = $this->pathReader->read($value, $constraint->path);

        if (!$reachable) {
            throw ConstraintPathMisconfiguredException::notReachable(
                ScopedToCurrentUser::class,
                $value,
                $constraint->path,
            );
        }

        if (in_array($filtered, [null, "", []], true)) {
            $this->context
                ->buildViolation($constraint->requiredMessage)
                ->atPath($constraint->path)
                ->addViolation();

            return;
        }

        $identity = $this->identity($user, $constraint);

        foreach (is_array($filtered) ? $filtered : [$filtered] as $item) {
            if ($this->stringify($item, $value, $constraint->path) !== $identity) {
                throw new AccessDeniedException($constraint->message);
            }
        }
    }

    private function identity(UserInterface $user, ScopedToCurrentUser $constraint): string
    {
        if ($constraint->identityPath === null) {
            return (string) $user->getUserIdentifier();
        }

        [$reachable, $identity] = $this->pathReader->read($user, $constraint->identityPath);

        if (!$reachable) {
            throw ConstraintPathMisconfiguredException::notReachable(
                ScopedToCurrentUser::class,
                $user,
                $constraint->identityPath,
            );
        }

        if ($identity === null) {
            throw new AccessDeniedException($constraint->message);
        }

        return $this->stringify($identity, $user, $constraint->identityPath);
    }

    private function stringify(mixed $value, object $root, string $path): string
    {
        if (is_object($value) && !$value instanceof Stringable) {
            if (method_exists($value, "getId")) {
                return (string) $value->getId();
            }

            if (method_exists($value, "getUserIdentifier")) {
                return (string) $value->getUserIdentifier();
            }

            throw ConstraintPathMisconfiguredException::unexpectedValue(
                ScopedToCurrentUser::class,
                $root,
                $path,
                "scalar|Stringable|object with getId()",
                $value,
            );
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw ConstraintPathMisconfiguredException::unexpectedValue(
            ScopedToCurrentUser::class,
            $root,
            $path,
            "scalar|Stringable",
            $value,
        );
    }
}
