<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use UniMethod\Frame\Integration\{ConstraintContext, Error};
use UniMethod\SymfonyLib\Constraint\{
    ConstraintContextHolder,
    HasRoleValidator,
    OwnedByCurrentUserValidator,
    PropertyPathReader,
    RoleChecker,
    ScopedToCurrentUserValidator,
    UnchangedOnUpdateValidator,
};
use UniMethod\SymfonyLib\Integration\SymfonyConstraintChecker;
use UniMethod\SymfonyLib\Tests\Stub\StubValidatorFactory;

/**
 * Constraints are run through the real Symfony Validator and
 * SymfonyConstraintChecker: the chain attribute → groups → validator →
 * Error/exception is checked, not the validator in isolation.
 *
 * Констрейнты прогоняются через реальный Symfony Validator и
 * SymfonyConstraintChecker: проверяется связка атрибут → группы → валидатор →
 * Error/исключение, а не валидатор в изоляции.
 */
abstract class ConstraintTestCase extends TestCase
{
    protected ConstraintContextHolder $holder;

    protected SymfonyConstraintChecker $checker;

    protected function setUp(): void
    {
        $this->holder = new ConstraintContextHolder();
        $reader = new PropertyPathReader();
        $roles = new RoleChecker();

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(
                new StubValidatorFactory([
                    OwnedByCurrentUserValidator::class => new OwnedByCurrentUserValidator($this->holder, $reader, $roles),
                    HasRoleValidator::class => new HasRoleValidator($this->holder, $roles),
                    UnchangedOnUpdateValidator::class => new UnchangedOnUpdateValidator($this->holder, $reader),
                    ScopedToCurrentUserValidator::class => new ScopedToCurrentUserValidator($this->holder, $reader, $roles),
                ]),
            )
            ->getValidator();

        $this->checker = new SymfonyConstraintChecker($validator, $this->holder);
    }

    /**
     * @return list<Error>
     */
    protected function check(object $object, ConstraintContext $context): array
    {
        return $this->checker->check($object, $context);
    }

    /**
     * @param list<Error> $errors
     * @return list<string>
     */
    protected static function pointers(array $errors): array
    {
        return array_map(static fn (Error $e): string => (string) ($e->source["pointer"] ?? ""), $errors);
    }
}
