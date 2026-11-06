# Обработка ошибок

Фреймворк делит исключения на три категории по маркер-интерфейсу:

| Категория | Маркер | Ответ |
|---|---|---|
| доменная — детерминирована вводом клиента | `UniMethod\Frame\Exception\FrameDomainException` | 4xx (400/401/403/404/422), структурированный ответ |
| конфигурация — конфиг, `impl`, сервисы и сборка контейнера не согласованы | `UniMethod\Frame\Exception\FrameConfigurationException` | 500 |
| без категории — сбой, баг | — | 500 |

Доменные обрабатываются внутри `ApiService` и возвращаются как ответ с нужным
кодом. Остальное пробрасывается на границу — `ApiController`:

- ошибки конфигурации (`FrameConfigurationException`, а также ошибки DI-контейнера
  Symfony — `Psr\Container\ContainerExceptionInterface`) логируются уровнем
  `critical` с сообщением `API configuration error`;
- прочие — уровнем `error` с сообщением `API request failed`;
- в обоих случаях клиент получает 500. При `kernel.debug` в `detail` ответа
  уходит класс и текст исключения, иначе — обобщённое `Internal Server Error`.

Конфиг API загружается, а сервисы его словарей `impl` проверяются при сборке
контейнера: такие ошибки роняют `cache:clear` и первый запуск ядра. Остальные
всплывают на первом запросе к API (ответ 500 с текстом в dev). Ошибки конфигурации, которые бросает бандл:

| Исключение | Когда |
|---|---|
| `Config\InvalidYamlException` | YAML-конфиг не читается или не парсится |
| `Exception\MissingDependencyException` | `rate_limit` с лимитером по умолчанию без `symfony/rate-limiter` |
| `Exception\RoutingMisconfiguredException` | тип роутов `symfony_lib_multi_api` импортирован повторно, `ApiHandler` не зарегистрирован или не того класса |
| `Exception\ImplServiceNotFoundException` | класс из `impl.locators`/`savers`/`removers`/`mutators`/`relationMutators` не зарегистрирован сервисом; ошибка сборки контейнера |
| `Exception\ImplServiceTypeMismatchException` | сервис из этих словарей не реализует нужный интерфейс; ошибка сборки контейнера |
| `Exception\EntityManagerNotFoundException` | Doctrine-locator/saver/remover получил класс, который не обслуживается entity manager |
| `Exception\ConstraintPathMisconfiguredException` | путь в констрейнте не существует на классе или ведёт к значению не того вида |
| `Exception\ConstraintContextNotSetException` | валидатор констрейнта вызван вне `ConstraintCheckerInterface::check()` |
| `Exception\RateLimiterTypeMismatchException` | сервис из `rate_limit.limiter` не реализует `RateLimiterInterface`. Бросается в слушателе до `ApiController`, поэтому ответ — стандартная страница ошибки Symfony |

Классы — в пространстве `UniMethod\SymfonyLib`. Полный список ошибок
конфигурации фреймворка и коды доменных — в документации frame,
`docs/ru/domains/errors.md`.
Свои исключения конфигурации (например, сейвер получил не ту модель) помечайте
`FrameConfigurationException` — `ApiController` обработает их так же.

Большую часть этих ошибок можно поймать до деплоя командой проверки конфига
(см. [config-validation.md](config-validation.md)).

## Наблюдение за доменными исключениями

Доменные исключения (4xx) — нормальный поток, а не сбой: `ApiService` превращает
их в ответ без логирования. Если их нужно фиксировать (аудит отказов 403,
счётчики 422, аналитика), реализуйте
`UniMethod\Frame\Integration\DomainExceptionObserverInterface` и
зарегистрируйте под его именем — бандл передаст сервис в каждый `ApiService`.
Сбои (5xx) сюда не попадают: их с трейсом логирует `ApiController`.

Логирование через Monolog, уровень по коду ответа:

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
            $logger: '@monolog.logger.api'   # или '@logger'

    UniMethod\Frame\Integration\DomainExceptionObserverInterface: '@App\Api\LoggingDomainExceptionObserver'
```

```yaml
# config/packages/monolog.yaml — отдельный канал, чтобы 4xx не мешались с ошибками
monolog:
    channels: ['api']
```

Без зарегистрированного сервиса аргумент остаётся `null` и доменные исключения
не логируются.
