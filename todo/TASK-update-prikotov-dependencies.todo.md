---
type: chore
created: 2026-11-05
due: 
started: 
completed: 2026-11-06 02:28:04 (1793932084)
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: backend_developer_levsha
assignee: 
branch: task/update-prikotov-dependencies
pr: https://github.com/prikotov/task-orchestrator/pull/460
status: review
---

# TASK-update-prikotov-dependencies: Обновить пакеты prikotov и документацию

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Пакеты `prikotov/*` и скопированная документация устарели.

### Варианты или путь решения (Solution Sketch)
- Обновить Composer-зависимости и выполнить init-команды без изменения проектных конфигов.

### Ожидаемый результат (Expected Result)
- Установлены последние версии `prikotov/*`, документация синхронизирована, проверки зелёные.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как владелец проекта, я хочу получить актуальные правила и инструменты качества без потери локальных настроек.

### Цель по SMART (Goal)
- Обновить три пакета `prikotov/*`, поддержать выбор сложности (complexity) для PHP-цепочек и resume dynamic-цепочек и успешно выполнить `make check`.

## 2. Контекст и Границы (Context and Scope)

- Scope: `composer.json`, `composer.lock`, локальный init документации, поддержка выбора сложности (complexity) для PHP-цепочек (CLI `--complexity` → `commands_by_complexity` применяется при маппинге ролей, `RoleConfigVo::resolveCommandByComplexity()`) и resume dynamic-цепочек (сложность из session.json, явный CLI `--complexity` приоритетнее), fail-fast валидация `commands_by_complexity` в `YamlChainLoaderService`, документация `docs/guide/roles.md`, запись в `CHANGELOG.md`, прямые unit-тесты resume-сложности в `DynamicExecutionStrategyTest`.
- Out of scope: обновление остальных прямых зависимостей и изменение конфигурации проекта.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] `coding-standard` 0.34.0, `git-workflow` 0.4.2, `todo-md` 0.3.0.
- [x] Init документации выполнен без итоговых изменений конфигов.
- [x] `make check` успешен.
- [x] Доработка по review: `commands_by_complexity` валидируется в `YamlChainLoaderService` (уровни C0–C5, существование профилей, непустота inline-команд); выбор команды по сложности задачи — `RoleConfigVo::resolveCommandByComplexity()`, resume берёт сложность из session.json (явный CLI `--complexity` приоритетнее); unit-тесты на валидацию, выбор по сложности и прямые тесты resume-сложности; `docs/guide/roles.md` и `CHANGELOG.md` (несовместимое изменение 0.x) актуализированы.

### ⚫ Won't Have (Не будем делать)
- Обновление Symfony, PHPUnit и других пакетов вне запрошенной группы.

## 4. План реализации (Implementation Plan)

1. [x] Обновить Composer-ограничения и lock-файл.
2. [x] Выполнить init с сохранением конфигов.
3. [x] Устранить ошибки статического анализа и выполнить проверки.

## 5. Критерии приёмки (Definition of Done)

- [x] Lock содержит последние версии запрошенных пакетов.
- [x] Конфиги не изменены.
- [x] Все проверки проекта успешны.

## 6. Самопроверка (Verification)

```bash
make check
php vendor/bin/todo-md validate todo/TASK-update-prikotov-dependencies.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)

- Часть обновлённой документация находится в `.gitignore` и остаётся локальной.

## 8. Источники (Sources)

- README обновлённых пакетов `prikotov/*`.

## 9. Комментарии (Comments)

## История изменений (Change History)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-05 | backend_developer_levsha | Обновление зависимостей и документации |
| 2026-11-05 | backend_developer_levsha | Доработка по review Пуаро и Гэндальфа: fail-fast валидация commands_by_complexity в лоадере (без VO), unit-тесты, docs/guide/roles.md, CHANGELOG |
| 2026-11-05 | backend_developer_tony | Scope переформулирован: не удаление commandsByComplexity, а поддержка выбора сложности для PHP-цепочек и resume; финальное замечание Пуаро закрыто прямыми unit-тестами resume-сложности в DynamicExecutionStrategyTest (без CLI → сложность из session.json; CLI C2 → переопределяет сохранённую C5) |
