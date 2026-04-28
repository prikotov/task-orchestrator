---
type: fix
created: 2026-04-28
value: V3
complexity: C1
priority: P1
depends_on: TASK-agent-runner-proxy
epic:
author: Бэкендер Левша (backend_developer_levsha)
assignee: Бэкендер Левша (backend_developer_levsha)
branch: task/fix-codex-jsonl-parser-v1250
pr: https://github.com/prikotov/task-orchestrator/pull/93
status: done
---

# TASK-codex-jsonl-parser-v1250: Починить CodexJsonlParser под формат codex CLI v0.125.0

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда codex CLI возвращает JSONL-поток, я хочу, чтобы CodexJsonlParser корректно извлекал текст ответа и usage-метрики, чтобы оркестратор получал полные результаты выполнения сабагента.

### Goal (Цель по SMART)
Починить `CodexJsonlParser` для совместимости с реальным форматом вывода codex CLI v0.125.0. Парсер должен извлекать `text` из `item.completed` и `usage` из `turn.completed`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/AgentRunner/Infrastructure/Service/Codex/CodexJsonlParser.php`
    *   `tests/Unit/Infrastructure/Service/AgentRunner/Codex/CodexJsonlParserTest.php`
*   **Текущее поведение:** Парсер не извлекает текст ответа и usage-метрики из JSONL-потока codex v0.125.0.
    *   `item.completed` → `item.text` (парсер ожидает `item.content[].text`) — текст не извлекается.
    *   `turn.completed` → `usage` на верхнем уровне (парсер ожидает `turn.usage`) — токены = 0.
    *   `turn.completed` → нет вложенного `turn.items` (парсер ожидает `turn.turn.items`) — fallback-извлечение не работает.
*   **Границы (Out of Scope):**
    *   Обратная совместимость со старыми форматами codex (если они не используются)
    *   Изменение Domain-слоя (VO, интерфейсы)
    *   Интеграция с прокси-мостом

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Парсер извлекает текст из `item.completed` при наличии `item.text` (строка)
- [ ] Парсер извлекает текст из `item.completed` при наличии `item.content[]` (массив блоков) — обратная совместимость
- [ ] Парсер извлекает usage-метрики из `turn.completed` при верхнеуровневом `usage` (без вложенности в `turn`)
- [ ] Парсер извлекает usage-метрики из `turn.completed` при `turn.usage` — обратная совместимость
- [ ] Unit-тесты с реальными JSONL-данными codex v0.125.0

### 🟡 Should Have (Желательно)
- [ ] Извлечение `reasoning_output_tokens` из usage

### 🟢 Could Have (Опционально)
- [ ] Логирование предупреждения при неизвестном формате события

### ⚫ Won't Have (Не будем делать)
- [ ] Поддержка стриминга (частичных событий)
- [ ] Изменение контракта `parse()` — возвращаемая структура остаётся той же

## 4. Implementation Plan (План реализации)

1. ✅ `extractTurnCompleted()` — поиск `usage` на верхнем уровне `$decoded` (v0.125.0), fallback на `$decoded['turn']['usage']` (обратная совместимость)
2. ✅ `extractItemText()` — извлечение текста из `item.text` (v0.125.0), fallback на `item.content[]` (обратная совместимость)
3. ✅ Добавлен `reasoningOutputTokens` в возвращаемый массив `parse()`
4. ✅ Обновлён PHPDoc с документацией обоих форматов
5. ✅ Unit-тесты: 7 новых тестов для codex v0.125.0 + обновлённые assertions

## 5. Definition of Done (Критерии приёмки)
- [ ] Парсер корректно обрабатывает реальный JSONL-вывод codex v0.125.0
- [ ] Unit-тесты покрывают оба формата (v0.125.0 и старый content[])
- [ ] PHPUnit и Psalm без новых ошибок

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Формат codex CLI может измениться в следующих версиях — стоит сделать парсер устойчивым к отсутствию полей
- Нет документации на формат JSONL codex CLI — опираемся на реальный вывод

## 8. Sources (Источники)
- [TASK-agent-runner-proxy](done/TASK-agent-runner-proxy.todo.md) — задача, в ходе которой обнаружен баг
- Реальный JSONL-вывод codex v0.125.0:
  ```jsonl
  {"type":"thread.started","thread_id":"..."}
  {"type":"turn.started"}
  {"type":"item.completed","item":{"id":"item_0","type":"agent_message","text":"PING_OK"}}
  {"type":"turn.completed","usage":{"input_tokens":26231,"cached_input_tokens":15744,"output_tokens":84,"reasoning_output_tokens":76}}
  ```

## 9. Comments (Комментарии)
Баг обнаружен при E2E-тестировании TASK-agent-runner-proxy с реальным codex CLI через прокси. Codex завершил успешно, но CodexAgentRunner вернул пустой результат (outputText='', tokens=0).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-28 | Бэкендер Левша | Создание задачи |
