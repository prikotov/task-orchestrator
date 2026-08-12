---
type: docs
research_kind: research
created: 2026-08-12
value: V3
complexity: C3
priority: P2
depends_on:
epic: EPIC-research-agent-frameworks-comparison
author: Аналитик (Шерлок)
assignee: Аналитик (Шерлок)
branch: task/research-agent-lifecycle-kit
pr:
status: in_progress
---

# TASK-research-agent-lifecycle-kit: Исследовать avksp/agent-lifecycle-kit как lifecycle controller (контроллер жизненного цикла) для coding-agent задач

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

- В `task-orchestrator` уже есть chain-based orchestration (цепочечная оркестрация): YAML-цепочки, роли, `run-subagent`, retry с backoff (повтор с задержкой), circuit breaker (автоматический выключатель), quality gates (ворота качества), бюджет, `fix_iterations` и JSONL audit trail (аудит JSONL).
- `Agent Lifecycle Kit` (`avksp/agent-lifecycle-kit`, ALK) заявляет другой слой: provider-neutral lifecycle controller (нейтральный к поставщику контроллер жизненного цикла), который связывает draft intake (черновой вход), reviewed plan (проверенный план), frozen plan (замороженный план), bounded execution (ограниченное выполнение), audit (аудит) и final proof (финальное доказательство) вокруг внешних coding agents (агентов программирования).
- Нужно проверить, является ли ALK самостоятельным coding agent, runtime dependency (зависимостью среды выполнения) или источником переносимых паттернов для `task-orchestrator`.

### Варианты или путь решения (Solution Sketch)

- Зафиксировать воспроизводимый snapshot (снимок): latest release (последний выпуск) `v1.62.0`, tag commit `88bc33f72070835a88422f499b10158bea099ab1`, а также `main` HEAD `87201e09e356700e8fc5c39b5bc2fbbac591b399` на дату 2026-08-12.
- Изучить первичные источники: `README.md`, `CHANGELOG.md`, release metadata (метаданные выпуска), architecture docs, public contracts (публичные контракты), adapter support matrix (матрицу поддержки адаптеров), CLI/workflow docs, schema-backed contracts (контракты со схемами), исходный код и тесты ключевых модулей.
- Сравнить ALK с `task-orchestrator` и ближайшими завершёнными соседями summary (сводки): Archon, Paperclip AI, Orca ADE, bx-dev, Herdr; `qm` и `omnigent` упомянуть только как незавершённые постановки.
- Оформить comparison report (сравнительный отчёт), добавить строку #35 в summary, обновить эпик стадией `1n` без перевода задачи в `done`.

### Ожидаемый результат (Expected Result)

