---
type: docs
created: 2026-09-22 03:34:07 (1790048047)
due: 
started: 2026-09-22 03:44:29 (1790048669)
completed: 
cancelled: 
value: V3
complexity: C3
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-pragmatic-orchestration
pr: https://github.com/prikotov/task-orchestrator/pull/406
status: in_progress
---

# TASK-research-pragmatic-orchestration: pragmatic-orchestration (CodeAlive-AI): porch CLI + cross-agent skills над внешними CLI-агентами

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Наш движок оркестрации AI-агентов развивается без систематического изучения того, как задачу оркестрации решают сторонние продукты.
- `pragmatic-orchestration` (CodeAlive-AI) — очень молодой (создан 2026-09-17), но концептуально близкий нам проект: набор Agent Skills (агентских навыков) + CLI `porch`, превращающий любой skill-совместимый харнес (Codex, Claude Code, OpenCode и др.) в оркестратор над внешними CLI-агентами.
- Ключевые механики не исследованы с позиций нашей архитектуры: steerable delegation (стерируемая делегация — перенаправление РАБОТАЮЩЕГО агента без остановки), мульти-модельное ревью с LLM-судьёй, закрытая схема событий, матрица режимов доступа fail-closed, поиск по историям сессий харнесов. Неизвестно, что из этого применимо к нашему AgentRunner, ChainExecution и наблюдаемости (`audit.jsonl`).

### Варианты или путь решения (Solution Sketch)
- Research-задача по методологии эпика: comparison-отчёт `docs/research/framework-comparisons/pragmatic-orchestration-comparison.md` с маппингом каждой механики на компоненты task-orchestrator и вердиктом заимствовать / dependency / не подходит.
- Объект исследования — открытый репозиторий (MIT) и документация скиллов; предварительный ресерч уже выполнен тимлидом (клон в `/tmp/research/pragmatic-orchestration`, отчёт в сессии) — исполнитель проверяет выводы по первоисточникам, а не копирует их.

### Ожидаемый результат (Expected Result)
- Comparison-документ `docs/research/framework-comparisons/pragmatic-orchestration-comparison.md`.
- Строка #38 в сводной таблице `docs/research/agent-frameworks-summary.md`, актуализация счётчиков (38/38) и затронутых сравнительных выводов.
- Чёткий вердикт: какие паттерны заимствовать (кандидаты: steer-механика, закрытая схема событий, fail-closed матрица доступа, deviation journal), что изучить дополнительно, что неприменимо.
- Обновлённый эпик: стадия 1p, история изменений.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story / Job Story)
> **Job Story:** Когда мы развиваем систему оркестрации AI-агентов (AgentRunner, ChainExecution, DynamicLoop, GitIdentity) и наши практики делегирования сабагентам, я хочу исследовать pragmatic-orchestration — orchestration-слой поверх внешних CLI-агентов с перенаправлением работающего исполнителя, — чтобы выявить применимые паттерны управления живыми запусками, наблюдаемости и дисциплины делегирования, и не изобретать колёса.

