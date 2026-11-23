---
type: refactor
created: 2026-11-23 19:30:07 (1795437007)
due: 
started: 
completed: 
cancelled: 
value: V2
complexity: C2
priority: P3
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: 
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: todo
---

# TASK-techdebt-runfacilitator-vo: Инкапсулировать хвост сигнатуры runFacilitator() в VO

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Метод `RunDynamicLoopAgentServiceInterface::runFacilitator()` принимает **15 позиционных скалярных параметров** — классический long parameter list. Хвост сигнатуры (промпты `brainstormSystemPrompt`/`facilitatorAppendPrompt`/`facilitatorStartPrompt`/`facilitatorContinuePrompt`, `facilitatorSummary`, `responseFilesList`, `timeout`, `command`, `retryPolicy`, `taskAddendum`) — связный контекст одного раунда, который передаётся единым блоком через все точки вызова.
- Замечание внесено ревьювером Бэка Пуаро при ревью resume-фикса (2026-11-22) и подтверждено в ревью PR #490 (RV-F/RV-A); в коде стоит `@techdebt 2026-11-23`.
- Риск: при добавлении нового параметра легко перепутать позиции однотипных строк (четыре промпта подряд) — компилятор это не поймает.

### Варианты или путь решения (Solution Sketch)
- Выделить VO (например, `FacilitatorRunContextVo`) в `DynamicLoop/Domain/ValueObject/` и инкапсулировать в него хвост сигнатуры (промпты, summary, список файлов, timeout, command, retryPolicy, taskAddendum), оставив в сигнатуре 2–4 позиционных параметра идентификации (`$step`, `$round`, `$facilitatorRole`, `$topic`).
- Обновить реализацию `RunDynamicLoopAgentService`, транзитные интерфейсы (`ExecuteDynamicTurnServiceInterface`) и точки вызова (`RunDynamicLoopService`, `ExecuteDynamicTurnService`).
- Аналогично рассмотреть `runParticipant()` (13 параметров) — если паттерн ложится, применить тот же VO-подход в том же PR; если нет — отдельным решением.

### Ожидаемый результат (Expected Result)
- Сигнатура `runFacilitator()` — не более 4–5 параметров, из них один VO; PHPMD-нарушений по сигнатуре нет; `@techdebt` снят.
- Поведение dynamic-цикла не изменилось: все существующие тесты зелёные без правок assertions.

## 1. Концепция и Цель (Concept and Goal)
- Устранить long parameter list в контракте запуска facilitator'а: связный контекст раунда — один объект, а не 15 позиционных аргументов; снизить риск перепутать однотипные строковые параметры при расширении.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopAgentServiceInterface.php`; `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnServiceInterface.php`; `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php`; `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php`; `src/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentService.php`; новый VO в `src/Module/DynamicLoop/Domain/ValueObject/`.
*   **Текущее поведение:** функциональность корректна; долю — чисто структурная (техдолг из ревью).
*   **Точка входа долга:** `@techdebt 2026-11-23` на `runFacilitator()` (установлен в PR #490).
*   **Критичные конвенции:** `docs/conventions/core-patterns/value-object.md` (или ближайший по Conventions паттерн VO), `docs/conventions/index.md`.
*   **Границы (Out of Scope):** изменение поведения dynamic-цикла, рендеринга промптов и resume-логики; рефакторинг `runParticipant()`, если VO-подход не ложится (зафиксировать решение в задаче); трогать другие модули.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] `runFacilitator()`: хвост сигнатуры инкапсулирован в VO, в сигнатуре осталось ≤ 5 параметров.
- [ ] Все точки вызова обновлены; поведение сохранено — тесты зелёные без правок assertions (правки фикстур/обвязки допустимы).
- [ ] `@techdebt` с метода снят (долг устранён).
- [ ] `make check` зелёный.

### 🟡 Желательно (Should Have)
- [ ] `runParticipant()` приведён к тому же паттерну (если решение согласовано при реализации).

### 🟢 Опционально (Could Have)
- [ ] Проверка аналогичных длинных сигнатур в модуле DynamicLoop и точечная инкапсуляция.

### ⚫ Не будем делать (Won't Have)
- [ ] Смена семантики параметров, порядка раундов и форматов промптов.
- [ ] Введение обратно несовместимых изменений публичных контрактов за пределами модуля DynamicLoop без обновления всех точек вызова в одном PR.

## 4. План реализации (Implementation Plan)
1. [ ] Спроектировать VO: состав полей = хвост сигнатуры, иммутабельность, `final readonly`.
2. [ ] Обновить интерфейсы и реализацию; транзитно — `ExecuteDynamicTurnServiceInterface`.
3. [ ] Обновить вызовы (`RunDynamicLoopService`, `ExecuteDynamicTurnService`) и тесты (конструкторная/аргументная обвязка).
4. [ ] Снять `@techdebt`, прогнать `make check`.

## 5. Критерии приёмки (Definition of Done)
- [ ] Сигнатура `runFacilitator()` ≤ 5 параметров с VO; PHPMD 0 нарушений.
- [ ] `vendor/bin/phpunit` и `make check` зелёные; assertions существующих тестов не изменены.
- [ ] `@techdebt` снят; трассировка в этой задаче (Change History) актуальна.

## 6. Самопроверка (Verification)
```bash
make check                                            # lint + валидация + тесты
php vendor/bin/todo-md validate todo/TASK-techdebt-runfacilitator-vo.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- `runFacilitator()` — контракт в цикле resume динамических цепочек: ошибка в порядке полей VO изменит поведение resume; закрывать существующими integration-тестами dynamic/resume-цепочек.
- VO объединяет параметры с разными жизненными циклами (промпты vs runtime-опции): при проектировании не смешивать группы без необходимости.

## 8. Источники (Sources)
- [ ] Замечание ревью resume-фикса (Пуаро, 2026-11-22) — зафиксировано в `todo/done/TASK-refactor-overlength-services.todo.md`, раздел 9.
- [ ] Ревью PR #490: RV-F (Пуаро) и RV-A (Локи) — наблюдение «techdebt без задачи» устранено созданием этой задачи.
- [ ] Отчёты ревью: `docs/agents/reports/system-architect-loki/2026-11-23_18-45…`, `…_19-01…`.

## 9. Комментарии (Comments)
- Задача создана по явному подтверждению владельца (2026-11-23, сессия merge PR #490): «заведи».
- Исполнитель и ревьювер по RACI для `refactor`: Бэкендер (реализация), Ревьювер Бэка (RV-F) + Архитектор (RV-A).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-23 19:30:07 (1795437007) | Тимлид Алекс (pi) | Создание задачи |
