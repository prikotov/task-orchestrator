---
type: docs
created: 2026-06-18
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-swarm-forge
pr: "#272"
status: done
---

# TASK-research-swarm-forge: Исследовать unclebob/swarm-forge для сравнения с task-orchestrator

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте уже развита собственная модель ролевой координации (`docs/agents/roles/team/*`), `AGENTS.md` + `docs/conventions/`, декларативная топология команды (`config/chains.yaml` → роли) и делегирование через сабагентов (`run-subagent`, `task-via-subagents`).
- `unclebob/swarm-forge` (автор — Robert C. Martin, «Uncle Bob», создатель Clean Architecture) решает **ту же задачу** иначе: tmux-based orchestration, git worktrees per role, формализованный handoff-протокол между равноправными ролями, layered constitution с явной override-семантикой.
- Нужно понять, какие паттерны SwarmForge стоит заимствовать в `task-orchestrator`, а какие нам не подходят (стек bash/Clojure/tmux/desktop-терминалы не переносим в PHP).

### Варианты или путь решения (Solution Sketch)
- Изучить первичные источники репозитория `unclebob/swarm-forge`: `README.md` (ветка `main`, documentary), `swarmforge/handoff-protocol.md`, shared-статьи constitution (`engineering/handoffs/workflow.prompt`), операционные скрипты (`swarmforge/scripts/*.bb`), runnable-ветки (`two-pack`/`four-pack`/`six-pack`).
- Сравнить swarm-coordination модель SwarmForge с нашей ролевой системой, конвенциями и chain-оркестрацией.
- Зафиксировать выводы в comparison report и новой строке сводной таблицы research-эпика.

### Ожидаемый результат (Expected Result)
- Есть отдельный отчёт по SwarmForge, новая строка в сводной таблице и понятный verdict (вердикт): не dependency, но источник паттернов swarm-coordination и layered constitution.
- Оркестратор может отправить задачу на review без ручного восстановления контекста.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы развиваем собственную модель ролевой координации, layered configuration (`AGENTS.md` + `conventions`) и декларативную топологию команды, я хочу изучить `unclebob/swarm-forge`, чтобы понять, какие паттерны swarm coordination (координации роя), layered constitution с override-семантикой, формализованного handoff-протокола и config-driven topology можно безопасно заимствовать в `task-orchestrator`.

