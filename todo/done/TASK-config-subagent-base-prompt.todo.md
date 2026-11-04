---
type: feat
created: 2026-11-04 10:10:37 (1793787037)
due: 
started: 2026-11-04 10:11:31 (1793787091)
completed: 2026-11-04 10:27:31 (1793788051)
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: backend_developer_levsha
assignee: backend_developer_levsha
branch: task/subagent-base-system-prompt
pr: https://github.com/prikotov/task-orchestrator/pull/457
status: done
---

# TASK-config-subagent-base-prompt: Сохранить базовые инструкции сабагентов при погружении в роль

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Ролевой `prompt_file` передаётся Pi через `--system-prompt` и заменяет базовые описания инструментов и правила работы.

### Варианты или путь решения (Solution Sketch)
- Добавить общий базовый промпт сабагента и передавать ролевую инструкцию через `--append-system-prompt`.

### Ожидаемый результат (Expected Result)
- Сабагент сохраняет инструкции по инструментам и одновременно получает назначенную роль.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как владелец оркестратора, я хочу разделять базовые рабочие инструкции и роль, чтобы смена роли не ухудшала работу сабагента с инструментами.

### Цель по SMART (Goal)
- Поддержать `system_prompt_file` в конфигурации ролей и применить его ко всем штатным ролям без изменения динамических цепочек.

## 2. Контекст и Границы (Context and Scope)

- Scope: загрузка `config/chains.yaml`, статические agent-шаги, Pi/Codex runner args, документация ролей и тесты.
- Out of scope: выбор модели по сложности задачи и изменение содержимого ролевых файлов.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Сохранить описания инструментов и стандартные правила в отдельном базовом промпте.
- [x] Передавать `prompt_file` как дополнение при заданном `system_prompt_file`.
- [x] Сохранить прежнее поведение для ролей без `system_prompt_file` и для динамических цепочек.
### ⚫ Won't Have (Не будем делать)
- Автоматическая синхронизация базового промпта с будущими версиями Pi.

## 4. План реализации (Implementation Plan)
1. [x] Добавить базовый промпт и конфигурационное поле.
2. [x] Пробросить базовый и ролевой промпты в запуск статического шага и fallback.
3. [x] Предотвратить дублирование append-промпта Pi.
4. [x] Обновить документацию и тесты.

## 5. Критерии приёмки (Definition of Done)
- [x] Штатные роли используют `prompts/subagent/base_system.md` как системный промпт.
- [x] Ролевая инструкция добавляется отдельно и только один раз.
- [x] PHPUnit и Psalm завершаются успешно.

## 6. Самопроверка (Verification)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Базовый промпт является снимком инструкций Pi 1.5.0 и требует ручной синхронизации при их изменении.

## 8. Источники (Sources)

- `docs/cli.md` и `dist/core/system-prompt.js` пакета `@earendil-works/pi-coding-agent` 1.5.0.

## 9. Комментарии (Comments)

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-04 10:10:37 (1793787037) | backend_developer_levsha | Создание задачи |
| 2026-11-04 | backend_developer_levsha | Реализация базового и ролевого системных промптов |
