<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use DateTimeImmutable;
use UniMethod\Frame\Config\ActionType;
use UniMethod\Frame\Integration\ConstraintContext;
use Symfony\Component\Validator\Constraints as Assert;
use UniMethod\SymfonyLib\Constraint\UnchangedOnUpdate;
use UniMethod\SymfonyLib\Exception\ConstraintPathMisconfiguredException;
use UniMethod\SymfonyLib\Tests\Stub\StubCompany;

final class ZoneEntity
{
    #[UnchangedOnUpdate(message: "Zone cannot be moved", groups: ["update"])]
    public ?StubCompany $garden = null;

    #[UnchangedOnUpdate(groups: ["update"])]
    public ?string $code = null;

    #[UnchangedOnUpdate(groups: ["update"])]
    public ?DateTimeImmutable $createdAt = null;
}

final class LineItem
{
    public function __construct(
        #[UnchangedOnUpdate(groups: ["update"])]
        public string $sku,
    ) {
    }
}

final class OrderEntity
{
    /** @var list<LineItem> */
    #[Assert\Valid]
    public array $items = [];
}

final class SealedEntity
{
    public function __construct(
        #[UnchangedOnUpdate(groups: ["update"])]
        private string $code,
    ) {
    }
}

final class UnchangedOnUpdateTest extends ConstraintTestCase
{
    private function zone(?StubCompany $garden, string $code = "A", string $createdAt = "2025-01-01"): ZoneEntity
    {
        $zone = new ZoneEntity();
        $zone->garden = $garden;
        $zone->code = $code;
        $zone->createdAt = new DateTimeImmutable($createdAt);

        return $zone;
    }

    public function testUnchangedPasses(): void
    {
        $garden = new StubCompany(1);
        $current = $this->zone($garden);
        $original = clone $current;

        $errors = $this->check($current, ConstraintContext::afterInput("zone", ActionType::Update, null, 1, $original));

        self::assertSame([], $errors);
    }

    public function testChangedScalarIsViolationAtProperty(): void
    {
        $current = $this->zone(new StubCompany(1), "B");
        $original = $this->zone(new StubCompany(1), "A");

        $errors = $this->check($current, ConstraintContext::afterInput("zone", ActionType::Update, null, 1, $original));

        self::assertSame(["code"], self::pointers($errors));
        self::assertSame(422, $errors[0]->status);
        self::assertSame("This value cannot be changed.", $errors[0]->detail);
    }

    public function testRelationComparedByIdNotInstance(): void
    {
        $current = $this->zone(new StubCompany(1));
        $original = $this->zone(new StubCompany(1));

        self::assertSame([], $this->check($current, ConstraintContext::afterInput("zone", ActionType::Update, null, 1, $original)));

        $current->garden = new StubCompany(2);

        $errors = $this->check($current, ConstraintContext::afterInput("zone", ActionType::Update, null, 1, $original));

        self::assertSame(["garden"], self::pointers($errors));
        self::assertSame("Zone cannot be moved", $errors[0]->detail);
    }

    public function testDateTimeComparedByValue(): void
    {
        $current = $this->zone(new StubCompany(1), "A", "2025-01-01");
        $original = $this->zone(new StubCompany(1), "A", "2025-01-01");

        self::assertSame([], $this->check($current, ConstraintContext::afterInput("zone", ActionType::Update, null, 1, $original)));

        $current->createdAt = new DateTimeImmutable("2025-02-01");

        self::assertSame(["createdAt"], self::pointers(
            $this->check($current, ConstraintContext::afterInput("zone", ActionType::Update, null, 1, $original)),
        ));
    }

    public function testSettingValueWhereBeforeWasNullIsViolation(): void
    {
        $current = $this->zone(new StubCompany(1));
        $original = $this->zone(null);

        self::assertSame(["garden"], self::pointers(
            $this->check($current, ConstraintContext::afterInput("zone", ActionType::Update, null, 1, $original)),
        ));
    }

    public function testSkippedOnCreateAndAfterLocateAndWithoutOriginal(): void
    {
        $current = $this->zone(new StubCompany(2), "B");

        self::assertSame([], $this->check($current, ConstraintContext::afterInput("zone", ActionType::Create)));
        self::assertSame([], $this->check($current, ConstraintContext::afterLocate("zone", ActionType::Update, 1)));
        self::assertSame([], $this->check($current, ConstraintContext::afterInput("zone", ActionType::Update, null, 1)));
    }

    /**
     * A collection element is compared with its snapshot element by the path
     * `items[i].sku`.
     *
     * Элемент коллекции сравнивается со своим элементом снимка по пути
     * `items[i].sku`.
     */
    public function testChangedElementOfCollectionIsViolation(): void
    {
        $original = new OrderEntity();
        $original->items = [new LineItem("A-1"), new LineItem("B-1")];

        $current = new OrderEntity();
        $current->items = [new LineItem("A-1"), new LineItem("B-2")];

        $errors = $this->check($current, ConstraintContext::afterInput("order", ActionType::Update, null, 1, $original));

        self::assertSame(["items[1].sku"], self::pointers($errors));
    }

    /**
     * A value that cannot be read on the snapshot is a configuration error, not
     * a silently skipped rule.
     *
     * Значение, которое нельзя прочитать на снимке, — ошибка конфигурации, а не
     * молча пропущенное правило.
     */
    public function testPathUnreachableOnOriginalIsConfigurationError(): void
    {
        $this->expectException(ConstraintPathMisconfiguredException::class);

        $this->check(
            new SealedEntity("B"),
            ConstraintContext::afterInput("sealed", ActionType::Update, null, 1, new SealedEntity("A")),
        );
    }
}
