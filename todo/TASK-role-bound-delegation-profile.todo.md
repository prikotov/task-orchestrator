---
type: fix
created: 2026-06-02
value: V1
complexity: C3
priority: P1
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер Левша
branch: task/role-bound-delegation-profile
pr:
status: in_progress
---

# TASK-role-bound-delegation-profile: Учитывать профиль роли при делегировании сабагентов

## 0. Простое описание (Human Brief)
### Проблема простыми словами (Problem)
При делегировании AI-сабагентов роль может быть настроена на запуск через `codex` (консольный агент Codex) и конкретную `model` (модель), но часть путей запуска всё равно выбирает default `pi` (движок Pi). Из-за этого Тимлид при запуске из Codex может делегировать роли через неверный консольный агент.

### Варианты или путь решения (Solution Sketch)
Использовать существующий `roles.<role>.command` из `config/chains.yaml` как `delegation profile` (профиль делегирования роли) для выбора effective `runner` (фактического движка) и `model` (модели), если они не переопределены явно.

### Ожидаемый результат (Expected Result)
Если роль настроена на `codex`, делегирование без явного `--runner` не уходит в default `pi`; static-chain и skill-based delegation (делегирование через скилл) учитывают профиль роли.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда Тимлид делегирует задачу роли, я хочу заранее привязать к роли пару `console agent + model` (консольный агент + модель), чтобы разные роли запускались предсказуемо и не требовали ручного `--runner` при каждом вызове.

### Goal (Цель по SMART)
За один PR унифицировать MVP-резолвинг `runner` для static-chain и `watch-subagent.sh`: явный override имеет приоритет, затем применяется `roles.<role>.command`, затем допустим documented default `pi`.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:**
  * `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`
  * `docs/agents/skills/run-subagent/SKILL.md`
  * `src/Module/ChainExecution/Domain/Service/Static/ExecuteAgentStepService.php`
  * unit/integration tests (модульные/интеграционные тесты)
* **Текущее поведение:**
  * `watch-subagent.sh` default-ит `RUNNER=pi`.
  * static-chain может передать `runnerName=pi`, даже если `roleConfig.command[0]=codex`.
  * dynamic-chain уже ближе к нужному поведению, потому что использует `command[0]`, но это не входит в MVP кроме регресс-проверок.
* **Границы (Out of Scope):**
  * не вводить новую YAML-схему `roles.*.runner/model/reasoning`;
  * не перепроектировать `AgentRunner` registry;
  * не делать реальные LLM/network вызовы в тестах;
  * не менять `agent:run`, если это разрастит scope — можно зафиксировать follow-up.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] `watch-subagent.sh` без явного `--runner`/`RUNNER` должен брать runner из `roles.<role>.command[0]` по `--role-file`.
- [x] `watch-subagent.sh` без явного `--model`/`MODEL` должен брать model из `roles.<role>.command` при наличии `--model`.
- [x] CLI/env override (явное переопределение) сильнее профиля роли.
- [x] Static agent-step не должен отправлять `codex` command в `pi` runner: если step runner не задан явно, effective runner берётся из `roleConfig.command[0]`.
- [x] Тесты покрывают role command `codex` → runner `codex`, а не `pi`.
- [x] Документация `run-subagent` обновлена: приоритеты, примеры, ограничения.

### 🟡 Should Have (Желательно)
- [ ] Dry-run/report (сухой запуск/отчёт) показывает effective runner, если изменение небольшое.
- [ ] Добавить понятный fail-fast или предупреждение для неизвестного runner в профиле.

### 🟢 Could Have (Опционально)
- [x] Поддержать `reasoning` extraction (извлечение уровня рассуждения) из `roles.<role>.command`.
- [ ] Добавить helper script (скрипт-помощник) для печати профиля роли.

### ⚫ Won't Have (Не будем делать)
- [ ] Не добавляем typed schema `roles.*.runner/model/reasoning` в этом PR.
- [ ] Не меняем реальные настройки моделей в `config/chains.yaml` сверх необходимого для тестов.

## 4. Implementation Plan (План реализации)
1. [x] Проверить текущие тесты `ExecuteAgentStepService` и добавить regression case (регрессионный кейс) для `roleConfig.command[0]=codex`.
2. [x] Изменить static runner resolution (резолв движка): explicit step runner > role command runner > default.
3. [x] Обновить `watch-subagent.sh`: вычислять role name из `--role-file`, читать `config/chains.yaml`, извлекать runner/model при отсутствии explicit override.
4. [x] Добавить contract test (контрактный тест) для script через fake `pi`/`codex` в temp `PATH`.
5. [x] Обновить `run-subagent/SKILL.md`.
6. [x] Запустить `vendor/bin/phpunit` и `vendor/bin/psalm`.

## 5. Definition of Done (Критерии приёмки)
- [x] Роль с `command: [codex, ...]` запускается через `codex`, если runner не задан явно.
- [x] Явный `--runner pi` / `RUNNER=pi` продолжает работать как override.
- [x] `MODEL`/`--model` не затираются профилем роли.
- [x] Тесты не используют реальные внешние сервисы.
- [x] PHPUnit и Psalm зелёные.

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- `command[0]` сейчас одновременно executable (исполняемый файл) и runner key (ключ движка); для MVP принимаем только `pi`/`codex` из текущего registry.
- Нужно отличить implicit runner (не задан) от explicit `runner: pi`; при невозможности — задокументировать компромисс и покрыть тестами фактическое поведение.
- Bash YAML parsing (парсинг YAML в bash) не должен стать хрупким; при необходимости использовать `php` helper внутри script.

## 8. Sources (Источники)
- [ ] `config/chains.yaml`
- [ ] `docs/agents/skills/run-subagent/SKILL.md`
- [ ] `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`
- [ ] `src/Module/ChainExecution/Domain/Service/Static/ExecuteAgentStepService.php`

## 9. Comments (Комментарии)
Задача создана по результатам brainstorm-сессии Тимлида Алекса, Архитекторов Гэндальфа/Локи, Бэкендера Тони и QA Хауса.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-02 | Тимлид (Алекс) | Создание задачи после подтверждения пользователя |
