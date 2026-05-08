---
type: docs
created: 2026-05-02
value: V2
complexity: C0
priority: P2
depends_on:
epic: EPIC-sprint-9-resilience-observability
author: system_analyst_sherlock (Шерлок)
assignee: system_architect_gandalf (Гэндальф)
branch:
pr:
status: todo
---

# TASK-docs-dynamic-split-adr: ADR: Dynamic split — решение

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда Sprint 8 завершился и Conditional Branching валидировал Integration-паттерн на 2 стратегиях (Static + Conditional), а OQ-6 из roadmap остаётся открытым — остаётся ли Dynamic в Orchestrator навсегда или планируется split? — я хочу зафиксировать архитектурное решение в ADR, чтобы закрыть открытый вопрос и дать команде ясность на Q4.

### Goal (Цель по SMART)
Принять и зафиксировать архитектурное решение о судьбе Dynamic-стратегии: остаётся в Orchestrator или планируется физический split в отдельный модуль (по аналогии со StaticExecution). Записать ADR в `docs/adr/`. Срок: 2 часа.

## 2. Context and Scope (Контекст и Границы)
### Где делаем
- `docs/adr/` — новый ADR документ
- [`docs/releases/ROADMAP-2026-Q2-Q3.md`](../../docs/releases/ROADMAP-2026-Q2-Q3.md) — обновить OQ-6 (решение принято)

### Предпосылки
- **OQ-6 (из roadmap):** «Физическое разделение Static/Dynamic на модули — Static split запланирован на Sprint 7. Dynamic остаётся в Orchestrator до стабилизации Integration-паттерна на ≥2 стратегиях (G6). Решение о Dynamic split — после Sprint 8 по результатам Conditional Branching.»
- **Sprint 8 завершён:** Conditional Branching валидировал Integration-паттерн. Integration-слой для Conditional создан по тому же паттерну, что Static — без God-interface на 15 методов.
- **G6 критерий:** Integration-паттерн работает для ≥2 стратегий ✅ (Static + Conditional)
- **Static split успешен:** StaticExecution — отдельный модуль, Deptrac green, Integration-слой работает
- **Dynamic complexity:** 9 Domain-сервисов, самый сложный модуль в Orchestrator

### Границы (Out of Scope)
- Не реализуем split в этом спринте — только решение (ADR)
- Не меняем код
- Не обновляем архитектурную документацию (это будет в release notes эпика)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] ADR создан в `docs/adr/` с номером (следующий доступный)
- [ ] ADR содержит: Context, Decision, Consequences, Alternatives Considered
- [ ] Явное решение: Dynamic остаётся в Orchestrator ИЛИ планируется split (с обоснованием)
- [ ] Критерии пересмотра решения (как и для ADR-006, ADR-008)
- [ ] OQ-6 в roadmap обновлён: статус → Resolved

### 🟡 Should Have (Желательно)
- [ ] Оценка LOC и сложности Dynamic path для обоснования
- [ ] Ссылка на G6-критерий и результаты Static + Conditional Integration

### 🟢 Could Have (Опционально)
- [ ] Прогноз timeline для split (если решение «split»)

### ⚫ Won't Have (Не будем делать)
- [ ] Реализация split
- [ ] Code changes

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем (Гэндальф) перед стартом.*

1. [ ] Проанализировать результаты Sprint 7 (Static split) и Sprint 8 (Conditional Integration)
2. [ ] Оценить Dynamic complexity: LOC, Domain services, cross-dependencies
3. [ ] Принять решение с обоснованием
4. [ ] Записать ADR по формату проекта (см. существующие ADR-006, ADR-008)
5. [ ] Обновить OQ-6 в roadmap

### Структура файлов
```
docs/adr/0XX-dynamic-split-decision.md  — новый ADR
docs/releases/ROADMAP-2026-Q2-Q3.md      — обновить OQ-6
```

## 5. Definition of Done (Критерии приёмки)
- [ ] ADR записан в `docs/adr/`
- [ ] Решение явно сформулировано и обосновано
- [ ] OQ-6 в roadmap обновлён (Resolved)
- [ ] ADR рецензирован владельцем проекта или тимлидом

## 6. Verification (Самопроверка)
```bash
# Проверить, что ADR существует и roadmap обновлён
cat docs/adr/0XX-dynamic-split-decision.md
grep "OQ-6" docs/releases/ROADMAP-2026-Q2-Q3.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Решение «split» создаёт обязательство на Q4, которое может не уложиться в timeline. Митигация: ADR фиксирует решение, но не timeline реализации.
- **Риск:** Dynamic — самый сложный модуль. Integration-слой для Dynamic может потребовать God-interface, что нарушит критерий успеха split («Integration-слой < 200 LOC, без God-interface»). Это аргумент «за» оставание в Orchestrator.
- **Нет кодовых зависимостей** — чисто документальная задача.

## 8. Sources (Источники)
- [ ] [Roadmap: OQ-6](../../docs/releases/ROADMAP-2026-Q2-Q3.md) — открытый вопрос
- [ ] [Анализ Локи: Integration-паттерн](../../docs/research/analytical/loki-roadmap-review-2026-05.md) — «Dynamic split решение не блокирует Sprint 9 задачи»
- [ ] [ADR-006: ExecutionStrategy composition](../../docs/adr/006-execution-strategy-composition.md) — формат ADR
- [ ] [ADR-008: Shared Kernel Contract](../../docs/adr/008-shared-kernel-contract.md) — Shared Kernel scope
- [ ] [EPIC-sprint-8-conditional-branching](EPIC-sprint-8-conditional-branching.md) — результаты Sprint 8

## 9. Comments (Комментарии)
- Ответственный: Гэндальф (архитектурные решения — его domain)
- Задача не блокирует кодовые задачи Sprint 9 — architectural bookmark
- Критерий успеха split (из brainstorm #2): «не Deptrac green, а Integration-слой для второй стратегии создан по тому же паттерну без God-interface на 15 методов»
- Аргументы «за оставание в Orchestrator»: Dynamic — 9 Domain-сервисов, Integration-слой будет сложным; Orchestrator без Dynamic теряет смысл как «оркестрирующий» модуль
- Аргументы «за split»: G6-критерий пройден, ISP violation в ChainDefinitionVo, модульность как ценность

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | system_analyst_sherlock (Шерлок) | Создание задачи |
