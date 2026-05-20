---
# Metadata (Метаданные)
type: research
created: 2026-05-20
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-zeroclaw
pr:
status: done
---

# TASK-research-zeroclaw: Исследовать Zeroclaw для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мы развиваем архитектуру task-orchestrator, я хочу исследовать Zeroclaw (zeroclaw-labs), чтобы понять его модель оркестрации, state management, error handling, extensibility — и определить, что стоит заимствовать, а от чего отказаться.

### Goal (Цель по SMART)
Исследовать Zeroclaw (https://github.com/zeroclaw-labs/zeroclaw) как систему оркестрации AI-агентов по 4 осям: модель оркестрации, state management, error handling, extensibility. Создать отчёт в `docs/research/framework-comparisons/zeroclaw-comparison.md`. Вердикт: заимствовать / dependency / не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/framework-comparisons/zeroclaw-comparison.md`
*   **Текущее поведение:** В `docs/research/` уже есть сравнительные анализы фреймворков, включая OpenClaw (`framework-comparisons/metagpt-openclaw-comparison.md`) и сводная таблица `agent-frameworks-summary.md`
*   **Границы (Out of Scope):** код интеграции, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Обзор проекта: что такое Zeroclaw, позиционирование, архитектура, язык/стек
- [ ] **Модель оркестрации** — как оркестируются агенты, workflow, цепочки
- [ ] **State management** — хранение состояния, контекст между шагами
- [ ] **Error handling** — retry, recovery, обработка ошибок агентов
- [ ] **Extensibility** — расширяемость, плагины, интеграции
- [ ] Сравнение с task-orchestrator
- [ ] Вердикт: заимствовать / dependency / не подходит

### 🟡 Should Have (Желательно)
- [ ] Оценка связи с OpenClaw (общая экосистема, fork, преемник?)
- [ ] Паттерны для заимствования

### 🟢 Could Have (Опционально)
- [ ] Mermaid-диаграмма архитектуры

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции, бенчмарки

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
1. [x] Изучить репозиторий: https://github.com/zeroclaw-labs/zeroclaw
2. [x] Оценить архитектуру по 4 осям
3. [x] Сравнить с task-orchestrator и OpenClaw
4. [x] Создать отчёт в `docs/research/framework-comparisons/zeroclaw-comparison.md`
5. [x] Добавить строку в `docs/research/agent-frameworks-summary.md`

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт `docs/research/framework-comparisons/zeroclaw-comparison.md` создан по формату существующих comparison-документов
- [x] Все 4 оси оценены
- [x] Вердикт с обоснованием
- [x] Строка Zeroclaw добавлена в сводную таблицу `docs/research/agent-frameworks-summary.md`

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/zeroclaw-comparison.md
grep "Zeroclaw" docs/research/agent-frameworks-summary.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Zeroclaw может быть в ранней стадии — мало документации
- Связь с OpenClaw требует уточнения (общая команда, fork, независимый проект)
- Проект может быть неактивен — нужно проверить свежесть коммитов

## 8. Sources (Источники)
- [Zeroclaw — GitHub](https://github.com/zeroclaw-labs/zeroclaw)

## 9. Comments (Комментарии)
Zeroclaw от zeroclaw-labs — название указывает на возможную связь с OpenClaw (уже исследован в `metagpt-openclaw-comparison.md`). Стоит выяснить: это fork, преемник, или независимый проект. Сравнение с OpenClaw поможет быстрее определить ценность.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-20 | Тимлид (Алекс) | Создание задачи |
