---
type: docs
created: 2026-08-04
value: V2
complexity: C3
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-triz-method
pr: https://github.com/prikotov/task-orchestrator/pull/343
status: done
---

# TASK-research-triz-method: Исследование TRIZ-метода (теория решения изобретательских задач) для реализации в task-orchestrator

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- `task-orchestrator` — движок chain-based оркестрации AI-агентов: YAML-цепочки (`ChainDefinition`), исполнение шагов (`ChainExecution`), динамические циклы (`DynamicLoop`), роли команды, скиллы (`SKILL.md`), условное ветвление. Сейчас в проекте нет встроенного метода для разрешения инженерных противоречий (engineering contradictions) — ситуаций, где улучшение одного требования ухудшает другое, и компромисс «посередине» не подходит.
- **TRIZ** (теория решения изобретательских задач, Theory of Inventive Problem Solving) — структурированный метод: формулировка технического/физического противоречия, идеальный конечный результат, разрешение через разделение (времени/пространства/условий/масштаба), перестановку функций, изобретательские принципы. Репозиторий [`snow-ghost/triz`](https://github.com/snow-ghost/triz) (Python, MIT, v0.1) — готовая реализация метода как portable Agent Skill: компактный workflow (`SKILL.md`), условно-загружаемые `references/` (core-method, inventive-principles, software-patterns, examples, sources), gate (когда TRIZ уместен, а когда — прямой метод), paired eval-harness (baseline vs TRIZ, слепое скоринг-оценивание).
- Нужно понять: что из себя представляет метод TRIZ и его реализация в `snow-ghost/triz`, **и как реализовать TRIZ-метод через возможности нашего оркестратора** (порт skill «как есть» / YAML-chain / chain + DynamicLoop / новый модуль) — с рекомендацией, оценкой усилий и фазированным планом.

### Варианты или путь решения (Solution Sketch)
- Изучить первоисточники: метод TRIZ (классические концепции — противоречия, идеальный конечный результат, разделение, изобретательские принципы, ARIZ-85C, 76 стандартных решений) и реализацию `snow-ghost/triz` (`SKILL.md`, `references/`, eval-harness, `docs/plan.md`, `docs/research.md`, `docs/evaluation.md`).
- Сопоставить workflow TRIZ (gate → evidence → модель системы/ресурсов → идеальный результат → противоречия → генерация разрешений → сходимость концепций → выбор и проверка → цикл обратной связи) с примитивами `task-orchestrator` (`ChainDefinition`, `ChainExecution`, `DynamicLoop`, условное ветвление, роли, скиллы).
- Сравнить варианты реализации (порт skill / YAML-chain / гибрид chain + DynamicLoop / новый модуль) по усилиям, соответствию конвенциям и ограничениям. Зафиксировать вердикт и draft порождаемых feat-задач.
- Оформить research-отчёт в `docs/research/methods/triz-method-research.md`.

### Ожидаемый результат (Expected Result)
- Есть research-отчёт `docs/research/methods/triz-method-research.md` со всеми критериями оценки.
- Вердикт зафиксирован: реализовать (какой подход) / отложить (defer) / пропустить (skip) — с обоснованием и оценкой усилий.
- Определён фазированный план (MVP → full) и список порождаемых feat-задач (draft).

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда в проекте возникает инженерное противоречие (улучшение одного требования ухудшает другое) и нужен структурированный метод вместо компромисса «посередине», я хочу исследовать метод TRIZ и его готовую реализацию `snow-ghost/triz`, чтобы определить, **как реализовать TRIZ-метод через примитивы нашего оркестратора** (`ChainDefinition`, `ChainExecution`, `DynamicLoop`, условное ветвление, роли, скиллы) — и дать обоснованную рекомендацию с планом реализации.

### Goal (Цель по SMART)
* **S (Конкретно):** исследовать метод TRIZ и реализацию `snow-ghost/triz`; сопоставить workflow TRIZ с примитивами `task-orchestrator`; сравнить варианты реализации.
* **M (Измеримо):** research-отчёт в `docs/research/methods/triz-method-research.md`; вердикт (implement/defer/skip) с оценкой усилий; draft feat-задач.
* **A (Достижимо):** метод TRIZ и репозиторий `snow-ghost/triz` задокументированы и доступны; архитектура `task-orchestrator` известна (AGENTS.md, `docs/conventions/`, `docs/guide/architecture.md`).
* **R (Релевантно):** TRIZ как встроенный метод разрешения противоречий расширяет сценарии оркестратора и переиспользует существующие примитивы (роли, chain, dynamic loop, условное ветвление).
* **T (Ограниченно во времени):** одна исследовательская задача; отчёт до старта feat-реализации.

## 2. Context and Scope (Контекст и Границы)
* **Объект исследования:** метод TRIZ (ключевые концепции) и его реализация `snow-ghost/triz` (Agent Skill v0.1: `SKILL.md`, `references/`, eval-harness, `docs/plan.md`).
* **Где делаем:** `docs/research/methods/triz-method-research.md`; agent-report в `docs/agents/reports/system-analyst/`.
* **Текущее поведение:** в `task-orchestrator` нет встроенного метода разрешения противоречий. Есть примитивы: `ChainDefinition` (YAML-цепочки с ролями и runners), `ChainExecution`, `DynamicLoop` (итеративные раунды), условное ветвление (`EPIC-sprint-8-conditional-branching`), роли команды (Архитектор Гэндальф/Локи, Аналитик Шерлок), скиллы (`SKILL.md`), brainstorm.
* **Границы (Out of Scope):** написание кода реализации, построение chain/модуля (отдельные feat-задачи), бенчмарки, полный code review Python-исходников `snow-ghost/triz`, воспроизведение их eval-харнеса.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерий 1 — Суть метода TRIZ:** что это, какую проблему решает (инженерные противоречия), ключевые концепции (техническое/физическое противоречие, идеальный конечный результат, разделение по осям, изобретательские принципы), границы метода (что НЕ является TRIZ-задачей — routine-баги, известные шаблоны, задачи без причинного trade-off). Отличие от ARIZ-85C, 76 стандартных решений, канонической матрицы 39×39.
- [x] **Критерий 2 — Архитектура snow-ghost/triz:** как метод закодирован в Agent Skill — workflow (`SKILL.md`: gate → evidence → модель → идеальный результат → противоречия → разрешения → сходимость → выбор и проверка → цикл обратной связи), условная загрузка `references/`, design rationale (компактный workflow, штраф за «framework theater», стандартный шаблон как валидный результат), eval-подход (paired baseline/TRIZ, слепое оценивание, результаты v0.1).
- [x] **Критерий 3 — Маппинг workflow TRIZ → примитивы task-orchestrator:** по каждому шагу TRIZ указать соответствующий примитив оркестратора:
  - Gate (решение TRIZ vs прямой метод) → условное ветвление / стратегия исполнения;
  - Evidence base (факты/ограничения/допущения/неизвестное) → инспекция кода/AGENTS.md/конвенций (контекст роли);
  - Модель системы и инвентаризация ресурсов → роль-эксперт (Архитектор);
  - Идеальный конечный результат → шаг chain;
  - Формулировка противоречий → шаг chain;
  - Генерация разрешений (разделение, ресурсы, изобретательские принципы) → `DynamicLoop` (итеративные раунды) / роли;
  - Сходимость концепций → роль (Архитектор Локи / Аналитик Шерлок);
  - Выбор, проверка и цикл обратной связи → `DynamicLoop` (один цикл ревизии).
- [x] **Критерий 4 — Варианты реализации в task-orchestrator** (сравнить по усилиям S/M/L, соответствию конвенциям, ограничениям):
  1. Порт skill «как есть» — установить `skills/triz/SKILL.md` в систему скиллов оркестратора (минимальные изменения);
  2. YAML-chain (`ChainDefinition`) — статическая последовательность ролей/шагов по workflow TRIZ;
  3. Гибрид chain + `DynamicLoop` — chain для linear-шагов + dynamic loop для итеративной генерации/ревизии концепций;
  4. Новый модуль (Domain: TRIZ как bounded context) — нативная реализация workflow.
- [x] **Критерий 5 — Вердикт:** implement (какой подход) / defer / skip — с обоснованием.

### 🟡 Should Have (Желательно)
- [x] **Критерий 6 — Рекомендация и фазированный план:** выбранный подход, фазы (MVP → full), оценка усилий, prerequisites (зависимости от условного ветвления / `DynamicLoop` / системы скиллов).
- [x] **Draft порождаемых feat-задач:** список задач на реализацию (например, `TASK-feat-triz-chain`, `TASK-feat-triz-skill` и т. д.) — как draft, без постановки.
- [x] **Критерий 7 — Интеграция с существующими возможностями:** как TRIZ-метод использует роли (Архитектор Гэндальф/Локи, Аналитик Шерлок), условное ветвление (`EPIC-sprint-8-conditional-branching`), brainstorm, AGENTS.md/конвенции как evidence-base, GitIdentity.

### 🟢 Could Have (Опционально)
- [x] Визуализация (Mermaid-диаграммы) маппинга workflow TRIZ → примитивы оркестратора и/или сравнения вариантов реализации.

### ⚫ Won't Have (Не будем делать)
- [ ] Код реализации TRIZ-метода, построение chain/модуля — это отдельные feat-задачи.
- [ ] Бенчмарки и воспроизведение eval-харнеса `snow-ghost/triz`.
- [ ] Полный code review Python-исходников `snow-ghost/triz` — анализ на уровне `SKILL.md`, `references/`, `docs/`.

## 4. Implementation Plan (План реализации)
*План предзаполнен автором (Тимлид Алекс); исполнитель подтверждает понимание (Reverse Briefing).*
1. [x] Создать/переключиться на ветку `task/research-triz-method` (без переключения на `main`).
2. [x] Прочитать reference-задачи: `done/TASK-research-skill-binding-and-context-overload.todo.md`, comparison-документы из `docs/research/framework-comparisons/` (agent-skills) — как прецедент исследования skills.
3. [x] Получить GitHub metadata и commit snapshot `snow-ghost/triz`; зафиксировать версию (v0.1).
4. [x] Изучить метод TRIZ: первоисточники и `snow-ghost/triz` (`README.md`, `SKILL.md`, `references/core-method.md`, `references/inventive-principles.md`, `references/software-patterns.md`, `docs/plan.md`, `docs/research.md`, `docs/evaluation.md`).
5. [x] Оценить критерии 1–2 (метод + архитектура skill).
6. [x] Построить маппинг workflow TRIZ → примитивы `task-orchestrator` (критерий 3): опираться на `src/Module/` (`ChainDefinition`, `ChainExecution`, `DynamicLoop`), `EPIC-sprint-8-conditional-branching`, систему ролей и скиллов.
7. [x] Сравнить варианты реализации (критерий 4), дать вердикт (критерий 5) и рекомендацию с планом (критерий 6).
8. [x] Оформить draft порождаемых feat-задач.
9. [x] Создать отчёт `docs/research/methods/triz-method-research.md`.
10. [x] Сохранить agent-report; запустить `make md-links` и `make validate-todo`.

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт `docs/research/methods/triz-method-research.md` создан, критерии 1–5 оценены.
- [x] Маппинг workflow TRIZ → примитивы `task-orchestrator` построен (по каждому шагу).
- [x] Варианты реализации сравнены; вердикт implement/defer/skip зафиксирован с обоснованием и оценкой усилий.
- [x] Рекомендация с фазированным планом и draft порождаемых feat-задач — если вердикт implement.

## 6. Verification (Самопроверка)
```bash
ls docs/research/methods/triz-method-research.md
grep -n "implement\|defer\|skip" docs/research/methods/triz-method-research.md
make md-links
make validate-todo
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Специфика реализации:** `snow-ghost/triz` — это Agent Skill (compact workflow + references + eval), а не фреймворк и не CLI-агент. Не классифицировать как coding-agent или orchestration-framework — это метод, закодированный в skill.
- **Зависимость от незавершённых эпиков:** оптимальные варианты реализации (гибрид chain + `DynamicLoop`, gate через условное ветвление) могут зависеть от `EPIC-sprint-8-conditional-branching` и зрелости `DynamicLoop` — зафиксировать prerequisites.
- **Риск «натягивания» метода:** TRIZ — метод для подлинных инженерных противоречий; опасно превращать оркестратор в «TRIZ-для-всего». Вердикт давать строго с обоснованием, когда метод уместен.
- **Зрелость источника:** `snow-ghost/triz` v0.1 — evaluated prototype (4★, малая выборка eval); их own evaluation маркирована как exploratory. Фиксировать commit snapshot + дату, не экстраполировать их замеры.

## 8. Sources (Источники)
- [x] [snow-ghost/triz — GitHub](https://github.com/snow-ghost/triz)
- [x] [TRIZ Skill — SKILL.md](https://github.com/snow-ghost/triz/blob/main/skills/triz/SKILL.md)
- [x] [Core Method — references/core-method.md](https://github.com/snow-ghost/triz/blob/main/skills/triz/references/core-method.md)
- [x] [План реализации skill — docs/plan.md](https://github.com/snow-ghost/triz/blob/main/docs/plan.md)
- [x] [Архитектура task-orchestrator — docs/guide/architecture.md](../docs/guide/architecture.md), [Конвенции — docs/conventions/index.md](../docs/conventions/index.md)

## 9. Comments (Комментарии)
TRIZ-задача открывает новый тип исследования — «метод для реализации» (method-for-implementation): в отличие от четырёх существующих ресерч-треков (coding-agents, agent-frameworks, approaches, orchestration-articles), которые сравнивают инструменты/подходы/паттерны, эта задача исследует конкретный метод с целью его последующей нативной реализации в оркестраторе. Эпик не назначен: задача самостоятельна; по результатам (вердикт implement) она может породить `EPIC-feat-triz-method`. Отчёт размещается в новом подкаталоге `docs/research/methods/` (новый профиль ресерча). Предварительный вердикт: реализовать через гибрид YAML-chain + `DynamicLoop` (переиспользование существующих примитивов, минимальные новые сущности) — подтвердить исследованием. Ссылку на репозиторий указал пользователь.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-04 | Тимлид (Алекс) | Создание задачи. Источник: пользователь указал репозиторий `snow-ghost/triz`. Классифицирован как research «метод для реализации» (новый профиль `docs/research/methods/`). Эпик не назначен (самостоятельная задача; может породить `EPIC-feat-triz-method`). Предварительный вердикт: реализовать через гибрид chain + `DynamicLoop` — подтвердить исследованием. |
| 2026-08-12 | Аналитик (Шерлок) | Перевёл задачу в `in_progress`, зафиксировал ветку `task/research-triz-method`, выполнил исследование, отметил выполненные пункты плана и критерии. |
| 2026-08-12 | Аналитик (Шерлок) | По self-review уточнил маппинг условного ветвления на реальные примитивы: текущий `when:` работает на уровне шагов по `passed`/`exitCode`/`status`, а смысловой routing и вложенный `DynamicLoop` требуют отдельной композиции или feat-доработки. |
| 2026-08-12 | Аналитик (Шерлок) | По Change Requests Архитектора Локи доработал research: убрал `tool` как готовый gate для conditional MVP (оставлен `quality_gate`; tool/output conditions вынесены в draft), уточнил активацию skill через role frontmatter или manual explicit invocation, снял предпосылку фазности `DynamicLoop` от `max_rounds`, разделил effort гибрида (ручные запуски S/M, wrapper command M, nested DSL L), добавил альтернативы и обновил вердикт: implement Phase 0–1 now; full hybrid defer до eval/composition decision; усилил критерии для Domain-модуля. |
| 2026-08-12 | Тимлид (Алекс) | Создан PR [#343](https://github.com/prikotov/task-orchestrator/pull/343); задача переведена в `review`. |

| 2026-08-15 | Тимлид (Алекс) | PR #343 принят: задача переведена в `done` перед слиянием. |
