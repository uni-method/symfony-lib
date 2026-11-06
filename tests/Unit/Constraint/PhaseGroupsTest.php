<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UniMethod\Frame\Config\ActionType;
use UniMethod\Frame\Integration\ConstraintContext;
use UniMethod\SymfonyLib\Constraint\PhaseGroups;

final class PhaseGroupsTest extends TestCase
{
    /**
     * @return iterable<string, array{ConstraintContext, list<string>}>
     */
    public static function contexts(): iterable
    {
        yield "afterLocate read" => [ConstraintContext::afterLocate("post", ActionType::Read, 1), ["read"]];
        yield "afterLocate update" => [ConstraintContext::afterLocate("post", ActionType::Update, 1), ["update"]];
        yield "afterLocate delete" => [ConstraintContext::afterLocate("post", ActionType::Delete, 1), ["delete"]];
        yield "afterInput create" => [ConstraintContext::afterInput("post", ActionType::Create), ["Default", "create"]];
        yield "afterInput update" => [ConstraintContext::afterInput("post", ActionType::Update, null, 1), ["Default", "update"]];
        yield "listQuery" => [ConstraintContext::listQuery("post"), ["Default"]];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider("contexts")]
    public function testMapsPhaseAndActionToGroups(ConstraintContext $context, array $expected): void
    {
        self::assertSame($expected, PhaseGroups::for($context));
    }
}
