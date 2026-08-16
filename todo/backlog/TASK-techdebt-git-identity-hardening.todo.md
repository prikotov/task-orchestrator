---
type: chore
created: 2026-06-20
value: V1
complexity: C2
priority: P3
depends_on:
epic:
author: Тимлид Алекс
assignee:
branch:
pr:
status: backlog
---

# TASK-techdebt-git-identity-hardening: Усиление безопасности и стиля модуля GitIdentity

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Модуль GitIdentity работает, но в защите «вглубь» и стиле остались слабые места: при записи токена в кеш-файл есть короткое окно, когда файл доступен для чтения чужим; символическая ссылка (symlink) может обмануть проверку прав ключа; в коде используются базовые PHP-исключения вместо своих; фабричный метод Value Object назван не по конвенции.

### Варианты или путь решения (Solution Sketch)
Каждое замечание закрыть точечно: создавать кеш-файл сразу с закрытыми правами (0600 до записи данных); отсечь symlink в проверке PEM; заменить базовые исключения на типизированные; переименовать фабрику Value Object по конвенции. Это не баги — работа не сломана, задача про запас прочности и единообразие.

### Ожидаемый результат (Expected Result)
Окно утечки устранено, symlink не обходит проверку прав, нет базовых исключений в CLI, фабрика Value Object по конвенции. `phpunit`/`psalm`/`deptrac` зелёные, без регрессий.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)

Когда модуль GitIdentity уже работает и прошёл code review, я хочу закрыть оставшиеся
«слабые места в броне» (defense-in-depth), чтобы каждый слой безопасности держал сам по себе,
а стиль кода полностью соответствовал конвенциям проекта.

### Goal (Цель по SMART)

