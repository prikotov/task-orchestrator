---
type: docs
created: 2026-09-24 02:05:20 (1790215520)
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
branch: task/research-agentic-patterns
pr: 
status: todo
---

# TASK-research-agentic-design-patterns: Каталог «Agentic Design Patterns» (Кирилл Мокевнин)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В evergreen-треке статей по оркестрации появился значимый публичный каталог паттернов «Agentic Design Patterns» (Кирилл Мокевнин, Хекслет/Праксор) — ~40 паттернов агентного программирования в духе «Банды четырёх»: постановка задачи, подготовка контекста, проверка результата, организация проекта + антипаттерны.
- Неизвестно: какие паттерны каталога применимы к task-orchestrator (цепочки ролей, skills, контекст), что мы уже реализуем, а что — антипаттерны, которых избегать.

### Варианты или путь решения (Solution Sketch)
- Исследовать каталог по единой методологии эпика из 6 критериев; по группам паттернов — маппинг на компоненты task-orchestrator и вердикт apply / study / skip.
- Первоисточник публичный (URL), локальная копия не требуется; при недоступности страниц — добросовестные вторичные источники.

### Ожидаемый результат (Expected Result)
- Research-отчёт `docs/research/orchestration-articles/agentic-design-patterns-research.md` по 6 критериям с маппингом групп паттернов на task-orchestrator и вердиктами; строка в сводной таблице `docs/research/orchestration-articles-summary.md`.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)
> **Job Story:** Когда мы развиваем систему оркестрации AI-агентов (AgentRunner, ChainExecution, DynamicLoop, GitIdentity), я хочу исследовать каталог «Agentic Design Patterns» — систематизированные паттерны взаимодействия с ИИ-агентом (постановка, контекст, верификация, организация) и антипаттерны, чтобы определить, какие паттерны применимы к task-orchestrator и каких ошибок избегать.

### Цель по SMART (Goal)
Исследовать каталог «Agentic Design Patterns» (https://mokevnin.github.io/agentic-coding-design-patterns/ru/, ~40 паттернов, русский язык) по единой методологии из 6 критериев. Создать отчёт в `docs/research/orchestration-articles/agentic-design-patterns-research.md` с маппингом групп паттернов на нашу архитектуру (AgentRunner, ChainExecution, DynamicLoop, GitIdentity, ChainDefinition) и вердиктом apply / study / skip. Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`.

## 2. Контекст и Границы (Context and Scope)
* **Объект:** «Agentic Design Patterns» — каталог паттернов агентного программирования в духе «Банды четырёх» (Кирилл Мокевнин, при поддержке Хекслета и Праксора). Паттерны сгруппированы по областям: постановка задачи, подготовка контекста, проверка результата, организация проекта; отдельный раздел антипаттернов. Первоисточник: https://mokevnin.github.io/agentic-coding-design-patterns/ru/
* **Где делаем:** `docs/research/orchestration-articles/agentic-design-patterns-research.md`, `docs/research/orchestration-articles-summary.md`
* **Границы (Out of Scope):** написание кода/конфигов, изменение архитектуры, бенчмарки. Только исследование и рекомендации. Изменения — отдельными задачами вне эпика.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] **Критерии 1–6** — по единой методологии эпика (тезис/проблема, паттерн/концепция, domain, failure handling, маппинг на нашу архитектуру, применяемость)
- [ ] Маппинг групп паттернов (постановка, контекст, верификация, организация, антипаттерны) на компоненты task-orchestrator (AgentRunner, ChainExecution, DynamicLoop, GitIdentity, ChainDefinition) — со ссылками
- [ ] Вердикт по каждому ключевому паттерну: apply / study / skip — с обоснованием и оценкой усилий
- [ ] Отдельно разобрать раздел антипаттернов: каких из них касается task-orchestrator
- [ ] Строка в сводной таблице `docs/research/orchestration-articles-summary.md`

### ⚫ Won't Have (Не будем делать)
- Код/конфиги, изменение архитектуры, бенчмарки

