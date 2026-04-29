---
type: refactor
created: 2026-04-29
value: V3
complexity: C4
priority: P1
depends_on:
epic: EPIC-refactor-orchestrator-decomposition
author: Тимлид (Алекс)
assignee: Тимлид (Алекс)
branch: task/orchestrator-brainstorm-analysis
pr: https://github.com/prikotov/task-orchestrator/pull/99
status: done
---

# TASK-refactor-orchestrator-brainstorm-analysis: Глубокий brainstorm-анализ Orchestrator (3 часа, 40 раундов)

## 1. Concept and Goal (Концепция и цель)
### Story (Job Story)
> Когда нужно декомпозировать Orchestrator (120 файлов, 9890 строк), я хочу провести глубокий анализ кода через brainstorm с архитекторами и бэкендером, чтобы получить объективную карту проблем и план декомпозиции, а не выдуманное решение одного агента.

### Goal (Цель по SMART)
Провести brainstorm-сессию: 3 часа (10800 сек), 40 раундов, 4 участника. На выходе — протокол с:
1. Схема текущей структуры Orchestrator (классы, зависимости, потоки вызовов)
2. Карта проблемных мест (связанность, размер, вложенность, VO-дублирование)
3. План декомпозиции с ≥ 1 вариантом, с учётом roadmap из research-summary

## 2. Context and Scope (Контекст и границы)
*   **Где делаем:** Запуск brainstorm через CLI, анализ модуля `src/Module/Orchestrator/`
*   **Текущее поведение:** Orchestrator — монолитный модуль из 120 файлов, 9890 строк, 25 VO, 5 DTO, 4 VO-дубликата с AgentRunner
*   **Границы (Out of Scope):**
    *   Не анализируем AgentRunner (отдельный модуль, адекватный размер)
    *   Не реализуем декомпозицию — только анализ и план
    *   Не проектируем новые фичи — только структуру

### Параметры brainstorm

| Параметр | Значение |
|---|---|
| Цепочка | `brainstorm` |
| Фасилитатор | `team_lead_alex` |
| Участники | `system_architect_gandalf`, `system_architect_loki`, `backend_developer_levsha`, `system_analyst_sherlock` |
| Макс. раундов | 40 |
| Таймаут шага | 600 сек |
| Макс. время | 10800 сек (3 часа) |
| Формат отчёта | text + файл в `var/sessions/brainstorm/` |

### Тема brainstorm

> Глубокий анализ модуля Orchestrator (src/Module/Orchestrator/, 120 файлов, 9890 строк) для декомпозиции на модули с чёткими границами.
>
> Цели анализа:
> 1. Построить полную карту структуры: все классы, их зависимости, потоки вызовов между слоями (Domain → Application → Infrastructure → Integration)
> 2. Выявить точки оверсложности: связанность между subdomain'ами (Static/Dynamic), VO-дублирование с AgentRunner (ChainRunRequestVo≈AgentRunRequestVo, ChainRunResultVo≈AgentResultVo, ChainTurnResultVo≈AgentTurnResultVo, ChainRetryPolicyVo≈RetryPolicyVo), вложенность вызовов (OrchestrateChainCommandHandler→...→RunAgentCommandHandler = 7 уровней), God-объекты (YamlChainLoader 414 строк, RunStaticChainService::execute 175 строк)
> 3. Предложить план декомпозиции с ≥ 1 вариантом, учитывая roadmap расширения из docs/research/agent-frameworks-summary.md (conditional branching, parallel execution, sub-agents, security policy, context management, typed I/O, hooks system)
> 4. Оценить каждый вариант: трудозатраты, риск, влияние на существующие тесты, совместимость с roadmap
>
> Контекст: проект планирует расширение — 16 исследованных AI-agent фреймворков показывают тренды: Archon/Agno/Mastra AI (workflow engine с типизированными шагами), LangGraph (DAG+durable execution), Paperclip AI (мета-оркестратор), Codex (security sandboxing). Границы модулей должны допускать эти расширения без повторной реструктуризации.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Brainstorm запущен через CLI — 40 раундов (81 шаг), 2ч 42мин, 4 участника
- [x] Сессия завершилась с синтезом — SUCCESS, max_rounds_reached, synthesis available
- [x] В протоколе есть схема текущей структуры Orchestrator (классы, зависимости, потоки вызовов)
- [x] В протоколе есть карта проблемных мест с конкретными примерами (ChainDefinitionVo, ChainSessionLogger, VO-дублирование, ExecuteDynamicTurnService, RunDynamicLoopService)
- [x] В протоколе есть ≥ 1 вариант декомпозиции с оценкой — 16 action items, ExecutionStrategy composition

