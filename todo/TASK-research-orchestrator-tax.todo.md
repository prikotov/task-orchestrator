---
# Metadata (Метаданные)
type: docs
created: 2026-07-29
value: V3
complexity: C3
priority: P2
depends_on:
epic: EPIC-research-orchestration-articles
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: —
pr: —
status: pending
---

# TASK-research-orchestrator-tax: Статья Martin Fowler «Orchestrator Tax» (orchestration overhead)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте есть система оркестрации AI-агентов (chain-based execution, retry, circuit breaker, dynamic loops), но отсутствует систематическое исследование классических паттернов оркестрации — четвёртый ресерч-профиль.
- Первый кандидат — Martin Fowler «Orchestrator Tax» (2012): тезис о том, что централизованная оркестрация создаёт overhead («налог») и есть альтернативы (choreography, sagas).
- Неизвестно: применим ли этот паттерн к нашей системе оркестрации AI-агентов и какие конкретные anti-patternы мы должны избегать.

### Варианты или путь решения (Solution Sketch)
- Создать четвёртый ресерч-эпик `EPIC-research-orchestration-articles` с методологией из 6 критериев (тезис, паттерн, domain, failure handling, маппинг, применяемость).
- Делегировать Аналитику (Шерлок) исследование статьи Fowler: research-док с маппингом каждого паттерна на компоненты task-orchestrator и вердиктом apply/study/skip.
- Проверить гипотезу: наша система — это orchestrator или choreography? Какой «налог» мы платим?

