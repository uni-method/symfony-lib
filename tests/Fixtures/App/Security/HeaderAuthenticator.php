<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Security;

use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\{Passport, SelfValidatingPassport};

/**
 * Authentication by the `X-Test-User: <id>` header.
 *
 * Аутентификация по заголовку `X-Test-User: <id>`.
 */
final class HeaderAuthenticator extends AbstractAuthenticator
{
    public const string HEADER = "X-Test-User";

    public function supports(Request $request): bool
    {
        return $request->headers->has(self::HEADER);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $id = (string) $request->headers->get(self::HEADER);

        return new SelfValidatingPassport(
            new UserBadge($id, static fn (string $identifier): User => new User($identifier)),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
