<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Integration;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use UniMethod\Frame\Integration\{ConstraintCheckerInterface, ConstraintContext, Error};
use UniMethod\SymfonyLib\Constraint\{ConstraintContextHolder, PhaseGroups};

final readonly class SymfonyConstraintChecker implements ConstraintCheckerInterface
{
    private const int HTTP_UNPROCESSABLE_ENTITY = 422;

    private const string VALIDATION_TITLE = "Validation errors";

    public function __construct(
        private ValidatorInterface $validator,
        private ConstraintContextHolder $contextHolder,
    ) {
    }

    public function check(object $object, ConstraintContext $context): array
    {
        $this->contextHolder->push($context);

        try {
            $violations = $this->validator->validate($object, null, PhaseGroups::for($context));
        } finally {
            $this->contextHolder->pop();
        }

        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = new Error(
                self::HTTP_UNPROCESSABLE_ENTITY,
                (string) $violation->getMessage(),
                self::VALIDATION_TITLE,
                ["pointer" => $violation->getPropertyPath()],
            );
        }

        return $errors;
    }
}
