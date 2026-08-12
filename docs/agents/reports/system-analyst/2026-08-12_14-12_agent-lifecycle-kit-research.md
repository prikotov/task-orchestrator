# Отчёт Аналитика: Agent Lifecycle Kit research

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-08-12
**Объект:** `avksp/agent-lifecycle-kit` (`v1.62.0`, commit `88bc33f72070835a88422f499b10158bea099ab1`; `main` HEAD `87201e09e356700e8fc5c39b5bc2fbbac591b399`)
**Задача:** `todo/TASK-research-agent-lifecycle-kit.todo.md`

---

## Краткое резюме

Исследование выполнено docs-only (только документация). Созданы/обновлены:

- `todo/TASK-research-agent-lifecycle-kit.todo.md`
- `docs/research/framework-comparisons/agent-lifecycle-kit-comparison.md`
- `docs/research/agent-frameworks-summary.md`
- `todo/done/EPIC-research-agent-frameworks-comparison.md`
- `docs/agents/reports/system-analyst/2026-08-12_14-12_agent-lifecycle-kit-research.md`

Итоговая классификация: **Agent Lifecycle Kit** — `provider-neutral lifecycle/evidence controller` (нейтральный контроллер жизненного цикла и доказательств) вокруг внешних `coding agents` (агентов программирования). Это **не coding agent**, **не LLM runtime** (среда выполнения модели) и **не chain engine** (движок цепочек) уровня `task-orchestrator`.

Итоговый verdict (вердикт): 🟡 **заимствовать паттерны**, 🔴 **не использовать как core dependency** (основную зависимость).

## Snapshot исследования

- GitHub repository: `avksp/agent-lifecycle-kit`
- Latest release: `v1.62.0`, published 2026-08-12T06:15:53Z
- Release commit: `88bc33f72070835a88422f499b10158bea099ab1`
- Current `main` HEAD на дату анализа: `87201e09e356700e8fc5c39b5bc2fbbac591b399`, 2026-08-12T06:35:14Z
- Version in `pyproject.toml`: `1.62.0`
- License: Apache-2.0
- Language: Python 3.11–3.14
- GitHub metadata на дату анализа: 15★, 1 fork, 0 open issues

## Главные выводы

1. Предварительная классификация подтверждена: ALK — внешний lifecycle/proof layer (слой жизненного цикла и доказательств), а не агент программирования.
2. Предварительный verdict (вердикт) подтверждён: брать patterns (паттерны), не dependency (зависимость). Причина — другой стек, другой источник истины и отсутствие runner-level (уровня запускателя) `retry/backoff + circuit breaker + fix_iterations + JSONL` parity (паритета повторов, задержек, предохранителя отказов, циклов исправления и JSONL-аудита).
3. Наиболее ценные паттерны для `task-orchestrator`:
   - frozen plan authority + lock digest;
   - receipt-bound controller gates;
   - implementation audit/final proof vocabulary;
   - adapter support-level taxonomy;
   - provider-neutral model classes with host-local concrete bindings;
   - host env allowlist + redacted receipts;
   - proof integrity hash chain для high-risk bugfix/release tasks.
4. ALK усиливает сводку отдельным уровнем: `Lifecycle / Evidence controller`; счётчики summary обновлены до 33 завершённых / 35 запланированных исследований, при этом `qm` #32 и `omnigent` #33 не считаются завершёнными.

## Self-review

Проверено:

- [x] Задача создана как research-задача; `type: docs` выбран по `docs/todo-md/reference/TYPES.md` и `todo-md` validator (валидатору задач), а `research_kind: research` фиксирует исследовательскую семантику из RACI. Конфликт RACI (`research`) и TYPES.md (`docs`) явно описан в рисках задачи, а не скрыт как guessed workaround (угаданный обходной путь).
- [x] Новая задача не переведена в `done` и не перенесена в `todo/done/`.
- [x] Отчёт содержит дату, release/commit snapshot (снимок выпуска/коммита), ссылки на primary sources (первичные источники), границы анализа и ограничения.
- [x] Summary (сводка) обновляет только затронутые счётчики и выводы; `qm`/`omnigent` не включены в completed-счётчик (счётчик завершённых).
- [x] Эпик получил stage `1n`, ссылку на активный todo-файл, риски и историю изменений без изменения старой истории.
- [x] Код приложения, конфигурации и скрипты не изменялись.

## Источники

- https://github.com/avksp/agent-lifecycle-kit
- https://github.com/avksp/agent-lifecycle-kit/releases/tag/v1.62.0
- https://github.com/avksp/agent-lifecycle-kit/blob/v1.62.0/README.md
- https://github.com/avksp/agent-lifecycle-kit/blob/v1.62.0/docs/architecture/system-architecture.md
- https://github.com/avksp/agent-lifecycle-kit/blob/v1.62.0/docs/reference/public-contracts.md
