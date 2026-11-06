<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use UniMethod\Frame\Integration\ConstraintContext;
use UniMethod\SymfonyLib\Constraint\ScopedToCurrentUser;
use UniMethod\SymfonyLib\Exception\AccessDeniedException;
use UniMethod\SymfonyLib\Tests\Stub\{StubCompany, StubUser};

final class ScopedEqual
{
    public string|int|null $userId = null;
}

final class ScopedIn
{
    public ?array $userId = null;
}

final class ScopedFilter
{
    public ?ScopedEqual $equal = null;

    public ?ScopedIn $in = null;
}

#[ScopedToCurrentUser(
    path: "filter.equal.userId",
    exceptRoles: ["ROLE_ADMIN"],
    requiredMessage: "Filter by user is required",
    message: "Own data only",
)]
final class ScopedListRequest
{
    public ?ScopedFilter $filter = null;
}

#[ScopedToCurrentUser(path: "filter.in.userId", exceptRoles: ["ROLE_ADMIN"])]
final class ScopedInListRequest
{
    public ?ScopedFilter $filter = null;
}

#[ScopedToCurrentUser(path: "filter.equal.userId", identityPath: "company.id")]
final class CompanyScopedListRequest
{
    public ?ScopedFilter $filter = null;
}

final class ScopedToCurrentUserTest extends ConstraintTestCase
{
    private function request(string|int|null $userId, string $class = ScopedListRequest::class): object
    {
        $request = new $class();

        if ($userId !== null) {
            $request->filter = new ScopedFilter();
            $request->filter->equal = new ScopedEqual();
            $request->filter->equal->userId = $userId;
        }

        return $request;
    }

    public function testAnonymousIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->check($this->request(1), ConstraintContext::listQuery("user"));
    }

    public function testMissingFilterIsRequiredViolationAtPath(): void
    {
        $errors = $this->check($this->request(null), ConstraintContext::listQuery("user", new StubUser(1)));

        self::assertSame(["filter.equal.userId"], self::pointers($errors));
        self::assertSame(422, $errors[0]->status);
        self::assertSame("Filter by user is required", $errors[0]->detail);
    }

    public function testEmptyStringCountsAsMissing(): void
    {
        $errors = $this->check($this->request(""), ConstraintContext::listQuery("user", new StubUser(1)));

        self::assertSame(["filter.equal.userId"], self::pointers($errors));
    }

    public function testOwnIdPassesAcrossScalarTypes(): void
    {
        self::assertSame([], $this->check($this->request(1), ConstraintContext::listQuery("user", new StubUser(1))));
        self::assertSame([], $this->check($this->request("1"), ConstraintContext::listQuery("user", new StubUser(1))));
    }

    public function testForeignIdIsDeniedWithMessage(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage("Own data only");

        $this->check($this->request(2), ConstraintContext::listQuery("user", new StubUser(1)));
    }

    public function testExceptRoleMayOmitOrUseAnyId(): void
    {
        $admin = new StubUser(9, ["ROLE_ADMIN"]);

        self::assertSame([], $this->check($this->request(null), ConstraintContext::listQuery("user", $admin)));
        self::assertSame([], $this->check($this->request(2), ConstraintContext::listQuery("user", $admin)));
    }

    public function testInOperatorAllowsOnlyOwnId(): void
    {
        $request = new ScopedInListRequest();
        $request->filter = new ScopedFilter();
        $request->filter->in = new ScopedIn();
        $request->filter->in->userId = [1];

        self::assertSame([], $this->check($request, ConstraintContext::listQuery("user", new StubUser(1))));

        $request->filter->in->userId = [1, 2];

        $this->expectException(AccessDeniedException::class);
        $this->check($request, ConstraintContext::listQuery("user", new StubUser(1)));
    }

    public function testEmptyArrayCountsAsMissing(): void
    {
        $request = new ScopedInListRequest();
        $request->filter = new ScopedFilter();
        $request->filter->in = new ScopedIn();
        $request->filter->in->userId = [];

        self::assertSame(["filter.in.userId"], self::pointers(
            $this->check($request, ConstraintContext::listQuery("user", new StubUser(1))),
        ));
    }

    public function testIdentityPathComparesAgainstUserSubpath(): void
    {
        $user = new StubUser(1, [], new StubCompany(77));

        self::assertSame([], $this->check(
            $this->request(77, CompanyScopedListRequest::class),
            ConstraintContext::listQuery("user", $user),
        ));

        $this->expectException(AccessDeniedException::class);
        $this->check($this->request(1, CompanyScopedListRequest::class), ConstraintContext::listQuery("user", $user));
    }

    public function testIdentityPathUnreachableIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->check(
            $this->request(77, CompanyScopedListRequest::class),
            ConstraintContext::listQuery("user", new StubUser(1)),
        );
    }
}
