---
type: fix
created: 2026-07-07
value: V1
complexity: C3
priority: P3
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Бэкендер Левша (codex-cli)
branch: task/close-pi-static-chain-system-prompt
pr: https://github.com/prikotov/task-orchestrator/pull/304
status: review
---

# TASK-fix-pi-static-chain-system-prompt: pi-роли не получают role-prompt в static chains

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
В static chains (`analyze`, `implement`, `hotfix`) pi-роли запускались без своего role-specific system prompt и фактически работали на default-prompt pi.

### Варианты или путь решения (Solution Sketch)
Исходная постановка допускала два пути: вариант A — резолвить `@system-prompt` в `PiAgentRunnerService`; вариант B — убрать `@system-prompt` из `chains.yaml` и положиться на авто-добавление `--system-prompt <path>`. Реализован вариант A через `resolvePromptMarkers`, без изменения `chains.yaml`.

### Ожидаемый результат (Expected Result)
pi-роль в static chain получает путь к своему `prompt_file` как `system-prompt`; literal `@system-prompt` не уходит в pi CLI. Корректность подтверждена unit/contract tests и полным набором локальных проверок: PHPUnit, Psalm, Deptrac, PHPCS и PHPMD проходят успешно. Live-проверка не подтверждена из-за `429 Usage limit reached` у провайдера `zai`; это исключение явно согласовано пользователем и не выдаётся за PASS.

## 1. Concept and Goal (Концепция и Цель)

### Story (User Story)
Как пользователь static chain (статической цепочки) с pi-ролями, я хочу, чтобы роль получала свой `prompt_file` как `system-prompt` (системный промпт), чтобы агент работал в нужной роли, а не на default-prompt (промпте по умолчанию) pi.

### Goal (Цель по SMART)
Починить передачу role-specific system prompt (ролевого системного промпта) для pi-ролей в static chains (`analyze`, `implement`, `hotfix`): при наличии маркера `@system-prompt` в command (команде) он должен резолвиться в путь из `AgentRunRequest::getSystemPrompt()`, а не передаваться в pi CLI как literal (буквальная строка).

## 2. Context and Scope (Контекст и Границы)

### Проблема простыми словами (Problem)
В static chains (`analyze`, `implement`, `hotfix`) pi-роли запускались **без своего role-specific system prompt** — на default-prompt pi. Codex после PR #299 получает свой prompt правильно; pi — нет.

### Почему (Root Cause)
1. pi-команда роли (`chains.yaml`) несёт literal `--system-prompt @system-prompt` (два элемента: `--system-prompt`, `@system-prompt`).
2. `PiAgentRunnerService::buildCommand`: так как `--system-prompt` уже есть в command — не добавляет `$request->getSystemPrompt()` (path) повторно.
3. `resolveCommandFiles` обрабатывает `@file` (`str_starts_with('@')`) → пытается найти файл `system-prompt` (без каталога) → не находит → оставляет literal `@system-prompt`.
4. pi CLI получает `--system-prompt @system-prompt` (literal), молча игнорирует неразрезолвленный маркер → бежит на default-prompt.

### Варианты решения из исходной постановки
- **A.** Добавить в `PiAgentRunnerService` resolution (разрешение) маркера `@system-prompt` → путь из `getSystemPrompt()`; отдельно от `resolveCommandFiles`, который отвечает за `@file` → содержимое. Это симметрично `CodexAgentRunnerService::resolvePromptSlots`.
- **B.** Убрать literal `@system-prompt` из pi-command в `chains.yaml` и положиться на авто-добавление `--system-prompt <path>` из `getSystemPrompt()` в `buildCommand`, если `--system-prompt` отсутствует.

Выбран и реализован вариант **A**: отдельное разрешение маркера `@system-prompt` через `resolvePromptMarkers` в pi runner (раннере), без изменения контракта `chains.yaml`.