### Ожидаемый результат (Expected Result)
- Research-документ `docs/research/orchestration-articles/orchestrator-tax-research.md` по 6 критериям.
- Сводная таблица `docs/research/orchestration-articles-summary.md` (строка #1 нового трека).
- Аналитический отчёт в `docs/agents/reports/system-analyst/`.
- Чёткий вердикт: что мы уже делаем правильно (валидация), что нужно изучить детально (gaps), что не применимо к AI-агентам.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мы проектируем и улучшаем нашу систему оркестрации AI-агентов (AgentRunner, ChainExecution, DynamicLoop, GitIdentity), я хочу исследовать классическую статью Martin Fowler «Orchestrator Tax» — overhead от централизованной оркестрации, альтернативы (choreography, sagas), failure handling — чтобы определить, какие anti-patternы мы должны избегать и какие паттерны применимы к AI-агентам.

### Goal (Цель по SMART)
Исследовать статью Martin Fowler «Orchestrator Tax» по единой методологии из 6 критериев. Создать отчёт в `docs/research/orchestration-articles/orchestrator-tax-research.md` с маппингом на нашу архитектуру (AgentRunner, ChainExecution, DynamicLoop, GitIdentity) и вердиктом apply/study/skip по каждому паттерну. Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md` (создать, если отсутствует).

## 2. Context and Scope (Контекст и Границы)
* **Объект:** Martin Fowler (2012), статья «Orchestrator Tax» (https://martinfowler.com/articles/orchestrator-tax.html). Ядро паттерна: централизованная оркестрация создаёт overhead («налог») — orchestrator знает слишком много о деталях каждого шага, становится хрупким (brittle) и трудно поддерживаемым. Альтернативы: choreography (децентрализованная координация через события), sagas (длинные транзакции с компенсирующими действиями).
* **Где делаем:** `docs/research/orchestration-articles/orchestrator-tax-research.md`, `docs/research/orchestration-articles-summary.md`
* **Текущее поведение:** Наша система (AgentRunner, ChainExecution, DynamicLoop, GitIdentity) — централизованный orchestrator цепочек AI-агентов. Задача — проверить, платим ли мы «orchestrator tax» и есть ли альтернативы.
* **Границы (Out of Scope):** написание кода/конфигов, изменение архитектуры, бенчмарки. Только исследование и рекомендации. Изменения архитектуры — отдельными задачами.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] **Критерий 1–6** — по единой методологии эпика (тезис/проблема, паттерн/концепция, domain, failure handling, маппинг на нашу архитектуру, применяемость)
- [ ] Маппинг КАЖДОГО паттерна/anti-pattern'а из статьи на компоненты task-orchestrator (AgentRunner, ChainExecution, DynamicLoop, GitIdentity, ChainDefinition) — со ссылками
- [ ] Вердикт по каждому паттерну: apply / study / skip — с обоснованием и оценкой усилий
- [ ] Отдельно проверить гипотезу: наша система — это orchestrator или choreography? Какой overhead («налог») мы платим?

### 🟡 Should Have (Желательно)
- [ ] Итоговая сводка: что мы УЖЕ делаем правильно (валидация), что нужно ИЗУЧИТЬ детально (gaps), что НЕ ПРИМЕНИМО к AI-агентам
- [ ] Оценка, как паттерн «orchestrator tax» влияет на архитектуру task-orchestrator (можем ли мы уменьшить overhead?)
- [ ] Явное разделение: паттерны про МИКРОСЕРВИСЫ (информационные) vs паттерны про AI-АГЕНТЫ (применимые к нам)

### ⚫ Won't Have (Не будем делать)
- [ ] Код/конфиги, изменение архитектуры, бенчмарки

## 4. Implementation Plan (План реализации)
1. [ ] Изучить первоисточник (см. секцию Sources) — статью Martin Fowler «Orchestrator Tax»
2. [ ] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`, `src/Module/GitIdentity/`, `docs/guide/architecture.md`
3. [ ] Оценить каждый из 6 критериев; по каждому паттерну/anti-pattern'у — маппинг + вердикт
4. [ ] Проверить гипотезу: orchestrator vs choreography? Какой overhead мы платим?
5. [ ] Создать отчёт в `docs/research/orchestration-articles/orchestrator-tax-research.md`
6. [ ] Создать/обновить сводную таблицу `docs/research/orchestration-articles-summary.md` (строка #1, статус `1 / N`)
7. [ ] Создать аналитический отчёт в `docs/agents/reports/system-analyst/` по формату проекта

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт создан, все 6 критериев оценены
- [ ] Маппинг каждого паттерна на нашу архитектуру — со ссылками на конкретные файлы/компоненты
- [ ] Вердикт apply/study/skip по каждому паттерну с обоснованием и усилиями
- [ ] Гипотеза про orchestrator vs choreography проверена
- [ ] Строка добавлена в сводную таблицу `docs/research/orchestration-articles-summary.md`
- [ ] Аналитический отчёт в `docs/agents/reports/system-analyst/` создан

## 6. Verification (Самопроверка)
```bash
ls docs/research/orchestration-articles/orchestrator-tax-research.md
ls docs/research/orchestration-articles-summary.md
ls docs/agents/reports/system-analyst/*orchestrator-tax*.md
```

## 7. Sources (Источники)
📚 **Внешние источники** (до 5, кратким списком):

- [Martin Fowler, Orchestrator Tax (2012)](https://martinfowler.com/articles/orchestrator-tax.html) — первоисточник
- [Martin Fowler, Saga Pattern (microservices.io)](https://microservices.io/patterns/data/saga.html) — дополнительный контекст по sagas
- [Martin Fowler, Aggregator Pattern (microservices.io)](https://microservices.io/patterns/aggregator.html) — дополнительный контекст по orchestration patterns

🔗 **Внутренние источники:**

- `docs/guide/architecture.md` — архитектура проекта
- `src/Module/AgentRunner/` — модуль запуска AI-агентов
- `src/Module/ChainExecution/` — модуль исполнения цепочек
- `src/Module/DynamicLoop/` — модуль динамических циклов
- `src/Module/ChainDefinition/` — модуль определения цепочек
- `src/Module/GitIdentity/` — модуль Git-identity бота

## 8. Comments (Комментарии)
**Резюме содержания статьи (для контекста исполнителя, НЕ заменяет самостоятельное изучение источников):**

Тезис Фаулера: централизованный **orchestrator** (оркестратор) знает слишком много о деталях каждого шага процесса, становится хрупким (brittle) и трудно поддерживаемым. Каждый шаг — точка отказа, любой change cascade требует переписывания orchestrator'а. Overhead («налог») — сложность централизованной координации.

**Альтернативы:**
1. **Choreography** (хореография) — децентрализованная координация через события. Каждый сервис знает свою роль и реагирует на события, без центрального orchestrator'а. Меньше связности, но сложнее отладка (траектория разбросана по логам).
2. **Saga** (сага) — длинные транзакции с компенсирующими действиями. Вместо одного ACID-transaction — серия локальных транзакций + компенсация при ошибке (rollback через компенсирующие действия).

**Failure handling:** orchestrator должен знать, как обрабатывать сбои на каждом шаге (retry, circuit breaker, compensation). Choreography — каждый шаг сам знает свои retry-правила.

**Перекличка с нашей системой (предварительно, требует верификации):** AgentRunner/ChainExecution — централизованный orchestrator цепочек AI-агентов; ChainDefinition — YAML-описание цепочек (steps, runners); DynamicLoop — динамические циклы (итеративные раунды); GitIdentity — Git-identity бота для коммитов агента. Платим ли мы «orchestrator tax»? Может ли choreography через события заменить централизованный orchestrator?

**Гипотеза тимлида к проверке:** наша система — это классический orchestrator (знает о каждом шаге, управляет retry, circuit breaker, dynamic loops). Overhead — сложность ChainExecution + DynamicLoop. Альтернатива — choreography через события (каждый agent паблишит события, следующий реагирует). Но для AI-агентов это может быть сложно (детерминированность цепочек, JSONL-парсинг).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-29 | Тимлид (Алекс) | Создание задачи. Первая задача эпика EPIC-research-orchestration-articles. |
