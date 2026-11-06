# Rate limiting

Limits are set in the bundle config separately for each API along the chain
"user type → model → action → rule". If there is no rule for a model and an action,
there is no limit.

```yaml
symfony_lib:
    apis:
        main:
            config_path: '%kernel.project_dir%/config/api.yaml'
            prefix: '/api'
            rate_limit:
                anonymous:
                    user:
                        list: { policy: sliding_window, limit: 100, interval: '1 minute' }
                        read: { policy: fixed_window, limit: 300, interval: '1 minute' }
                authenticated:
                    user:
                        list:
                            policy: token_bucket
                            limit: 100
                            rate: { interval: '1 minute', amount: 10 }
```

- The `authenticated` user type is chosen when `UserProviderInterface::getCurrentUser()`
  returned a user, otherwise `anonymous` is chosen. Without security-bundle all requests
  are anonymous.
- The model is a model name from `schema.actions`. The action is `create`, `read`, `update`,
  `delete` or `list`. An unknown action or rule field is a container build
  error.
- The client key for an anonymous user is the IP (`Request::getClientIp()`; behind a proxy
  `framework.trusted_proxies` is needed), for an authorized one — `getUserIdentifier()`.
  Counters are separate for each API, user type, model and action.

| policy | Required fields | Behavior |
|---|---|---|
| `fixed_window` | `limit`, `interval` | `limit` requests per `interval` window, a burst of up to 2× is possible at the window boundary |
| `sliding_window` | `limit`, `interval` | the same with smoothing at the window boundary |
| `token_bucket` | `limit`, `rate.interval` (`rate.amount`, 1 by default) | a burst of up to `limit`, then `rate.amount` requests per `rate.interval` |
| `no_limit` | — | no limit |

`interval` is set in the PHP relative format: `'30 seconds'`, `'1 minute'`,
`'1 hour'`. The format and positivity of the interval are checked when the container
is built.

The check is performed by a `kernel.request` listener after the router and the firewall, before
the controller, so requests that then get 403 or 422 also count
towards the limit. When the limit is exceeded, a 429 is returned in the framework error format with
the `Retry-After` header containing the number of seconds.

## Example: limiting login

Login is called without a token, so the rule is set in `anonymous` for the model
and action from `schema.actions` (here `login` / `create`). `sliding_window`
does not allow doubling the attempts at the window boundary:

```yaml
symfony_lib:
    apis:
        main:
            rate_limit:
                anonymous:
                    login:
                        create: { policy: sliding_window, limit: 3, interval: '1 minute' }
```

The limit is counted by IP, not by login: it will not stop password guessing for one account from different
IPs, and users behind one NAT share common attempts.

## The default limiter

If `limiter` is not specified, `UniMethod\SymfonyLib\RateLimit\SymfonyRateLimiter` is used
— an adapter over `symfony/rate-limiter`:

```bash
composer require symfony/rate-limiter
```

Without the package the container build fails with `MissingDependencyException`. The state
is stored in `cache.app`, locks are taken from `lock.factory` if
`symfony/lock` is installed. Without locks, parallel requests of one client are consumed
without synchronization. Clearing `cache.app` (`cache:pool:clear`) resets the counters.
With several application instances the pool must be shared (for example, Redis).
The storage and locks are overridden in the application services:

```yaml
# config/services.yaml
services:
    UniMethod\SymfonyLib\RateLimit\SymfonyRateLimiter:
        arguments:
            $cache: '@cache.rate_limit'
            $lockFactory: '@lock.factory'
```

## A custom implementation

1. Implement `UniMethod\SymfonyLib\RateLimit\RateLimiterInterface`.
2. Register the class as a service (it does not have to be public).
3. Specify its id in `limiter`:

```yaml
symfony_lib:
    apis:
        main:
            rate_limit:
                limiter: App\Api\RedisRateLimiter
                anonymous: # ...
```

```php
<?php

declare(strict_types=1);

namespace App\Api;

use UniMethod\SymfonyLib\RateLimit\{RateLimiterInterface, RateLimitResult, RateLimitRule};

final readonly class RedisRateLimiter implements RateLimiterInterface
{
    public function consume(RateLimitRule $rule, string $key): RateLimitResult
    {
        // The state is stored under $rule->id and $key. Rule parameters:
        // $rule->policy, limit, interval, rateInterval, rateAmount.

        return new RateLimitResult(accepted: true);
    }
}
```

`RateLimitRule::$id` is unique for the combination of API, user type, model and
action. On a denial, `RateLimitResult::$retryAfter` goes into `Retry-After`.
A service that does not implement the interface gives `RateLimiterTypeMismatchException` on
the first request with a rule.
