---
type: refactor
created: 2026-06-05
value: V2
complexity: C1
priority: P1
depends_on:
epic:
author: Пользователь
assignee: Бэкендер (Левша)
branch: task/remove-service-integration-path
pr: https://github.com/prikotov/task-orchestrator/pull/249
status: review
---

# TASK-refactor-domain-service-chain-definition-path: Убрать технический путь Domain Service Integration

## 0. Простое описание (Human Brief)
Убрать путь `Domain/Service/Integration` из активного кода, чтобы контракты Domain Service лежали в бизнес-контексте, а не в техническом контексте слоя Integration.

### Проблема простыми словами (Problem)
В модулях ChainExecution и DynamicLoop появились контракты в `Domain/Service/Integration`, что смешивает слой Integration с контекстом доменного сервиса.

### Варианты или путь решения (Solution Sketch)
Перенести контракты загрузки ChainDefinition в `Domain/Service/ChainDefinition` и обновить все зависимости.

### Ожидаемый результат (Expected Result)
В активном коде нет пути `Domain/Service/Integration`, а проверки проекта проходят успешно.

## 1. Concept and Goal (Концепция и Цель)
### Story
Как разработчик, я хочу чтобы доменные сервисные контракты назывались по бизнес-контексту, чтобы архитектура оставалась понятной и не смешивала слои.

### Goal
Перенести контракты ChainDefinition provider/mapper из `Domain/Service/Integration` в `Domain/Service/ChainDefinition` без изменения поведения.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/ChainExecution`, `src/Module/DynamicLoop`, `config/services.yaml`, unit-тесты, актуальная документация.
*   **Текущее поведение:** часть контрактов находится в техническом пути `Domain/Service/Integration`.
*   **Границы (Out of Scope):** не меняем runtime-поведение orchestrator, не переписываем исторические отчёты в `docs/agents/reports/` и закрытые задачи в `todo/done/`.

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [x] Перенести контракты в `Domain/Service/ChainDefinition`.
- [x] Обновить DI aliases, imports и тесты.
- [x] Добавить защитный архитектурный unit-тест.
- [x] Запустить `make check`.

### ⚫ Won't Have (Не будем делать)
- Менять бизнес-логику выполнения цепочек.
- Переписывать исторические документы и закрытые задачи.

## 4. Implementation Plan
1. [x] Проверить текущие вхождения `Domain/Service/Integration`.
2. [x] Перенести интерфейсы ChainExecution и DynamicLoop в `Domain/Service/ChainDefinition`.
3. [x] Обновить imports, DI aliases и тесты.
4. [x] Обновить актуальные документы.
5. [x] Запустить проверки.

## 5. Definition of Done
- [x] Активный код не содержит `Domain/Service/Integration`.
- [x] Unit-тест защищает от возврата технического пути.
- [x] `make check` зелёный.

## 6. Verification
```bash
vendor/bin/phpunit
vendor/bin/psalm
make check
```

## 7. Risks and Dependencies
- Исторические упоминания в старых отчётах и завершённых задачах сохранены как архивный контекст.

## 8. Sources
- [ ] Prompt пользователя: «к тебе пробралась дичь в виде путя Service\\Integration».

## 9. Comments
PR: https://github.com/prikotov/task-orchestrator/pull/249

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-05 | Бэкендер (Левша) | Создание задачи |
