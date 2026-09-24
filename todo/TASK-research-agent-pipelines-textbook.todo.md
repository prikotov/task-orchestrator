---
type: docs
created: 2026-09-24 02:37:48 (1790217468)
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
branch: task/research-orchestration-batch
pr: https://github.com/prikotov/task-orchestrator/pull/414
status: todo
---

# TASK-research-agent-pipelines-textbook: Учебник «Агентные пайплайны: контроль, верификация и надёжность разработки»

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В evergreen-треке статей по оркестрации появился практический учебник «Агентные пайплайны» (издание 1, 2026-09-12; исходный авторский текст — Антон, комментарии и разбор — ИИ-ассистент Астра): основной цикл агентного пайплайна, матрица хуков, принципы контроля, теория надёжности, пайплайн как конечный автомат.
- Неизвестно: какие паттерны учебника применимы к task-orchestrator, что мы уже реализуем (роли, верификация, hooks), какие gaps подсвечены.

### Варианты или путь решения (Solution Sketch)
- Исследовать учебник по единой методологии эпика из 6 критериев; по каждой из 7 глав — маппинг на компоненты task-orchestrator и вердикт apply / study / skip.
- Первоисточник без внешнего URL — локальная копия сохранена в репозитории (`docs/research/orchestration-articles/sources/agent-pipelines-textbook.pdf`).

### Ожидаемый результат (Expected Result)
- Research-отчёт `docs/research/orchestration-articles/agent-pipelines-textbook-research.md` по 6 критериям с маппингом на task-orchestrator и вердиктами; строка в сводной таблице `docs/research/orchestration-articles-summary.md`.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)
> **Job Story:** Когда мы развиваем систему оркестрации AI-агентов (AgentRunner, ChainExecution, DynamicLoop, GitIdentity), я хочу исследовать учебник «Агентные пайплайны: контроль, верификация и надёжность разработки» — основной цикл (бриф → воркеры → верификаторы → loop), матрицу хуков, двухконтурное управление и пайплайн как конечный автомат, чтобы определить применимые паттерны для task-orchestrator и выявить gaps.

