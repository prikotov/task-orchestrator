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
branch: task/prompt-configuration-vo
pr: https://github.com/prikotov/task-orchestrator/pull/102
status: done
---

# TASK-refactor-prompt-configuration-vo: PromptConfiguration VO для ChainDefinitionVo

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда 7 промпт-полей протаскиваются через 4 слоя оркестрации (ChainDefinitionVo → BuildDynamicContextService → DynamicChainContextVo → ExecuteDynamicTurnService), я хочу инкапсулировать их в отдельный PromptConfigurationVo, чтобы уменьшить coupling и упростить добавление новых промптов.

### Goal (Цель по SMART)
Создать `PromptConfigurationVo`, инкапсулирующий 7 промпт-полей. Добавить метод `getPromptConfiguration()` в `ChainDefinitionVo`. Старые геттеры пометить `@deprecated`. Back-compat change, blast radius = 1 VO + 1 метод.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/Orchestrator/Domain/`
*   **Текущее поведение:** ChainDefinitionVo содержит 7 промпт-полей (system_prompt, user_prompt, dynamic_prompt, facilitator_prompt, participant_append, facilitator_start, facilitator_continue), которые передаются через цепочку сервисов как отдельные параметры.
*   **Границы (Out of Scope):**
    *   Не трогаем ChainDefinitionVo расщепление (P4 — отдельная задача)
    *   Не трогаем ExecutionStrategy (P2)
    *   Не меняем публичный CLI API

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Создан `PromptConfigurationVo` с 7 промпт-полями
- [ ] Добавлен метод `getPromptConfiguration(): PromptConfigurationVo` в `ChainDefinitionVo`
- [ ] 7 старых геттеров промптов помечены `@deprecated`
- [ ] `BuildDynamicContextService` использует `getPromptConfiguration()`
- [ ] Все существующие тесты проходят
- [ ] `vendor/bin/phpunit` + `vendor/bin/psalm` — без ошибок

### 🟡 Should Have (Желательно)
- [ ] Unit-тест на `PromptConfigurationVo`

### ⚫ Won't Have (Не будем делать)
- [ ] Удаление deprecated геттеров (отдельная задача в будущем)
- [ ] Расщепление ChainDefinitionVo

## 4. Implementation Plan (План реализации)
1. [ ] Создать `PromptConfigurationVo` в Domain (immutable, readonly)
2. [ ] Добавить `getPromptConfiguration()` в `ChainDefinitionVo`
3. [ ] Пометить 7 промпт-геттеров `@deprecated`
4. [ ] Обновить `BuildDynamicContextService` — использовать новый VO
5. [ ] Обновить `DynamicChainContextVo` — принимать `PromptConfigurationVo` вместо 7 полей
6. [ ] Добавить unit-тест на `PromptConfigurationVo`
7. [ ] Запустить проверки

## 5. Definition of Done (Критерии приёмки)
- [ ] `PromptConfigurationVo` создан в Domain
- [ ] `ChainDefinitionVo` содержит `getPromptConfiguration()`
- [ ] Deprecated геттеры помечены `@deprecated`
- [ ] `BuildDynamicContextService` использует новый VO
- [ ] Все тесты проходят (`vendor/bin/phpunit`, `vendor/bin/psalm`)

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php vendor/prikotov/coding-standard/bin/run-sniff-tests.php
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск 1:** YamlChainLoader и тесты конфигурации могут использовать старые геттеры — убедиться, что deprecated не ломает
- **Зависимость:** Нет внешних зависимостей

## 8. Sources (Источники)
- [ ] [Протокол brainstorm (решение 1)](../var/sessions/brainstorm/2026-04-29_08-06-49/result.md)

## 9. Comments (Комментарии)
Brainstorm-раунды 2-3: Локи первым идентифицировал проблему (7 промптов через 4 слоя), Левша предложил PromptConfigurationVo как quick fix с `@deprecated`. Все согласны — ROI > 0, back-compat change.

Action item #2 из brainstorm-протокола.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-29 | Тимлид (Алекс) | Создание задачи |
