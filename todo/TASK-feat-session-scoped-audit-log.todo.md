---
type: feat
created: 2026-04-16
value: V3
complexity: C3
priority: P2
depends_on:
epic:
author: Бэкендер (Левша)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-session-scoped-audit-log: Audit log как артефакт сессии

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда я запускаю brainstorm-сессию и потом анализирую результаты в `var/sessions/brainstorm/2026-04-17_12-30-00/`, я хочу видеть audit.jsonl рядом с остальными артефактами (topic.md, session.json, result.md, шаги), чтобы вся история одной сессии была в одном месте.

### Goal (Цель по SMART)
Audit log должен быть part of session artifacts — лежать внутри директории сессии (`var/sessions/<chain>/<timestamp>/audit.jsonl`), а не в глобальном `var/log/audit.jsonl`. Глобальный путь убрать из конфигурации.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/Orchestrator/Infrastructure/Service/Chain/ChainSessionLogger.php` — создание audit.jsonl внутри сессии
    *   `src/Module/Orchestrator/Infrastructure/Service/Chain/JsonlAuditLogger.php` — без изменений (фабрика создаёт с нужным путём)
    *   `src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php` — передавать путь `<sessionDir>/audit.jsonl` вместо глобального
    *   `src/DependencyInjection/Configuration.php` — убрать `audit_log_path` из конфигурации (больше не нужен)
    *   `config/console_services.yaml` — убрать `AuditLoggerInterface` сервис (создаётся фабрикой per-session)
    *   `bin/console` — убрать `audit_log_path` параметр
*   **Текущее поведение:** `var/log/audit.jsonl` — единый append-only файл для всех запусков. Записи от разных сессий перемешаны. Файл удалён из git через `var/` в `.gitignore`.
*   **Границы (Out of Scope):**
    *   Структура JSONL-записей — без изменений
    *   `--no-audit-log` флаг CLI — оставить как есть (опциональное отключение)
    *   Session resume — audit log должен дозаписываться при resume

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Audit log создаётся внутри директории сессии: `var/sessions/<chain>/<timestamp>/audit.jsonl`
- [ ] Глобальный `var/log/audit.jsonl` больше не используется
- [ ] `audit_log_path` убран из DI-конфигурации (Configuration.php, TaskOrchestratorExtension.php)
- [ ] Session resume дозаписывает в существующий audit.jsonl
- [ ] PHPUnit: существующие тесты проходят
### 🟡 Should Have (Желательно)
- [ ] Psalm: 0 errors
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- Миграцию старого var/log/audit.jsonl — он gitignored, не нужен

## 4. Implementation Plan (План реализации)
1. [ ] В `ChainSessionLogger::startSession()` — создать `audit.jsonl` путь, вернуть через VO или getter
2. [ ] В `OrchestrateChainCommandHandler` — получить audit path из session logger, передать в фабрику
3. [ ] Убрать `audit_log_path` из Configuration.php, TaskOrchestratorExtension.php, bin/console
4. [ ] Обновить console_services.yaml — убрать дефолтный AuditLoggerInterface
5. [ ] Протестировать: `php bin/console app:agent:orchestrate "test" -c brainstorm`
6. [ ] Проверить: audit.jsonl лежит рядом с session.json

## 5. Definition of Done (Критерии приёмки)
- [ ] После brainstorm в `var/sessions/<chain>/<timestamp>/` есть `audit.jsonl` с записями
- [ ] Глобальный `var/log/audit.jsonl` не создаётся
- [ ] `--no-audit-log` флаг работает
- [ ] PHPUnit + Psalm проходят

## 6. Verification (Самопроверка)
```bash
php bin/console app:agent:orchestrate "test task" -c brainstorm -vvv
ls var/sessions/brainstorm/*/audit.jsonl
vendor/bin/phpunit
vendor/bin/psalm --no-cache
```

## 7. Risks and Dependencies (Риски и зависимости)
- `AuditLoggerInterface` внедрён через DI во многие сервисы — нужно аккуратно перепривязать к per-session lifecycle
- Bundle consumers (TasK) могут использовать `audit_log_path` — нужен release note

## 8. Sources (Источники)

## 9. Comments (Комментарии)
Идея: audit.jsonl — такой же артефакт сессии как topic.md, session.json, prompt-файлы. Все артефакты в одном месте = удобно анализировать, архивировать, удалять.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-16 | Бэкендер (Левша) | Создание задачи |
