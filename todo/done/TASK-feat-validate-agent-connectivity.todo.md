---
type: feat
created: 2026-05-25
value: V3
complexity: C2
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер Тони
branch: task/feat-validate-agent-connectivity
pr: https://github.com/prikotov/task-orchestrator/pull/235
status: done
---

# TASK-feat-validate-agent-connectivity: Верификатор запуска ролей — проверка что агент запускается и отвечает

## 0. Простое описание (Human Brief)
Создать CLI-команду `validate:connectivity`, которая берёт роли из `chains.yaml`, подставляет тестовый промпт и проверяет, что каждый настроенный агент (command) реально запускается и возвращает валидный ответ.

### Проблема простыми словами (Problem)
Пользователь настроил `chains.yaml`: прописал команды для ролей (`pi --mode json ...`, `codex exec ...`, с разными `--provider`, `--model`). Но нет способа быстро убедиться, что всё работает: бинарник существует, провайдер доступен, модель отвечает, формат ответа ожидаемый. Ошибки обнаруживаются только при реальном запуске цепочки — когда уже потрачено время.

### Варианты или путь решения (Solution Sketch)
Новая CLI-команда `validate:connectivity`:
1. Читает `chains.yaml`, извлекает все роли и их `command`.
2. Для каждой роли формирует минимальный тестовый промпт (например, «Ответь одним словом: ok»).
3. Запускает `command` с подставленным `@system-prompt` и тестовой задачей.
4. Проверяет: процесс завершился с exit code 0, stdout содержит читаемый ответ, время ответа в пределах таймаута.
5. Выводит таблицу: роль ✓/✗, время ответа, ошибка если есть.

### Ожидаемый результат (Expected Result)
```bash
php vendor/bin/task-orchestrator validate:connectivity
# или с опциями:
php vendor/bin/task-orchestrator validate:connectivity --config=path/to/chains.yaml --timeout=30

# Вывод:
# ----------------------------- ---------- -------- --------
#  Role                          Status     Time     Error
# ----------------------------- ---------- -------- --------
#  team_lead_alex                ✓ OK       2.3s
#  system_analyst_sherlock       ✓ OK       4.1s
#  system_architect_gandalf      ✗ FAIL     12.0s    exit code 1: model not found
#  backend_developer_tony        ✗ FAIL     30.0s    timeout
# ----------------------------- ---------- -------- --------
# 3/4 passed, 1 failed, 1 timeout
```

## 1. Concept and Goal (Концепция и Цель)
### Story
Как тимлид, я хочу одной командой проверить, что все агенты из конфига доступны и отвечают, чтобы убедиться в корректности настроек до запуска реальной цепочки.

### Goal
CLI-команда `validate:connectivity` в группе `validate`, интегрированная в `agent:orchestrate --validate-config` (или отдельная).

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** новая команда `apps/console/src/Module/Orchestrator/Command/ValidateConnectivityCommand.php`, использование существующих Domain-сервисов для парсинга конфига и запуска агентов.
*   **Исходные данные:** `config/chains.yaml` — роли с `command` и `prompt_file`.
*   **Границы:**
    - Проверяем только `command` — что бинарник/процесс запускается и отвечает.
    - НЕ проверяем корректность самого YAML (это уже делает `--validate-config`).
    - НЕ проверяем наличие `prompt_file` (это делает `--validate-config`).

## 3. Requirements (MoSCoW)

### 🔴 Must Have
- [x] Команда `validate:connectivity` — читает конфиг, перебирает роли, запускает каждую
- [x] Тестовый промпт: минимальный, требующий короткий ответ
- [x] Проверка exit code = 0, stdout не пустой
- [x] Таймаут на каждый агент (default: 30 сек, configurable через `--timeout`)
- [x] Таблица с результатами: роль, статус ✓/✗, время ответа, ошибка
- [x] Exit code: 0 если все ок, 1 если хотя бы один агент не ответил
- [x] Поддержка `--config` для указания произвольного `chains.yaml`
- [x] Опция `--role=<name>` для проверки конкретной роли (не всех)
- [x] Опция `--dry-run` — показать что будет запущено, без реального запуска

### 🟡 Should Have
- [x] Цветной вывод (зелёный ✓, красный ✗)
- [ ] Опция `--format=json` — машинно-читаемый результат
- [ ] Проверка что `command[0]` (бинарник) существует в `$PATH` перед запуском (быстрая проверка без таймаута)

### ⚫ Won't Have (Не будем делать)
- Полная интеграция в `--validate-config` (отдельная команда, другая ответственность)
- Проверка качества ответа (достаточно что не пустой и exit code 0)
- Параллельный запуск агентов (последовательно, чтобы не перегружать API)

## 4. Implementation Plan
1. Добавить Application use case (`RunRoleStartupCheckCommandHandler`) в `ChainDefinition` (модуль определения chain/role config), который возвращает Presentation-friendly DTO без Domain VO в CLI.
2. Ввести Domain interfaces (контракты) для чтения top-level `roles` из YAML и запуска процесса; реализации оставить в Infrastructure.
3. Реализовать Symfony Process runner (запуск процесса) только через argv array, последовательно по ролям, с timeout и проверкой `exitCode=0` + non-empty stdout.
4. Добавить CLI command (команду CLI) `validate:connectivity` с опциями `--config`, `--role`, `--timeout`, `--dry-run`, таблицей `Role | Status | Time | Error` и exit code `0/1`.
5. Покрыть handler unit-тестами через fakes/stubs и command integration-тестом через fake PHP agent без реальных LLM/внешних сервисов.
6. Обновить CLI documentation (документацию CLI), затем запустить targeted PHPUnit, полный PHPUnit, Psalm и при возможности `make check`.

## 5. Definition of Done
- [x] Команда `validate:connectivity` работает для всех ролей из `chains.yaml`
- [x] `--dry-run` показывает команды без запуска
- [x] Exit code корректный (0/1)
- [x] Unit-тесты на логику валидации
- [x] Integration-тест на команду с mock-агентом
- [x] Psalm + PHPUnit зелёные
- [x] Документация: `docs/guide/cli.md` обновлён

## 6. Verification
```bash
php vendor/bin/task-orchestrator validate:connectivity
php vendor/bin/task-orchestrator validate:connectivity --dry-run
php vendor/bin/task-orchestrator validate:connectivity --role=team_lead_alex
php vendor/bin/task-orchestrator validate:connectivity --timeout=60
```

## 7. Risks and Dependencies
- Разные раннеры (pi, codex) имеют разный формат ответа — нужно унифицировать проверку или опираться только на exit code + непустой stdout
- API-провайдеры могут быть временно недоступны — это не баг валидатора, но нужно адекватно отображать
- Стоимость: каждый запуск тратит API-токены — промпт должен быть минимальным

## 8. Sources
- [`config/chains.yaml`](../config/chains.yaml) — пример конфигурации ролей
- [`docs/guide/cli.md`](../docs/guide/cli.md) — документация CLI-команд
- [`apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php`](../apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php) — референс команды

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-25 | Тимлид (Алекс) | Создание задачи |
