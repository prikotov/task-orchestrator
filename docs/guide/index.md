# TasK Orchestrator — Руководства

> Руководства по использованию бандла task-orchestrator.

## Содержание

- [Архитектура](architecture.md) — структура модуля, DDD-слои, зависимости, CQRS, мультидвижковая архитектура
- [Диаграммы](diagrams.md) — Mermaid-диаграммы: component-обзор слоёв, class-диаграмма Domain, sequence (static/dynamic)
- [Цепочки](chains.md) — static/dynamic цепочки, fix_iterations, quality gates, cross-model verification
- [Роли](roles.md) — конфигурация ролей, маппинг на `.md` файлы
- [Наблюдаемость](observability.md) — Audit Trail (JSONL), Budget (ограничение стоимости), Reports
- [Надёжность](reliability.md) — Retry Policy, Circuit Breaker, Fallback, Sessions/Resume
- [Troubleshooting](troubleshooting.md) — типичные проблемы, симптомы, причины, решения
- [CLI-команды](cli.md) — консольные команды: оркестрация, запуск агента, список движков
- [Расширение](extension.md) — пошаговые гайды: добавление runner'а, цепочки, роли