### Scope (Границы)
- **Где делаем:** `PiAgentRunnerService::buildCommand` и связанная логика сборки pi command (команды) для static chains.
- **Проверяемые сценарии:** pi command с `@system-prompt`, pi command без `@system-prompt`, application-level (уровень приложения) передача `prompt_file` как `systemPrompt` в runner request (запрос раннера).
- **Out of Scope (вне задачи):** не менять контракт `chains.yaml`, не менять codex runner, не добавлять новые fallback (резервные пути) и не расширять поведение pi runner сверх передачи role prompt (ролевого промпта).
- **Согласованное расширение scope (границ):** пользователь явно разрешил в этой же ветке исправить `ProcessLivenessWatcher`, потому что это было препятствие для зелёных проверок. Фактическая доработка шире одной обработки `preg_split() === false`: добавлены PHPMD-safe cleanup (чистка под PHPMD) с описательными именами локальных переменных и приватные безопасные обёртки `executeShellCommandSilently()`, `isReadableSilently()`, `readFileContentsSilently()` для `shell_exec()`, `is_readable()`, `file_get_contents()`. Обёртки используют временный error handler (обработчик ошибок), `catch Throwable` и обязательный `restore_error_handler()` в `finally`. Успешное runtime-поведение liveness-логики не менялось; fallback (резервный результат) остаётся `0` / `[]` / `false` / `null` по месту использования.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [x] pi-роль в static chain получает свой `prompt_file` как `system-prompt`, а не literal `@system-prompt`.
- [x] `PiAgentRunnerService::buildCommand` при `@system-prompt` в command и `systemPrompt`=path формирует command с path, а не literal.
- [x] Существующие pi-command без `@system-prompt` не сломаны: сохраняется fallback на `getSystemPrompt()`.
- [x] На уровне application contract (контракт приложения) `ExecuteAgentStepService` передаёт role `prompt_file` как `systemPrompt` в runner request.

### 🟡 Should Have (Желательно)
- [x] Реализация симметрична подходу codex runner и не смешивает `@system-prompt` с `@file`-подстановками.

### 🟢 Could Have (Опционально)
- [ ] Дополнительная диагностика вокруг лимитов внешнего провайдера `zai`, если потребуется для отдельной задачи.

> Live-критерий остаётся обязательным в Definition of Done ниже. Текущий live-запуск был предпринят, но провайдер `zai` вернул `429 Usage limit reached`, input tokens `0`. Пользователь явно согласовал исключение для этой проверки: закрытие допускается без успешной live-проверки, если остальные обязательные проверки зелёные и исключение честно зафиксировано.

### ⚫ Won't Have (Не будем делать)
- [x] Не менять `chains.yaml` ради удаления `@system-prompt`.
- [x] Не менять публичный контракт runner request сверх существующего `systemPrompt`.
- [x] Не чинить и не настраивать лимиты внешнего провайдера `zai` в рамках этой задачи.

## 4. Implementation Plan (План реализации)

1. [x] Сопоставить поведение pi runner с codex runner для `@system-prompt`.
2. [x] Реализовать вариант A: добавить `resolvePromptMarkers` для pi command и подставлять путь из `getSystemPrompt()` вместо literal `@system-prompt`.
3. [x] Сохранить существующий fallback для pi-command без `@system-prompt`.
4. [x] Добавить unit test (модульный тест) на сборку команды pi с `@system-prompt` и `systemPrompt`=path.
5. [x] Добавить/подтвердить contract test (контрактный тест) на передачу `prompt_file` как `systemPrompt` из `ExecuteAgentStepService`.
6. [x] Перенести PHPDoc `list<string>` к правильному методу `PiAgentRunnerService::resolveCommandFiles()` без изменения runtime-поведения.
7. [x] По явному разрешению пользователя доработать `ProcessLivenessWatcher`: обработать `preg_split() === false`, привести локальные переменные к PHPMD-safe (безопасным для PHPMD) описательным именам и изолировать потенциально шумные системные вызовы в приватные безопасные обёртки с временным error handler (обработчиком ошибок), `catch Throwable` и `restore_error_handler()` в `finally`.
8. [x] Подтвердить, что успешное runtime-поведение liveness-логики не изменилось, а fallback (резервный результат) остаётся прежним по месту использования: `0` / `[]` / `false` / `null`.
9. [x] Выполнить финальную проверку и зафиксировать результаты ревью; PR ещё не оформлен, задача остаётся `in_progress`.

## 5. Definition of Done (Критерии приёмки)

- [x] pi-роль в static chain получает свой `prompt_file` как system-prompt по unit/contract tests.
- [x] Unit test для `PiAgentRunnerService::buildCommand` подтверждает замену `@system-prompt` на путь.
- [x] Сценарий pi-command без `@system-prompt` не сломан.
- [x] Contract test `ExecuteAgentStepService::runPassesRolePromptFileAsSystemPrompt` подтверждает передачу role prompt file в runner request.
- [ ] Live-проверка через реальную static chain с pi-шагом: pi загружает role prompt, что видно по input tokens / поведению. Исключение явно согласовано пользователем: текущий live-запуск заблокирован внешним лимитом `zai` (`429 Usage limit reached`, input tokens `0`), поэтому успешная live-проверка не подтверждена.
- [x] Psalm 0, PHPUnit green, Deptrac/PHPCS/PHPMD чисто.

