---
type: refactor
created: 2026-04-29
value: V3
complexity: C1
priority: P1
depends_on:
epic: EPIC-refactor-orchestrator-decomposition
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/session-writer-consumers
pr: https://github.com/prikotov/task-orchestrator/pull/103
status: done
---

# TASK-refactor-session-writer-consumers: Переключение 3 потребителей на ChainSessionWriterInterface

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда Domain-слой содержит расщеплённые интерфейсы `ChainSessionWriterInterface` и `ChainSessionReaderInterface`, но 0 потребителей их используют, я хочу переключить 3 сервиса-потребителя на `ChainSessionWriterInterface` вместо `ChainSessionLoggerInterface`, чтобы интерфейсы перестали быть мёртвыми и соответствовали реальному usage.

### Goal (Цель по SMART)
Переключить `RecordDynamicRoundService`, `CheckDynamicBudgetService`, `RunDynamicLoopAgentService` на инжекцию `ChainSessionWriterInterface` вместо `ChainSessionLoggerInterface`. Обновить DI-конфигурацию. Blast radius: 3 файла + DI config.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/`, `config/services.yaml`
*   **Текущее поведение:** `ChainSessionLoggerInterface` расщеплён на `ChainSessionWriterInterface` + `ChainSessionReaderInterface` в Domain, но все 3 потребителя инжектят `ChainSessionLoggerInterface`. Sub-интерфейсы мертвы.
*   **Границы (Out of Scope):**
    *   Не создаём новые sub-интерфейсы (SessionLifecycle, RoundData, SessionMetadata)
    *   Не расщепляем реализацию `ChainSessionLogger` (P3 задача)
    *   Не меняем публичный API

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `RecordDynamicRoundService` инжектит `ChainSessionWriterInterface`
- [ ] `CheckDynamicBudgetService` инжектит `ChainSessionWriterInterface`
- [ ] `RunDynamicLoopAgentService` инжектит `ChainSessionWriterInterface`
- [ ] DI-конфигурация обновлена
- [ ] Все тесты проходят (`vendor/bin/phpunit`, `vendor/bin/psalm`)

### ⚫ Won't Have (Не будем делать)
- [ ] Расщепление реализации ChainSessionLogger
- [ ] Создание новых интерфейсов

## 4. Implementation Plan (План реализации)
1. [ ] Проверить, какие методы `ChainSessionWriterInterface` нужны каждому из 3 сервисов
2. [ ] Заменить `ChainSessionLoggerInterface` на `ChainSessionWriterInterface` в каждом сервисе
3. [ ] Обновить `config/services.yaml` — привязать `ChainSessionWriterInterface` к алиасу/реализации
4. [ ] Запустить проверки

## 5. Definition of Done (Критерии приёмки)
- [ ] 3 файла изменены (зависимость → `ChainSessionWriterInterface`)
- [ ] DI-конфигурация обновлена
- [ ] Все тесты проходят (`vendor/bin/phpunit`, `vendor/bin/psalm`)

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php vendor/prikotov/coding-standard/bin/run-sniff-tests.php
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск 1:** Один из сервисов может вызывать методы, которые не входят в `ChainSessionWriterInterface` — проверить перед переключением
- **Зависимость:** Нет внешних зависимостей

## 8. Sources (Источники)
- [ ] [Протокол brainstorm (решение 4)](../var/sessions/brainstorm/2026-04-29_08-06-49/result.md)

## 9. Comments (Комментарии)
Brainstorm-раунд 15: Левша обнаружил, что 0 потребителей используют Writer/Reader напрямую — интерфейсы расщеплены в Domain, но мертвы в реальности. Blast radius: всего 3 файла + DI config.

Action item #3 из brainstorm-протокола.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-29 | Тимлид (Алекс) | Создание задачи |