## 4. План реализации (Implementation Plan)
1. [ ] Изучить первоисточник: https://mokevnin.github.io/agentic-coding-design-patterns/ru/ (оглавление, «Как читать книгу», все группы паттернов и антипаттерны)
2. [ ] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`, `src/Module/GitIdentity/`, `docs/guide/architecture.md`, `docs/agents/skills/`
3. [ ] Оценить 6 критериев; по группам паттернов — маппинг + вердикт apply/study/skip; антипаттерны — отдельным блоком
4. [ ] Проверить гипотезу: каталог ориентирован на взаимодействие «разработчик ↔ один агент»; task-orchestrator — оркестрация цепочек ролей. Какие паттерны переносятся, какие дублируются нашими механизмами (skills, chains, todo-md, become-role)?
5. [ ] Создать отчёт в `docs/research/orchestration-articles/agentic-design-patterns-research.md`
6. [ ] Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`

## 5. Критерии приёмки (Definition of Done)
- [ ] Отчёт создан, все 6 критериев оценены, все группы паттернов и антипаттерны покрыты маппингом
- [ ] Вердикт apply/study/skip по каждому ключевому паттерну с обоснованием и усилиями
- [ ] Гипотеза «одиночный агент vs оркестрация цепочек» проверена
- [ ] Строка добавлена в сводную таблицу `docs/research/orchestration-articles-summary.md`

## 6. Самопроверка (Verification)
```bash
ls docs/research/orchestration-articles/agentic-design-patterns-research.md
php vendor/bin/todo-md validate todo/TASK-research-agentic-design-patterns.todo.md
```

## 7. Риски и зависимости (Risks и Dependencies)
- Каталог живой (GitHub Pages): главы могут добавляться/меняться — фиксировать дату снятия среза в отчёте; при недоступности страниц использовать добросовестные вторичные источники.
- Часть паттернов про инструменты-конкуренты (Claude Code и др.) — отделять переносимую суть от привязки к конкретному харнесу.

## 8. Источники (Sources)
📚 **Внешние источники** (до 5, кратким списком):

- [Agentic Design Patterns — каталог (Кирилл Мокевнин)](https://mokevnin.github.io/agentic-coding-design-patterns/ru/) — первоисточник
- [Оглавление книги](https://mokevnin.github.io/agentic-coding-design-patterns/ru/) — навигация по ~40 паттернам

🔗 **Внутренние источники:**

- `docs/research/orchestration-articles/orchestrator-tax-research.md` — прецедент отчёта трека
- `docs/guide/architecture.md` — архитектура проекта
- `docs/agents/skills/` — скиллы проекта (кандидаты маппинга: skills-as-packaged-workflows, superpowers)

## 9. Комментарии (Comments)
**Резюме содержания каталога (для контекста исполнителя, НЕ заменяет самостоятельное изучение первоисточника):**

Каталог паттернов агентного программирования в духе «Банды четырёх» от Кирилла Мокевнина (Хекслет, Праксор). ~40 глав, сгруппированных по областям работы с ИИ-агентом:

- **Постановка задачи:** design-it-twice, tracer-bullet-tickets, one-feature-at-a-time, let-claude-interview-you, grilling, spec-driven-development, vibe-coding (антипаттерн-контекст).
- **Подготовка контекста:** context-engineering, domain-context-file, claude-md-memory, bloated-claude-md (антипаттерн), agents-md-phrases, handoff, wayfinder.
- **Проверка результата:** give-agent-a-way-to-verify, tdd-with-agent, agent-workflow-evals, writer-reviewer, reflection, hypothesis-driven-debugging.
- **Организация проекта:** isolated-parallel-work, one-shotting, explore-plan-code-commit, feature-list-harness, triage-state-machine, candidates, superpowers, skills-as-packaged-workflows, matt-pocock-skills, reproducible-agent-bootstrap, executable-guardrails.
- **Мета:** how-to-read, glossary, resources.

**Перекличка с нашей системой (предварительно, требует верификации):** skills-as-packaged-workflows ↔ `docs/agents/skills/`; writer-reviewer ↔ цепочки implement→review (`config/chains.yaml`); isolated-parallel-work ↔ параллельное выполнение (бэклог `TASK-feat-parallel-execution`); spec-driven-development ↔ постановки todo-md; executable-guardrails ↔ quality gates; reproducible-agent-bootstrap ↔ module system / installer. Каталог ориентирован на «разработчик ↔ один агент» — проверить переносимость на оркестрацию цепочек ролей.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-24 02:05:20 (1790215520) | Тимлид Алекс (pi) | Создание задачи (предложена владельцем). Первоисточник — публичный каталог https://mokevnin.github.io/agentic-coding-design-patterns/ru/ |