### 🟡 Should Have (Желательно)
- [x] Потоки вызовов описаны для ключевых сценариев (static chain execution, dynamic loop, report generation)
- [ ] Каждый вариант декомпозиции включает Mermaid-схему будущей структуры
- [x] Оценка трудозатрат в часах (C1 ~2ч, C4 ~1.5 дня, Static/Dynamic split ~1 день)

### 🟢 Could Have (Опционально)
- [ ] Сравнение с архитектурой 2–3 фреймворков — частично, тренды учтены в обсуждении

### ⚫ Won't Have (Не будем делать)
- [ ] Реализация декомпозиции
- [ ] Написание кода

## 4. Implementation Plan (План реализации)
1. [x] Сформулировать финальную тему brainstorm
2. [x] Запустить brainstorm — 40 раундов, 81 шаг, 2ч 42мин, SUCCESS
3. [x] Сессия не прерывалась — resume не потребовался
4. [x] Проанализировать протокол (result.md) — все 4 цели анализа покрыты
5. [x] Оформить результаты — 16 action items, 10 решений, передано в TASK-refactor-orchestrator-decomposition-plan

## 5. Definition of Done (Критерии приёмки)
- [x] Brainstorm завершён — 40 раундов, 81 шаг, 2ч 42мин, SUCCESS
- [x] Протокол содержит схему структуры, карту проблем, план декомпозиции (10 решений, 16 action items)
- [x] Протокол сохранён в `var/sessions/brainstorm/2026-04-29_08-06-49/`
- [x] Результаты переданы в следующую задачу (TASK-refactor-orchestrator-decomposition-plan)

## 6. Verification (Самопроверка)
*Задача docs-only/аналитическая — PHPUnit/Psalm не требуются.*
- [x] Протокол `result.md` содержит все 4 цели анализа из темы brainstorm

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск 1:** Сессия 3 часа может прерваться — использую `--resume` для продолжения
- **Риск 2:** 40 раундов — тяжёлая нагрузка на модели, возможен timeout на отдельных шагах — установлен `--timeout=600`
- **Риск 3:** Участники могут уйти в детали и не успеть покрыть все 4 цели — фасилитатор (team_lead_alex) должен направлять обсуждение
- **Зависимость:** Наличие chains.yaml с цепочкой `brainstorm`

## 8. Sources (Источники)
- [ ] [Архитектура проекта](../docs/guide/architecture.md)
- [ ] [Сводная таблица исследований](../docs/research/agent-frameworks-summary.md)
- [x] [Протокол нового brainstorm (40 раундов)](../var/sessions/brainstorm/2026-04-29_08-06-49/result.md)
- [x] [Протокол предыдущего brainstorm (17 раундов)](../var/sessions/brainstorm/2026-04-29_03-42-46/result.md)
- [ ] [Brainstorm SKILL.md](../docs/agents/skills/brainstorm/SKILL.md)

## 9. Comments (Комментарии)
Это первая задача эпика EPIC-refactor-orchestrator-decomposition. Результат brainstorm — вход для задачи на планирование (Фаза 2) и создания задач на реализацию (Фаза 3).

**Важно:** brainstorm проводится строго через CLI, симуляция в голове агента запрещена (см. TASK-docs-brainstorm-anti-pattern).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-29 | Тимлид (Алекс) | Создание задачи |
| 2026-04-29 | Тимлид (Алекс) | Brainstorm завершён: 40 раундов, 81 шаг, 2ч 42мин. 10 решений, 16 action items. Протокол: var/sessions/brainstorm/2026-04-29_08-06-49/result.md |
