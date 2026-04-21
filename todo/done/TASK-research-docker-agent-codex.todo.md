---
type: research
created: 2026-04-20
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Технический писатель (Гермиона)
branch: task/research-docker-agent-codex
pr:
status: done
---

# TASK-research-docker-agent-codex: Исследовать Docker Agent и OpenAI Codex для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем подходы к изоляции и среде выполнения AI-агентов, я хочу изучить Docker Agent и OpenAI Codex, чтобы понять их модель контейнеризованного выполнения, sandboxing, и оркестрации — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование Docker Agent (OpenAI) и OpenAI Codex CLI: архитектура, модель контейнеризации, агентный цикл, обработка ошибок, безопасность. Составить отчёт с выводами: заимствовать паттерны, использовать как dependency, или не подходит.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы (agent-bernstein-comparison.md, agent-orchestrator-comparison.md, superpowers-brainstorming-comparison.md)
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Изучить Docker Agent: архитектуру, контейнеризацию, sandboxing, связь с хост-системой
- [x] Изучить OpenAI Codex (CLI / cloud): агентный цикл, модель инструментов, контекст
- [x] Сравнить оба продукта с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [x] Оформить отчёт в docs/research/docker-agent-codex-comparison.md по формату существующих comparison-документов
- [x] Заполнить строку для Docker Agent + Codex в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [x] Определить конкретные паттерны, которые стоит заимствовать
- [x] Оценить подход к изоляции выполнения и безопасности
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции

## 4. Implementation Plan (План реализации)
1. [x] Изучить документацию OpenAI: Docker Agent, Codex CLI
2. [x] Изучить открытые материалы: статьи, GitHub, API reference
3. [x] Сравнить с нашей моделью оркестрации
4. [x] Написать docs/research/docker-agent-codex-comparison.md

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт docs/research/docker-agent-codex-comparison.md создан по формату существующих comparison-документов
- [x] Содержит чёткий вывод: заимствовать / использовать / не подходит
- [x] Строка Docker Agent + Codex в сводной таблице docs/research/agent-frameworks-summary.md заполнена

## 6. Verification (Самопроверка)
```bash
ls docs/research/docker-agent-codex-comparison.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Продукты проприетарные — анализ по документации и наблюдаемому поведению
- Docker Agent может быть ещё в preview/beta — ограниченная документация

## 8. Sources (Источники)
- https://openai.com/index/introducing-codex/
- https://github.com/openai/codex (if available)

## 9. Comments (Комментарии)
Оба продукта используют контейнеризацию для изоляции выполнения агента. Это важный паттерн — можно заимствовать подход к sandboxing и управлению средой выполнения.

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/technical_writer.ru.md
**Ветка:** task/research-docker-agent-codex (уже создана и активна)

### Порядок действий
1. Реализуй задачу согласно описанию.
2. Проверок PHPUnit/Psalm не требуется — задача docs-only.
3. Сделай git commit и git push после завершения.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-20 | Тимлид (Алекс) | Создание задачи |
| 2026-04-22 | Технический писатель (Гермиона) | Задача выполнена: создан отчёт, заполнена сводная таблица |
