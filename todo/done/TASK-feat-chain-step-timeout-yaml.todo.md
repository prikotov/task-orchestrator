---
type: feat
created: 2026-04-18
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-arch-orchestrator-module-decomposition
author: Бэкендер
assignee: Бэкендер (Левша)
branch: task/feat-chain-step-timeout-yaml
pr: https://github.com/prikotov/task-orchestrator/pull/31
status: done
---

# TASK-feat-chain-step-timeout-yaml: Таймаут на шаг цепочки через YAML

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда я конфигурирую цепочку в `chains.yaml`, я хочу задать `timeout` на уровне цепочки и переопределять его на уровне роли, чтобы агенты с тяжёлыми задачами (архитектор, бэкендер) получали больше времени, а лёгкие (техписатель) — меньше.

### Goal (Цель по SMART)
Параметр `timeout` (секунды) доступен в YAML-конфигурации цепочки и роли с fallback: `role.timeout → chain.timeout → CLI --timeout → 1800`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `config/chains.yaml` — добавить `timeout` на уровень цепочки и роли
    *   `src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php` — парсинг `timeout`
    *   `src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php` — поле `timeout`
    *   `src/Module/Orchestrator/Domain/ValueObject/DynamicChainContextVo.php` — использовать `chain.timeout` как дефолт вместо hardcoded 300
    *   `src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommandHandler.php` — приоритет: CLI → chain → default
*   **Текущее поведение:** `timeout` задаётся только через CLI `--timeout` (дефолт 1800). В YAML нет способа задать таймаут. `RoleConfigVo` уже имеет `?int $timeout`, но он не читается из YAML для dynamic chains.
*   **Границы (Out of Scope):**
    *   Логика самого таймаута (Symfony Process) — не меняется
    *   Static chains — отдельная задача (там `timeout_seconds` уже есть на уровне step)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `timeout` на уровне цепочки в YAML: `chains.brainstorm.timeout: 600`
- [ ] `timeout` на уровне роли: через `roles` секцию (если роль объявлена) или inline
- [ ] Fallback: `role.timeout → chain.timeout → CLI --timeout → 1800`
- [ ] `YamlChainLoader` парсит `timeout` для dynamic chains
- [ ] `ChainDefinitionVo` содержит `?int $timeout`
- [ ] PHPUnit: тесты на парсинг YAML с/без timeout
### 🟡 Should Have (Желательно)
- [ ] Psalm: 0 errors
- [ ] Документация в `docs/guide/chains.md` (или аналог)
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- Runtime-логику таймаута — она уже работает

## 4. Implementation Plan (План реализации)
1. [ ] Добавить `timeout` в `ChainDefinitionVo` (`?int $timeout = null`)
2. [ ] В `YamlChainLoader::parseDynamicChain()` — читать `$raw['timeout']` и передавать в VO
3. [ ] В `OrchestrateChainCommandHandler::executeDynamic()` — fallback: `CLI → chain → 1800`
4. [ ] В `DynamicChainContextVo` — убрать hardcoded 300, использовать переданное значение
5. [ ] Unit-тесты: YAML с timeout, без timeout, timeout на роли
6. [ ] Обновить `config/chains.yaml` — добавить `timeout: 600` для brainstorm

## 5. Definition of Done (Критерии приёмки)
- [ ] `chains.yaml` с `timeout: 600` → chain timeout = 600
- [ ] `chains.yaml` без `timeout` → fallback на CLI `--timeout` → 1800
- [ ] `--timeout 300` CLI переопределяет YAML
- [ ] PHPUnit + Psalm проходят

## 6. Verification (Самопроверка)
```bash
php bin/console app:agent:orchestrate "test" --chain=brainstorm --dry-run
# Проверить invocation.timeout в session.json
vendor/bin/phpunit
vendor/bin/psalm --no-cache
```

## 7. Risks and Dependencies (Риски и зависимости)
- Обратная совместимость: YAML без `timeout` должен работать как раньше
- Static chains уже имеют `timeout_seconds` на уровне step — нужно убедиться, что naming не конфликтует

## 8. Sources (Источники)

## 9. Comments (Комментарии)
`RoleConfigVo` уже имеет `?int $timeout` — для dynamic chains нужно пробросить fallback через chain-level default.

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/backend_developer.ru.md
**Ветка:** task/feat-chain-step-timeout-yaml (уже создана и активна)
**PR:** уже создан (draft) из task/feat-chain-step-timeout-yaml в main — [PR #31](https://github.com/prikotov/task-orchestrator/pull/31)

### Порядок действий
1. Переключись в ветку `task/feat-chain-step-timeout-yaml`: `git checkout task/feat-chain-step-timeout-yaml`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready 31`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-18 | Бэкендер | Создание задачи |