- Создан отчёт `../docs/research/framework-comparisons/agent-lifecycle-kit-comparison.md`.
- В `../docs/research/agent-frameworks-summary.md` добавлена строка `Agent Lifecycle Kit` (#35), счётчики отражают `33 завершённых / 35 запланированных`, `qm` #32 и `omnigent` #33 не считаются завершёнными.
- В `done/EPIC-research-agent-frameworks-comparison.md` добавлена стадия `1n` и история изменений.
- Итоговый verdict (вердикт): подтвердить или опровергнуть «заимствовать паттерны, не core dependency».

## 1. Concept and Goal (Концепция и цель)

### Story (Job Story)

Когда мы развиваем `task-orchestrator` как CLI/library (библиотеку командной строки) для воспроизводимых цепочек AI-агентов, я хочу исследовать ALK как внешний provider-neutral lifecycle controller (нейтральный контроллер жизненного цикла), чтобы отделить переносимые паттерны freeze/proof/audit/adapters (заморозки, доказательств, аудита и адаптеров) от непереносимых Python/process-layer (процессного слоя) решений.

### Goal (Цель по SMART)

До завершения ветки `task/research-agent-lifecycle-kit` подготовить воспроизводимое исследование ALK по snapshot 2026-08-12, классифицировать продукт, описать lifecycle/state/orchestration/persistence/failure/security/extensibility, сравнить с `task-orchestrator` и ближайшими соседями, обновить summary и эпик без изменения кода приложения.

## 2. Context and Scope (Контекст и границы)

* **Объект:** `avksp/agent-lifecycle-kit`, Python package (пакет Python) `agent-lifecycle-kit`, Apache-2.0, release `v1.62.0`.
* **Где делаем:** `../docs/research/framework-comparisons/agent-lifecycle-kit-comparison.md`, `../docs/research/agent-frameworks-summary.md`, `done/EPIC-research-agent-frameworks-comparison.md`, `../docs/agents/reports/system-analyst/`.
* **Текущее поведение:** ALK отсутствует в сводке. Номера #32 (`qm`) и #33 (`omnigent`) зарезервированы активными задачами, #34 Herdr завершён.
* **Границы:** только research/docs (исследование и документация). Не интегрировать ALK, не менять PHP-код, конфигурацию цепочек или скрипты, не запускать внешние coding agents и не использовать секреты.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [x] Прочитать `AGENTS.md`, `todo/AGENTS.md`, `docs/conventions/index.md`, RACI (матрицу ответственности) и релевантные research-задачи/отчёты/summary.
- [x] Зафиксировать metadata (метаданные): GitHub repository (репозиторий GitHub), latest release/tag/version (последний выпуск/тег/версия), default branch (основная ветка), commit snapshot (снимок коммита), license (лицензия), language (язык), stars/forks/issues (звёзды/ответвления/вопросы), created/pushed (создан/обновлён).
- [x] Проверить README, release notes (заметки выпуска), architecture docs (архитектурную документацию), public contracts/schemas (публичные контракты/схемы), adapter docs (документацию адаптеров) и выборочно исходный код/тесты.
- [x] Описать реальную границу продукта и подтвердить/опровергнуть классификацию: provider-neutral coding-agent lifecycle controller (нейтральный контроллер жизненного цикла для агентов программирования), внешний слой над CLI-агентами; не coding agent (не агент программирования).
- [x] Разобрать lifecycle and state model (модель жизненного цикла и состояния), orchestration (оркестрацию), persistence/state (хранение/состояние), failure handling/recovery (обработку сбоев/восстановление), frozen plans (замороженные планы), evidence/receipts/proof (доказательства/квитанции/финальное доказательство), audits/gates (аудиты/ворота), adapter model/support levels (модель адаптеров/уровни поддержки), context/resource/model routing (контекст/ресурсы/маршрутизацию моделей), security/containment (безопасность/изоляцию), extensibility (расширяемость), license/maturity (лицензию/зрелость).
- [x] Сравнить ALK с `task-orchestrator`, Archon, Paperclip AI, Orca ADE, bx-dev, Herdr; `qm`/`omnigent` не выдавать за завершённые исследования.
- [x] Дать concrete adopt/adapt/reject recommendations (конкретные рекомендации принять/адаптировать/отклонить) с effort (оценкой усилий) и рисками.
- [x] Создать comparison report и обновить summary.
- [x] Обновить эпик стадией `1n`, счётчиками, рисками и историей изменений.
- [x] Проверить Markdown-ссылки, согласованность чисел/названий поиском и выполнить self-review (самопроверку).

### 🟡 Should Have (Желательно)

- [x] Отдельно различить documented claims (заявления документации) и code/test-confirmed facts (факты, подтверждённые кодом/тестами).
- [x] Добавить Mermaid-диаграмму границ ALK и `task-orchestrator`.
- [x] Выделить 3–7 паттернов для возможного заимствования.

### 🟢 Could Have (Опционально)

- [ ] Создать отдельные backlog tasks на внедрение паттернов — только после решения тимлида/пользователя.

### ⚫ Won't Have (Не будем делать)

- [x] Не добавлять ALK как dependency (зависимость).
- [x] Не запускать внешних provider CLIs (командные агенты поставщиков).
- [x] Не менять код приложения, конфигурацию, тесты или скрипты.
- [x] Не помечать задачу `done` и не переносить её в `todo/done/` до review (ревью) и решения тимлида.

## 4. Implementation Plan (План реализации)

1. [x] Проверить текущую ветку `task/research-agent-lifecycle-kit` и чистоту статуса перед правками.
2. [x] Прочитать обязательные проектные правила, RACI, шаблон задачи и соседние research-артефакты.
3. [x] Получить первичные источники ALK через GitHub API/tarball, зафиксировать release и commit snapshots.
4. [x] Изучить README, changelog (журнал изменений), architecture (архитектуру), workflow (рабочий процесс), public contracts (публичные контракты), adapter support (поддержку адаптеров), security docs (документацию безопасности).
5. [x] Выборочно проверить исходный код и тесты: workflow state (состояние рабочего процесса), managed runner (управляемый запускатель), task transitions (переходы задач), gates (ворота), runner (запускатель), launcher (модуль запуска), redaction (редактирование секретов), model routing (маршрутизация моделей), proof integrity (целостность доказательств), managed-run tests (тесты управляемого запуска).
6. [x] Сравнить ALK с `task-orchestrator` и соседями summary.
7. [x] Создать `todo/TASK-research-agent-lifecycle-kit.todo.md`.
8. [x] Создать `docs/research/framework-comparisons/agent-lifecycle-kit-comparison.md`.
9. [x] Обновить `docs/research/agent-frameworks-summary.md`: строка #35, счётчики и только изменившиеся выводы.
10. [x] Обновить `todo/done/EPIC-research-agent-frameworks-comparison.md`: стадия `1n`, счётчики, риски, история.
11. [x] Сохранить отчёт Аналитика в `docs/agents/reports/system-analyst/`.
12. [x] Запустить целевые проверки документации и исправить найденное.
13. [x] Выполнить self-review и зафиксировать результат в отчёте.

## 5. Definition of Done (Критерии приёмки)

- [x] Задача создана по Definition of Ready (определению готовности) с заполненным планом.
- [x] Отчёт создан и содержит воспроизводимый snapshot на дату 2026-08-12.
- [x] В отчёте есть сравнение с `task-orchestrator` и соседями summary.
- [x] В summary добавлена строка ALK #35, счётчики не включают незавершённые `qm`/`omnigent` в completed (завершённые).
- [x] Эпик обновлён стадией `1n`, но новая задача не переведена в `done`.
- [x] Внутренние Markdown-ссылки проверены.

## 6. Verification (Самопроверка)

```bash
test -f docs/research/framework-comparisons/agent-lifecycle-kit-comparison.md
grep -n "Agent Lifecycle Kit" docs/research/agent-frameworks-summary.md
grep -n "33 завершённых / 35 запланированных" docs/research/agent-frameworks-summary.md
grep -n "1n" todo/done/EPIC-research-agent-frameworks-comparison.md
make md-links
make validate-todo
make validate-language
```

PHPUnit и Psalm можно не запускать: изменения docs-only (только документация и todo-файл), код, конфигурация и скрипты не затрагиваются.

## 7. Risks and Dependencies (Риски и зависимости)

- ALK быстро развивается: latest release и main отличаются на дату анализа. Выводы привязаны к `v1.62.0` и отдельно отмечен `main` HEAD.
- Документация ALK обширна; глубокий аудит всех схем и тестов не входит в scope. Выводы о коде основаны на выборочной проверке ключевых модулей.
- Некоторые заявления ALK — support-level (уровень поддержки) с redacted evidence summaries (сводками скрытых доказательств). Без локального запуска внешних хостов это считается documented evidence (доказательством из документации), а не independent live-test (независимой живой проверкой).
- ALK использует терминологию `ports and adapters` в своей документации; это не переносится в код `task-orchestrator`, где такая терминология запрещена конвенциями.
- Нормативная несогласованность: RACI содержит отдельный тип `research`, но `docs/todo-md/reference/TYPES.md`, `todo/AGENTS.md` и текущий `todo-md` validator (валидатор задач) разрешают для task-файлов только типы Conventional Commits (стандарта сообщений коммитов) и отклоняют `type: research`. Для этой docs-only research-задачи выбран нормативно валидируемый `type: docs`; исследовательская семантика явно зафиксирована в `research_kind: research`, названии, RACI-контексте и содержании. Это не скрытый fallback (запасной путь), а зафиксированный регламентный конфликт, который стоит синхронизировать отдельной задачей после решения тимлида.

## 8. Sources (Источники)

- [x] [avksp/agent-lifecycle-kit — GitHub](https://github.com/avksp/agent-lifecycle-kit)
- [x] [Agent Lifecycle Kit v1.62.0 — release](https://github.com/avksp/agent-lifecycle-kit/releases/tag/v1.62.0)
- [x] [README.md v1.62.0](https://github.com/avksp/agent-lifecycle-kit/blob/v1.62.0/README.md)
- [x] [System architecture v1.62.0](https://github.com/avksp/agent-lifecycle-kit/blob/v1.62.0/docs/architecture/system-architecture.md)
- [x] [Public contracts v1.62.0](https://github.com/avksp/agent-lifecycle-kit/blob/v1.62.0/docs/reference/public-contracts.md)
- [x] [Workflow customization v1.62.0](https://github.com/avksp/agent-lifecycle-kit/blob/v1.62.0/docs/reference/workflow-customization.md)
- [x] [Adapter support matrix v1.62.0](https://github.com/avksp/agent-lifecycle-kit/blob/v1.62.0/docs/adapters/support-matrix.md)

## 9. Comments (Комментарии)

Предварительная классификация подтверждена с уточнением: ALK — не coding agent (не агент программирования) и не chain engine (движок цепочек), а provider-neutral lifecycle/evidence controller (нейтральный контроллер жизненного цикла и доказательств) вокруг внешних host CLIs (командных хостов). Verdict (вердикт): 🟡 заимствовать паттерны freeze/proof/audit/adapters (заморозки, доказательств, аудита и адаптеров); 🔴 не core dependency (не основная зависимость).

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-12 | Аналитик (Шерлок) | Создание задачи и выполнение исследования `avksp/agent-lifecycle-kit` для stage `1n` research-эпика. |
