# Error handling

The framework divides exceptions into three categories by a marker interface:

| Category | Marker | Response |
|---|---|---|
| domain — determined by client input | `UniMethod\Frame\Exception\FrameDomainException` | 4xx (400/401/403/404/422), a structured response |
| configuration — the config, `impl`, services and the container assembly are inconsistent | `UniMethod\Frame\Exception\FrameConfigurationException` | 500 |
| no category — a failure, a bug | — | 500 |

Domain exceptions are handled inside `ApiService` and returned as a response with the required
code. Everything else is rethrown to the boundary — `ApiController`:

- configuration errors (`FrameConfigurationException`, as well as errors of the Symfony DI container
  — `Psr\Container\ContainerExceptionInterface`) are logged at the
  `critical` level with the message `API configuration error`;
- others — at the `error` level with the message `API request failed`;
- in both cases the client gets a 500. With `kernel.debug` the class and message of the exception
  go into the response's `detail`, otherwise — the generic `Internal Server Error`.

The API config is loaded and the services of its `impl` dictionaries are checked when the container
is built: such errors break `cache:clear` and the first kernel boot. The others
surface on the first request to the API (a 500 response with the text in dev). Configuration errors thrown by the bundle:

| Exception | When |
|---|---|
| `Config\InvalidYamlException` | the YAML config cannot be read or parsed |
| `Exception\MissingDependencyException` | `rate_limit` with the default limiter without `symfony/rate-limiter` |
| `Exception\RoutingMisconfiguredException` | the `symfony_lib_multi_api` route type is imported again, `ApiHandler` is not registered or is of the wrong class |
| `Exception\ImplServiceNotFoundException` | a class from `impl.locators`/`savers`/`removers`/`mutators`/`relationMutators` is not registered as a service; a container build error |
| `Exception\ImplServiceTypeMismatchException` | a service from these dictionaries does not implement the required interface; a container build error |
| `Exception\EntityManagerNotFoundException` | a Doctrine locator/saver/remover received a class that is not served by an entity manager |
| `Exception\ConstraintPathMisconfiguredException` | a path in a constraint does not exist on the class or leads to a value of the wrong kind |
| `Exception\ConstraintContextNotSetException` | a constraint validator is called outside `ConstraintCheckerInterface::check()` |
| `Exception\RateLimiterTypeMismatchException` | the service from `rate_limit.limiter` does not implement `RateLimiterInterface`. It is thrown in the listener before `ApiController`, so the response is the standard Symfony error page |

The classes are in the `UniMethod\SymfonyLib` namespace. The full list of the framework's configuration
errors and the codes of domain ones are in the frame documentation,
`docs/en/domains/errors.md`.
Mark your own configuration exceptions (for example, a saver received the wrong model) with
`FrameConfigurationException` — `ApiController` handles them the same way.

Most of these errors can be caught before deployment with the config check command
(see [config-validation.md](config-validation.md)).

## Observing domain exceptions

Domain exceptions (4xx) are a normal flow, not a failure: `ApiService` turns
them into a response without logging. If you need to record them (an audit of 403 denials,
422 counters, analytics), implement
`UniMethod\Frame\Integration\DomainExceptionObserverInterface` and
register it under its name — the bundle passes the service to each `ApiService`.
Failures (5xx) do not get here: `ApiController` logs them with the trace.

Logging via Monolog, the level by the response code:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Psr\Log\LoggerInterface;
use UniMethod\Frame\Exception\FrameDomainException;
use UniMethod\Frame\Integration\DomainExceptionObserverInterface;

final readonly class LoggingDomainExceptionObserver implements DomainExceptionObserverInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function onDomainException(FrameDomainException $exception): void
    {
        $status = $exception->getHttpErrorCode();

        $this->logger->log(
            $status === 403 ? 'warning' : 'info',
            'API domain error',
            [
                'status' => $status,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        );
    }
}
```

```yaml
# config/services.yaml
services:
    App\Api\LoggingDomainExceptionObserver:
        arguments:
            $logger: '@monolog.logger.api'   # or '@logger'

    UniMethod\Frame\Integration\DomainExceptionObserverInterface: '@App\Api\LoggingDomainExceptionObserver'
```

```yaml
# config/packages/monolog.yaml — a separate channel so that 4xx do not mix with errors
monolog:
    channels: ['api']
```

Without a registered service the argument stays `null` and domain exceptions
are not logged.
