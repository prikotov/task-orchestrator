---
type: docs
created: 2026-09-24 01:55:07 (1790214907)
due: 
started: 
completed: 
cancelled: 
value: V3
complexity: C3
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: EPIC-research-orchestration-articles
author: Тимлид Алекс (pi)
assignee: Аналитик Шерлок (pi)
branch: task/research-omega-frameworks
pr: 
status: todo
---

# TASK-research-omega-reliable-frameworks: Книга Ω «Инженерия надёжных фреймворков на AI-агентах»

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В evergreen-трек статей по оркестрации появился значимый первоисточник — практическая книга Ω «Инженерия надёжных фреймворков на AI-агентах» (синтез корпуса переписки): 12 глав о ролях, спецификации, workflow как state machine, hooks/permissions, верификации, восстановлении и экономике.
- Неизвестно: какие из паттернов книги применимы к task-orchestrator, что мы уже реализуем (валидация), какие gaps подсвечены и что внедрять.

### Варианты или путь решения (Solution Sketch)
- Исследовать книгу по единой методологии эпика из 6 критериев; по каждой главе — маппинг на компоненты task-orchestrator и вердикт apply / study / skip.
- Первоисточник сохранён локально в репозитории (`docs/research/orchestration-articles/sources/omega-reliable-ai-agent-frameworks.html`) — внешнего URL нет.

### Ожидаемый результат (Expected Result)
- Research-отчёт `docs/research/orchestration-articles/omega-reliable-frameworks-research.md` по 6 критериям с по-главным маппингом и вердиктами; строка в сводной таблице `docs/research/orchestration-articles-summary.md`.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)
> **Job Story:** Когда мы развиваем движок оркестрации AI-агентов (AgentRunner, ChainExecution, DynamicLoop, GitIdentity), я хочу исследовать книгу Ω «Инженерия надёжных фреймворков на AI-агентах» — систематическое изложение паттернов надёжности (роли, state machine workflow, hooks, бюджеты, многоуровневая верификация, recovery), чтобы определить, какие из них применять в task-orchestrator и какие gaps у нас есть.

