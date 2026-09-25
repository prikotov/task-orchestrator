# Исследование pragmatic-orchestration как слоя делегации над внешними агентами

**Роль:** Аналитик (Шерлок)  
**Дата:** 2026-09-22  
**Объект:** `CodeAlive-AI/pragmatic-orchestration`, ветка `main`, commit `a72695dded7e2f28c185c3a2d770b26eb45528fa` от 2026-09-21  
**Задача:** [TASK-research-pragmatic-orchestration](../../../../todo/done/TASK-research-pragmatic-orchestration.todo.md), эпик [EPIC-research-agent-frameworks-comparison](../../../../todo/EPIC-research-agent-frameworks-comparison.todo.md)

---

## Резюме

pragmatic-orchestration классифицирован как `orchestration skill suite / delegation layer over external agents` (набор навыков оркестрации и слой делегации над внешними агентами), а не собственный coding agent (агент программирования) и не chain engine (движок цепочек). Agent Skills обучают родительский харнес, а `porch` запускает и нормализует Codex CLI, Claude Code, OpenCode, Grok Build и Devin CLI; Gemini доступен только для read-only review (ревью без записи).

Итоговый вердикт:

- 🟡 заимствовать steer delivery lifecycle (жизненный цикл доставки коррекции), закрытые события, fail-closed capability matrix (матрицу возможностей с отказом по умолчанию), deviation journal (журнал отклонений) и fake-CLI tests (тесты с поддельными CLI);
- 🟡 отдельно исследовать интерактивный `AgentRunner`, read-only роли, multi-model review judge (мультимодельное ревью с судьёй) и аналитику сессий;
- 🔴 не использовать Python+Bash-навык как основную зависимость PHP/Symfony-движка;
- 🔴 не вводить ACP (Agent Client Protocol) ради унификации текущих пакетных запусков.

## Ключевые находки

1. **Steer не является одной командой.** Файловый mailbox сериализует писателей через `flock`, выдаёт монотонный `seq`, обеспечивает идемпотентность `client_id`; supervisor и backend adapters (адаптеры харнесов) асинхронно повышают статус по рангу доказательства.
2. **`accepted` не означает применение.** Это только локальная запись. Даже terminal status (терминальный статус) подтверждает жизненный цикл сообщения, но не изменение проекта; эффект проверяется отдельно.
3. **Наблюдаемость двухуровневая.** `PorchEvent` и `raw/normalized/final` относятся к транспорту runner; наш `audit.jsonl` — к бизнес-исполнению цепочки. Один контракт не заменяет другой.
4. **Read-only должен исполняться технически.** `mode_policy.py` закрывает filesystem/shell/web/memory/subagents/steer/interrupt и отклоняет неизвестные режимы, но собственную песочницу `porch` не создаёт.
5. **ACP не устраняет backend-specific сложность.** Для batch (пакетного) режима добавляются клиент, сессия, permission handling (обработка подтверждений), shutdown и адаптеры; ACP mode не является границей безопасности.
6. **Дисциплина делегирования усиливает наши навыки.** First-minute check (проверка первой минуты), контрольные точки 5–15 минут, родительская ответственность и deviation journal дополняют текущие таймауты, self-review и независимое ревью.
7. **История читается без собственного индекса.** `sessions` потоково декодирует нативные хранилища, сохраняет locator до источника и честно сообщает неполноту сканирования.
8. **`remote-agents` — отдельная инфраструктура.** SSH-over-SSM, WireGuard/SMB, Terraform и bounded visual QA (ограниченное визуальное тестирование) не относятся к ядру цепочек.

## Проверка гипотез тимлида

| # | Результат | Вывод |
|---:|---|---|
| 1 | **Подтверждена как архитектурный кандидат** | Live steer полезен, но требует нового async execution lifecycle (асинхронного жизненного цикла), capability и статусов доказательства. |
| 2 | **Подтверждена** | Closed events и артефактные слои усиливают runner observability (наблюдаемость запускателя), не заменяя chain audit. |
| 3 | **Подтверждена с оговоркой** | Fail-closed read-only нужен, однако должен обеспечиваться backend/OS, а не только промптом. |
| 4 | **Подтверждена с ограничением** | Deviation journal полезен для рискованных или более слабых исполнителей; повсеместная обязательность создаст шум. |
| 5 | **Подтверждена** | ACP не песочница и сейчас не оправдан для batch-контракта `AgentRunner`. |
| 6 | **Частично подтверждена** | Fan-out + judge перспективен, но `dedup-findings.py` делает только deterministic union/sort/reindex; семантическую дедупликацию выполняет LLM. |
| 7 | **Подтверждена как R&D** | Session locators и coverage summary полезны для ретроспектив; начинать следует с используемых Pi/Codex данных и privacy contract (контракта приватности). |

Полностью опровергнутых гипотез нет.

## Сравнение с task-orchestrator

`task-orchestrator` сильнее в YAML-цепочках, retry/backoff (повторах с задержкой), Circuit Breaker (предохранителе отказов), fallback, budget, quality gates и DDD-границах. pragmatic-orchestration сильнее в управлении одним живым внешним агентом, backend capability truth (честном описании возможностей), транспортных событиях, независимом review fan-out и навигации по сессиям.

Наиболее переносимые инварианты:

1. приём команды, backend delivery (доставка харнесу) и semantic application (смысловое выполнение) — разные состояния;
2. неизвестный режим/тип события должен завершаться отказом, а не неявным fallback;
3. capability intent (намерение режима) отделяется от backend enforcement (принудительного применения);
4. родитель владеет scope, супервизией и независимой проверкой.

## Артефакты

- `docs/research/framework-comparisons/pragmatic-orchestration-comparison.md` — полный comparison-отчёт, 7 гипотез, матрица паттернов и рекомендации.
- `docs/research/agent-frameworks-summary.md` — строка #38, статус 38/38 и пересчитанные затронутые тренды.
- `docs/agents/reports/system-analyst/2026-09-22_10-49_pragmatic-orchestration-research.md` — данный самодостаточный отчёт.
- `todo/TASK-research-pragmatic-orchestration.todo.md` — отмеченные требования/критерии и фактическая стоимость.
- `todo/EPIC-research-agent-frameworks-comparison.todo.md` — история стадии 1p без процессной отметки чекбокса.

## Источники

- https://github.com/CodeAlive-AI/pragmatic-orchestration/tree/a72695dded7e2f28c185c3a2d770b26eb45528fa
- https://github.com/CodeAlive-AI/pragmatic-orchestration/blob/a72695dded7e2f28c185c3a2d770b26eb45528fa/skills/pragmatic-orchestration/SKILL.md
- https://github.com/CodeAlive-AI/pragmatic-orchestration/blob/a72695dded7e2f28c185c3a2d770b26eb45528fa/skills/pragmatic-orchestration/references/delegate.md
- https://github.com/CodeAlive-AI/pragmatic-orchestration/blob/a72695dded7e2f28c185c3a2d770b26eb45528fa/skills/pragmatic-orchestration/references/runtime-contracts.md
- https://github.com/CodeAlive-AI/pragmatic-orchestration/blob/a72695dded7e2f28c185c3a2d770b26eb45528fa/skills/pragmatic-orchestration/ACP-RESEARCH.md