### Цель по SMART (Goal)
Исследовать учебник «Агентные пайплайны» (издание 1, 2026-09-12, 34 стр., 7 глав; локальная копия — `docs/research/orchestration-articles/sources/agent-pipelines-textbook.pdf`) по единой методологии из 6 критериев. Создать отчёт в `docs/research/orchestration-articles/agent-pipelines-textbook-research.md` с по-главным маппингом на нашу архитектуру (AgentRunner, ChainExecution, DynamicLoop, GitIdentity, ChainDefinition) и вердиктом apply / study / skip по каждому паттерну. Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`.

## 2. Контекст и Границы (Context and Scope)
* **Объект:** «Агентные пайплайны. Контроль, верификация и надёжность разработки. Роли / Хуки / Проверки / Конечные автоматы» — практический учебник, издание 1, 12 сентября 2026. Исходный авторский текст — Антон; комментарии, пояснения, учебные примеры и заключительный разбор — ИИ-ассистент Астра. Первоисточник: `docs/research/orchestration-articles/sources/agent-pipelines-textbook.pdf` (локальная копия в репозитории, внешний URL отсутствует).
* **Где делаем:** `docs/research/orchestration-articles/agent-pipelines-textbook-research.md`, `docs/research/orchestration-articles-summary.md`
* **Границы (Out of Scope):** написание кода/конфигов, изменение архитектуры, бенчмарки. Только исследование и рекомендации. Изменения — отдельными задачами вне эпика.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] **Критерии 1–6** — по единой методологии эпика (тезис/проблема, паттерн/концепция, domain, failure handling, маппинг на нашу архитектуру, применяемость)
- [ ] Маппинг паттернов КАЖДОЙ из 7 глав на компоненты task-orchestrator (AgentRunner, ChainExecution, DynamicLoop, GitIdentity, ChainDefinition) — со ссылками
- [ ] Вердикт по каждому ключевому паттерну: apply / study / skip — с обоснованием и оценкой усилий
- [ ] Отдельно проверить соответствие основного цикла учебника (бриф → воркеры → верификаторы → loop) нашим цепочкам `config/chains.yaml` (implement → self-review → review → quality gate)
- [ ] Строка в сводной таблице `docs/research/orchestration-articles-summary.md`

### ⚫ Won't Have (Не будем делать)
- Код/конфиги, изменение архитектуры, бенчмарки

## 4. План реализации (Implementation Plan)
1. [ ] Изучить первоисточник: `docs/research/orchestration-articles/sources/agent-pipelines-textbook.pdf` (все 7 глав, включая «Разбор Астры» с этапами внедрения)
2. [ ] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`, `src/Module/GitIdentity/`, `config/chains.yaml`, `docs/guide/architecture.md`
3. [ ] Оценить 6 критериев; по каждой главе — маппинг + вердикт apply/study/skip
4. [ ] Проверить гипотезы: (а) спек-верификатор + адверсальный верификатор ↔ ревью-роли цепочек; (б) «оркестратор не кодит» ↔ разделение ролей тимлида/разработчика; (в) хуки-бэкстопы ↔ quality gates и hooks проекта; (г) конечный автомат ↔ lifecycle задач todo-md и статусы цепочек; (д) «модель по роли» ↔ профили моделей в `config/chains.yaml`
5. [ ] Создать отчёт в `docs/research/orchestration-articles/agent-pipelines-textbook-research.md`
6. [ ] Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`

## 5. Критерии приёмки (Definition of Done)
- [ ] Отчёт создан, все 6 критериев оценены, все 7 глав покрыты маппингом
- [ ] Вердикт apply/study/skip по каждому ключевому паттерну с обоснованием и усилиями
- [ ] Соответствие цикла учебника нашим цепочкам проверено, гипотезы закрыты
- [ ] Строка добавлена в сводную таблицу `docs/research/orchestration-articles-summary.md`

## 6. Самопроверка (Verification)
```bash
ls docs/research/orchestration-articles/sources/agent-pipelines-textbook.pdf
ls docs/research/orchestration-articles/agent-pipelines-textbook-research.md
php vendor/bin/todo-md validate todo/TASK-research-agent-pipelines-textbook.todo.md
```

## 7. Риски и зависимости (Risks и Dependencies)
- Первоисточник — локальный учебник без внешнего URL: цитировать по главам/страницам локальной копии в `sources/`; ссылочная целостность обеспечивается файлом в репозитории.
- Составной текст (авторский текст + комментарии ИИ-ассистента): в отчёте разделять источник утверждений (Антон vs Астра); авторские оценки рынка воспроизводить с атрибуцией, без принятия на веру.

## 8. Источники (Sources)
🔗 **Внутренние источники:**

- `docs/research/orchestration-articles/sources/agent-pipelines-textbook.pdf` — первоисточник (локальная копия в репозитории)
- `docs/research/orchestration-articles/orchestrator-tax-research.md` — прецедент отчёта трека
- `config/chains.yaml` — цепочки и роли (кандидат прямого маппинга основного цикла)
- `docs/guide/architecture.md` — архитектура проекта
- `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`, `src/Module/GitIdentity/` — модули для маппинга

## 9. Комментарии (Comments)
**Резюме содержания учебника (для контекста исполнителя, НЕ заменяет самостоятельное изучение первоисточника):**

- **Гл. 1** — основной цикл агентного пайплайна: бриф (самодостаточный пакет от агента-брифера) → воркеры (параллельно код-агент и тест-агент) → верификаторы (read-only: спек-верификатор сверяет дифф со спекой 1:1; адверсальный — для денег/платежей/безопасности) → loop (FAIL → новый воркер с замечаниями через файл). Правила: оркестратор не кодит; нет брифа — нет диспетча; нет неверифицированного вывода; модель по роли (сильная — брифам/верификации, дешёвая — коду); гейт на неоднозначность (вопрос человеку до запуска); хуки-бэкстопы дублируют ключевые правила детерминированным кодом.
- **Гл. 2** — матрица хуков: как правила дублируются кодом (детерминированные shell-хуки как подстраховка LLM-инструкций).
- **Гл. 3** — фундамент: принципы контроля работы агентов (инициатор ≠ контролёр; контроль вне изменяемого агентом контура; разведение создания и утверждения).
- **Гл. 4** — перекличка с теорией надёжности.
- **Гл. 5** — пайплайн как конечный автомат: состояния, запрещённые переходы, атомарные изменения состояния, число попыток после перезапуска.
- **Гл. 6** — пайплайн против рынка: объективная сверка (честная база сравнения, версии конфигураций, репрезентативные задачи).
- **Гл. 7** — разбор Астры: от дисциплины агентов к проверяемой системе; полномочия ролей, защищаемые результаты, каталог отказов, реестр критических правил, реестр находок, этапы внедрения.

**Перекличка с нашей системой (предварительно, требует верификации):** цикл бриф→воркеры→верификаторы↔loop ↔ цепочки `implement`/`task-implement` (implement → self-review → review → quality gate, fix_iterations); read-only верификаторы ↔ роли ревьюеров; адверсальный верификатор ↔ потенциальная новая роль; модель по роли ↔ профили моделей в `config/chains.yaml` (включая свежий переход на GPT‑6/GLM с уровнями рассуждения по ролям); хуки-бэкстопы ↔ quality gates + post-step hooks; конечный автомат ↔ статусы задач todo-md.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-24 02:37:48 (1790217468) | Тимлид Алекс (pi) | Создание задачи (предложена владельцем). Первоисточник — учебник «Агентные пайплайны» — сохранён в репозиторий: `docs/research/orchestration-articles/sources/agent-pipelines-textbook.pdf` |
