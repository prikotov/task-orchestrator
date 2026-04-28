---
# Metadata (Метаданные)
type: feat
created: 2026-04-27
value: V2
complexity: C2
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер Левша
branch: task/orchestrator-codex-runner
pr: https://github.com/prikotov/task-orchestrator/pull/89
status: review
---

# TASK-orchestrator-codex-runner: Codex CLI как runner для ролей в brainstorm

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда brainstorm-сессия запускается с ролями архитекторов, я хочу иметь возможность использовать Codex CLI (OpenAI) вместо pi, чтобы задействовать другие модели (o3, gpt-4.1) и повысить диверсификацию точек зрения через кросс-модельную аргументацию.

### Goal (Цель по SMART)
Реализовать `CodexAgentRunner` — реализацию `AgentRunnerInterface`, которая запускает `codex exec --json` через Symfony Process и парсит его JSONL-вывод. После этого обновить `config/chains.yaml` для ролей архитекторов (`system_architect_gandalf`, `system_architect_loki`) — заменить `pi` на `codex` с моделью o3.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    * `src/Module/AgentRunner/Infrastructure/Service/Codex/` — новый runner + парсер
    * `config/chains.yaml` — конфигурация ролей архитекторов
    * `config/services.yaml` — регистрация в DI
    * `tests/Unit/` — unit-тесты
*   **Текущее поведение:** Все роли используют `pi` CLI (`PiAgentRunner`). Runner — единственный, зарегистрированный через тег `agent.runner`.
*   **Границы (Out of Scope):**
    * Не трогаем `PiAgentRunner` и существующие роли на `pi`
    * Не реализуем Codex fallback (отдельная задача, если потребуется)
    * Не меняем формат `chains.yaml` — массив `command` уже поддерживает любой CLI
    * Не добавляем Codex для ролей, кроме архитекторов (это можно сделать позже простой правкой YAML)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `CodexJsonlParser` — парсер JSONL-вывода `codex exec --json` в структуру `AgentResultVo` (outputText, inputTokens, outputTokens, cost, model, turns)
- [ ] `CodexAgentRunner` — реализация `AgentRunnerInterface`, строит команду `codex exec --full-auto --json --sandbox danger-full-access -m <model>`, передаёт system-prompt через stdin/промпт (Codex не имеет `--system-prompt`)
- [ ] Регистрация в DI: тег `agent.runner`, имя runner'а — `codex`
- [ ] Обновление `config/chains.yaml`: роли `system_architect_gandalf` и `system_architect_loki` используют `command` с `codex exec ...`
- [ ] Unit-тесты для `CodexJsonlParser` (на основе реального образца вывода `codex exec --json`)
- [ ] Unit-тесты для `CodexAgentRunner::buildCommand()`
- [ ] PHPUnit и Psalm проходят без новых ошибок

### 🟡 Should Have (Желательно)
- [ ] Integration-тест: запуск brainstorm с codex-архитектором (mocked codex)
- [ ] Склеивание `@system-prompt` + `@append-system-prompt` в один промпт для Codex (Codex поддерживает только один поток инструкций)

### 🟢 Could Have (Опционально)
- [ ] Документация в `docs/guide/` по добавлению новых runner'ов

### ⚫ Won't Have (Не будем делать)
- [ ] Codex fallback при ошибке pi — отдельная задача
- [ ] GUI / интерактивный режим Codex — только `exec` (non-interactive)
- [ ] Поддержка `codex review` — только `codex exec`

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
1. [x] Spike: запустить `codex exec --json` с тестовым промптом, изучить формат JSONL-событий
2. [x] Реализовать `CodexJsonlParser`
3. [x] Реализовать `CodexAgentRunner` (включая логику инъекции system-prompt через stdin)
4. [x] Зарегистрировать в `services.yaml` через тег `agent.runner` (автоматически через `_instanceof`)
5. [x] Обновить `config/chains.yaml` для архитекторов
6. [x] Unit-тесты
7. [x] Проверка: PHPUnit + Psalm

## 5. Definition of Done (Критерии приёмки)
- [ ] `CodexAgentRunner` корректно реализует `AgentRunnerInterface` и возвращает `AgentResultVo`
- [ ] Роли архитекторов в `chains.yaml` используют `codex`
- [ ] `php bin/console app:agent:orchestrate "test" --chain=brainstorm --dry-run` показывает корректную команду для архитекторов
- [ ] PHPUnit: все тесты зелёные, покрытие новых классов ≥ 80%
- [ ] Psalm: 0 новых ошибок
- [ ] Существующие цепочки на `pi` не сломаны

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php bin/console app:agent:orchestrate "test topic" --chain=brainstorm --dry-run
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Формат JSONL Codex:** вывод `codex exec --json` может отличаться от ожидаемого. Необходим spike перед реализацией парсера.
- **Codex не имеет `--system-prompt`:** придётся передавать роль через stdin или `AGENTS.md`. Подход через stdin предпочтительнее — не требует модификации файлов проекта.
- **`resolveSlot()` в `PromptFormatterService`:** работает с `@system-prompt` для любого runner'а. Для Codex нужно убедиться, что слоты корректно резолвятся или что `buildCommand()` обрабатывает их.
- **Зависимость от OpenAI API key:** Codex требует авторизацию (`codex login`). В CI/тестах — только unit-тесты с mock, реальный запуск Codex — вручную.

## 8. Sources (Источники)
- [ ] `codex exec --help` — интерфейс Codex CLI non-interactive mode
- [ ] `src/Module/AgentRunner/Infrastructure/Service/Pi/PiAgentRunner.php` — референс-реализация runner'а
- [ ] `src/Module/AgentRunner/Infrastructure/Service/Pi/PiJsonlParser.php` — референс-реализация парсера
- [ ] `config/chains.yaml` — текущая конфигурация ролей и примеры других CLI (qwen, gemini, opencode, kilo)

## 9. Comments (Комментарии)
* **Предпосылка:** в brainstorm-сессии 2026-04-27 (45 раундов, декомпозиция Orchestrator) все участники работали на pi + GLM. Кросс-модельное разнообразие может улучшить качество архитектурных решений.
* **Codex CLI уже установлен:** `codex-cli 0.125.0` (`/home/dp/.npm-global/bin/codex`).
* **Механизм `command` в `chains.yaml`** уже поддерживает любой CLI — достаточно указать массив аргументов. В файле есть закомментированные примеры для qwen, gemini, opencode, kilo.
* **Ключевое отличие от pi:** Codex не имеет `--system-prompt` — промпт роли нужно передавать через stdin (первые аргументы промпта) или через `--output-schema`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) | Создание задачи |