### Цель по SMART (Goal)
Исследовать книгу Ω (12 глав, локальная копия — `docs/research/orchestration-articles/sources/omega-reliable-ai-agent-frameworks.html`) по единой методологии из 6 критериев. Создать отчёт в `docs/research/orchestration-articles/omega-reliable-frameworks-research.md` с по-главным маппингом на нашу архитектуру (AgentRunner, ChainExecution, DynamicLoop, GitIdentity, ChainDefinition) и вердиктом apply / study / skip по каждому паттерну. Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`.

## 2. Контекст и Границы (Context and Scope)
* **Объект:** Книга Ω «Инженерия надёжных фреймворков на AI-агентах» — практическое руководство по построению надёжных систем разработки на AI-агентах (синтез корпуса переписки). Первоисточник: `docs/research/orchestration-articles/sources/omega-reliable-ai-agent-frameworks.html` (локальная копия в репозитории, внешний URL отсутствует).
* **Где делаем:** `docs/research/orchestration-articles/omega-reliable-frameworks-research.md`, `docs/research/orchestration-articles-summary.md`
* **Границы (Out of Scope):** написание кода/конфигов, изменение архитектуры, бенчмарки. Только исследование и рекомендации. Изменения — отдельными задачами вне эпика.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] **Критерии 1–6** — по единой методологии эпика (тезис/проблема, паттерн/концепция, domain, failure handling, маппинг на нашу архитектуру, применяемость)
- [ ] Маппинг паттернов КАЖДОЙ из 12 глав на компоненты task-orchestrator (AgentRunner, ChainExecution, DynamicLoop, GitIdentity, ChainDefinition) — со ссылками
- [ ] Вердикт по каждому ключевому паттерну: apply / study / skip — с обоснованием и оценкой усилий
- [ ] Строка в сводной таблице `docs/research/orchestration-articles-summary.md`

### ⚫ Won't Have (Не будем делать)
- Код/конфиги, изменение архитектуры, бенчмарки

## 4. План реализации (Implementation Plan)
1. [ ] Изучить первоисточник: `docs/research/orchestration-articles/sources/omega-reliable-ai-agent-frameworks.html`
2. [ ] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`, `src/Module/GitIdentity/`, `docs/guide/architecture.md`
3. [ ] Оценить 6 критериев; по каждой главе — маппинг + вердикт apply/study/skip
4. [ ] Создать отчёт в `docs/research/orchestration-articles/omega-reliable-frameworks-research.md`
5. [ ] Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`

## 5. Критерии приёмки (Definition of Done)
- [ ] Отчёт создан, все 6 критериев оценены, все 12 глав покрыты маппингом
- [ ] Вердикт apply/study/skip по каждому ключевому паттерну с обоснованием и усилиями
- [ ] Строка добавлена в сводную таблицу `docs/research/orchestration-articles-summary.md`

## 6. Самопроверка (Verification)
```bash
ls docs/research/orchestration-articles/sources/omega-reliable-ai-agent-frameworks.html
ls docs/research/orchestration-articles/omega-reliable-frameworks-research.md
php vendor/bin/todo-md validate todo/TASK-research-omega-reliable-frameworks.todo.md
```

## 7. Риски и зависимости (Risks и Dependencies)
- Первоисточник — локальная книга без внешнего URL: цитировать по главам/разделам локальной копии в репозитории; ссылочная целостность обеспечивается файлом в `sources/`.
- Книга описывает обобщённый «framework», не task-orchestrator: маппинг с осторожностью, часть паттернов может оказаться вне нашего домена (skip).

## 8. Источники (Sources)
🔗 **Внутренние источники:**

- `docs/research/orchestration-articles/sources/omega-reliable-ai-agent-frameworks.html` — первоисточник (локальная копия в репозитории)
- `docs/research/orchestration-articles/orchestrator-tax-research.md` — прецедент отчёта трека
- `docs/guide/architecture.md` — архитектура проекта
- `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`, `src/Module/GitIdentity/` — модули для маппинга

## 9. Комментарии (Comments)
**Резюме содержания книги (для контекста исполнителя, НЕ заменяет самостоятельное изучение первоисточника):**

- **Гл. 1** — система из вероятностных компонентов: надёжность живёт между агентами; разделять функции, а не профессии; анти-паттерны.
- **Гл. 2** — спецификация как исполняемый источник истины: model-readable spec, цепочка трассировки, gap как состояние, brief как скомпилированный контекст.
- **Гл. 3** — функциональные роли и изоляция контекста: оркестратор, Brief Agent, Explorer, Implementation/Test Worker, Verifier, Adversarial Verifier, permission matrix, запрет рекурсивной власти.
- **Гл. 4** — workflow как state machine: три уровня планирования, lifecycle задачи, dispatch и retry budgets, parking ≠ failure, recovery-first scheduler, baseline и frozen zones.
- **Гл. 5** — hooks, permissions, границы: prompt не является enforcement, hooks на рёбрах, capability design, исполняемая permission matrix, trust boundary.
- **Гл. 6** — независимое тестирование и многоуровневая верификация: зелёный тест ≠ правильный продукт, развязка code/tests, лестница доказательств, rework loop, Definition of Done.
- **Гл. 7** — контекст, память, восстановление: типы памяти, compaction как контролируемое событие, доверенный handoff, authority order, safe replay.
- **Гл. 8** — портфель моделей и экономика: capability matrix, production eval против общего benchmark, budgets как часть routing, метрики здоровья.
- **Гл. 9** — параллелизм, Git, delivery control plane: waves, worktree на task, conflict zones, same-SHA rule, merge owner.
- **Гл. 10** — отказы, gaps, escalation, learning loop: классы ошибок, context gap vs spec gap, матрица реакций, bounded rework, инцидент должен изменить систему.
- **Гл. 11** — минимальная архитектура универсального framework: компоненты ядра, durable records, порядок реализации, критерии готовности v1.
- **Гл. 12** — операционная инструкция и чек-листы: admission checklist, наблюдение за run, post-incident review, уровни зрелости, «короткая конституция».

**Перекличка с нашей системой (предварительно, требует верификации):** роли и цепочки (`config/chains.yaml`, `ChainDefinition`), retry/fallback (`ChainExecution`), фикс-итерации и dynamic loops (`DynamicLoop`), раннеры (`AgentRunner`), идентичность бота (`GitIdentity`) — прямые кандидаты маппинга; state machine lifecycle, permission matrix, hooks на рёбрах, capability matrix моделей и recovery-first scheduler — потенциальные gaps.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-24 01:55:07 (1790214907) | Тимлид Алекс (pi) | Создание задачи (предложена владельцем). Первоисточник — книга Ω — сохранён в репозиторий: `docs/research/orchestration-articles/sources/omega-reliable-ai-agent-frameworks.html` |
