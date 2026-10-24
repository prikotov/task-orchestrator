---
# Metadata (Метаданные)
type: docs
created: 2026-10-24
value: V3
complexity: C3
priority: P2
depends_on:
epic: EPIC-research-orchestration-articles
author: Аналитик Шерлок (pi)
assignee: Аналитик Шерлок (pi)
branch: task/research-pi-durable
pr: '#426'
status: done
---

# TASK-research-pi-durable: Статья Earendil Works «Pi Durable» (durable execution для AI-агентов)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Движок task-orchestrator переживает сбои runner'ов (retry, circuit breaker, budget), но не переживает сбой самого процесса оркестратора: упав на середине цепочки, исполнение начинается заново, компенсации побочных эффектов нет, политика остановки «первого сбоя» для параллельных шагов не определена.
- Кандидат для исследования — пост Earendil Works «Pi Durable» (https://earendil.com/posts/pi-durable/): документация и анонс экспериментальной библиотеки `@earendil-works/pi-durable`, где долговечность (durable execution) агентов решена системно — чекпойнты, транзакции, задачи с компенсацией, подагенты-беседы, компакция контекста, смена кода без остановки.
- Неизвестно: какие из этих механизмов применимы к нашему PHP-оркестратору CLI-агентов и в каком порядке их стоит переносить.

### Варианты или путь решения (Solution Sketch)
- Исследовать пост по единой методологии эпика из 6 критериев (тезис, паттерн, domain, failure handling, маппинг, применяемость).
- Сопоставить каждый механизм Pi Durable с модулями task-orchestrator (AgentRunner, ChainExecution, DynamicLoop, ChainDefinition) и вынести вердикт apply / study / skip.
- Особо проверить живую реализацию saga-компенсации (abort → refund, каскад bottom-up) — тема из базовой методологии эпика, впервые встречающаяся в первоисточнике не как теория, а как код.

### Ожидаемый результат (Expected Result)
- Research-документ `docs/research/orchestration-articles/pi-durable-research.md` по 6 критериям.
- Строка #2 в сводной таблице `docs/research/orchestration-articles-summary.md` + новые строки в backlog рекомендаций.
- Аналитический отчёт в `docs/agents/reports/system-analyst/`.
- Чёткий вердикт: что переносить как принцип (apply), что проектировать отдельными задачами (study), что неприменимо (skip).

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мы развиваем отказоустойчивость нашего оркестратора AI-агентов (retry, circuit breaker, budget, dynamic loops), я хочу исследовать пост Earendil Works «Pi Durable» — долговечное исполнение агентов: чекпойнты после каждого шага, replay-семантика вызовов, saga-компенсация, ожидание задач с политикой failFast, компакция контекста, смена кода без остановки, — чтобы определить, какие механизмы применимы к task-orchestrator и заложить их в план доработок движка.

### Goal (Цель по SMART)
Исследовать пост «Pi Durable» (https://earendil.com/posts/pi-durable/) по методологии из 6 критериев. Создать отчёт в `docs/research/orchestration-articles/pi-durable-research.md` с маппингом на модули task-orchestrator и вердиктом apply/study/skip по каждому механизму. Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`.

## 2. Context and Scope (Контекст и Границы)
* **Объект:** Earendil Works, «Pi Durable» (https://earendil.com/posts/pi-durable/). Документация + анонс экспериментальной библиотеки `@earendil-works/pi-durable` (TypeScript, `packages/durable` в репозитории pi; статус — «experimental, and the API might still change»). Ядро: долговечный harness (каркас) для агентов — транзакционные коммиты, документы состояния, задачи с чекпойнтами, подагенты как беседы, компакция, malleable (изменяемость кода на ходу).
* **Где делаем:** `docs/research/orchestration-articles/pi-durable-research.md`, `docs/research/orchestration-articles-summary.md`
* **Текущее поведение:** наш движок устойчив на уровне вызова runner'а (retry с классификацией ошибок, circuit breaker с fallback, бюджетные проверки, прерывание dynamic loop по `agent_error`/`timeout`), но не имеет возобновления цепочки после сбоя процесса, компенсации выполненных шагов и политики остановки параллельных шагов.
* **Границы (Out of Scope):** написание кода, изменение архитектуры, бенчмарки. Только исследование и рекомендации. Изменения — отдельными задачами.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерий 1–6** — по единой методологии эпика (тезис/проблема, паттерн/концепция, domain, failure handling, маппинг на нашу архитектуру, применяемость)
- [x] Маппинг КАЖДОГО механизма Pi Durable на компоненты task-orchestrator (AgentRunner, ChainExecution, DynamicLoop, ChainDefinition, GitIdentity) — со ссылками
- [x] Вердикт по каждому механизму: apply / study / skip — с обоснованием и оценкой усилий
- [x] Отдельно разобрать saga-компенсацию (abort → refund, каскад bottom-up) и replay-семантику (safe / no-replay) — их в методологии эпика не закрывает ни одна исследованная ранее статья

### 🟡 Should Have (Желательно)
- [x] Сопоставление с backlog-задачами параллельного исполнения (TASK-feat-parallel-execution, TASK-feat-dag-orchestration, TASK-feat-docker-sandboxing): механизм Pi Durable как готовый контракт для их дизайна
- [x] Итоговая сводка: что уже закрыто у нас, что проектировать, что неприменимо из-за разницы модели исполнения (долгоживущий TS-процесс vs разовый PHP-процесс)

### ⚫ Won't Have (Не будем делать)
- [ ] Код/конфиги, изменение архитектуры, бенчмарки

## 4. Implementation Plan (План реализации)
1. [x] Изучить первоисточник через скилл knowledge-extraction (проект `pi-durable-research`: обзор → механизмы → задачи → подагенты → контекст → malleable → документы → среда исполнения → позиционирование → семантика сбоев)
2. [x] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`
3. [x] Оценить каждый из 6 критериев; по каждому механизму — маппинг + вердикт
4. [x] Сопоставить с backlog-задачами параллельного исполнения и сэндбоксинга
5. [x] Создать отчёт в `docs/research/orchestration-articles/pi-durable-research.md`
6. [x] Обновить сводную таблицу `docs/research/orchestration-articles-summary.md` (строка #2, backlog рекомендаций, тренды)
7. [x] Создать аналитический отчёт в `docs/agents/reports/system-analyst/` по формату проекта

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт создан, все 6 критериев оценены
- [x] Маппинг каждого механизма на нашу архитектуру — со ссылками на конкретные файлы/компоненты
- [x] Вердикт apply/study/skip по каждому механизму с обоснованием и усилиями
- [x] Saga-компенсация и replay-семантика разобраны отдельно
- [x] Строка добавлена в сводную таблицу `docs/research/orchestration-articles-summary.md`
- [x] Аналитический отчёт в `docs/agents/reports/system-analyst/` создан

## 6. Verification (Самопроверка)
```bash
ls docs/research/orchestration-articles/pi-durable-research.md
ls docs/research/orchestration-articles-summary.md
ls docs/agents/reports/system-analyst/*pi-durable*.md
```

## 7. Sources (Источники)
📚 **Внешние источники** (до 5, кратким списком):

- [Earendil Works, Pi Durable](https://earendil.com/posts/pi-durable/) — первоисточник
- [npm, @earendil-works/pi-durable](https://www.npmjs.com/package/@earendil-works/pi-durable) — пакет (статус experimental)

🔗 **Внутренние источники:**

- `docs/guide/architecture.md` — архитектура проекта
- `src/Module/AgentRunner/` — модуль запуска AI-агентов (retry, circuit breaker, liveness)
- `src/Module/ChainExecution/` — модуль исполнения цепочек
- `src/Module/DynamicLoop/` — модуль динамических циклов (session/journal)
- `src/Module/ChainDefinition/` — модуль определения цепочек (retry policy, when, postStep)
- `todo/backlog/TASK-feat-parallel-execution.todo.md`, `todo/backlog/TASK-feat-dag-orchestration.todo.md`, `todo/backlog/TASK-feat-docker-sandboxing.todo.md` — связанные backlog-задачи

## 8. Comments (Комментарии)
**Резюме содержания первоисточника (для контекста, НЕ заменяет самостоятельное изучение):**

Pi Durable делает агента и состояние приложения долговечными: «every step of a run is a task that stores a checkpoint before it moves on. If the process dies, a new process opens the same storage, finds the unfinished tasks, and continues each one from its last checkpoint».

Ключевые механизмы:

1. **Транзакции и документы.** Инструменты читают/пишут через `api.commit`; документы — типизированный JSON рядом со стенограммой, «changed in the same atomic commits, so the state never disagrees with the transcript that produced it».
2. **Replay-семантика.** `replay: "safe"` (только чтение) — повтор после сбоя допустим; без пометки — «a deploy interrupted by a crash is reported to the model, never repeated». Оборванный запрос модели отправляется снова, частичный ответ остаётся в стенограмме с пометкой aborted.
3. **Задачи и компенсация.** `defineTask` с фазами; идемпотентный ключ `payment-${task.id}`; `abort` делает refund; каскад bottom-up: «Aborting a task aborts what it owns… every task cleans up its own effects first».
4. **Ожидание.** `status: "waiting"` + checkpoint + `policy: "failFast"` — «Run no code until every payment is done. The first failed payment aborts the others».
5. **Подагенты — беседы.** «The subagent is a conversation like any other, so it survives a crash, counts its own cost»; владение (abort каскадом), сужение модели/инструментов/инструкций.
6. **Контекст.** Компакция — фоновая задача с порогами (`reserveTokens: 16384`, `backgroundTokens: 32768`); ручной `compact`; `reset()`/handoff — новый контекст, «nothing is deleted», поиск `search_history` включая сообщения до handoff.
7. **Malleable.** Реестр меняется на ходу: «A tool call that is already running finishes on the code it started with; the next call uses the new code»; беседы хранят имена, не код.
8. **Среда.** `env` строится из рабочей директории беседы («each conversation can run in a different place»); harness на одной машине, инструменты на другой; sandbox — документ.

**Статус:** «Pi Durable is experimental, and the API might still change». Сравнений с Temporal и другими workflow-движками автор не приводит.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-10-24 | Аналитик Шерлок (pi) | Создание задачи по запросу владельца (evergreen-режим эпика: новая статья → задача Этапа 1). |
| 2026-10-24 | Аналитик Шерлок (pi) | Исследование выполнено: создан `docs/research/orchestration-articles/pi-durable-research.md` (оценка 18/18, итог — `study` как основное направление + `apply` для отдельных принципов), сводная таблица дополнена строкой #2 и backlog-рекомендациями, аналитический отчёт сохранён. PHPUnit/Psalm пропущены (docs-only). Задача переведена в `done`, файл перенесён в `todo/done/`. |
