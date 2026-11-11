---
type: fix
created: 2026-11-11 03:32:45 (1794367965)
due: 
started: 2026-11-11 03:33:46 (1794368026)
completed: 2026-11-11 04:14:50 (1794370490)
cancelled: 
value: V3
complexity: C2
priority: P1
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/complexity-required-watch-subagent
pr: https://github.com/prikotov/task-orchestrator/pull/479
status: done
---

# TASK-fix-watch-subagent-complexity-required: Требовать явный уровень сложности в watch-subagent.sh (--task-id или --complexity)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Механизм выбора модели по сложности (`--task-id`/`--complexity` + `commands_by_complexity` в `chains.yaml`) де-факто не работает: анализ журналов сессий проекта-потребителя показал, что ~96% запусков `watch-subagent.sh` не передают ни один из флагов.
- При отсутствии обоих флагов скрипт молча применяет дефолт `C3` — тиры моделей (дешёвая на C0, сильная на C4/C5) не включаются, и неверный выбор модели ничем себя не выдаёт.
- Дефолт был введён как мягкая совместимость, но на практике стал основным путём: дисциплина флагов не поддержана ни предупреждением, ни записью в run-log.

### Варианты или путь решения (Solution Sketch)
- Убрать молчаливый дефолт `C3`: отсутствие обоих флагов — ошибка запуска с внятным сообщением (fail-fast), без env-переключателей строгости.
- В `RUN START` run-log добавить строку `complexity=C<n> source=explicit|task-file` (+ `task_id`), чтобы выбор модели был виден в каждом запуске.
- Усилить документацию: SKILL.md и README.md скилла `run-subagent`, скиллы делегирования `task-via-subagents` и `epic-via-subagents`.

### Ожидаемый результат (Expected Result)
- Запуск `watch-subagent.sh` без `--task-id` и без `--complexity` завершается ошибкой до старта агента (ноль расхода токенов).
- Каждый run-log содержит фактический уровень сложности и его источник.
- Документация скиллов не содержит упоминаний дефолта `C3` и предписывает всегда передавать один из флагов.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как владелец оркестратора, я хочу, чтобы каждый запуск сабагента имел явный уровень сложности, чтобы настроенные тиры моделей реально работали, а неверный выбор модели был виден в журналах.

### Цель по SMART (Goal)
- Сделать уровень сложности обязательным параметром запуска `watch-subagent.sh` (ошибка при отсутствии `--task-id` и `--complexity`) и логировать применённый уровень в `RUN START` run-log; обновить документацию скиллов. Срок — один PR.

## 2. Контекст и Границы (Context and Scope)

- Точка изменения: `docs/agents/skills/run-subagent/scripts/watch-subagent.sh` (bash-валидация аргументов, PHP-блок `load_role_profile`, `log_run`-строка `RUN START`).
- Документация: `docs/agents/skills/run-subagent/SKILL.md`, `README.md` скилла, `docs/agents/skills/task-via-subagents/SKILL.md`, `docs/agents/skills/epic-via-subagents/SKILL.md`.
- Тесты: `tests/Integration/Docs/Agents/Skills/RunSubagent/WatchSubagentScriptTest.php` (31 существующий сценарий получает сложность через helper; добавляются новые fail-fast и лог-сценарии).
- Scope: скрипт, документация скиллов, интеграционные тесты.
- Out of scope: автоматическая оценка сложности агентом, изменение формата `chains.yaml`, env-переключатель строгости (явно отклонён владельцем), изменение CLI-оркестратора PHP.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Запуск без `--task-id` и без `--complexity` завершается с ошибкой (ненулевой код выхода) до запуска раннера; сообщение об ошибке объясняет оба способа задания уровня.
- [x] Некорректное значение `--complexity` (не `C0–C5`) отклоняется до запуска раннера.
- [x] `RUN START` run-log содержит строку вида `complexity=C2 source=task-file task_id=TASK-...` (для явного флага: `source=explicit task_id=<none>`).
- [x] Существующие сценарии запусков в тестах получают уровень сложности через helper без дублирования в каждом тесте.
- [x] Документация (SKILL.md, README.md, task-via-subagents, epic-via-subagents) не содержит упоминаний дефолта `C3` и описывает обязательность уровня.
### ⚫ Won't Have (Не будем делать)
- Env-переключатель строгости (владелец отклонил: строго всегда).
- Автоматический вывод уровня сложности из текста промпта.
- Изменение формата `chains.yaml` и логики выбора ближайшего тира.

## 4. План реализации (Implementation Plan)
1. [ ] `watch-subagent.sh`: bash-валидация — при пустых `TASK_ID` и `COMPLEXITY` ошибка с выходом до запуска раннера; ранняя проверка формата `--complexity` (`C0–C5`).
2. [ ] `watch-subagent.sh`: PHP-блок `load_role_profile` — убрать ветку дефолта `C3`, выводить шестой строкой резолв-уровень `C<n>`; bash-логирование в `RUN START` строки `complexity=... source=... task_id=...`.
3. [ ] Обновить CONTRACT-комментарий и `--help` скрипта (убрать «default: C3»).
4. [ ] Тесты: helper `runScript` подставляет `--complexity C3` по умолчанию; новые сценарии — отсутствие обоих флагов (ошибка, раннер не вызван), неверный формат, содержимое `complexity`-строки в run-log для `--complexity` и `--task-id`.
5. [ ] Документация: SKILL.md и README.md скилла `run-subagent`, `task-via-subagents`, `epic-via-subagents` — обязательность уровня, убрать упоминания дефолта.
6. [x] Проверки: `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные. `make check`: phpstan/deptrac/psalm/phpcs/md-links/validate-todo/validate-roles/validate-language/tests — зелёные; `phpmd` падает на ранее существовавших замечаниях `ExcessiveClassLength` (`src/Kernel.php`, `src/Module/ChainDefinition/.../YamlChainLoaderService.php`) — файлы этой веткой не тронуты, замечания присутствуют в выпущенной v0.14.1.

## 5. Критерии приёмки (Definition of Done)
- [ ] Все Must Have требования выполнены и отмечены.
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` зелёные.
- [ ] Документация согласована (поиск по «default: C3» и «C3» в скиллах не находит устаревших формулировок).
- [ ] Постановка задачи актуальна, ссылка на PR заполнена.

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Обратная несовместимость: потребители, вызывающие скрипт без флагов (в т.ч. внешние обёртки и CLI-оркестратор PHP, если он не передаёт уровень), начнут получать ошибку. Перед слиянием проверить вызовы внутри пакета.
- Явный отказ от env-обходного пути (решение владельца): breaking change фиксируется в CHANGELOG.

## 8. Источники (Sources)

## 9. Комментарии (Comments)

- Аналогичный фолбэк `C3` сохраняется в PHP-оркестраторе (`agent:orchestrate`) — заведена отдельная задача `todo/backlog/TASK-fix-orchestrate-complexity-required.todo.md`.
- Мелкое замечание, вне scope: в PHP-блоке скрипта сообщение `Expected exactly one file for task "%s".\n` выводит литеральный `\n` (одинарные кавычки в `sprintf`).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-11 03:32:45 (1794367965) | team_lead_alex | Создание задачи |
