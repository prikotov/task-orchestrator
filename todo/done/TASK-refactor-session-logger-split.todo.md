---
type: refactor
created: 2026-04-30
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-refactor-orchestrator-p3
author: pi
assignee: Бэкендер Левша
branch: task/refactor-session-logger-split
pr: https://github.com/prikotov/task-orchestrator/pull/115
status: done
---

# TASK-refactor-session-logger-split: Расщепление ChainSessionLogger

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда ChainSessionLogger содержит 536 LOC и смешивает запись, чтение, хранение и форматирование бюджета, я хочу расщепить его на 4 класса с единой ответственностью, чтобы каждый компонент сессии можно было тестировать и менять изолированно.

### Goal (Цель по SMART)
Расщепить ChainSessionLogger на: (1) Writer (~280 LOC — запись событий), (2) Reader (~60 LOC — чтение протокола), (3) FileStorage (~60 LOC — работа с файлами), (4) BudgetFormatter (~40 LOC — форматирование). Интерфейсы не меняются.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Infrastructure/Service/Chain/`
*   **Текущее поведение:** ChainSessionLogger = 536 LOC, God-объект Infrastructure-слоя
*   **Границы (Out of Scope):**
    *   Не меняем Domain-интерфейсы (ChainSessionWriterInterface, ChainSessionReaderInterface)
    *   Не меняем потребителей

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Writer — запись событий сессии
- [ ] Reader — чтение протокола сессии
- [ ] FileStorage — абстракция работы с файловой системой
- [ ] BudgetFormatter — форматирование бюджета для протокола
- [ ] Существующие интерфейсы не меняются
- [ ] Все тесты проходят

### 🟡 Should Have (Желательно)
- [ ] Unit-тесты на каждый класс

### ⚫ Won't Have (Не будем делать)
- [ ] Изменение Domain-слоя
- [ ] Изменение CLI

## 5. Definition of Done (Критерии приёмки)
- [ ] 4 класса вместо ChainSessionLogger (536 LOC)
- [ ] Ни один класс не превышает 300 LOC
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные
- [ ] Обновить Roadmap: статус AI#15 `📋` → `✅ Done`, добавить ссылку на задачу и PR

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Низкий риск: Infrastructure-слой, тесты на существующее поведение

## 8. Sources (Источники)
- [ ] [Roadmap AI#15](../../docs/releases/ROADMAP-2026-Q2-Q3.md)

## 9. Comments (Комментарии)
Roadmap Sprint 7. Выполняется параллельно с AI#16 (Shared/ reorg) перед физическим split Static (AI#17).

## Инструкции для сабагента

**Ветка:** task/refactor-session-logger-split (уже создана и активна)
**PR:** уже создан (draft) из task/refactor-session-logger-split в task/epic-refactor-orchestrator-p3 — [PR #115](https://github.com/prikotov/task-orchestrator/pull/115)

### Порядок действий
1. Переключись в ветку `task/refactor-session-logger-split`: `git checkout task/refactor-session-logger-split`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `make check`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-30 | pi | Создание задачи |

