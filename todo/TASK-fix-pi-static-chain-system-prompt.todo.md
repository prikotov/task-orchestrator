---
type: bug
created: 2026-07-07
value: V1
complexity: C3
priority: P3
depends_on:
epic:
author: Тимлид Алекс
assignee:
branch:
pr:
status: todo
---

# TASK-fix-pi-static-chain-system-prompt: pi-роли не получают role-prompt в static chains

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
В static-цепочках (`analyze`, `implement`, `hotfix`) pi-роли бегают **без своего role-specific system prompt** — на default-prompt'е pi. Codex после #299 получает свой prompt правильно; pi — нет.

### Почему (Root Cause)
1. pi-команда роли (chains.yaml) несёт literal `--system-prompt @system-prompt` (два элемента: `--system-prompt`, `@system-prompt`).
2. `PiAgentRunnerService::buildCommand`: т.к. `--system-prompt` уже в command — НЕ добавляет `$request->getSystemPrompt()` (path) повторно.
3. `resolveCommandFiles` обрабатывает `@file` (str_starts_with `@`) → пытается найти файл `system-prompt` (без каталога) → не находит → оставляет literal `@system-prompt`.
4. pi CLI получает `--system-prompt @system-prompt` (literal), молча игнорирует неразрезолвленный маркер → бежит на default-prompt.

### Варианты или путь решения (Solution Sketch)
Варианты (выбрать при реализации):
- **A.** Добавить в `PiAgentRunnerService` resolution маркера `@system-prompt` → путь из `getSystemPrompt()` (симметрично `CodexAgentRunnerService::resolvePromptSlots`), отдельно от `resolveCommandFiles` (который про `@file` → содержимое).
- **B.** Убрать literal `@system-prompt` из pi-command в chains.yaml и положиться на авто-добавление `--system-prompt <path>` из `getSystemPrompt()` в `buildCommand` (если `--system-prompt` отсутствует).

Вариант B — проще, но меняет chains.yaml (контракт). Вариант A — код в runner, симметричен codex.

## Контекст

- Обнаружено при code-review #299 (Пуаро, note 1).
- #299 починил codex; pi остался latent (работает, но без role-prompt).
- codex уже корректно резолвит — см. `CodexAgentRunnerService::resolvePromptSlots` и тесты `buildCommandResolvesSystemPromptAsPath` / `buildCommandLeavesSystemPromptMarkerWhenNoFile`.
- После фикса pi должен получать свой role-prompt в static chains (как в dynamic loop).

## Критерии готовности (Definition of Done)

- [ ] pi-роль в static chain получает свой `prompt_file` как system-prompt (не default).
- [ ] Live-проверка: `analyze` или `implement` с pi-шагом — pi загружает role-prompt (видно по input tokens / поведению).
- [ ] Unit-тест: `PiAgentRunnerService::buildCommand` с `@system-prompt` в command + `systemPrompt`=путь → command содержит путь, не literal.
- [ ] Не сломаны существующие pi-command'ы без `@system-prompt` (fallback на `getSystemPrompt()`).
- [ ] Psalm 0, PHPUnit green, Deptrac/PHPCS чисто.
