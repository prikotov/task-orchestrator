---
# Metadata (Метаданные)
type: chore
created: 2026-04-24
value: V1
complexity: C2
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/chore-presentation-domain-decouple
pr:
status: done
---

# TASK-chore-presentation-domain-decouple: Убрать зависимость OrchestrateCommand от Domain-слоя

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как разработчик, я хочу чтобы Presentation-слой (OrchestrateCommand) не зависел от Domain-слоя напрямую, чтобы архитектура соответствовала DDD-конвенциям проекта.

### Goal (Цель по SMART)
Заменить прямые зависимости `OrchestrateCommand` от `Domain\ChainLoaderInterface` и `Domain\ChainDefinitionVo` на Application-слой: `ChainDefinitionDto` + `ChainLoaderApplicationInterface`.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php`
* **Текущее поведение:** Command импортирует `Domain\Service\Chain\Shared\ChainLoaderInterface` и читает `Domain\ValueObject\ChainDefinitionVo` (dry-run, list chains)
* **Границы:**
  - Только OrchestrateCommand — другие Presentation-классы проверить, но не трогать без необходимости
  - Не рефакторить StaticChainExecution::@techdebt (отдельная задача)

## 3. Requirements (Требования)
### 🔴 Must Have
- [x] `OrchestrateCommand` не содержит `use ...Domain\...` (кроме Domain-исключений для catch)
- [x] Создан `ChainDefinitionDto` в Application-слое (или используется существующий)
- [x] Создан `ChainLoaderApplicationInterface` в Application-слое (или используется существующий `ValidateChainConfigServiceInterface`)
- [x] PHPUnit и Psalm зелёные

### 🟡 Should Have
- [x] Alias в `services.yaml` для нового интерфейса
- [x] Unit-тесты обновлены

## 4. Implementation Plan
1. Создать `ChainDefinitionDto` в Application\Dto (или проверить наличие)
2. Создать `ChainLoaderApplicationInterface` с методами `load()`, `list()` → возвращающими DTO
3. Создать реализацию-адаптер в Infrastructure (делегирует к Domain ChainLoaderInterface, маппит VO→DTO)
4. Заменить зависимости в OrchestrateCommand
5. Обновить тесты

## 5. Definition of Done
- [x] В OrchestrateCommand нет `use ...Domain\...` (кроме catch-исключений)
- [x] Psalm clean (1 pre-existing error, не связанная с изменениями)
- [x] PHPUnit 496 тестов зелёные
- [x] @techdebt-комментарий удалён

## 6. Verification
```bash
grep -n 'Domain\\' apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php
# Ожидается: 0 совпадений (или только catch-исключения)
vendor/bin/psalm
vendor/bin/phpunit
```

## 7. Risks
- Может потребоваться каскадное изменение: если Command передаёт Domain VO дальше в Application-хендлеры

## 8. Sources
- [Конвенция: слои и зависимости](docs/conventions/index.md)
- [Архитектура](docs/guide/architecture.md)

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-24 | Тимлид (Алекс) | Создание задачи из @techdebt в OrchestrateCommand |