## 6. Verification (Самопроверка)

Факты финальной проверки:

```text
TMPDIR=/tmp/task-orchestrator-close-prompt-check make check: PASS
PHPStan: PASS
Deptrac: PASS, 0 violations
Psalm: PASS
PHPMD: PASS, 0 violations
PHPCS: PASS
MD-links: PASS
validate-todo: PASS
validate-roles: PASS
PHPUnit: PASS, 1339 tests / 3614 assertions
git diff --check: PASS
```

Финальный Self-review Approval (одобрение самопроверки) от Бэкендера Левши получен; Change Requests (запросов на изменение) нет. Финальный Code Review Approval (одобрение ревью кода) от Ревьювера Бэка Пуаро получен; Change Requests нет. Live-проверка через реальную static chain была предпринята, но не дала подтверждения корректности: провайдер `zai` вернул `429 Usage limit reached`, input tokens `0`. Это не считается успешной live-проверкой; исключение по этому критерию явно согласовано пользователем.

## 7. Risks and Dependencies (Риски и зависимости)

- Внешняя зависимость: live-проверка зависит от доступности и лимитов провайдера `zai`; в момент проверки он вернул `429 Usage limit reached`.
- Риск регрессии pi command снижен unit tests для сборки command и contract test для передачи `prompt_file`.
- Изменение `chains.yaml` не выполнялось, чтобы не менять контракт static chains.

## 8. Sources (Источники)

- Исторический PR исходной реализации: https://github.com/prikotov/task-orchestrator/pull/300
- Merge commit: https://github.com/prikotov/task-orchestrator/commit/b390980310bd219dbfa18de60eed4a683866eef9
- Исходная ветка исторического PR: `task/chore-pi-static-chain-system-prompt-task`
- Текущая ветка доработки: `task/close-pi-static-chain-system-prompt`
- Связанный контекст: PR #299 починил codex-поведение; pi-дефект оставался latent (скрытым).

## 9. Comments/Result (Комментарии/Результат)

Итоговый результат текущего этапа: исходная реализация была оформлена в историческом PR #300, merge commit `b390980310bd219dbfa18de60eed4a683866eef9`. Выбран вариант **A**: в pi runner добавлено разрешение marker (маркера) `@system-prompt` через `resolvePromptMarkers`, чтобы static chain передавала путь к role prompt file (файлу ролевого промпта), а не literal `@system-prompt`.

Текущая доработка выполнена Бэкендером Левшой (`codex-cli`). В `PiAgentRunnerService` PHPDoc `list<string>` перенесён к правильному методу `resolveCommandFiles()`; runtime-поведение не менялось. Пользователь явно разрешил в этой же ветке доработать `ProcessLivenessWatcher`. Фактически исправлены обработка `preg_split() === false`, PHPMD-safe cleanup (чистка под PHPMD) через описательные имена локальных переменных и безопасная изоляция вызовов `shell_exec()`, `is_readable()`, `file_get_contents()` в приватные обёртки `executeShellCommandSilently()`, `isReadableSilently()`, `readFileContentsSilently()`. Обёртки временно устанавливают error handler (обработчик ошибок), ловят `Throwable` и гарантированно восстанавливают обработчик через `restore_error_handler()` в `finally`. Успешный runtime-путь liveness-логики не изменён; fallback (резервный результат) остаётся прежним по месту использования: `0` / `[]` / `false` / `null`.

