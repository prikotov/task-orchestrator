# TasK Orchestrator — AI Agent Orchestration Library

> Part of **TasK Orchestrator** documentation. See [README](../README.md) for installation and usage.

TasK Orchestrator — PHP-оркестратор AI-агентов, который позволяет автоматически запускать цепочки ролей (pi, Codex CLI и другие) для анализа, проектирования, реализации и ревью кода.

Поддерживает два типа цепочек:
- **Static** — фиксированные шаги, линейное выполнение **с поддержкой итерационных циклов** (retry-группы).
- **Dynamic** — фасилитатор решает в рантайме, кому дать слово (brainstorm, итеративное ревью).

## Содержание

- [Архитектура](architecture.md) — структура модуля, DDD-слои, зависимости, CQRS, мультидвижковая архитектура
- [Диаграммы](diagrams.md) — Mermaid-диаграммы: component-обзор слоёв, class-диаграмма Domain, sequence (static/dynamic), flowchart PiAgentRunner
- [Цепочки](chains.md) — static/dynamic цепочки, fix_iterations, quality gates, cross-model verification, кастомные цепочки
- [Роли](roles.md) — конфигурация ролей, маппинг на `.md` файлы
- [Наблюдаемость](observability.md) — Audit Trail (JSONL), Budget (ограничение стоимости), Reports
- [Надёжность](reliability.md) — Retry Policy, Circuit Breaker, Fallback, Sessions/Resume
- [Troubleshooting](troubleshooting.md) — типичные проблемы, симптомы, причины, решения, отладочные команды
- [Расширение](extension.md) — пошаговые гайды: добавление runner'а, цепочки, роли с примерами кода, YAML и тестами
- [Исследование: Bernstein](research/agent-bernstein-comparison.md) — сравнение с Bernstein AI Agent Governance Framework
- [Исследование: AI-Agents-Orchestrator](research/agent-orchestrator-comparison.md) — сравнение с AI-Agents-Orchestrator (Python)
- [Исследование: Superpowers Brainstorming](research/superpowers-brainstorming-comparison.md) — сравнение с Superpowers Brainstorming Skill (checklist vs adaptive loop)

### CLI Integration

For CLI commands (`app:agent:orchestrate`, `app:agent:run`, `app:agent:runners`) see the TasK project documentation: [`docs/architecture/agent/cli-reference.md`](https://github.com/prikotov/TasK/blob/master/docs/architecture/agent/cli-reference.md).

> **Note:** These commands are part of the TasK Console application (Presentation layer), not the library itself. When integrating into your own project, you will create your own CLI commands that use the library's Application layer (Command/Query handlers).
