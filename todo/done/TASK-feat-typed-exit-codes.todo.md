---
# Metadata (Метаданные)
type: feat
created: 2026-04-23
value: V3
complexity: C2
priority: P0
depends_on: TASK-chore-composer-library-type
epic: EPIC-feat-standalone-cli
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/feat-typed-exit-codes
pr: '#64'
status: done
---

# TASK-feat-typed-exit-codes: Typed exit codes для CLI-команды

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как AI-агент, я хочу получать разные exit codes в зависимости от типа ошибки, чтобы понимать причину сбоя и принимать решение о recovery без парсинга stderr.

### Goal (Цель по SMART)
Заменить один catch-all `Command::FAILURE` на enum `OrchestrateExitCode` с говорящими значениями. AI-агент (и скрипты CI) получает конкретный код: не «что-то сломалось», а «chain не найден» / «budget exceeded» / «невалидный конфиг».

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `src/` (Domain enum) + `apps/console/` (Command)
* **Текущее поведение:** все ошибки → `Command::FAILURE` (exit code 1). AI-агент вынужден парсить текст ошибки.
* **Границы:**
  - Не меняем формат вывода (stdout/stderr) — только exit codes
  - Не добавляем `--output=json` (отдельная задача)

## 3. Requirements (Требования)
### 🔴 Must Have
- [ ] Enum `OrchestrateExitCode` в Domain-слое:
  ```php
  enum OrchestrateExitCode: int {
      case SUCCESS = 0;
      case CHAIN_FAILED = 1;
      case CHAIN_NOT_FOUND = 3;
      case BUDGET_EXCEEDED = 4;
      case INVALID_CONFIG = 5;
      case TIMEOUT = 6;
  }
  ```
- [ ] `OrchestrateCommand` использует enum вместо `Command::FAILURE`/`Command::SUCCESS`
- [ ] Unit-тесты: каждый exit code возвращается в соответствующем сценарии

### 🟡 Should Have
- [ ] Таблица exit codes в README или `--help`

## 4. Implementation Plan
1. Создать `OrchestrateExitCode` enum в Domain
2. Обновить `OrchestrateCommand`: заменить все `return Command::FAILURE` на конкретные exit codes
3. Добавить `resolveExitCode()` метод (Вариант C из brainstorm: renderer → void, Command решает exit code)
4. Unit-тесты

## 5. Definition of Done
- [ ] `OrchestrateExitCode` enum создан
- [ ] Все сценарии возвращают конкретный exit code
- [ ] PHPUnit и Psalm зелёные
- [ ] Unit-тесты покрывают все cases

## 6. Verification
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks
- Обратная совместимость: существующие CI-скрипты могут проверять только exit code 0/1 — но проект в v0.x, breaking change допустим

## 8. Sources
- [RFC: cli-distribution-rfc.md](../docs/research/cli-distribution-rfc.md) — Brainstorm #2, решение про typed exit codes
- [OrchestrateCommand.php](../apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php) — текущая реализация

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/backend_developer_levsha.ru.md
**Ветка:** task/feat-typed-exit-codes (уже создана и активна)
**PR:** уже создан (draft) из task/feat-typed-exit-codes в epic/feat-standalone-cli — [PR #64](https://github.com/prikotov/task-orchestrator/pull/64)

### Порядок действий
1. Переключись в ветку `task/feat-typed-exit-codes`: `git checkout task/feat-typed-exit-codes`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-23 | Тимлид (Алекс) | Создание задачи из RFC brainstorm |
