---
type: docs
created: 2026-04-30
value: V2
complexity: C2
priority: P1
depends_on:
epic: EPIC-refactor-orchestrator-decomposition
author: pi
assignee:
branch:
pr:
status: todo
---

# TASK-docs-security-policy-analysis: Анализ Security Policy как cross-cutting concern

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда Sprint 9 планирует внедрение Security Policy (exec policy + permission system), а brainstorm #2 подтвердил SecurityPolicy как безусловный отдельный модуль, я хочу иметь анализ влияния Security Policy на архитектуру Orchestrator и границы модулей, чтобы Sprint 9 начинался с готового дизайнерского решения.

### Goal (Цель по SMART)
Создать документ (2–3 стр.) с анализом: (1) как Security Policy взаимодействует со Static/Dynamic стратегиями, (2) нужен ли ACL между SecurityPolicy и Orchestrator, (3) какие interfaces нужны для cross-cutting concern.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** Анализ `src/Module/Orchestrator/` + исследованные паттерны из `docs/research/agent-frameworks-summary.md`
*   **Текущее поведение:** Нет анализа. OQ-3 roadmap фиксирует: «Security Policy — единственный roadmap-сценарий, где разделение Static/Dynamic создаёт проблему».
*   **Границы (Out of Scope):**
    *   Не проектируем implementation (это Sprint 9)
    *   Не создаём модуль

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Анализ: какие точки входа нужны Security Policy в Static/Dynamic path
- [ ] Оценка: разные ли permission models для Static vs Dynamic (триггер G4)
- [ ] Рекомендация: ACL pattern или shared interface для cross-cutting
- [ ] Ссылки на паттерны из исследования фреймворков (Codex, Claude Code, Crush)

### 🟡 Should Have (Желательно)
- [ ] Эскиз interfaces для SecurityPolicy module
- [ ] Оценка влияния на Integration-слой при split Static в Sprint 7

### ⚫ Won't Have (Не будем делать)
- [ ] Код
- [ ] ADR (отдельная задача в Sprint 9)

## 5. Definition of Done (Критерии приёмки)
- [ ] Документ создан в `docs/releases/` или `docs/adr/`
- [ ] Ответ на OQ-3 roadmap дан
- [ ] Триггер G4 (разные permission models?) оценён

## 6. Verification (Самопроверка)
Аналитическая задача — `make check` не требуется.

## 7. Risks and Dependencies (Риски и зависимости)
- Зависимость: хорошо бы иметь инвентаризацию Domain (AI#13), но не блокирует

## 8. Sources (Источники)
- [ ] [Roadmap AI#14, OQ-3](../../docs/releases/ROADMAP-2026-Q2-Q3.md)
- [ ] [Протокол brainstorm #2](../var/sessions/brainstorm/2026-04-30_16-02-26/result.md)
- [ ] [Исследование фреймворков](../docs/research/agent-frameworks-summary.md)

## 9. Comments (Комментарии)
Roadmap Sprint 2, AI#14. Результат анализа — входные данные для Sprint 9 (Security Policy implementation).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-30 | pi | Создание задачи |
