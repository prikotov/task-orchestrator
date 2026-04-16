# TasK Orchestrator — Документация

> Part of **TasK Orchestrator** documentation. See [README](../README.md) for installation and usage.

TasK Orchestrator — PHP-оркестратор AI-агентов, который позволяет автоматически запускать цепочки ролей (pi, Codex CLI и другие) для анализа, проектирования, реализации и ревью кода.

Поддерживает два типа цепочек:
- **Static** — фиксированные шаги, линейное выполнение с поддержкой итерационных циклов (retry-группы).
- **Dynamic** — фасилитатор решает в рантайме, кому дать слово (brainstorm, итеративное ревью).

## Разделы

| Раздел | Описание |
|---|---|
| [Руководства](guide/index.md) | Архитектура, цепочки, роли, надёжность, расширение |
| [Конвенции](conventions/index.md) | DDD, паттерны, слои, тестирование, стиль кода |
| [Git Workflow](git-workflow/index.md) | Ветки, коммиты, PR, релизы, code review |
| [Исследования](research/) | Сравнение с аналогами |
| [Агенты](agents/roles/team/) | Роли AI-агентов |

### CLI Integration

For CLI commands (`app:agent:orchestrate`, `app:agent:run`, `app:agent:runners`) see the TasK project documentation: [`docs/architecture/agent/cli-reference.md`](https://github.com/prikotov/TasK/blob/master/docs/architecture/agent/cli-reference.md).

> **Note:** These commands are part of the TasK Console application (Presentation layer), not the library itself. When integrating into your own project, you will create your own CLI commands that use the library's Application layer (Command/Query handlers).