Закрыть low-severity замечания code review Пуаро (PR #275): устранить окно утечки секрета
при записи кеша, отсечь symlink-обход проверки прав, убрать базовые PHP-исключения, привести
имя фабричного метода Value Object к конвенции. Результат — чистый `make deptrac`/`psalm`/`phpunit`,
без регрессий, безопасность модуля усилена «вглубь».

> ⚠️ **Это не баги.** Модуль работает корректно. Задача — про «эшелонированную защиту» (defense-in-depth)
> и чистоту стиля: каждое замечание в одиночку маловероятно приведёт к проблеме, но в сумме они снижают
> запас прочности. Приоритет низкий (P3) — можно делать спокойно, без спешки.

## 2. Context and Scope (Контекст и Границы)

* **Где делаем:** `src/Module/GitIdentity/` (модуль из PR #275), конкретно:
  - `Infrastructure/Service/FilesystemTokenCacheService.php`
  - `Infrastructure/Service/LoadGitIdentityConfigService.php`
  - `Application/UseCase/Command/ObtainToken/ObtainTokenCommandHandler.php`
  - `apps/console/src/Module/GitIdentity/Command/AgentTokenCommand.php`
  - `Domain/ValueObject/RepoSlugVo.php`
* **Текущее поведение:** модуль работает, все проверки (phpunit/psalm/deptrac) зелёные, но есть
  несколько мест, где защита «вглубь» или стиль можно усилить (см. раздел 3).
* **Зависимость:** задача выполнена после merge PR #275 (`TASK-chore-bot-account-for-agent`),
  т.к. правит код, введённый в нём.
* **Границы (Out of Scope):**
  - Не меняем архитектуру модуля (Domain/Application/Infrastructure) — она устояла на review.
  - Не трогаем рабочую логику получения токена (она корректна).
  - Не расширяем функциональность (новые features — отдельными задачами).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] **L1 — убрать окно утечки при atomic-write кеша.**
      `FilesystemTokenCacheService::atomicWrite()` создаёт временный файл через `fopen($tmp, 'wb')`
      (права по umask, обычно 0644), пишет туда токен, и только потом делает `chmod($tmp, 0600)`.
      В короткий момент между «создал» и «закрыл права» файл доступен для чтения группе/остальным.
      **Фикс:** создавать файл сразу с закрытыми правами. Варианты: `fopen` с последующим `chmod`
      ДО записи данных; либо `touch` + `chmod 0600` + затем `fopen`. Цель — на момент записи токена
      в файл прав уже были 0600. Покрыть тестом: после записи права файла = 0600 даже если создать
      без него в начале (это уже есть), плюс — желательно assert, что в момент записи права уже 0600.

- [ ] **L2 — отсечь symlink-обход проверки прав PEM.**
      `LoadGitIdentityConfigService::resolvePrivateKey()` использует `is_file()`, который следует
      по symlink. Symlink с chmod 0600 может указывать на файл с открытыми правами (0644) — проверка
      пройдёт, хотя ключ фактически доступен шире.
      **Фикс:** использовать `is_link()` для отсечения (запретить symlink на PEM) ИЛИ проверять
      права по реальному пути (`realpath` + `fileperms`). Зафиксировать выбор в коде и тесте.

### 🟡 Should Have (Желательно)

- [ ] **L4 — убрать голое `\RuntimeException` в CLI.**
      `AgentTokenCommand.php` бросает `throw new \RuntimeException(...)` (базовое PHP-исключение).
      Конвенция исключений проекта не приветствует прямое использование базовых исключений — лучше
      типизированное доменное/прикладное исключение (например, существующее
      `ObtainTokenFailedException` или новое для CLI-специфичных ошибок).
      **Фикс:** заменить на типизированное исключение модуля. Проверить, что это не нарушает deptrac
      (Presentation → Application исключение допустимо; Presentation → Domain — нет).

- [ ] **INFO — привести `RepoSlugVo::fromString()` к конвенции `createFromString()`.**
      Конвенция Value Object предписывает фабричные методы с префиксом `createFrom*()` (а не `from*()`).
      `RepoSlugVo::fromString()` — формальное нарушение. Обновить все вызовы + тесты.

- [ ] **CR-2 (из review PR #275) — VO бросают не тот тип исключения.**
      Конвенция `core-patterns/value-object.md`: «При нарушении инвариантов выбрасывайте `InvalidArgumentException`.»
      Реальность: все VO модуля GitIdentity (`AppIdVo`, `PrivateKeyVo`, `RepoSlugVo`, `InstallationIdVo`,
      `JwtTokenVo`, `GitIdentityConfigVo`) бросают `InvalidConfigurationException extends GitIdentityException
      extends \RuntimeException` — НЕ `InvalidArgumentException`. Остальные VO проекта корректно используют
      `InvalidArgumentException`. Причина отклонения — осознанная унификация доменных исключений под
      `GitIdentityException` (чтобы Application-хендлер ловил один базовый класс). Решить: либо VO →
      `InvalidArgumentException` (+ `GitIdentityException implements DomainExceptionInterface`), либо
      обновить конвенцию под доменные exception-деревья. Файлы: `src/Module/GitIdentity/Domain/ValueObject/*.php`.

### ⟫ Won't Have (Не будем делать)

- Не реализуем `null`-TTL для `installation_id_cache` через Configuration (контракт можно пересмотреть
  отдельной задачей, если понадобится no-expiry режим).
- Не переписываем обработку исключений на domain-wide интерфейс — текущая обёртка в Application
  (`ObtainTokenFailedException`) прошла review и адекватна.

## 4. Implementation Plan (План реализации)

*Заполняется исполнителем перед стартом. Ориентир:*

1. [ ] L1: переделать `atomicWrite` — права 0600 до записи данных; тест на «права 0600 в момент записи».
2. [ ] L2: отсечь symlink в `resolvePrivateKey` (выбрать стратегию: запретить ИЛИ realpath); тест со symlink.
3. [ ] L4: заменить `\RuntimeException` в `AgentTokenCommand` на типизированное исключение.
4. [ ] INFO: переименовать `RepoSlugVo::fromString` → `createFromString`, обновить вызовы и тесты.
5. [ ] Проверки: `vendor/bin/phpunit`, `vendor/bin/psalm`, `make deptrac` — зелёные, без регрессий.

## 5. Definition of Done (Критерии приёмки)

- [ ] L1: окно утечки устранено (права 0600 выставляются до записи токена в файл), есть тест.
- [ ] L2: symlink не позволяет обойти проверку прав PEM, есть тест.
- [ ] L4: в CLI нет базовых PHP-исключений, используется типизированное.
- [ ] INFO: `RepoSlugVo` фабрика переименована по конвенции, вызовы обновлены.
- [ ] `vendor/bin/phpunit`, `vendor/bin/psalm`, `make deptrac` — зелёные.
- [ ] Нет регрессий в существующих тестах модуля GitIdentity (119 тестов из PR #275).

## 6. Verification (Самопроверка)

```bash
# Тесты модуля (без сети)
vendor/bin/phpunit tests/Unit/Module/GitIdentity/ tests/Integration/Module/GitIdentity/

# Все проверки проекта
vendor/bin/phpunit
vendor/bin/psalm
make deptrac

# Проверка: после L1 права кеш-файла всегда 0600
ls -la var/cache/git-identity/*.json
```

## 7. Risks and Dependencies (Риски и зависимости)

- **Зависимость:** PR #275 (`TASK-chore-bot-account-for-agent`) должен быть слит. Файлы для правок
  появляются в нём. Стартовать после merge.
- **Риск регрессии кеша:** изменение `atomicWrite` может повлиять на гонки при параллельной записи.
  Mitigation: сохранить `flock` + atomic rename, покрыть тестом конкурентной записи.
- **Symlink-запрет может сломать удобные сетапы:** некоторые пользователи могут держать PEM через
  symlink на общий каталог. Решение: если запрещаем — задокументировать в гайде; если realpath —
  поведение прозрачно.

## 8. Sources (Источники)

- Code review Пуаро, PR #275 (разделы L1, L2, L4, INFO).
- Конвенции: `docs/conventions/core-patterns/value-object.md` (фабрики `createFrom*`),
  `docs/conventions/core-patterns/exception.md` (типизированные исключения).
- Принцип defense-in-depth (эшелонированная защита) — OWASP.

## 9. Comments (Комментарии)

Почему эти замечания — low-severity, но заслуживают отдельной задачи:

| Замечание | Реальная вероятность проблемы | Почему всё же стоит закрыть |
|---|---|---|
| L1 (окно утечки кеша) | Низкая (каталог уже 0700, нужен злоумышленник с доступом к машине в долю секунды) | Defense-in-depth: даже если каталог скомпрометирован, файл не должен светиться |
| L2 (symlink) | Очень низкая (нужен кто-то, кто специально подсунул symlink) | Замыкает проверку прав; паранойя для credential-кода оправдана |
| L4 (RuntimeException) | Не влияет на работу | Чистота стиля, соответствие конвенциям |
| INFO (fromString) | Не влияет на работу | Единообразие с другими VO проекта |

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-20 | Тимлид Алекс | Создание задачи по итогам code review Пуаро (PR #275). Замечания L1/L2/L4/INFO вынесены из основного PR, чтобы не раздувать его scope. |