### Goal (Цель по SMART)
Провести техническое исследование `unclebob/swarm-forge`: архитектура (tmux + git worktrees + Babashka daemon), layered constitution (`constitution.prompt` + shared `articles/` + `local-*.prompt` override-семантика), handoff-протокол (`awake`/`git_handoff`/`note`, daemon `handoffd.bb`), config-driven topology (`swarmforge.conf`: `window <role> <agent> <worktree>`), pack-пресеты (`two-pack`/`four-pack`/`six-pack`), backend selection per role (`claude`/`codex`/`copilot`/`grok`), lifecycle (cleanup window как единственный shutdown-path) и применимость к нашему PHP/Symfony `task-orchestrator`. Оформить отчёт, обновить сводную таблицу research-эпика и reopen'уть эпик новой стадией.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/framework-comparisons/` (новый `swarm-forge-comparison.md`), `docs/research/agent-frameworks-summary.md` (новая строка + счётчик), `todo/done/EPIC-research-agent-frameworks-comparison.md` (reopen: статус `in_progress`, новая стадия `1h`, change history).
*   **Текущее поведение:** В research-эпике уже исследованы 28 AI-agent frameworks/orchestrators (фреймворков/оркестраторов). Ближайшие аналоги по уровню абстракции — AgentCraft (#16, GUI-wrapper поверх внешних агентов + git worktrees) и Factory Missions (#17, orchestrator-worker swarm с communication patterns). SwarmForge концептуально ближе всего к нашей собственной системе ролей/AGENTS.md/conventions (роль = файл-промпт, layered config, pack-пресеты под тип задачи).
*   **Границы (Out of Scope):** Не интегрируем SwarmForge как dependency (стек bash/Clojure/tmux не переносим в PHP), не запускаем swarm локально, не переносим tmux/desktop-terminal логику, не переписываем наши роли/конвенции в рамках этой задачи.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Изучить documentary-ветку `main`: `README.md`, `swarmforge/handoff-protocol.md`, `swarmforge/constitution/articles/{engineering,handoffs,workflow}.prompt`, `swarmforge/scripts/*.bb` (`handoffd.bb`, `swarm_handoff.bb`, `ready_for_next*.bb`, `done_with_current*.bb`, `swarmforge.bb`, `handoff_lib.bb`), `bb.edn`, `test/`.
- [ ] Изучить runnable-ветки `two-pack`, `four-pack`, `six-pack`: `swarmforge/swarmforge.conf`, `swarmforge/roles/*.prompt`, `swarmforge/constitution/articles/local-*.prompt` (override-семантика).
- [ ] Изучить layered constitution override-семантику: shared `articles/` на `main` → установка при запуске → `local-*.prompt` «дополняет» vs одноимённый shared-файл «замещает».
- [ ] Изучить handoff-протокол: типы сообщений (`awake`/`git_handoff`/`note`), daemon `handoffd.bb` (владение tmux-сокетом, валидация, доставка), runtime-директории (`outbox`/`sent`/`failed`/`inbox`), режимы приёма `task`/`batch`.
- [ ] Сравнить с нашей моделью `docs/agents/roles/team/*`, `AGENTS.md` + `docs/conventions/`, `config/chains.yaml` (роли/делегирование), `task-via-subagents`, `epic-via-subagents`.
- [ ] Оформить отчёт `docs/research/framework-comparisons/swarm-forge-comparison.md` по формату существующих comparison-документов (см. референс `sandcastle-comparison.md`).
- [ ] Добавить строку `SwarmForge` (#29) в `docs/research/agent-frameworks-summary.md`, обновить счётчик на `29 / 29`.
- [ ] Reopen'уть эпик: статус `in_progress`, добавить стадию `1h` + задачу в план, запись в change history.
- [ ] Дать чёткий verdict (вердикт): dependency / заимствовать паттерны / не подходит — с обоснованием (ожидаемо: 🟡 заимствовать паттерны, 🔴 не dependency — как у большинства из 28).
### 🟡 Should Have (Желательно)
- [ ] Выделить конкретные паттерны для возможного заимствования: layered constitution с явной override-семантикой, формализованный handoff-протокол между ролями (vs наша subprocess-модель), config-driven topology, pack-пресеты под сложность задачи, cleanup-window как единственный shutdown-path.
- [ ] Сопоставить роль-файлы SwarmForge (`roles/<role>.prompt`) с нашими `docs/agents/roles/team/*.md` и constitution-articles с нашими `AGENTS.md`/`conventions` — формат, override-механика, управление shared-контентом.
- [ ] Оценить ограничения: desktop-first (AppleScript/wt.exe/Ghostty), отсутствие retry/circuit breaker/quality gates/budget control (наши сильные стороны), не CI/server-first.
### 🟢 Could Have (Опционально)
- [ ] Составить Mermaid-диаграмму сопоставления handoff-протокола SwarmForge с нашим делегированием через сабагентов.
- [ ] Предложить backlog tasks (задачи бэклога) на формализацию override-семантики в наших конвенциях (если паттерн окажется ценным).
### ⚫ Won't Have (Не будем делать)
- [ ] Интеграция SwarmForge как runtime dependency.
- [ ] Локальный запуск swarm (tmux/worktrees/desktop-терминалы).
- [ ] Перенос bash/Clojure/Babashka логики в PHP.
- [ ] Изменение существующих production ролей/конвенций/конфигов без отдельной задачи.

## 4. Implementation Plan (План реализации)
1. [ ] Изучить metadata (метаданные) GitHub repo: description, license, default branch, topics, активность/звёзды.
2. [ ] Изучить `README.md` ветки `main` (documentary): intent, branches (two/four/six-pack), prerequisites, what it does, core features, constitution structure, roles, how it works, handoff protocol, `swarmforge.conf`, tmux/terminal behavior.
3. [ ] Изучить `swarmforge/handoff-protocol.md`: полная спецификация протокола доставки сообщений между ролями.
4. [ ] Изучить shared-статьи constitution: `engineering.prompt`, `handoffs.prompt`, `workflow.prompt` — инженерные правила, контракт handoff'ов, правила workflow.
5. [ ] Изучить операционные скрипты `swarmforge/scripts/*.bb`: `handoffd.bb` (daemon), `swarm_handoff.bb`/`ready_for_next*.bb`/`done_with_current*.bb` (helper'ы), `swarmforge.bb` (launcher), `handoff_lib.bb` (общая библиотека).
6. [ ] Изучить runnable-ветки `two-pack`/`four-pack`/`six-pack`: `swarmforge.conf`, `roles/*.prompt`, `local-*.prompt` overrides — состав ролей, потоки (coder→cleaner, specifier→coder→refactorer→architect, specifier→coder→cleaner→architect→hardender→QA).
7. [ ] Сравнить findings (находки) с нашими `docs/agents/roles/team/`, `AGENTS.md`, `docs/conventions/`, `config/chains.yaml`, `task-via-subagents`/`epic-via-subagents`.
8. [ ] Написать `docs/research/framework-comparisons/swarm-forge-comparison.md`.
9. [ ] Обновить `docs/research/agent-frameworks-summary.md`: строка `SwarmForge` (#29), счётчик `29 / 29`, рекомендации/паттерны при необходимости.
10. [ ] Reopen'уть эпик `todo/done/EPIC-research-agent-frameworks-comparison.md`: статус `in_progress`, стадия `1h`, задача в плане, change history.
11. [ ] Провести self-review (саморевью), external review (внешнее ревью), оставить задачу в `review` до merge finalization (финализации перед слиянием).

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт `docs/research/framework-comparisons/swarm-forge-comparison.md` создан и содержит сравнение с `task-orchestrator`.
- [ ] В отчёте есть стандартная comparison table (таблица сравнения): orchestration model, state management, error handling, extensibility, applicability.
- [ ] В отчёте подробно разобраны ключевые механизмы: layered constitution (override-семантика), handoff-протокол, config-driven topology, pack-пресеты, cleanup window.
- [ ] В `docs/research/agent-frameworks-summary.md` добавлена строка `SwarmForge` (#29) и счётчик обновлён до `29 / 29`.
- [ ] Эпик `EPIC-research-agent-frameworks-comparison.md` reopened: статус `in_progress`, стадия `1h`, задача в плане, запись в change history.
- [ ] В отчёте перечислены 3–7 concrete patterns (конкретных паттернов) для возможного заимствования с приоритетами.
- [ ] Указаны sources (источники) и дата анализа.

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/swarm-forge-comparison.md
grep -c "SwarmForge" docs/research/agent-frameworks-summary.md
grep -n "29 / 29" docs/research/agent-frameworks-summary.md
grep -n "1h" todo/done/EPIC-research-agent-frameworks-comparison.md
make validate-todo
```

## 7. Risks and Dependencies (Рisks и зависимости)
- Репозиторий активно развивается (Uncle Bob публично работает над ним) — metadata и структура веток могут быстро меняться; зафиксировать дату анализа и commit/branch snapshot.
- SwarmForge — desktop-first система (AppleScript/wt.exe/Ghostty, tmux), не server/CI-first: сравнение должно быть честным и не притягивать «CI-возможности» туда, где их нет.
- У SwarmForge нет retry/circuit breaker/quality gates/budget control — это наши сильные стороны; вердикт должен это отразить, а не дублировать их паттерны.
- Runnable-конфиги (roles, swarmforge.conf) живут на отдельных ветках, а не на `main` — нужно явно переключаться между ветками при анализе, чтобы не пропустить override-семантику и pack-пресеты.
- Часть возможностей (terminal automation, watchdog) — desktop-инфраструктура, неприменимая к нашему CLI/PHP-стеку; помечать как `🟢 out of scope` для заимствования.

## 8. Sources (Источники)
- https://github.com/unclebob/swarm-forge (репозиторий; ветка `main` — documentary)
- https://github.com/unclebob/swarm-forge/blob/main/README.md
- https://github.com/unclebob/swarm-forge/blob/main/swarmforge/handoff-protocol.md
- https://github.com/unclebob/swarm-forge/tree/main/swarmforge/constitution/articles (engineering/handoffs/workflow.prompt)
- https://github.com/unclebob/swarm-forge/tree/main/swarmforge/scripts (handoffd.bb, swarm_handoff.bb, ready_for_next*.bb, done_with_current*.bb, swarmforge.bb, handoff_lib.bb)
- Runnable-ветки: https://github.com/unclebob/swarm-forge/tree/two-pack , `/tree/four-pack` , `/tree/six-pack` (swarmforge.conf, roles/*.prompt, local-*.prompt)
- Референс формата отчёта: `docs/research/framework-comparisons/sandcastle-comparison.md`

## 9. Comments (Комментарии)
По первичной разведке (выполнена Тимлидом при постановке): SwarmForge — tmux-based swarm orchestration platform от Robert C. Martin. Ключевое: **config-driven topology** (`window <role> <agent> <worktree> [task|batch]`), **layered constitution** с явной override-семантикой (`local-*.prompt` дополняет, одноимённый shared-файл замещает), **handoff-протокол** (daemon `handoffd.bb` владеет tmux-сокетом; сообщения 3 типов `awake`/`git_handoff`/`note`; прямые tmux-сообщения запрещены), **git worktree per role**, **pack-пресеты** (two/four/six-pack), **cleanup window** как единственный shutdown-path.

Концептуально SwarmForge ближе всего к нашей собственной системе ролей/AGENTS.md/conventions — многие решения повторяют наши почти 1:1, но реализованы иначе (peer-to-peer daemon messaging vs наша subprocess-делегирование; desktop tmux vs CLI/PHP). Это делает его особо ценным для сравнения: виден альтернативный путь реализации тех же идей. Предварительный verdict: **🟡 заимствовать отдельные паттерны, 🔴 не dependency** (стек bash/Clojure/tmux не переносим в PHP; нет retry/CB/quality gates/budget).

## 10. Result (Результат выполнения)
- Создан comparison report: `docs/research/framework-comparisons/swarm-forge-comparison.md` (379 строк).
- Обновлена сводная таблица: строка `SwarmForge` (#29), счётчик `29 / 29`, рекомендации по governance/swarm паттернам (structured handoff schema, team topology presets, layered constitution override semantics).
- Сохранён self-contained отчёт аналитика: `docs/agents/reports/system-analyst/2026-06-18_09-20_swarm-forge-research.md`.
- Итоговый verdict: 🟡 заимствовать отдельные swarm-governance паттерны, 🔴 не dependency.
- ⚠️ Мандатное external review через сабагента не выполнено из-за блокировки обёртки `watch-subagent.sh` (см. следствие — `TASK-fix-watch-subagent-timeout`, выполнено в PR #273). Компенсировано личным self-review Тимлида.
- Создана задача-следствие: `todo/done/TASK-fix-watch-subagent-timeout.todo.md` (починка само-терминирования watch-subagent.sh — выполнена).
- Эпик reopened (стадия `1h`), после приёмки возвращается в `done`.
- Создан PR: https://github.com/prikotov/task-orchestrator/pull/272. CI зелёный (`test`, `phar-smoke`). Проверки `make md-links` и `make validate-todo` — зелёные; `phpunit`/`psalm` пропущены обоснованно (docs-only).
- Задача переведена в `done` перед merge (acceptance пользователем подтверждён).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-18 | Тимлид (Алекс) | Создание задачи и постановка исследования. Эпик `EPIC-research-agent-frameworks-comparison` reopened (статус `in_progress`, стадия `1h`). |
| 2026-06-18 | Аналитик (Шерлок) | Выполнено исследование SwarmForge, создан comparison report, обновлена сводная таблица, сохранён agent-report. |
| 2026-06-18 | Тимлид (Алекс) | Создан PR #272, задача переведена в `review`; заведена задача-следствие `TASK-fix-watch-subagent-timeout`. |
| 2026-06-18 | Тимлид Алекс (pi) | Задача-следствие `TASK-fix-watch-subagent-timeout` выполнена в PR #273: фоновый watcher по wall-clock для enforcement таймаутов. Ссылка обновлена на `done/`. |
| 2026-06-18 | Тимлид (Алекс) | После acceptance (merge подтверждён пользователем) задача переведена в `done` перед merge и перенесена в `todo/done/`. |
