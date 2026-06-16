---
type: research
created: 2026-06-12
value: V3
complexity: C2
priority: P2
depends_on: []
epic: EPIC-research-agent-frameworks-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-odysseus-ai-workspace
pr: https://github.com/prikotov/task-orchestrator/pull/254
status: done
---

# TASK-research-odysseus-ai-workspace: Исследовать Odysseus (PewDiePie) для сравнения с task-orchestrator

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы оцениваем AI-agent фреймворки, я хочу изучить Odysseus (PewDiePie archdaemon), чтобы понять его модель оркестрации агента, систему tools/MCP/skills, Deep Research workflow, память, обработку ошибок — и сравнить с нашими подходами.

### Goal (Цель по SMART)
Провести техническое исследование Odysseus: архитектура (FastAPI + Python), agent loop, tools/MCP/skills, Deep Research, state management, error handling/retry/fallback/safety, extensibility. Составить отчёт с выводами: подходит ли Odysseus как dependency, какие product capabilities можно реализовать у нас с нуля, какие ограничения связаны с AGPL-3.0. Добавить строку в сводную таблицу.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** docs/research/
*   **Текущее поведение:** В docs/research/ уже есть сравнительные анализы 26+ фреймворков и сводная таблица agent-frameworks-summary.md
*   **Границы (Out of Scope):** Не пишем код интеграции — только исследование

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Изучить Odysseus: архитектура, Python/FastAPI стек, agent loop, tools/MCP/skills
- [x] Изучить Deep Research workflow (multi-step runs, adapted from Alibaba DeepResearch)
- [x] Изучить state management (sessions, memory via ChromaDB, skills persistence)
- [x] Изучить error handling (retry, host health, no circuit breaker, timeouts)
- [x] Изучить безопасность (tool security, prompt security, owner-scoped endpoints)
- [x] Сравнить с нашей моделью (static/dynamic chains, retry, circuit breaker, budget, quality gates)
- [x] Оформить отчёт в docs/research/framework-comparisons/odysseus-comparison.md по формату существующих comparison-документов
- [x] Заполнить строку для Odysseus в сводной таблице docs/research/agent-frameworks-summary.md
### 🟡 Should Have (Желательно)
- [x] Определить конкретные feature candidates для самостоятельной реализации в task-orchestrator
- [x] Оценить подход Odysseus к memory (ChromaDB + vector + keyword retrieval, persistence)
- [x] Сравнить Python/FastAPI подход с нашим PHP/Symfony подходом
- [x] Проанализировать AGPL лицензию и риски использования кода
### 🟢 Could Have (Опционально)
- [ ] Изучить интеграцию с OpenCode (built on OpenCode)
- [ ] Изучить Cookbook (модельный менеджер с GPU awareness)
### ⚫ Won't Have (Не будем делать)
- [ ] Написание кода интеграции
- [ ] Глубокий code review исходников за пределами ключевых файлов

## 4. Implementation Plan (План реализации)
1. [x] Изучить репозиторий https://github.com/pewdiepie-archdaemon/odysseus и README
2. [x] Изучить agent_loop.py (agent loop, domain rules, tool parsing)
3. [x] Изучить llm_core.py (retry, host health, no circuit breaker)
4. [x] Изучить memory system (ChromaDB, vector + keyword retrieval)
5. [x] Изучить Deep Research workflow (research_routes.py)
6. [x] Изучить tool security, prompt security, owner-scoped endpoints
7. [x] Сравнить с нашей моделью (chains, retry, circuit breaker, budget, quality gates)
8. [x] Написать docs/research/framework-comparisons/odysseus-comparison.md
9. [x] Добавить строку Odysseus в docs/research/agent-frameworks-summary.md

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт docs/research/framework-comparisons/odysseus-comparison.md создан по формату существующих comparison-документов
- [x] Содержит чёткий вывод: dependency не подходит / feature candidates для самостоятельной реализации
- [x] Строка Odysseus в сводной таблице docs/research/agent-frameworks-summary.md заполнена
- [x] Обновлён счётчик заполнения в заголовке таблицы на «27 / 27»

## 6. Verification (Самопроверка)
```bash
ls docs/research/framework-comparisons/odysseus-comparison.md
grep -c "Odysseus" docs/research/agent-frameworks-summary.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Odysseus активно развивается (текущая версия 1.0, май 2026) — архитектура и API могут меняться
- AGPL-3.0 лицензия — ограничивает использование кода в проприетарных продуктах
- Python-экосистема — оценка применимости паттернов в PHP требует аккуратности
- Зависимость от OpenCode (built on OpenCode) — дополнительная точка анализа

## 8. Sources (Источники)
- https://github.com/pewdiepie-archdaemon/odysseus — репозиторий проекта (~69k★ на дату анализа)
- https://pewdiepie-archdaemon.github.io/odysseus/ — landing page с демо
- README.md — обзор возможностей и quick start
- src/agent_loop.py — agent loop, domain rules, tool parsing
- src/llm_core.py — retry, host health, no circuit breaker
- src/memory.py — memory management, ChromaDB
- services/memory/ — memory services (vector + keyword retrieval)
- routes/research_routes.py — Deep Research workflow
- src/tool_security.py — tool security
- src/prompt_security.py — prompt security
- core/database.py — ModelEndpoint (owner-scoped endpoints)

## 9. Comments (Комментарии)
Odysseus — self-hosted AI workspace с Chat, Agent, Deep Research, Cookbook, Documents, Memory/Skills, Email, Calendar, Notes & Tasks. Built on OpenCode. Python (FastAPI) backend, AGPL-3.0 лицензия. Agent loop: LLM → tool call → observation → LLM. Tools: bash, python, web_search, web_fetch, read/write/edit files, documents, email, calendar, notes, memory, skills, research, image generation, chat_with_model. Memory: ChromaDB + vector + keyword retrieval, persistence. Deep Research: multi-step runs adapted from Alibaba DeepResearch. Error handling: retry max 3, host health (cooldown after 2 consecutive failures), no circuit breaker. Security: tool security (blocked tools per owner, plan mode disabled tools), prompt security (untrusted context filtering), owner-scoped endpoints, 2FA.

**Результат исследования:** Создан отчёт [odysseus-comparison.md](../docs/research/framework-comparisons/odysseus-comparison.md) и обновлена сводная таблица [agent-frameworks-summary.md](../docs/research/agent-frameworks-summary.md). Вердикт: 🔴 не подходит как dependency, 🟡 feature candidates for independent implementation. AGPL-3.0 запрещает копирование кода, но product capabilities полезны как feature candidates (Deep Research Chain, Skills Registry, Tool Permission Policy, Agent Memory/Context Store, Provider Health/Failover).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-12 | Аналитик (Шерлок) | Создание задачи |