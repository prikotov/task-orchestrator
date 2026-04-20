---
type: feat
created: 2026-04-18
value: V3
complexity: C3
priority: P2
depends_on:
  - TASK-feat-chain-step-timeout-yaml
epic: EPIC-arch-orchestrator-module-decomposition
author: Бэкендер
assignee:
branch:
pr:
status: done
assignee: Бэкендер (Левша)
branch: task/feat-chain-max-time-yaml
---

# TASK-feat-chain-max-time-yaml: Общий таймаут цепочки через YAML

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда я запускаю brainstorm с `max_rounds: 20` и `timeout: 600` на шаг, я хочу ограничить **общее время** сессии (например, 30 минут), чтобы фасилитатор не растягивал обсуждение на часы — даже если шаги укладываются в пошаговый таймаут.

### Goal (Цель по SMART)
Параметр `max_time` (секунды) в YAML ограничивает суммарное время выполнения цепочки. При достижении лимима — graceful shutdown: фасилитатор получает финальный шаг для synthesis, цепочка завершается с `completion_reason: max_time_exceeded`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `config/chains.yaml` — добавить `max_time` на уровень цепочки
    *   `src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php` — парсинг `max_time`
    *   `src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php` — поле `maxTime`
    *   `src/Module/Orchestrator/Domain/Service/Chain/Dynamic/RunDynamicLoopService.php` — проверка перед каждым раундом, graceful finalize
    *   `src/Module/Orchestrator/Domain/Entity/DynamicLoopExecution.php` — хранение `maxTime`
    *   `src/Module/Orchestrator/Domain/ValueObject/DynamicChainContextVo.php` — проброс `maxTime`
    *   `src/Module/Orchestrator/Infrastructure/Service/Chain/ChainSessionLogger.php` — причина завершения `max_time_exceeded` в session.json и result.md
*   **Текущее поведение:** Единственное ограничение по времени — `timeout` на один шаг. Общего лимита нет. При 20 раундах × 30 мин/шаг = до 10 часов. Budget (`max_cost_total`) ограничивает по деньгам, но не по времени.
*   **Границы (Out of Scope):**
    *   Static chains — там фиксированное число шагов, проблема менее актуальна
    *   Таймаут на шаг (отдельная задача TASK-feat-chain-step-timeout-yaml)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] `max_time` на уровне цепочки в YAML: `chains.brainstorm.max_time: 1800` (30 мин)
- [x] `ChainDefinitionVo` содержит `?int $maxTime`
- [x] `YamlChainLoader` парсит `max_time` для dynamic chains
- [x] Проверка перед каждым раундом: `elapsed >= maxTime` → прерываем с finalize
- [x] Graceful shutdown: при `max_time_exceeded` даётся один finalize-шаг фасилитатору для synthesis
- [x] `session.json`: `completion_reason: "max_time_exceeded"`
- [x] `result.md`: отображается причина `Max time exceeded`
- [x] Fallback: `max_time` не указан → безлимит (как сейчас)
### 🟡 Should Have (Желательно)
- [x] Psalm: 0 errors
- [ ] Warning при достижении 80% лимита (аналог budget warning)
- [x] Unit-тесты: elapsed ≥ maxTime → finalize, elapsed < maxTime → continue
### 🟢 Could Have (Опционально)
- [ ] CLI `--max-time` для переопределения YAML
### ⚫ Won't Have (Не будем делать)
- Static chains support на этом этапе

## 4. Implementation Plan (План реализации)
1. [x] Добавить `maxTime: ?int` в `ChainDefinitionVo`
2. [x] В `YamlChainLoader::parseDynamicChain()` — читать `$raw['max_time']`
3. [x] Добавить `maxTime: ?int` в `DynamicChainContextVo`
4. [x] В `BuildDynamicContextService::buildContext()` — проброс `maxTime`
5. [x] В `RunDynamicLoopService::execute()` — проверка `elapsed >= maxTime` перед каждым раундом, вызов `executeFinalizeTurn` при превышении
6. [x] В `OrchestrateChainCommandHandler::finalizeSession()` — обработка причины `max_time_exceeded`
7. [x] Unit-тесты на все ветки
8. [x] Обновить `config/chains.yaml` — добавить `max_time: 1800` для brainstorm

## 5. Definition of Done (Критерии приёмки)
- [x] `chains.yaml` с `max_time: 1800` → цепочка завершается не позже 30 мин
- [x] `chains.yaml` без `max_time` → безлимит (обратная совместимость)
- [x] При `max_time_exceeded` фасилитатор даёт synthesis (не обрыв)
- [x] `session.json` содержит `completion_reason: "max_time_exceeded"`
- [x] `result.md` содержит причину прерывания
- [x] PHPUnit + Psalm проходят

## 6. Verification (Самопроверка)
```bash
# Установить max_time: 10 в chains.yaml для быстрой проверки
php bin/console app:agent:orchestrate "test" --chain=brainstorm
# Проверить session.json → completion_reason
# Проверить result.md → причина прерывания
vendor/bin/phpunit
vendor/bin/psalm --no-cache
```

## 7. Risks and Dependencies (Риски и зависимости)
- Зависит от TASK-feat-chain-step-timeout-yaml (timeout на шаг) — общая инфраструктура парсинга YAML timeout-параметров
- Graceful finalize при max_time требует 1 дополнительный шаг — нужно учесть это в логике (не прерывать грубо, а дать 1 шанс на synthesis)
- Взаимодействие с budget: оба могут прервать цепочку — приоритет у первого сработавшего

## 8. Sources (Источники)

## 9. Comments (Комментарии)
Аналог `budget.max_cost_total`, но для времени. Паттерн тот же: проверка перед шагом → прерывание → graceful finalize.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-18 | Бэкендер | Создание задачи |
| 2026-04-20 | Бэкендер (Левша) | Реализация: maxTime через все слои |
| 2026-04-20 | Пуаро (Ревьювер) | Code review — апрув с 1 minor CR |
