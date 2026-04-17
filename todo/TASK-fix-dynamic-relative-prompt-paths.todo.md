---
type: fix
created: 2026-04-16
value: V3
complexity: C2
priority: P0
depends_on:
epic:
author: Бэкендер (Левша)
assignee:
branch:
pr:
status: todo
---

# TASK-fix-dynamic-relative-prompt-paths: Dynamic chain использует относительные пути к prompt-файлам

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда я запускаю dynamic-цепочку (brainstorm), я хочу чтобы агенты запускались корректно, а не падали с 0 токенов из-за ненайденных prompt-файлов.

### Goal (Цель по SMART)
Исправить ChainSessionLogger::writePromptFile() — должен возвращать абсолютные пути к файлам. Сейчас возвращает относительные, pi не находит файлы и мгновенно падает.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Common/Module/Orchestrator/Infrastructure/Service/Chain/ChainSessionLogger.php`
*   **Текущее поведение:** `writePromptFile()` возвращает `$this->currentSessionDir . '/' . $fileName` — относительный путь (например `var/sessions/brainstorm/2026-04-17_05-15-41/step_001_round_001_system_architect_1_system.md`). Pi запускается из CWD, не находит файл, возвращает пустой вывод (0 tokens, 0s).
*   **Границы (Out of Scope):** Static chain, app:agent:run — работают корректно

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `writePromptFile()` возвращает абсолютный путь (через `realpath()` или добавлением `getcwd()`)
- [ ] Dynamic brainstorm (`php bin/console app:agent:orchestrate "task" -c brainstorm`) завершается успешно
### 🟡 Should Have (Желательно)
- [ ] Unit-тест на абсолютный путь
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)

## 4. Implementation Plan (План реализации)
1. [ ] Найти и исправить `writePromptFile()` — `realpath($this->currentSessionDir . '/' . $fileName)`
2. [ ] Проверить другие методы ChainSessionLogger на аналогичную проблему
3. [ ] Протестировать `php bin/console app:agent:orchestrate "task" -c brainstorm`

## 5. Definition of Done (Критерии приёмки)
- [ ] `php bin/console app:agent:orchestrate "test" -c brainstorm` запускает агентов (non-zero tokens)
- [ ] Static chain не сломана
- [ ] PHPUnit проходит

## 6. Verification (Самопроверка)
```bash
php bin/console app:agent:orchestrate "test" -c brainstorm -vvv
vendor/bin/phpunit
```

## 7. Risks and Dependencies (Риски и зависимости)
- `realpath()` вернёт false если директория ещё не создана — нужно убедиться что createDirectory() вызывается раньше

## 8. Sources (Источники)

## 9. Comments (Комментарии)
Обнаружено при попытке запустить brainstorm: `app:agent:run` работает (промпты передаются inline), а dynamic chain падает (prompt-файлы через `--system-prompt <path>` с относительными путями).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-16 | Бэкендер (Левша) | Создание задачи |
