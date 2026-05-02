---
type: feat
created: 2026-05-02
value: V3
complexity: C5
priority: P4
depends_on: []
epic:
author:
assignee:
branch:
pr:
status: backlog
---

# TASK-feat-docker-sandboxing: Изоляция выполнения агентов в Docker-контейнерах

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда AI-агент выполняет shell-команды через оркестратор, я хочу быть уверен что он не может выйти за пределы рабочей директории и не имеет доступа к критичным системным ресурсам.

### Goal (Цель по SMART)
Ограничить выполнение shell-команд внутри Docker-контейнера с минимальными правами. Контейнер = sandbox для одного запуска цепочки.

## 2. Context and Scope (Контекст и Границы)
* **Текущее состояние:** Агент работает с полными правами пользователя. Security Policy отменён — текстовые правила не защищают от реальных shell-команд.
* **Границы (Out of Scope):**
  - Kubernetes orchestration
  - Multi-container setup
  - GUI/X11 sandboxing

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Docker-образ с минимальным набором инструментов (PHP, Composer, git)
- [ ] Монтирование рабочей директории read-write, остальное read-only
- [ ] Ограничение ресурсов (memory, CPU, network)
- [ ] Интеграция с оркестратором через `ShellHookExecutor` или отдельный `SandboxedProcessRunner`

### 🟡 Should Have (Желательно)
- [ ] Кеширование Docker-образа между запусками
- [ ] Конфигурация sandbox через YAML (какие ресурсы доступны)

## 4. Implementation Plan (План реализации)
TBD — задача в бэклоге, план будет при приоритизации.

## 5. Definition of Done (Критерии приёмки)
- [ ] Агент не может выйти за пределы рабочей директории
- [ ] Ресурсы ограничены (memory, CPU caps)
- [ ] CI/CD pipeline работает в sandbox
- [ ] Документация по настройке и использованию

## 6. Risks and Dependencies (Риски и зависимости)
- Docker overhead: +5-15с на создание контейнера
- Сложность отладки внутри контейнера
- Network access: нужен для Composer, но опасен для произвольных команд

## 7. Sources (Источники)
- Research: Codex (Docker sandbox), Claude Code (permissions)

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-02 | Тимлид | Создание задачи (бэклог) |
