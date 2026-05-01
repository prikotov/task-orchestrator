---
type: fix
value: stability
complexity: low
priority: high
author: pi
created: 2026-04-30
branch: task/brainstorm-results-and-services-fix
status: done
pr: ""
---

# Fix: bridge.php выполняется при загрузке DI-контейнера

## Проблема

`config/services.yaml` использует auto-discovery с `resource: '../src/*'`. Каталог `src/Module/AgentRunner/Infrastructure/Service/Codex/Resources/` не был исключён из auto-discovery. Symfony DI `FileLoader::findClasses()` обнаруживает `bridge.php`, конструирует из пути имя класса (`TaskOrchestrator\Common\...\bridge`) и делегирует автозагрузчику. Поскольку `bridge.php` содержит процедурный код с `exit(1)` при отсутствии env-переменных — любой `php bin/console` завершался с ошибкой:

```
Bridge: missing required environment variables.
```

## Ожидаемый результат

- `php bin/console` команды работают без `BRIDGE_*` env-переменных
- `bridge.php` вызывается только через `proc_open` из `HttpsProxyBridge`

## Scope

- Добавить `'../src/Module/AgentRunner/Infrastructure/Service/Codex/Resources/'` в `exclude` списка `services.yaml`

## Out of scope

- Изменение логики `bridge.php`
- Рефакторинг auto-discovery

## Критерии выполнения

- [x] `config/services.yaml` содержит exclude для `Resources/`
- [x] `php bin/console app:agent:orchestrate "test" --dry-run` отрабатывает без ошибки
- [x] `vendor/bin/phpunit` — зелёный
- [x] `vendor/bin/psalm` — зелёный

## Риски и зависимости

- Нет. Изменение минимально и не затрагивает бизнес-логику.
