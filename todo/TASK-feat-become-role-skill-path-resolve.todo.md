---
type: feat
created: 2026-07-17
value: V2
complexity: C1
priority: P2
depends_on:
epic:
author: pi
assignee: pi
branch: task/feat-become-role-skill-path-resolve
pr: https://github.com/prikotov/task-orchestrator/pull/315
status: review
---

# TASK-feat-become-role-skill-path-resolve: Подсказать агенту резолвить пути skill'ов через <location>

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Агент в host-проекте путался на относительном пути `scripts/watch-subagent.sh` в SKILL.md: не связывал его с `<location>`, искал в корне проекта, тратил лишние шаги. Подсказка в каталоге `become-role` была абстрактной.

### Варианты или путь решения (Solution Sketch)

Усилить подсказку в `FormatSkillCatalogService` и `become-role/SKILL.md` конкретным правилом: пути внутри skill лежат рядом с SKILL.md, каталог брать из `<location>` и подставлять перед путём. Решение централизовано в `become-role`.

### Ожидаемый результат (Expected Result)

Агент при работе с любым role-специфичным skill корректно строит абсолютный путь к скрипту через `<location>` и не ищет его в корне проекта.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда агент вызывает skill вроде `run-subagent` в host-проекте, а SKILL.md предписывает относительный путь `scripts/watch-subagent.sh`, я хочу, чтобы `become-role` явно учил резолвить его через `<location>`, чтобы агент не искал скрипт в корне проекта и не тратил лишние шаги.

### Goal (Цель по SMART)
Усилить подсказку в каталоге skills (`FormatSkillCatalogService`) и в `become-role/SKILL.md` конкретным правилом резолва: `dirname(<location>) + относительный путь`, с примером.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `src/Module/AgentRole/Domain/Service/FormatSkillCatalogService.php`, `tests/Unit/Module/AgentRole/Domain/Service/FormatSkillCatalogServiceTest.php`, `docs/agents/skills/become-role/SKILL.md`.
* **Текущее поведение:** подсказка уже есть («Относительные пути внутри skill разрешай относительно его каталога»), но абстрактна — без связи с `<location>` и без примера; эмпирически агент путается (рассуждения в stocks2 при работе с `run-subagent`).
* **Границы (Out of Scope):** не трогаем role-специфичные skills (`run-subagent` и др.) — подсказка централизована в `become-role`.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] HEADER каталога учит резолвить пути skill через `<location>` (`dirname(<location>) + путь`) с примером.
- [x] `become-role/SKILL.md` (пункт 4) содержит то же правило с примером.
- [x] Unit-тест покрывает новую подсказку.
### ⚫ Won't Have (Не будем делать)
- [ ] Правки `run-subagent` и других role-специфичных skills.

## 4. Implementation Plan (План реализации)
1. [x] Усилить HEADER в `FormatSkillCatalogService` примером резолва.
2. [x] Добавить assertion в unit-тест.
3. [x] Усилить пункт 4 в `become-role/SKILL.md`.
4. [x] `make check` зелёный.

## 5. Definition of Done (Критерии приёмки)
- [x] `make check` проходит (1462 tests).
- [x] Подсказка о резолве через `<location>` есть и в каталоге, и в SKILL.md.

## 6. Verification (Самопроверка)
```bash
make check
```

## 7. Risks and Dependencies (Риски и зависимости)
- Изменяется текст промпта каталога для всех ролей — минимальный оверхед контекста (несколько строк), оправдан снятием путаницы.

## 8. Sources (Источники)
- Эмпирика: рассуждения агента в host-проекте `stocks2` при работе `run-subagent` (поиск `scripts/watch-subagent.sh` в корне проекта).
- pi `dist/core/skills.js` (`formatSkillsForPrompt`).

## 9. Comments (Комментарии)
Централизованное решение в `become-role` вместо правки каждого role-специфичного skill.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-17 | pi | Создание задачи и реализация. |