### Цель по SMART (Goal)
Исследовать репозиторий `CodeAlive-AI/pragmatic-orchestration` (README, `skills/pragmatic-orchestration/SKILL.md`, `references/`, `scripts/lib/`, `ACP-RESEARCH.md`, `skills/remote-agents/`) по методологии эпика. Создать comparison-отчёт в `docs/research/framework-comparisons/pragmatic-orchestration-comparison.md` с маппингом ключевых механик на компоненты task-orchestrator, вердиктом по каждой и сравнением с аналогами трека (qm #32, omnigent #33, Herdr #34, bb #36, bx-dev #31). Добавить строку в сводную таблицу `docs/research/agent-frameworks-summary.md`. Срок — в рамках evergreen-режима эпика.

## 2. Контекст и Границы (Context and Scope)

- **Объект:** `CodeAlive-AI/pragmatic-orchestration` (MIT, Python 3.11+ + Bash, ~159 файлов; создан 2026-09-17, снапшот исследования — main на 2026-09-21). Форма: два Agent Skills (`pragmatic-orchestration`, `remote-agents`) в макете SKILL.md + единый CLI `porch` (bash-точка входа + python-библиотека `scripts/lib/`). Режимы porch: `review` (независимые мнения / код-ревью глубинами basic/specialists/super/ultra с LLM-судьёй), `delegate` (задача одному выбранному агенту; steerable по умолчанию, `--detach`, `wait/watch/events/steer/cancel`), `sessions` (поиск по локальным историям сессий харнесов), `quota` (остатки квот Codex/Grok). Поддержка: Codex CLI, Claude Code, OpenCode, Grok Build, Devin CLI; Gemini — только review. Steer реализован файловым mailbox (flock, монотонная нумерация) + supervisor + адаптер по каждому харнесу; режимы `auto/queue/interrupt`; подтверждение доставки — ранговая система доказательств (evidence ranks). Закрытая схема событий `PorchEvent` (неизвестные типы отвергаются); контракт потоков: stderr = живой прогресс, stdout = чистый финальный ответ, артефакты `raw/normalized/final` per-run. Матрица режимов доступа (`mode_policy.py`): review — read-only флаги харнесов, delegate — полный доступ, неизвестные режимы — fail closed. `ACP-RESEARCH.md` — обоснованный отказ от миграции на ACP (Agent Client Protocol): переносит, а не убирает сложность; ACP не создаёт песочницу. Практики делегирования: контракт задачи, проверка в первую минуту, супервизия 5–15 минут, deviation journal (журнал отклонений) для слабых исполнителей, «родитель владеет верификацией». Скилл `remote-agents`: выделенные Linux/Windows-хосты, SSH-over-SSM без публичного ingress, WireGuard/SMB мост, Terraform, headless Windows-десктоп, визуальное QA. Тесты: офлайн-suite с fake-харнессами + e2e steer-тесты, CI на Ubuntu/macOS/Windows.
- **Классификация (предварительная, проверить):** `orchestration skill suite / single-CLI delegation layer over external agents` (набор агентских навыков оркестрации + CLI-слой делегации над внешними агентами) — не самостоятельный агент и не chain engine; ближайшие аналоги трека: qm (#32), omnigent (#33), bx-dev (#31, тоже skill-based), Herdr (#34).
- **Где делаем:** `docs/research/framework-comparisons/pragmatic-orchestration-comparison.md`, `docs/research/agent-frameworks-summary.md`, эпик.
- **Текущее поведение:** наш AgentRunner абстрагирует запуск раннеров (pi, codex) с JSONL-парсингом; ChainExecution исполняет цепочки с повторами и прерыванием при сбоях; наблюдаемость — `audit.jsonl`; делегирование сабагентов — скилл `docs/agents/skills/run-subagent/`; контроля работающего шага «на ходу» (перенаправление без остановки) у нас нет.
- **Границы (Out of Scope):** написание кода/конфигов, изменение архитектуры, бенчмарки, установка и запуск porch, аудит безопасности исходников, проверка маркетинговых заявлений («2–10× больше работы») — фиксируем их в отчёте как неверифицированные.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] Зафиксировать дату исследования и воспроизводимый идентификатор версии: commit snapshot `main` на 2026-09-21 (архив GitHub API), т.к. релизных тегов нет.
- [ ] Проверить по первичным источникам классификацию как orchestration-слоя над внешними агентами (skill suite + CLI), а не агента и не chain engine; отделить функции porch от функций харнесов.
- [ ] Описать 4 режима porch (review/delegate/sessions/quota): назначение, контракт, границы; для review — модель глубин и роль LLM-судьи (`review_super/ultra`, `judge.txt`, `dedup-findings`).
- [ ] Описать steer-механику детально: mailbox (flock, seq), supervisor, адаптеры харнесов, режимы `auto/queue/interrupt`, ранги подтверждения доставки (`supervisor.py`: `_STEER_SUCCESS_RANK`, `_EVIDENCE_RANK`), семантика «accepted ≠ применено».
- [ ] Описать контракты наблюдаемости: закрытая схема `PorchEvent` (`events.py`), stderr/stdout/артефакты, debug tape (`debug_tape.py`); сравнить с нашим `audit.jsonl` (см. `docs/guide/observability.md`).
- [ ] Описать матрицу режимов доступа (`mode_policy.py`, `backend_contract.py`): read-only review vs полный доступ delegate, fail-closed; сопоставить с нашей моделью подтверждений и запретами AGENTS.md.
- [ ] Проанализировать `ACP-RESEARCH.md`: аргументы отказа от ACP, тезис «ACP не песочница»; соотнести с нашим подходом прямого запуска CLI-раннеров в AgentRunner.
- [ ] Выделить практики делегирования (контракт задачи, first-minute check, интервалы супервизии, deviation journal, «родитель владеет верификацией») и оценить их применимость к нашим скиллам (`run-subagent`, `task-via-subagents`) и ролям.
- [ ] Кратко охарактеризовать скилл `remote-agents` (SSH-over-SSM, WireGuard/SMB, Terraform, visual QA) — как отдельный класс инфраструктурного подхода, без глубокого погружения.
- [ ] Сравнить pragmatic-orchestration с `task-orchestrator` по единой матрице трека (модель оркестрации, state mgmt, error handling, extensibility) и с аналогами: qm, omnigent, bx-dev, Herdr, bb.
- [ ] Проверить гипотезы тимлида (секция «Комментарии») — подтверждены/опровергнуты, явно.
- [ ] Создать `docs/research/framework-comparisons/pragmatic-orchestration-comparison.md` по формату существующих comparison-документов, со ссылками на первичные источники, ограничениями и чётким вердиктом: заимствовать паттерны / использовать как зависимость / не подходит.
- [ ] Обновить `docs/research/agent-frameworks-summary.md`: строка #38, счётчики (38/38), затронутые сравнительные выводы и тренды.
- [ ] Обновить эпик: стадия 1p в плане, запись в истории изменений.

### 🟡 Желательно (Should Have)
- [ ] Выделить 3–7 потенциально переносимых паттернов с приоритетом, ожидаемой пользой, ограничениями и необходимой последующей проверкой.
- [ ] Архитектурную диаграмму porch (оркестратор → porch CLI → харнесы; steer-поток) в Mermaid, если улучшает проверяемость.
- [ ] Оценить офлайн-подход к тестированию харнесов (fake-claude/fake-codex/…) как паттерн для наших интеграционных тестов AgentRunner.
- [ ] Зафиксировать пробелы документации как неизвестные данные, не подменяя их предположениями.

### 🟢 Опционально (Could Have)
- [ ] Предложить отдельные backlog-задачи на проверку наиболее ценных паттернов после подтверждения пользователем.

### ⚫ Не будем делать (Won't Have)
- [ ] Не считать рекламные формулировки README («2–10× больше работы» и т.п.) подтверждёнными фактами — только как заявления авторов.
- [ ] Не проводить полный аудит безопасности исходного кода или бенчмарки производительности.
- [ ] Не интегрировать porch и не добавлять его как зависимость; не менять код, конфигурацию и архитектуру `task-orchestrator`.
- [ ] Не исследовать заново внутреннюю архитектуру каждого поддерживаемого харнеса за пределами интеграционного контракта porch.
- [ ] Не создавать задачи реализации без отдельного решения пользователя.

## 4. План реализации (Implementation Plan)
1. [ ] Клонировать/скачать снапшот `CodeAlive-AI/pragmatic-orchestration` (main на момент исследования), зафиксировать дату и идентификатор.
2. [ ] Изучить: `README.md`, `skills/pragmatic-orchestration/SKILL.md` + `references/` (delegate.md, runtime-contracts.md, configuration.md, review.md), `scripts/lib/` (steer/, events.py, mode_policy.py, backend_contract.py, normalize_stream.py), `ACP-RESEARCH.md`, `skills/remote-agents/SKILL.md` (обзорно), тесты `scripts/tests/`.
3. [ ] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `docs/guide/observability.md`, `docs/agents/skills/run-subagent/SKILL.md`.
4. [ ] Сопоставить по единой матрице трека; сравнить с qm (#32), omnigent (#33), bx-dev (#31), Herdr (#34), bb (#36).
5. [ ] Проверить гипотезы тимлида (секция «Комментарии»).
6. [ ] Создать `docs/research/framework-comparisons/pragmatic-orchestration-comparison.md`.
7. [ ] Обновить `docs/research/agent-frameworks-summary.md` (строка #38, счётчики, тренды).
8. [ ] Обновить эпик (стадия 1p, история изменений); выполнить `php vendor/bin/todo-md validate`.

## 5. Критерии приёмки (Definition of Done)
- [ ] Comparison-документ создан по формату трека; все ключевые механики описаны с маппингом на компоненты task-orchestrator и ссылками на первоисточники.
- [ ] Вердикт по каждой механике: заимствовать / dependency / не подходит — с обоснованием.
- [ ] Гипотезы тимлида проверены явно (подтверждены/опровергнуты).
- [ ] Сравнение с аналогами трека (qm, omnigent, bx-dev, Herdr, bb) выполнено.
- [ ] Строка #38 добавлена в `docs/research/agent-frameworks-summary.md`, счётчики и затронутые тренды актуализированы.
- [ ] Эпик обновлён (стадия 1p, история изменений).
- [ ] `php vendor/bin/todo-md validate` — без ошибок.

## 6. Самопроверка (Verification)
```bash
ls docs/research/framework-comparisons/pragmatic-orchestration-comparison.md
grep -n "pragmatic-orchestration" docs/research/agent-frameworks-summary.md
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Проект очень молодой (создан 2026-09-17, активные пуши) — зафиксировать commit snapshot и дату; выводы привязывать к снапшоту, переносить только проверенные паттерны.
- Маркетинговые заявления README («2–10×», «field-tested») неверифицируемы — в отчёте отделять факты (код, документация) от заявлений авторов.
- Bash+Python смесь без типизации — часть поведения видна только из скриптов; не домысливать, фиксировать пробелы как неизвестное.
- Отдельные харнесы (Grok Build, Devin CLI) могут быть недоступны для проверки — опираться на код адаптеров и документацию скилла.

## 8. Источники (Sources)
📚 **Внешние источники** (до 5, кратким списком):

- [github.com/CodeAlive-AI/pragmatic-orchestration](https://github.com/CodeAlive-AI/pragmatic-orchestration) — репозиторий (README, skills/, scripts/)
- [skills/pragmatic-orchestration/SKILL.md](https://github.com/CodeAlive-AI/pragmatic-orchestration/blob/main/skills/pragmatic-orchestration/SKILL.md) — контракт скилла, decision map
- [references/delegate.md](https://github.com/CodeAlive-AI/pragmatic-orchestration/blob/main/skills/pragmatic-orchestration/references/delegate.md) — дисциплина делегирования
- [references/runtime-contracts.md](https://github.com/CodeAlive-AI/pragmatic-orchestration/blob/main/skills/pragmatic-orchestration/references/runtime-contracts.md) — схема событий, политики доступа
- [ACP-RESEARCH.md](https://github.com/CodeAlive-AI/pragmatic-orchestration/blob/main/skills/pragmatic-orchestration/ACP-RESEARCH.md) — исследование ACP

🔗 **Внутренние источники:**

- `docs/guide/architecture.md` — архитектура проекта
- `src/Module/AgentRunner/` — модуль запуска AI-агентов (JSONL-парсинг)
- `src/Module/ChainExecution/` — модуль исполнения цепочек (повторы, прерывание)
- `docs/guide/observability.md` — наш контракт наблюдаемости (`audit.jsonl`)
- `docs/agents/skills/run-subagent/SKILL.md` — наше делегирование сабагентов
- `docs/research/agent-frameworks-summary.md` — сводная таблица трека (аналоги: qm #32, omnigent #33, Herdr #34, bb #36)

## 9. Комментарии (Комментарии)

**Резюме объекта исследования (для контекста исполнителя, НЕ заменяет самостоятельного изучения источников):**

pragmatic-orchestration распространяется как Agent Skills + CLI, а не как библиотека: любой skill-совместимый харнес становится оркестратором и вызывает `porch`. Философия — «прагматичная» оркестрация: сильная модель планирует и супервизирует, дешёвые исполняют; явная дисциплина делегирования вместо декларативных цепочек. Это идейная противоположность нашему подходу (декларативные YAML-цепочки, DDD-движок): у нас цепочка — первичный артефакт, у них — контракт задачи и живая супервизия родителем.

**Гипотезы тимлида к проверке:**

1. **Steer живого шага:** перенаправление работающего агента (mailbox + supervisor + адаптеры, режимы auto/queue/interrupt, evidence-ранги доставки) — у нас запуск шага атомарен: сбой → прерывание/повтор. Вопрос: применим ли steer к нашему AgentRunner/ChainExecution (команда работающему раннеру без убийства процесса), и что даёт их модель подтверждения доставки («accepted ≠ применено»)?
2. **Закрытая схема событий:** фиксированный перечень `PorchEvent`, неизвестные типы отвергаются; stderr=прогресс/stdout=финал; per-run артефакты raw/normalized/final. Наш `audit.jsonl` — сравнить, что стоит ужесточить (закрытый перечень типов, контракт потоков).
3. **Матрица режимов доступа fail-closed:** review = read-only флаги харнесов, delegate = полный доступ, явная таблица per-backend. У нас нет read-only сценария (анализ/ревью без права записи) — нужен ли нам режим «только чтение» для аналитических ролей?
4. **Deviation journal (журнал отклонений):** обязательный артефакт для слабых исполнителей — неожиданные находки, доказательства, действия, нерешённые риски; родитель сверяет журнал перед приёмкой. Кандидат на усиление наших скиллов делегирования (run-subagent, task-via-subagents) и чек-листов приёмки — почти бесплатно.
5. **Выводы по ACP:** отказ от ACP для пакетных задач; «ACP не песочница, режимы сессий — не граница безопасности». Для нас — аргумент не вводить ACP-подобный слой между ChainExecution и раннерами; безопасность — ответственность флагов раннера, не протокола.
6. **Мульти-модельное ревью с судьёй:** fan-out (веерная раздача) ревью нескольким семействам моделей + LLM-судья с дедупликацией находок (`judge.txt`, `dedup-findings.py`). Сравнить с нашим циклом задача → ревью Пуаро → доработка: стоит ли добавить мульти-модельный read-only ревью как тип шага цепочки.
7. **Sessions-поиск по историям:** porch читает локальные хранилища сессий Codex/Claude/OpenCode (карта слоёв, grep, «флаги» проблемных сессий). У нас подобного нет — оценить полезность для ретроспектив и анализа эффективности сабагентов.

**Связь с треком:** ближайшие аналоги — qm (#32, платформа над харнесами), omnigent (#33, мета-харнес), bx-dev (#31, тоже skill-based workflow), Herdr (#34, terminal runtime). Отличие porch: не платформа и не IDE, а тонкий CLI-слой + агентские навыки; проверить, как это сказывается на границах ответственности и переносимости паттернов в PHP/Symfony.

**Предварительный вердикт тимлида (уточнить по итогам):** 🟡 заимствовать паттерны (steer-механика, закрытая схема событий, deviation journal, fail-closed матрица) / 🔴 не dependency (Python+Bash skill, не встраивается в PHP-движок).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-22 03:34:07 (1790048047) | Тимлид (Алекс) | Создание задачи |
| 2026-09-22 | Тимлид (Алекс) | Заполнена постановка по итогам предварительного ресерча (снапшот main 2026-09-21): методология эпика, 7 гипотез, предварительный вердикт. |
