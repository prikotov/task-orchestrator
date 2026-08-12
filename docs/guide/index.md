# TasK Orchestrator — Руководства

> Руководства по использованию бандла task-orchestrator.

## Содержание

- [Архитектура](architecture.md) — структура модуля, DDD-слои, зависимости, CQRS, мультидвижковая архитектура
- [Диаграммы](diagrams.md) — Mermaid-диаграммы: component-обзор слоёв, class-диаграмма Domain, диаграммы последовательностей (sequence, static/dynamic)
- [Цепочки](chains.md) — static/dynamic цепочки, `fix_iterations`, `quality gates`, перекрёстная проверка моделей (cross-model verification)
- [Роли](roles.md) — конфигурация ролей, маппинг на `.md` файлы
- [Наблюдаемость](observability.md) — журнал аудита (Audit Trail, JSONL), бюджет (Budget), отчёты (Reports)
- [Надёжность](reliability.md) — Retry Policy, Circuit Breaker, Fallback, сессии и возобновление (Sessions/Resume)
- [Устранение неполадок](troubleshooting.md) — типичные проблемы, симптомы, причины, решения
- [CLI-команды](cli.md) — консольные команды: оркестрация, запуск агента, список движков, проверка запуска ролей из `chains.yaml`
- [Расширение](extension.md) — пошаговые гайды: добавление runner'а, цепочки, роли
- [Идентичность агента (Agent Identity)](agent-identity.md) — разделение идентичности AI-агента и владельца репо через приложение GitHub (GitHub App; имя выбирает пользователь), команда `bin/console agent:token` (модуль GitIdentity)
