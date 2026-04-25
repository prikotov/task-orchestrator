---
# Metadata (Метаданные)
type: refactor
created: 2026-04-25
value: V2
complexity: C2
priority: P2
depends_on:
epic: EPIC-feat-standalone-cli
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/refactor-validate-config-dedup
pr: #70
status: done
---

# TASK-refactor-validate-config-dedup: Вынести валидационные инварианты в Domain Specification

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story или Job Story)
> **Job Story:** Когда я разрабатываю новые типы шагов или правил цепочки, я хочу чтобы инварианты определялись в одном месте (Domain), чтобы не дублировать их в Application-сервисе и не рисковать рассинхронизацией.

### Goal (Цель по SMART)
Создать `ChainDefinitionSpecification` в Domain, который реализует «мягкую» валидацию (сбор всех ошибок без исключений). `ValidateChainConfigService` делегирует ему проверку и маппит результат в DTO. Устранить дублирование 7 из 7 бизнес-проверок между Application и Domain.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/Orchestrator/Domain/Specification/ChainDefinitionSpecification.php` — новый (инварианты цепочки)
    *   `src/Module/Orchestrator/Domain/ValueObject/ChainConfigViolationVo.php` — новый (результат нарушения)
    *   `src/Module/Orchestrator/Application/Service/ValidateChainConfigService.php` — делегирует в Specification
    *   `tests/` — unit-тесты
*   **Текущее поведение:** `ValidateChainConfigService` (Application) содержит приватные методы `validateStaticChain()`, `validateDynamicChain()`, `validateStep()`, которые дублируют guard-проверки из `ChainDefinitionVo` и `ChainStepVo`. При этом VO уже валидны после конструирования — методы никогда не найдут ошибку в нормальном flow.
*   **Границы (Out of Scope):**
    *   Не меняем существующие guard-проверки в VO (fail-fast при конструировании остаётся)
    *   Не меняем `--validate-config` CLI-интерфейс
    *   Не трогаем `ChainLoader`

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `ChainDefinitionSpecification` в Domain с методом `validate(ChainDefinitionVo): list<ChainConfigViolationVo>`
- [ ] `ChainConfigViolationVo` в Domain (chain name, field, message)
- [ ] Все 7 инвариантов перенесены из Application в Specification
- [ ] `ValidateChainConfigService` делегирует в Specification, маппит ViolationVo → ErrorDto
- [ ] Приватные методы-валидаторы удалены из ValidateChainConfigService
- [ ] Unit-тесты на Specification

### 🟡 Should Have (Желательно)
- [ ] Specification покрывает те же сценарии, что и удалённые методы

### 🟢 Could Have (Опционально)
- [ ] Specification переиспользуется для валидации при создании цепочки (не только pre-flight)

### ⚫ Won't Have (Не будем делать)
- [ ] Изменение существующих guard-проверок в VO
- [ ] Изменение CLI-интерфейса `--validate-config`
- [ ] Решение @techdebt в OrchestrateCommand (отдельная задача TASK-chore-presentation-domain-decouple)

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*

## 5. Definition of Done (Критерии приёмки)
- [ ] Приватные методы-валидаторы удалены из ValidateChainConfigService
- [ ] Инварианты определены в одном месте — Domain Specification
- [ ] `--validate-config` работает без изменений (backward compatible)
- [ ] PHPUnit и Psalm зелёные
- [ ] Unit-тесты покрывают Specification

## 6. Verification (Сампроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php bin/task-orchestrator app:agent:orchestrate --validate-config "check"
php bin/task-orchestrator app:agent:orchestrate --validate-config --chain=implement "check"
```

## 7. Risks and Dependencies (Риски и зависимости)
- Specification должен работать с уже сконструированными VO — нужна «мягкая» проверка без исключений
- Возможен соблазн убрать guard-проверки из VO — этого делать нельзя (fail-fast при конструировании)
- Связано с TASK-chore-presentation-domain-decouple (OrchestrateCommand зависит от Domain напрямую) — можно решать совместно, но не обязательно

## 8. Sources (Источники)
- [ ] [Отчёт Локи](../../docs/agents/reports/system-architect/2026-04-25_12-00_validate-chain-config-layer-audit.md)
- [ ] [ValidateChainConfigService](../../src/Module/Orchestrator/Application/Service/ValidateChainConfigService.php)
- [ ] [ChainDefinitionVo](../../src/Module/Orchestrator/Domain/ValueObject/ChainDefinitionVo.php)
- [ ] [ChainStepVo](../../src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php)

## 9. Comments (Комментарии)
Архитектурный отчёт Локи: `docs/agents/reports/system-architect/2026-04-25_12-00_validate-chain-config-layer-audit.md`

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-25 | Тимлид (Алекс) | Создание задачи |
