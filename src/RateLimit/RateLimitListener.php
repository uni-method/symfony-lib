<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\RateLimit;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\HttpKernel\Event\RequestEvent;
use UniMethod\Frame\Integration\{Error, Errors, UserInterface, UserProviderInterface};
use UniMethod\SymfonyLib\Exception\RateLimiterTypeMismatchException;

/**
 * Applies rate_limit from the bundle config to the API routes. The rule is chosen by
 * API, user type, model and action; no rule — no limit.
 * Client key: the user identifier, or the IP for anonymous users.
 *
 * Применяет rate_limit из конфига бандла к роутам API. Правило выбирается по
 * API, типу пользователя, модели и действию; нет правила — нет лимита.
 * Ключ клиента: идентификатор пользователя или IP для анонимов.
 */
final readonly class RateLimitListener
{
    public const ANONYMOUS = "anonymous";

    public const AUTHENTICATED = "authenticated";

    /**
     * After RouterListener (32) and Firewall (8).
     *
     * После RouterListener (32) и Firewall (8).
     */
    public const PRIORITY = 0;

    /**
     * @param array<string, array<string, array<string, array<string, array{
     *     policy: string,
     *     limit?: int,
     *     interval?: string,
     *     rate?: array{interval: string, amount: int},
     * }>>>> $rules api → user type → model → action → rule
     * @param ContainerInterface $limiters api → RateLimiterInterface
     */
    public function __construct(
        private array $rules,
        private ContainerInterface $limiters,
        private ?UserProviderInterface $userProvider = null,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $apiName = $request->attributes->get("_api_name");
        $model = $request->attributes->get("_model");
        $action = $request->attributes->get("_actionType");

        if (!is_string($apiName) || !is_string($model) || !is_string($action)) {
            return;
        }

        if (!isset($this->rules[$apiName])) {
            return;
        }

        $user = $this->userProvider?->getCurrentUser();
        $userType = $user instanceof UserInterface
            ? self::AUTHENTICATED
            : self::ANONYMOUS;

        $ruleConfig = $this->rules[$apiName][$userType][$model][$action] ?? null;

        if ($ruleConfig === null) {
            return;
        }

        $rule = new RateLimitRule(
            id: sprintf(
                "symfony_lib.%s.%s.%s.%s",
                $apiName,
                $userType,
                $model,
                $action,
            ),
            policy: RateLimitPolicy::from($ruleConfig["policy"]),
            limit: $ruleConfig["limit"] ?? null,
            interval: $ruleConfig["interval"] ?? null,
            rateInterval: $ruleConfig["rate"]["interval"] ?? null,
            rateAmount: $ruleConfig["rate"]["amount"] ?? 1,
        );

        $result = $this->limiter($apiName)->consume(
            $rule,
            $this->clientKey($request, $user),
        );

        if ($result->accepted) {
            return;
        }

        $event->setResponse($this->tooManyRequests($result));
    }

    private function limiter(string $apiName): RateLimiterInterface
    {
        $limiter = $this->limiters->get($apiName);

        if (!$limiter instanceof RateLimiterInterface) {
            throw new RateLimiterTypeMismatchException($apiName, $limiter);
        }

        return $limiter;
    }

    private function clientKey(Request $request, ?UserInterface $user): string
    {
        if ($user instanceof UserInterface) {
            return "user:" . $user->getUserIdentifier();
        }

        return "ip:" . ($request->getClientIp() ?? "unknown");
    }

    private function tooManyRequests(RateLimitResult $result): JsonResponse
    {
        $headers = [];

        if ($result->retryAfter instanceof \DateTimeImmutable) {
            $headers["Retry-After"] = (string) max(
                0,
                $result->retryAfter->getTimestamp() - time(),
            );
        }

        $errors = new Errors(
            new Error(
                status: 429,
                detail: "Rate limit exceeded",
                title: "Too Many Requests",
            ),
        );

        return new JsonResponse($errors->toArray(), 429, $headers);
    }
}
