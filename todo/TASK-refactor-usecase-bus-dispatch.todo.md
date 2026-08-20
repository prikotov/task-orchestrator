---
type: refactor
created: 2026-08-20
due:
started: 2026-08-20
completed:
cancelled:
value: V2
complexity: C3
priority: P1
cost_plan:
cost_fact:
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/usecase-bus-dispatch
pr:
status: in_progress
---

# TASK-refactor-usecase-bus-dispatch: Диспетчеризация Use Case через CommandBus/QueryBus

## 0. Простое описание (Human Brief)
### Проблема простыми словами (Problem)
Конвенция требует вызывать Command/Query Handler только через шины, но в проекте шин нет: CLI-команды и Integration-сервисы вызывают хендлеры напрямую (16 нарушений в 10 файлах). Правила PHPStan из coding-standard 0.30.0, подключённые при обновлении зависимостей, выявили эти нарушения — `make check` красный.

### Варианты или путь решения (Solution Sketch)
Реализовать компонент шин (`src/Component/CommandBus`, `src/Component/QueryBus`) с PHAR-safe компилятор-пасом, который по рефлексии связывает каждый invokable-хендлер с классом его сообщения, и перевести все 10 точек вызова на диспатч через шины.

### Ожидаемый результат (Expected Result)
`make check` зелёный с включёнными PHPStan-правилами пакета; все Use Case-вызовы идут через шины; новые хендлеры подхватываются шинами автоматически без конфигурации.

## 1. Концепция и Цель (Concept and Goal)
### История (Job Story)
> Когда я вызываю Use Case из Presentation или Integration, я хочу диспатчить сообщение через шину, чтобы выполнялась конвенция «Handler вызывается только через CommandBus/QueryBus» и правила coding-standard проходили.

### Цель по SMART (Goal)
Создать CommandBus/QueryBus (интерфейсы + реализации + UseCaseBusCompilerPass), заменить 16 прямых вызовов хендлеров в 10 файлах на диспатч, покрыть шины unit-тестами и проводкой integration-тестом.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `src/Component/CommandBus/`, `src/Component/QueryBus/`, `src/Kernel.php`, `config/services.yaml`, 6 CLI-команд `apps/console`, 4 Integration-сервиса `src/Module/{ChainExecution,DynamicLoop}/Integration`.
*   **Текущее поведение:** 16 прямых вызовов `($this->xHandler)(new XCommand(...))`; PHPStan-правила пакета не подключены.
*   **Границы (Out of Scope):** сигнатуры хендлеров и сообщения не меняются; RunAgentQueryHandler (не-invokable) не трогаем; middleware/транзакции не вводим.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Компонент CommandBus/QueryBus: интерфейсы `CommandBusComponentInterface::execute()` / `QueryBusComponentInterface::query()` по конвенции use-case.md.
- [x] UseCaseBusCompilerPass: PHAR-safe связывание хендлеров (рефлексия `__invoke`, namespace Command/Query) с шинами, без ручных тегов.
- [x] Все 16 точек вызова переведены на шины; `vendor/bin/phpstan` без ошибок prikotov.useCase.directHandlerInvocation.
- [x] `coding-standard-verify` — phpstan: OK.
- [x] `make check` зелёный.
### 🟡 Желательно (Should Have)
- [x] Integration-тест проводки шин в реальном контейнере.
### 🟢 Опционально (Could Have)
- [ ] —
### ⚫ Не будем делать (Won't Have)
- [ ] Изменение возвращаемых типов хендлеров (в т.ч. скалярный GetPromptFilePath).
- [ ] Middleware, транзакции, асинхронные транспорты.

## 4. План реализации (Implementation Plan)
1. [x] Компонент: интерфейсы, исключения, реализации шин.
2. [x] UseCaseBusCompilerPass + регистрация в Kernel::build() + services.yaml.
3. [x] Рефакторинг 6 CLI-команд и 4 Integration-сервисов на шины.
4. [x] Unit-тесты шин; правка тестов, конструирующих изменённые классы.
5. [x] `make check`, фикс замечаний.

## 5. Критерии приёмки (Definition of Done)
- [x] Все пункты Must Have выполнены.
- [x] Обновлённая документация компонентов (PHPDoc; docs/guide/architecture.md при необходимости).

## 6. Самопроверка (Verification)
```bash
make check
vendor/bin/coding-standard-verify --offline
```

## 7. Риски и зависимости (Risks and Dependencies)
- Круговые зависимости контейнера исключены ленивым ServiceLocator.
- Ветка включает обновление prikotov/* (coding-standard 0.30.0, todo-md 0.0.11) — оно и вскрыло нарушения.

## 8. Источники (Sources)
- [docs/conventions/layers/application/use-case.md](../docs/conventions/layers/application/use-case.md)
- [docs/conventions/layers/application/command-handler.md](../docs/conventions/layers/application/command-handler.md)

## 9. Комментарии (Comments)
- Вариант согласован с пользователем (выбор «2» из трёх вариантов обработки 16 нарушений).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-20 | Тимлид Алекс (pi) | Создание задачи; выбор варианта 2 (починить все нарушения). |
