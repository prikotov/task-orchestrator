---
type: feat
created: 2026-11-22 13:40:00 (1795326000)
due:
started:
completed:
cancelled:
value: V3
complexity: C3
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Лид Арагорн (pi, host: TasK)
assignee:
consultants: []
reviewer:
approver:
informed: []
branch:
pr:
status: todo
---

# TASK-feat-unified-profile-command-contract: Единый контракт consumption command_profiles (профиль = команда с плейсхолдерами, без per-runner шаблонов)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Сейчас `command_profiles` потребляются ДВУМЯ потребителями с РАЗНЫМИ контрактами:
  - PHP-оркестратор (`PiAgentRunnerService::buildCommand`) **исполняет** массив команды как есть (+ аппендит аргументы, поддерживает слот `@append-system-prompt`) — профиль обязан быть полной командой;
  - `watch-subagent.sh` (скилл run-subagent) **игнорирует** массив команды как команду: извлекает только runner/provider/model/thinking и строит команду по СВОИМ per-runner шаблонам (`case pi) ... codex) ...` — профиль с любой другой консольной командой (и даже с pi, но без распознанного `runner`) через скилл не запустится (RUNNER_CMD останется пустым).
- Следствие — двойной источник истины и скрытые требования к формату: профиль должен одновременно быть «полной командой» (для PHP) и «метаданными для case» (для bash). Дефект был воспроизведён на host TasK: профили без `--mode json -p` ломали PHP-путь (пустой ответ, 0 токенов), хотя скилл работал — потому что скилл свои флаги хардкодит (см. TASK-feat-dynamic-chain-participant-metrics и историю 21.11.2026).
- Отзыв владельца host (21.11.2026): «watch-subagent.sh не должен знать тонкости запуска консольных приложений, а по максимуму пользоваться тем, что дают, за исключением редких тонкостей».

### Варианты или путь решения (Solution Sketch)
- Единый контракт: **профиль = полная команда с плейсхолдерами**, оба потребителя исполняют её verbatim, подставляя значения:
  - `@append-system-prompt` — слот role system prompt (уже поддержан в PiAgentRunnerService);
  - `@user-prompt` — слот финального промпта задачи (новый, опционально; по умолчанию аппендится в конец — обратная совместимость);
  - runner-специфика (например, codex `-c model_instructions_file=...`) — остаётся ВНУТРИ профиля: каждый профиль и так таргетирует один runner, поэтому несоответствие «одна универсальная команда для всех CLV» не требуется.
- `watch-subagent.sh`: убрать per-runner case-шаблоны в основной путь; взять профиль verbatim, подставить плейсхолдеры, дополнить только общими обязательствами (промпт, роль). Hardcoded шаблоны pi/codex оставить как fallback ТОЛЬКО для профилей с пустой командой (совместимость) и пометить deprecated.
- Метаданные (provider/model/thinking для CLI-приоритетов и отчётов) — продолжить extracting из профиля (совместимо).

## 1. Concept and Goal (Концепция и Цель)
- Один профиль = одна самодостаточная команда для ЛЮБОГО потребителя: PHP-оркестратор, bash-скилл, будущие consumers. Добавление нового runner-CLI не требует правки скилла — только нового профиля.

## 2. Context and Scope (Контекст и Границы)
- In: `watch-subagent.sh` (основной путь построения команды), `PiAgentRunnerService` (слот-контракт — расширить `@user-prompt`), документация профилей (docs/guide/roles.md), host-примеры (config/chains.yaml).
- Out: codex-bridge модули (`Module/AgentRunner/.../Codex/*`), PHAR-runtime, изменение формата `session.json`.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- watch-subagent.sh: профиль с командой исполняется verbatim + подстановка `@append-system-prompt`/`@user-prompt`; пустой профиль → deprecated fallback-шаблоны pi/codex (совместимость) + warning.
- PiAgentRunnerService: поддержка `@user-prompt` слота (опционального) — без него текущее поведение (аппенд в конец).
- Документация: контракт профилей (полная команда + плейсхолдеры), примеры pi/codex/любой другой CLI.
- Тесты: оба потребителя на одном профиле дают эквивалентные вызовы; unknown-CLI профиль исполняется verbatim.

### 🟡 Should Have (Желательно)
- Валидатор профилей: warning если в команде pi отсутствуют `--mode json -p` (ловит класс дефекта host TasK 21.11).

### ⚫ Won't Have (Не будем делать)
- Ретро-миграция всех host-конфигов; изменение PHAR-упаковки.

## 4. Implementation Plan (План реализации)
1. Спека плейсхолдеров (список, приоритеты подстановки, экранирование).
2. watch-subagent.sh: verbatim-путь + fallback; сохранение extract-метаданных.
3. PiAgentRunnerService: `@user-prompt` слот.
4. Тесты (bash + PHP), обновление docs/guide/roles.md и примеров.

## 5. Definition of Done (Критерии приёмки)
- [ ] Один и тот же профиль даёт консистентные вызовы в обоих потребителях (интеграционный тест).
- [ ] Профиль с unknown-CLI (не pi/codex) исполняется скиллом verbatim.
- [ ] Fallback для пустых профилей сохранён + warning; `make check` репозитория зелёный.

## 6. Verification (Самопроверка)
- Golden-тесты собранных команд для pi/codex/unknown-CLI; прогон скилла и orchestrator на общем профиле.

## 7. Risks and Dependencies (Риски и зависимости)
- Обратная совместимость host-конфигов (TasK и др.) — сохранена через fallback.
- Разъезд контрактов между PHP и bash при будущих правках — mitigation: общий тест-фикстура профилей.

## 8. Sources (Источники)
- Host TasK, 21–22.11.2026: дефект «профили без `--mode json -p`» (PHP-путь), ревью Снейпа PR #3213 (безопасность правки), отзыв владельца о design smell.

## 9. Comments (Комментарии)
- Связана: TASK-feat-dynamic-chain-participant-metrics (общий контекст consumption command_profiles).
- Замечание ревью Снейпа M2 (sync-check host/vendor промптов) — вне этой задачи, отдельным chore.
