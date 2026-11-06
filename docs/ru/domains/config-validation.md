# Проверка конфига

Команда `symfony-lib:config:validate` проверяет конфиги API без HTTP-запросов —
на тех же ошибках конфигурации, которые иначе дают 500 на первом запросе.
Регистрируется, если установлен `symfony/console`.

```bash
bin/console symfony-lib:config:validate        # все API из symfony_lib.apis
bin/console symfony-lib:config:validate main   # одно API
```

Проверка идёт на трёх уровнях; каждая ошибка в выводе помечена уровнем:

| Метка | Что проверяется |
|---|---|
| `[load]` | конфиг загружается и проходит валидацию фреймворка. Валидатор останавливается на первой ошибке; если конфиг не загрузился, остальные уровни для этого API не проверяются |
| `[services]` | `config.repository` каждого list-действия — сервис с `EntityCollectionInterface`; `ApiService` собирается. Сервисы словарей `impl` проверяются раньше, при сборке контейнера |
| `[impl]` | сверка `impl` с классами: класс модели существует; геттеры и сеттеры — public-методы или public-свойства; конструктор создаваемых моделей совпадает с `constructorParam`; модели под Doctrine-locator/saver/remover замаплены в Doctrine; reference-связи не ведут на модели без read-действия с локатором; пути `OwnedByCurrentUser`/`ScopedToCurrentUser` читаются на классах |

Коды выхода: `0` — ошибок нет, `1` — найдена хотя бы одна ошибка, `2` —
неизвестное имя API.

Пример вывода:

```text
API 'main'
----------

 * [services] List action of model 'post': repository App\Repository\PostRepository is not a service implementing UniMethod\Frame\Persistence\EntityCollectionInterface
 * [impl] Model 'post' attribute 'title': getter is not set and App\Entity\Post has no public property 'title' — the value is silently omitted from responses. Set 'getter' or 'getter: false'
 * [impl] UniMethod\SymfonyLib\Constraint\OwnedByCurrentUser on App\Entity\Post: path 'autor' — segment 'autor' is not readable on App\Entity\Post

 [ERROR] Configuration errors found
```

Что стоит знать:

- `[impl]` считает ошибкой атрибут или связь без `getter`/`setter` и без
  одноимённого публичного свойства: в рантайме такое значение молча не попадает
  в ответ или не записывается. Если пропуск намеренный, укажите в `impl.models`
  `getter: false` или `setter: false`.
- Сеттеры проверяются только у моделей, которые пишутся (create/update и
  вложенные create через `writable`), связи — только перечисленные в `writable`.
- Пути констрейнтов проверяются только у констрейнтов, объявленных атрибутами
  (включая родительские классы), и по объявленным типам свойств и возвращаемых
  значений: где тип не задан, `mixed` или union, дальше путь не проверяется.
  `identityPath` проверяется, если в конфиге задан `userModel`.
- Статически не ловится несоответствие значений при маппинге ответа
  (`ResponseTypeMismatchException`): оно зависит от данных.
- `[impl]` повторяет правила фреймворка, а не вызывает их. Если команда и
  рантайм разошлись, источник истины — рантайм.

## В CI

Запускайте в том окружении, конфиг которого уходит в деплой: набор сервисов и
параметров между dev и prod может отличаться. Команда сама к БД не обращается,
Doctrine нужна ей только для метаданных маппинга.

```bash
composer install --no-interaction
APP_ENV=prod bin/console symfony-lib:config:validate
```

Шаг упадёт с кодом `1`, если в каком-либо API есть ошибка конфигурации.
