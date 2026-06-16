# Ретроспектива: post-roadmap cleanup — PHPMD baseline elimination, research-волна, CLI releases

**Дата:** 2026-06-15
**Период:** 2026-05-14 → 2026-06-15 (после ретро `2026-05-13_16-30-readme-rewrite-brainstorm`)
**Что делали:** Межретроспективный период — закрытие хвоста ROADMAP-2026-Q2-Q3 (веха M7) + пост-roadmap чистка техдолга: `EPIC-refactor-phpmd-baseline-elimination` (baseline 12→0 архитектурой), `EPIC-research-agent-frameworks-comparison` (28/28 проектов), фиксация инварианта `fix-iterations` (PR #262/#263), research по Odysseus/Agent Skills/Zeroclaw, CLI-releases v0.1.4–v0.1.23.
**Эпики в `done`:** 13 перенесено 2026-05-17 (broken links fix) + 5 закрыто в период (`Sprint 8/9/10`, `research-agent-frameworks-comparison`, `refactor-phpmd-baseline-elimination`, `research-coding-agents-comparison`).
**PR:** ~57 (#208–#264), без merge-конфликтов и блокирующих доработок после ревью.

> Источники: git log за период, 14 отчётов сабагентов (06-13 и 06-15), front matter задач в `done/`, ROADMAP changelog. Brainstorm-сессий в периоде не проводилось — вся работа через `task-via-subagents`. Отчёты сабагентов формально велись только 13 и 15 июня; рутина (releases/chores/validation-фичи) оценена косвенно по git-истории и содержанию задач.

## Оценка ролей

- **Тимлид Алекс** (`docs/agents/roles/team/team_lead_alex.ru.md`)
  - **Плюсы:**
    - ✅ Рецидив «Тимлид пишет код/документацию сам» (🔴 в ретро 2026-05-13) НЕ повторился весь период — чистое делегирование. Это главное улучшение.
    - ✅ Корректно оркестрировал конвейер 15 июня: Локи (4 mini-ADR) → Левша (3 редизайна) → Пуаро (4 ревью со слепыми зонами) → Локи (консультация) → свои решения по 5 REMARK. Healthy conflict сработал эталонно.
    - ✅ Принял волевое решение по пользователю «устранить ВСЕ записи baseline» — расширил scope эпика (DynamicLoopExecution редизайн возвращён из OUT OF SCOPE) вместо «KEEP в baseline».
    - ✅ Сознательно положил `TASK-fix-fixiterations-config-error-dx` в backlog (осознанный trade-off, не «сделать побыстрее»).
  - **Минусы:**
    - 🟡 Не до конца держал исполнение процесса завершения задач: Шерлок по `TASK-research-agent-skills` оставил `status: in_progress`, `pr:` пустым, PR не создал — Тимлид поймал, но это повтор, который не должен был доходить до review.
  - **Предложения по улучшению:** внедрить чеклист перед commit (см. Предложения).

- **Бэкендер Левша** (`docs/agents/roles/team/backend_developer_levsha.ru.md`) — `EPIC-refactor-phpmd-baseline-elimination`
  - **Плюсы:**
    - ✅ Тесты росли синхронно с кодом: PHPUnit **981 → 1030** за один день 15 июня при 3 архитектурных редизайнах. 38 прямых unit-тестов добавлено для mapper/factory/specification.
    - ✅ Zero behavioral change выдержан byte-to-byte (edge cases типа `runner: null → explicit=true`, `false ≠ null` для `no_context_files`).
    - ✅ Принимал решения Тимлида по REMARK оперативно (1 прогон на правку).
  - **Минусы:**
    - 🔴 **Нарушение `helper.md` при «рефакторинге под PHPMD» (коммит c8f2789, 14 июня):** при устранении `LongMethod parseSteps` логику вынесли в `ChainStepParserHelper` + `ChainFixIterationsValidatorHelper`. Классы формально чистые (`final`/static/no-DI/pure), но с бизнес-логикой: guessed defaults `?? 120`/`?? 'pi'`, правило merge `step ?? chain`, доменные инварианты с исключениями, флаг `runnerExplicit`. По чек-листу `helper.md` — 3 FAIL (п.8 «нет бизнес-логики», п.9 «SRP/один контекст», п.12 «unit-тесты при ветвлениях»). По факту это замаскированная фабрика. Поймали только на ревью Пуаро 15 июня → отдельный конвейер редизайна в Mapper+Factory.
    - 🟡 Тестовое покрытие косвенное (`YamlChainLoaderTest` с I/O) — прямых unit-тестов на ветвления helper изначально не было.
  - **Предложения по улучшению:** при «рефакторинге под PHPMD» обязательно сверять новый паттерн с конвенцией (helper/factory/mapper), а не только с PHPMD-результатом. PHPMD ловит размер, но не SRP.

- **Архитектор Локи** (`docs/agents/roles/team/system_architect_loki.ru.md`) — fix-iterations + ChainStepParser + DynamicLoopExecution редизайны
  - **Плюсы:**
    - ✅ 4 mini-ADR за 15 июня с явными развилками и обоснованиями по конвенциям. Каждый ADR давал explicit «выбор + почему не альтернативы».
    - ✅ Оспаривал сам себя: вариант constructor-only VO отклонил из-за DI-конвенции; гибрид (factory+constructor guard) отклонил как «две точки инварианта». Скептик в действии.
    - ✅ Финальная консультация по слепой зоне №1 deprecated `ChainDefinitionVo` — чёткое решение «принимаем без guard» с обоснованием trade-off.
    - ✅ Граничные условия зафиксировал заранее (PHPMD-бюджет deprecated класса, Deptrac-правила, autowiring).
  - **Минусы:**
    - Без существенных минусов. В одном дизайне (`fixiterations-validation-redesign`, раздел 4.3) допускал fallback «перенести generic guard в private constructor», а реализация выбрала третий путь (никакой проверки) — но это допустимое расхождение, выявленное ревью, не ошибка дизайна.
  - **Предложения по улучшению:** Без предложений.

- **Ревьювер Бэка Пуаро** (`docs/agents/roles/team/code_reviewer_backend_puaro.ru.md`) — 4 ревью 15 июня
  - **Плюсы:**
    - ✅ Поймал нарушение `helper.md` в коммите c8f2789, которое прошло PHPMD-чисто — аудит с явным PASS/FAIL по 12 пунктам чек-листа и точной локализацией строк (118, 153, 176, 182, 185, 190).
    - ✅ Систематически выявлял слепые зоны (№1–4 в fix-iterations review): поведенческое ослабление deprecated API, недостижимость detailed-validator, отсутствие duplicate-membership проверки — с оценкой риска и без преувеличений.
    - ✅ Edge cases на zero behavioral change: `no_context_files: null` (behavioral change), `role: ''` (изменённое сообщение) — отдельно, без блокировки merge.
    - ✅ Независимо перепроверял проверки (PHPUnit/Psalm/Deptrac/PHPMD), а не доверял отчёту исполнителя.
  - **Минусы:**
    - Без существенных минусов. Качество ревью — самое высокое за наблюдаемый период.
  - **Предложения по улучшению:** Без предложений.

- **Аналитик Шерлок** (`docs/agents/roles/team/system_analyst_sherlock.ru.md`) — `TASK-research-agent-skills`
  - **Плюсы:**
    - ✅ Точная классификация: Agent Skills = skill pack, not runtime orchestrator. Корректно отделил паттерны авторинга (SKILL.md anatomy, anti-rationalization, validator) от runtime-функций.
    - ✅ Конкретные рекомендации (7 паттернов) с привязкой к `docs/agents/skills/*`.
  - **Минусы:**
    - ⚠️ **Неполное завершение задачи** (повтор из ретро 2026-04-22, 2026-04-27): DoD отмечены, но `status: in_progress`, `pr:` пуст, PR не создан, файл не в `done/`. Поймано на external review Гермионы.
    - 🟡 В сводке `agent-frameworks-summary.md` счётчик sub-agents изменился 16/26 → 17/28 так, что Agent Skills мог читаться как runtime-support — конфликт с позиционированием «skill pack» (CR-1 от Гермионы).
  - **Предложения по улучшению:** соблюдать чеклист перед commit (см. Предложения).

- **Тех. писатель Гермиона** (`docs/agents/roles/team/technical_writer_hermione.ru.md`) — external review `TASK-research-agent-skills`
  - **Плюсы:**
    - ✅ Не дала Approval до исправления CR-1 (misleading sub-agent count) — удержала качество против «докинуть в numerator».
    - ✅ Поймала broken link (CR-2) в epic artifact, который был сломан ДО текущей ветки — внимание к артефактам, а не только к своему scope.
    - ✅ Re-review после исправлений → Approval. Цикл работает.
  - **Минусы:**
    - Без существенных минусов.
  - **Предложения по улучшению:** Без предложений.

## Что прошло хорошо

- **Рецидив «Тимлид делает сам» побеждён.** Весь период — чистое делегирование через `task-via-subagents`. Это прямое разрешение 🔴 из ретро 2026-05-13.
- **Конвейер 15 июня — эталонный healthy conflict:** Локи (4 дизайна) → Левша (3 редизайна, тесты 981→1030) → Пуаро (4 ревью со слепыми зонами) → Локи (консультация) → Тимлид (5 решений по REMARK). PHPMD baseline **12→0** устранён архитектурой (specification + factory + mapper + owned components), а не suppression.
- **PHPMD baseline elimination — архитектурная победа.** `EPIC-refactor-phpmd-baseline-elimination`: вместо «подавить пороги» — `FixIterationsReferenceIntegritySpecification` (bool-only), `ChainDefinitionFactory` (DI-граница), `ChainStepFactory` + `YamlChainStepMapper` + `YamlRetryPolicyMapper` (разделение technical mapping / domain creation), `DynamicLoopMetrics` + `DynamicLoopJournal` (owned mutable components). 3 алгоритмические копии проверки → 1 specification.
- **Пуаро ловит реальное, не косметику.** Аудит `ChainStepParserHelper` (3 FAIL `helper.md`), слепые зоны №1–4, edge cases zero-change — качество ревью выросло.
- **ROADMAP-2026-Q2-Q3 → ✅ Completed** (веха M7). Все 10 спринтов закрыты: P1 quick wins → P2 ExecutionStrategy → P3 декомпозиция God-объектов → Conditional Branching → Resilience/Observability → Hooks + ChainDefinitionVo split.
- **20 CLI-релизов (v0.1.4–v0.1.23)** без регрессий: standalone CLI, Phar end-to-end smoke, detection vendor context.
- **Проверки стабильны:** PHPUnit ~1030/2888, Psalm 0 errors, Deptrac 0 violations — фикс на каждом PR.

## Проблемы

- 🔴 **Нарушение `helper.md` под видом PHPMD-рефакторинга** (коммит c8f2789, Левша): при устранении `LongMethod` логику вынесли в `Helper`-классы, провалив SRP/«нет бизнес-логики» (3 FAIL по чек-листу). PHPMD ловит размер, но не бизнес-логику в helper. Поймано на ревью → редизайн в Mapper+Factory. **Урок: рефакторинг под метрику ≠ рефакторинг по конвенции.**
- ⚠️ **Повтор «неполные метаданные задач после сабагента»** (ретро 2026-04-22, 2026-04-27): Шерлок оставил `TASK-research-agent-skills` в `in_progress` без `pr` и без переноса в `done`. Предложение «добавить чеклист перед commit в SKILL» звучало в 2026-04-27 — **не внедрено**, повторилось.
- 🟡 **PHPMD flakiness** (PDepend): единичный прогон недосчитывает нарушения (0–3 вместо реальных 6); очистка кэша не помогает. Решения по baseline принимались по 3/3 идентичным прогонам. Предсуществующий риск CI.
- 🟡 **Накопление мелких static-analysis warnings:** `MissingOverrideAttribute` ×3 в новых тестах (тренд 13/61 к adoption `#[Override]`, но не enforced); pre-existing PHPCS в `DynamicLoopExecutionMaxTimeTest`.
- 🟡 **Coverage отчётов сабагентов неравномерный:** формальные отчёты только за 13 и 15 июня. Рутина (releases, chores, validation-фичи, P2-integration) шла без отчётных артефактов — снижает observability периода.
- 🟡 **`TASK-fix-fixiterations-config-error-dx` оставлен в backlog** — осознанно (тянет архитектурный дизайн ради одного сценария, инвариант защищён fail-fast), но фиксирует известную DX-слепую зону (detailed-диагностика `fix_iterations` недостижима в production-пути `validate-config`).

## Предложения для улучшения

- [ ] **Внедрить чеклист «перед commit/finalize» в SKILL `run-subagent`/`task-via-subagents`.** — TODO, файл: `docs/agents/skills/run-subagent/SKILL.md` и/или `docs/agents/skills/task-via-subagents/SKILL.md`. Содержание: `status: done`, `pr: <url>` заполнен, `assignee` заполнен, файл перенесён в `done/`, ссылки в эпике обновлены (новый путь `done/...`), `make validate-todo` зелёный. ⚠️ **Повтор из ретро 2026-04-27** (предложение уже звучало, не внедрено) — приоритизировать.
- [ ] **Добавить в SKILL/роль правило: «рефакторинг под PHPMD требует сверки нового паттерна с конвенцией»** (helper/factory/mapper/service), не только PHPMD-результата. — TODO, требует обсуждения места (AGENTS.md, SKILL `task-via-subagents`, или замечание Пуаро). Предотвратит повтор коммита c8f2789.
- [ ] **Завести задачу на PHPMD flakiness (PDepend)** — отдельная backlog-задача на исследование детерминированности прогона. — TODO, файл: `todo/backlog/`.
- [ ] **Поддерживать отчёты сабагентов для всех нетривиальных задач**, не только архитектурных редизайнов. — TODO, требует обсуждения (может быть overhead для chores).

## Метрики

| Метрика | Значение |
|---|---|
| Длительность периода | ~33 дня (2026-05-14 → 2026-06-15) |
| PR merged | ~57 (#208–#264) |
| Эпиков закрыто | 5 (Sprint 8/9/10, research-frameworks, research-coding, phpmd-baseline) + 13 перенесено 05-17 |
| Релизов CLI | ~20 (v0.1.4 → v0.1.23) |
| Brainstorm-сессий | 0 (весь период через `task-via-subagents`) |
| PHPUnit (конец периода) | 1030 тестов, 2888 assertions |
| PHPMD baseline | 12 → **0** (устранён архитектурой) |
| Psalm errors | 0 |
| Deptrac violations | 0 |
| Рецидив «Тимлид делает сам» | **0** (разрешено 🔴 из ретро 2026-05-13) |
| Доработок после ревью (блокирующих) | 0 |
| Нарушений конвенции, пропущенных в main | 0 (c8f2789 поймано на ревью до merge) |
| Исследованных AI-agent проектов | 28/28 (research-эпик) |

## Важные комментарии

- ✅ **Положительная динамика (3 эпика подряд):** проблема «Тимлид выполняет задачи сам» (🔴 ретро 2026-04-19, повтор в 2026-05-13 в новой форме — симуляция brainstorm) в этом периоде НЕ проявилась ни разу. Делегирование через сабагенты закреплено как норма.
- ⚠️ **Повтор из ретро 2026-04-22 / 2026-04-27:** «неполные метаданные задач после сабагента» — предложение «добавить чеклист перед commit в SKILL» звучало дважды, не внедрено, повторилось на `TASK-research-agent-skills`. **Приоритет №1 для внедрения.**
- ⚠️ **Новый системный паттерн (предупредить):** «рефакторинг под метрику (PHPMD) ≠ рефакторинг по конвенции». PHPMD не ловит SRP/бизнес-логику, только размер. Риск: исполнители выносят логику в формально-чистые классы, нарушая семантику паттерна. Митигация — сверка с конвенцией на self-review и ревью.
- **Healthy conflict Локи↔Пуаро↔Левша** доказал ценность контраста подходов (скептик-архитектор ↔ дотошный ревьювер ↔ аккуратный реализатор) — тот самый сценарий, под который подбиралась команда.
- **ROADMAP-2026-Q2-Q3 закрыт полностью.** Следующий шаг — планирование Q4 roadmap (parallel execution, docker sandboxing, DAG) через brainstorm Архитекторов. В backlog уже лежат `TASK-feat-parallel-execution`, `TASK-feat-docker-sandboxing`, `TASK-feat-dag-orchestration` — требуют декомпозиции и ADR до старта.
