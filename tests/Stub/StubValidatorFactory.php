<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Stub;

use Symfony\Component\Validator\{Constraint, ConstraintValidatorFactoryInterface, ConstraintValidatorInterface};

/**
 * A factory for the real Symfony Validator in tests: validators with dependencies
 * are taken from the map, the rest are created without arguments.
 *
 * Фабрика для реального Symfony Validator в тестах: валидаторы с зависимостями
 * берутся из мапы, остальные создаются без аргументов.
 */
final class StubValidatorFactory implements ConstraintValidatorFactoryInterface
{
    /**
     * @param array<class-string, ConstraintValidatorInterface> $validators
     */
    public function __construct(private readonly array $validators)
    {
    }

    public function getInstance(Constraint $constraint): ConstraintValidatorInterface
    {
        $class = $constraint->validatedBy();

        if (isset($this->validators[$class])) {
            return $this->validators[$class];
        }

        /** @var class-string<ConstraintValidatorInterface> $class */
        return new $class();
    }
}