Review verdict (вердикт ревью): финальный Self-review Approval (одобрение самопроверки) от Бэкендера Левши получен, Change Requests (запросов на изменение) нет. Финальный Code Review Approval (одобрение ревью кода) от Ревьювера Бэка Пуаро получен, Change Requests нет. Создан PR (запрос на слияние) #304: https://github.com/prikotov/task-orchestrator/pull/304. PR открыт (`OPEN`), base branch (базовая ветка) — `main`, head branch (рабочая ветка) — `task/close-pi-static-chain-system-prompt`, author (автор) — GitHub App `prikotov-agent` (`app/prikotov-agent` в gh JSON), label (метка) — `codex-cli`. Задача переведена из `in_progress` в `review`; в `todo/done/` не переносилась, потому что человеческого approval (одобрения) и явного разрешения на merge (слияние) ещё нет. Финальная локальная проверка `TMPDIR=/tmp/task-orchestrator-close-prompt-check make check` прошла успешно: PHPStan, Deptrac (0 violations), Psalm, PHPMD (0 violations), PHPCS, MD-links, validate-todo, validate-roles и PHPUnit (1339 tests / 3614 assertions) — PASS; `git diff --check` — PASS. Проверяемые факты по историческому PR #300: PR author — GitHub App `prikotov-agent[bot]`, label `pi`, assignees отсутствуют. Live-проверка не подтверждена из-за лимита провайдера `zai`; пользователь согласовал исключение по live-критерию на основании unit/contract tests и зелёных локальных проверок.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-07 | Тимлид Алекс | Создание задачи с описанием дефекта передачи `@system-prompt` для pi в static chains. |
| 2026-07-11 | Технический писатель Гермиона | Ретроспективное оформление исходной реализации: добавлены исторический PR #300, merge commit, выбранный вариант A (`resolvePromptMarkers`), результаты проверок и согласованное исключение по live-проверке из-за `429` провайдера `zai`. |
| 2026-07-11 | Технический писатель Гермиона | Change Request Пуаро: задача возвращена из `todo/done/` в активные `todo/`, статус изменён на `in_progress`, текущая ветка доработки зафиксирована как `task/close-pi-static-chain-system-prompt`, `pr` очищен до нового PR; причина возврата позднее устранена текущей доработкой. |
| 2026-07-11 | Бэкендер Левша | Доработка по self-review: PHPDoc `list<string>` перенесён к `PiAgentRunnerService::resolveCommandFiles()`; по явному разрешению пользователя в этой же ветке доработан `ProcessLivenessWatcher`: добавлена обработка `preg_split() === false`, выполнена PHPMD-safe cleanup (чистка под PHPMD) локальных переменных, добавлены безопасные обёртки `executeShellCommandSilently()`, `isReadableSilently()`, `readFileContentsSilently()` для `shell_exec()`, `is_readable()`, `file_get_contents()` с временным error handler (обработчиком ошибок), `catch Throwable` и обязательным `restore_error_handler()` в `finally`. Успешное runtime-поведение liveness-логики не менялось; fallback (резервный результат) остаётся `0` / `[]` / `false` / `null` по месту использования. Проверки PHPUnit, Psalm, Deptrac, PHPCS, PHPMD и целевые тесты зелёные; live-проверка остаётся невыполненной из-за согласованного исключения по `429` провайдера `zai`. |
| 2026-07-11 | Технический писатель Гермиона | Актуализирован task-файл по Change Request self-review Левши: убраны устаревшие сведения о проверках и блокировке approval, DoD по локальным проверкам отмечен выполненным, live-критерий оставлен невыполненным с явно согласованным исключением. |
| 2026-07-11 | Технический писатель Гермиона | Финальное self-review: уточнён фактический scope (границы) доработки `ProcessLivenessWatcher`, удалены формулировки о том, что изменение ограничено только `preg_split()`, добавлен результат `make phpmd: PASS, No violations`. |
| 2026-07-11 | Технический писатель Гермиона | После финального self-review и code review актуализированы Verification (самопроверка), Comments/Result (комментарии/результат) и Change History (история изменений): зафиксированы approvals (одобрения) Левши и Пуаро без Change Requests, финальный `TMPDIR=/tmp/task-orchestrator-close-prompt-check make check: PASS`, `git diff --check: PASS`; устаревшие формулировки о предстоящем code review и Change Request по task-файлу удалены. `status` сохранён `in_progress`, `pr` оставлен пустым до создания PR. |
| 2026-07-12 | Технический писатель Гермиона | После создания PR #304 синхронизирован task-файл: поле `pr` заполнено ссылкой `https://github.com/prikotov/task-orchestrator/pull/304`, `status` изменён с `in_progress` на `review`, в Comments/Result зафиксированы факты PR (`OPEN`, base `main`, head `task/close-pi-static-chain-system-prompt`, author GitHub App `prikotov-agent`, label `codex-cli`). Задача не перенесена в `todo/done/`, потому что человеческого approval (одобрения) и явного разрешения на merge (слияние) ещё нет; согласованное исключение live-проверки сохранено без изменений. |
