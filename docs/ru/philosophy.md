# Философия

Symfony-бандл для интеграции фреймворка `UniMethod\Frame` с Symfony-приложениями.
Превращает декларативный конфиг (`schema` + `impl`) в готовый REST API: роутинг,
маппинг тела запроса в сущности и обратно, проверку ограничений, контроль
доступа и обработку ошибок.

## Принципы

- **Бандл — тонкий слой над frame.** Конфиг, маппинг, проверки и модель
  ошибок принадлежат frame; бандл связывает их с Symfony: роутинг, DI,
  Security, Validator, Doctrine, rate limit, консольная проверка.
- **Каждый API изолирован.** Сервисы регистрируются по набору на каждый API
  из `symfony_lib.apis` (`symfony_lib.<вид>.<имя API>`), приватны и
  достаются через ServiceLocator; общих сервисов «первого API» нет. См.
  [domains/configuration.md](domains/configuration.md).
- **Ошибки конфигурации — при сборке контейнера.** Конфиг API загружается и
  валидируется при сборке, сервисы словарей `impl` проверяются там же;
  остальное ловит команда проверки до первого запроса. См.
  [domains/config-validation.md](domains/config-validation.md),
  [domains/errors.md](domains/errors.md).
- **Fail-closed доступ.** Без провайдера пользователя нетривиальный `access`
  запрещён. См. [domains/security.md](domains/security.md).
- **Проверки — констрейнтами.** Правила доступа к объекту и инварианты живут в
  констрейнтах Symfony Validator с группами действий. См.
  [domains/constraints.md](domains/constraints.md).
- **Интеграции — по наличию пакета.** Doctrine, Security, Validator, rate
  limiter и консоль подключаются, если пакет установлен. См.
  [usage/quickstart.md](usage/quickstart.md).
