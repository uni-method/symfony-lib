# Ограничение частоты запросов (rate limit)

Лимиты задаются в конфиге бандла отдельно для каждого API по цепочке
«тип пользователя → модель → действие → правило». Если для модели и действия
правила нет, лимита нет.

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

- Тип пользователя `authenticated` выбирается, когда `UserProviderInterface::getCurrentUser()`
  вернул пользователя, иначе выбирается `anonymous`. Без security-bundle все запросы
  анонимные.
- Модель — имя модели из `schema.actions`. Действие — `create`, `read`, `update`,
  `delete` или `list`. Неизвестное действие или поле правила — ошибка сборки
  контейнера.
- Ключ клиента для анонима — IP (`Request::getClientIp()`; за прокси нужен
  `framework.trusted_proxies`), для авторизованного — `getUserIdentifier()`.
  Счётчики раздельные для каждого API, типа пользователя, модели и действия.

| policy | Обязательные поля | Поведение |
|---|---|---|
| `fixed_window` | `limit`, `interval` | `limit` запросов за окно `interval`, на стыке окон возможен всплеск до 2× |
| `sliding_window` | `limit`, `interval` | то же со сглаживанием на стыке окон |
| `token_bucket` | `limit`, `rate.interval` (`rate.amount`, по умолчанию 1) | всплеск до `limit`, затем `rate.amount` запросов за `rate.interval` |
| `no_limit` | — | без ограничения |

`interval` задаётся в относительном формате PHP: `'30 seconds'`, `'1 minute'`,
`'1 hour'`. Формат и положительность интервала проверяются при сборке
контейнера.

Проверка выполняется слушателем `kernel.request` после роутера и firewall, до
контроллера, поэтому в лимит попадают и запросы, которые затем получат 403 или
422. При превышении лимита возвращается 429 в формате ошибок фреймворка и
заголовок `Retry-After` с числом секунд.

## Пример: ограничение логина

Логин вызывается без токена, поэтому правило задаётся в `anonymous` для модели
и действия из `schema.actions` (здесь `login` / `create`). `sliding_window`
не даёт удвоить попытки на стыке окон:

```yaml
symfony_lib:
    apis:
        main:
            rate_limit:
                anonymous:
                    login:
                        create: { policy: sliding_window, limit: 3, interval: '1 minute' }
```

Лимит считается по IP, а не по логину: подбор пароля к одному аккаунту с разных
IP он не остановит, а пользователи за одним NAT делят общие попытки.

## Лимитер по умолчанию

Если `limiter` не указан, используется `UniMethod\SymfonyLib\RateLimit\SymfonyRateLimiter`
— адаптер над `symfony/rate-limiter`:

```bash
composer require symfony/rate-limiter
```

Без пакета сборка контейнера падает с `MissingDependencyException`. Состояние
хранится в `cache.app`, блокировки берутся из `lock.factory`, если подключён
`symfony/lock`. Без блокировок параллельные запросы одного клиента списываются
без синхронизации. Очистка `cache.app` (`cache:pool:clear`) сбрасывает счётчики.
При нескольких инстансах приложения пул должен быть общим (например, Redis).
Хранилище и блокировки переопределяются в сервисах приложения:

```yaml
# config/services.yaml
services:
    UniMethod\SymfonyLib\RateLimit\SymfonyRateLimiter:
        arguments:
            $cache: '@cache.rate_limit'
            $lockFactory: '@lock.factory'
```

## Своя реализация

1. Реализуйте `UniMethod\SymfonyLib\RateLimit\RateLimiterInterface`.
2. Зарегистрируйте класс как сервис (публичным он быть не обязан).
3. Укажите его id в `limiter`:

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
        // Состояние хранится под $rule->id и $key. Параметры правила:
        // $rule->policy, limit, interval, rateInterval, rateAmount.

        return new RateLimitResult(accepted: true);
    }
}
```

`RateLimitRule::$id` уникален для пары API, типа пользователя, модели и
действия. `RateLimitResult::$retryAfter` при отказе попадает в `Retry-After`.
Сервис, не реализующий интерфейс, даёт `RateLimiterTypeMismatchException` на
первом запросе с правилом.
