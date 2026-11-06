<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use UniMethod\Frame\Config\ActionType;
use UniMethod\Frame\Integration\ConstraintContext;
use UniMethod\SymfonyLib\Constraint\OwnedByCurrentUser;
use UniMethod\SymfonyLib\Exception\AccessDeniedException;
use UniMethod\SymfonyLib\Tests\Stub\StubUser;

#[OwnedByCurrentUser(
    path: "author",
    exceptRoles: ["ROLE_ADMIN"],
    message: "Not your post",
    groups: ["read", "update", "delete"],
)]
final class OwnedPost
{
    public function __construct(public ?StubUser $author = null)
    {
    }
}

#[OwnedByCurrentUser(path: "post.author", groups: ["read"])]
final class OwnedComment
{
    public function __construct(public ?OwnedPost $post = null)
    {
    }
}

final class OwnedByCurrentUserTest extends ConstraintTestCase
{
    public function testOwnerPasses(): void
    {
        $owner = new StubUser("u1");

        $errors = $this->check(
            new OwnedPost($owner),
            ConstraintContext::afterLocate("post", ActionType::Read, 1, $owner),
        );

        self::assertSame([], $errors);
    }

    public function testOtherUserIsDeniedWithMessage(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage("Not your post");

        $this->check(
            new OwnedPost(new StubUser("u1")),
            ConstraintContext::afterLocate("post", ActionType::Delete, 1, new StubUser("u2")),
        );
    }

    public function testExceptRolePasses(): void
    {
        $errors = $this->check(
            new OwnedPost(new StubUser("u1")),
            ConstraintContext::afterLocate("post", ActionType::Update, 1, new StubUser("admin", ["ROLE_ADMIN"])),
        );

        self::assertSame([], $errors);
    }

    public function testAnonymousIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->check(
            new OwnedPost(new StubUser("u1")),
            ConstraintContext::afterLocate("post", ActionType::Read, 1),
        );
    }

    public function testNullOwnerIsSkipped(): void
    {
        $errors = $this->check(
            new OwnedPost(),
            ConstraintContext::afterLocate("post", ActionType::Read, 1, new StubUser("u2")),
        );

        self::assertSame([], $errors);
    }

    public function testNotEvaluatedOutsideItsGroups(): void
    {
        // create → ['Default', 'create']: the attribute is declared only for read/update/delete
        //
        // create → ['Default', 'create']: атрибут объявлен только для read/update/delete
        $errors = $this->check(
            new OwnedPost(new StubUser("u1")),
            ConstraintContext::afterInput("post", ActionType::Create, new StubUser("u2")),
        );

        self::assertSame([], $errors);
    }

    public function testNestedPathAndIdentifierComparison(): void
    {
        // different instances with the same identifier — the same user
        //
        // разные инстансы с одним идентификатором — один пользователь
        $errors = $this->check(
            new OwnedComment(new OwnedPost(new StubUser(5))),
            ConstraintContext::afterLocate("comment", ActionType::Read, 1, new StubUser(5)),
        );

        self::assertSame([], $errors);
    }

    public function testIdentifierComparisonIsStrict(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->check(
            new OwnedComment(new OwnedPost(new StubUser(5))),
            ConstraintContext::afterLocate("comment", ActionType::Read, 1, new StubUser("5")),
        );
    }
}
