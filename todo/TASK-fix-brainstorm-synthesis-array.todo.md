---
# Metadata (Метаданные)
type: fix
created: 2026-04-27
value: V3
complexity: C0
priority: P0
depends_on:
epic: EPIC-fix-brainstorm-session-bugs
author: Тимлид (Алекс) (pi)
assignee: Бэкендер (Левша) (pi)
branch: task/fix-brainstorm-synthesis-array
pr: https://github.com/prikotov/task-orchestrator/pull/84
status: in_progress
---

# TASK-fix-brainstorm-synthesis-array: Исправление бага synthesis=«Array» в result.md

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как фасилитатор brainstorm-сессии, я хочу чтобы `result.md` содержал осмысленный текст synthesis, а не слово «Array», чтобы протокол сессии был читаем без ручного восстановления.

### Goal (Цель по SMART)
Исправить баг в `FacilitatorResponseParserService::parse()`: когда LLM возвращает `synthesis` как массив строк (например, `{"done": true, "synthesis": ["line1", "line2"]}`), код делает `(string)$json['synthesis']`, что в PHP превращает массив в строку `"Array"`. Нужно добавить проверку типа и склеивать массив через `\n`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Infrastructure/Service/Chain/FacilitatorResponseParserService.php` (строка 33)
*   **Текущее поведение:** `(string)($json['synthesis'] ?? $llmText)` — если `$json['synthesis']` — массив, PHP приводит его к строке `"Array"`
*   **Границы (Out of Scope):** Не меняем `FacilitatorResponseVo`, `ChainSessionLogger`, промпты

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Если `$json['synthesis']` — массив, склеить его через `\n` перед передачей в `createFromDone()`
- [ ] Если `$json['synthesis']` — строка, поведение не меняется
- [ ] Если `$json['synthesis']` — другой тип (число, null), поведение не меняется
- [ ] Unit-тест для случая `synthesis` = массив строк
- [ ] Unit-тест для случая `synthesis` = массив с одним элементом
- [ ] Существующие тесты не ломаются

### 🟡 Should Have (Желательно)
- [ ] Unit-тест для вложенного массива (массив массивов) — склеить рекурсивно или взять как есть

### ⚫ Won't Have (Не будем делать)
- [ ] Валидация структуры массива (ожидаем только строки)

## 4. Implementation Plan (План реализации)
1. [ ] В `FacilitatorResponseParserService::parse()` добавить метод `normalizeSynthesis(mixed $value): string`
2. [ ] Заменить `(string)($json['synthesis'] ?? $llmText)` на вызов `normalizeSynthesis`
3. [ ] Добавить unit-тесты: массив строк, массив с одним элементом, вложенный массив

## 5. Definition of Done (Критерии приёмки)
- [ ] `parser->parse('{"done": true, "synthesis": ["A", "B"]}')` возвращает VO с `getSynthesis() === "A\nB"`
- [ ] `parser->parse('{"done": true, "synthesis": "text"}')` — поведение не изменилось
- [ ] PHPUnit проходит

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Infrastructure/Service/Chain/FacilitatorResponseParserServiceTest.php
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Минимальный риск — изолированное изменение в одном методе

## 8. Sources (Источники)
- [ ] [FacilitatorResponseParserService](../src/Module/Orchestrator/Infrastructure/Service/Chain/FacilitatorResponseParserService.php)
- [ ] [Существующие тесты](../tests/Unit/Infrastructure/Service/Chain/FacilitatorResponseParserServiceTest.php)

## 9. Comments (Комментарии)
Баг обнаружен в сессии `var/sessions/brainstorm/2026-04-27_12-29-03/` — `result.md` содержит `## Synthesis\nArray`.

## Инструкции для сабагента

**Ветка:** task/fix-brainstorm-synthesis-array (уже создана и активна)
**PR:** уже создан (draft) из task/fix-brainstorm-synthesis-array в fix/epic-brainstorm-session-bugs — [PR #84](https://github.com/prikotov/task-orchestrator/pull/84)

### Порядок действий
1. Переключись в ветку `task/fix-brainstorm-synthesis-array`: `git checkout task/fix-brainstorm-synthesis-array`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) (pi) | Создание задачи |
