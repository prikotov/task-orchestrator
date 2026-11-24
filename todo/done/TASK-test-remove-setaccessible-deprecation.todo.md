---
type: test
created: 2026-11-24 03:50:21 (1795492221)
due: 
started: 2026-11-24 04:36:16 (1795494976)
completed: 2026-11-24 04:47:17 (1795495637)
cancelled: 
value: V2
complexity: C0
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Бэкендер Тони (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/test-remove-setaccessible-deprecation
pr: https://github.com/prikotov/task-orchestrator/pull/497
status: done
---

# TASK-test-remove-setaccessible-deprecation: Убрать deprecated ReflectionMethod::setAccessible из теста FinalizeReserve

<!--
  Секция создана по шаблону docs/todo-md/templates/task.md по решению владельца 2026-11-24
  («сделай задачу»). Предсуществующий шум, подтверждён в задаче runfacilitator-vo (PR #493).
  Плейсхолдеры заменены, AI-инструкции удалены.
-->

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Каждый прогон тестов PHPUnit выводит deprecation-предупреждение (сообщение «метод устарел, перестанет работать в будущих версиях»): в файле `tests/Unit/Domain/Service/Chain/Dynamic/RunDynamicLoopServiceFinalizeReserveTest.php` на строке 151 вызывается `ReflectionMethod::setAccessible(true)`. Начиная с PHP 8.1 этот вызов не делает ничего (методы и без него доступны), а с PHP 8.5 он официально помечен устаревшим. Предупреждение шумит в отчётах каждого прогона тестов и статических проверок CI, засоряя вывод: реальные проблемы легче пропустить. Если предупреждение в будущих версиях PHP превратится в ошибку/падение, ломать будет уже в момент апгрейда, а не по плану. Мешает всем, кто читает вывод тестов: разработчикам, ревьюверам, CI-логам.

### Варианты или путь решения (Solution Sketch)

Один минимальный вариант: удалить вызов `->setAccessible(true)` в указанном тесте (одна строка; при необходимости подрезать ставший лишним комментарий про доступность метода). Поведение теста не меняется: с PHP 8.1+ reflection-методы доступны без этого вызова. Факт проверен в задаче runfacilitator-vo: исполнитель воспроизвёл deprecation на `main` и подтвердил отсутствие эффекта вызова (раздел 9 задачи, PR #493). Альтернатива «заменить на что-то» не требуется — удалять, ничего не добавляя.

### Ожидаемый результат (Expected Result)

Прогон `vendor/bin/phpunit` больше не содержит deprecation-предупреждения про `ReflectionMethod::setAccessible()`; вывод тестов чище, а будущий апгрейд PHP не наткнётся на этот устаревший вызов. Человек это увидит по чистому выводу PHPUnit и по отсутствию упоминания setAccessible в отчётах проверок.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)

> **Job Story:** Когда я прогоняю тесты или читаю CI-лог, я хочу не видеть deprecation-шум от `setAccessible()`, чтобы реальные предупреждения и ошибки были заметны.

### Цель по SMART (Goal)

Удалить вызов `ReflectionMethod::setAccessible(true)` (строка 151 `tests/Unit/Domain/Service/Chain/Dynamic/RunDynamicLoopServiceFinalizeReserveTest.php`) и сопутствующий лишний код/комментарий, если останутся; убедиться, что deprecation-сообщение исчезло из вывода PHPUnit, а все тесты зелёные. Критерий: `vendor/bin/phpunit` без предупреждений об этом методе, `make check` зелёный.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `tests/Unit/Domain/Service/Chain/Dynamic/RunDynamicLoopServiceFinalizeReserveTest.php` (строка 151; только тест, продакшн-код не трогается).
*   **Текущее поведение:** вызов `$method->setAccessible(true);` генерирует deprecation-предупреждение PHPUnit с PHP 8.5 и не имеет эффекта с PHP 8.1+.
*   **Прецеденты для переиспользования:** нет — единственное вхождение `setAccessible` в кодовой базе (проверено `grep -rn "setAccessible" tests/ src/ apps/` 2026-11-24).
*   **Критичные конвенции:** [Конвенции тестирования](../../docs/conventions/testing/index.md).
*   **Границы (Out of Scope):** любые другие тесты/файлы; рефакторинг самого теста (структура, стабы, ассерты); настройка PHPUnit-конфигурации (фильтры deprecation); апгрейд зависимостей.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)

- [x] Вызов `$method->setAccessible(true);` удалён из `RunDynamicLoopServiceFinalizeReserveTest.php`; ставшие лишними переменные/комментарии подчищены.
- [x] Прогон `vendor/bin/phpunit` не содержит deprecation-предупреждения про `ReflectionMethod::setAccessible()`.
- [x] Все тесты зелёные: поведение теста не изменилось.

### 🟡 Желательно (Should Have)

- [x] Если рядом останутся пустые конструкции (неиспользуемая reflection-переменная) — минимальная подчистка без изменения логики теста. (Не потребовалось: `$method` используется в `invoke()`, лишних конструкций нет.)

### 🟢 Опционально (Could Have)

- [ ] Ничего сверх Must/Should.

### ⚫ Не будем делать (Won't Have)

- [ ] Никаких правок продакшн-кода и конфигураций PHPUnit.
- [ ] Никаких других «заодно»-фиксов в этом тесте.
- [ ] Введение suppressions/фильтров deprecation вместо удаления вызова.

## 4. План реализации (Implementation Plan)

1. [x] Открыть `RunDynamicLoopServiceFinalizeReserveTest.php`, локализовать строку 151 и контекст использования `$method`.
2. [x] Удалить вызов `setAccessible(true)` (и ставшие лишними строки, если есть).
3. [x] `vendor/bin/phpunit tests/Unit/Domain/` — зелёный, без deprecation про setAccessible.
4. [x] Полный `vendor/bin/phpunit` + `vendor/bin/psalm` — зелёные.
5. [ ] Обновить Change History задачи, перевести в `done`, PR. (Change History обновлён; перенос в `done` и поле `pr` — после создания PR.)

## 5. Критерии приёмки (Definition of Done)

- [x] Сценарий протестирован (Unit/Integration)
- [x] Нет регрессий в смежных модулях
- [x] Обновлена техническая документация (если требуется) — не требуется: продакшн-контракты не затронуты.
- [x] Вызов `setAccessible` удалён; `grep -rn "setAccessible" tests/ src/ apps/` возвращает пусто.
- [x] `vendor/bin/phpunit` полный — зелёный, без deprecation про `ReflectionMethod::setAccessible()`.
- [x] `vendor/bin/psalm` — зелёный.
- [ ] Задача в `done`, файл в `todo/done/`, поле `pr` заполнено.

## 6. Самопроверка (Verification)

```bash
vendor/bin/phpunit                                            # полный прогон, без deprecation
vendor/bin/phpunit tests/Unit/Domain/                         # целевой каталог
vendor/bin/psalm                                              # статический анализ
grep -rn "setAccessible" tests/ src/ apps/                    # пустой вывод
make check                                                    # полный барьер перед PR
php vendor/bin/todo-md validate TASK-test-remove-setaccessible-deprecation
```

## 7. Риски и зависимости (Risks and Dependencies)

- Риск минимальный: удаление вызова, не имеющего эффекта с PHP 8.1+ (минимальная версия проекта — 8.4; факт подтверждён воспроизведением в задаче runfacilitator-vo).
- Зависимости и блокеры отсутствуют.

## 8. Источники (Sources)

- [ ] [Задача TASK-techdebt-runfacilitator-vo (done), раздел 9 — подтверждение предсуществующего deprecation](TASK-techdebt-runfacilitator-vo.todo.md)
- [ ] [Ретро runfacilitator-vo — «Важные комментарии», пункт о deprecation](../../docs/agents/team-retro/2026-11-23_08-56-runfacilitator-vo-task-retro.md)
- [ ] Тест `tests/Unit/Domain/Service/Chain/Dynamic/RunDynamicLoopServiceFinalizeReserveTest.php` (строка 151)

## 9. Комментарии (Comments)

- Задача заведена по решению владельца 2026-11-24 («сделай задачу») после отказа чинить deprecation в рамках PR #493 (вне рамок той задачи, подтверждено как предсуществующее).
- Это микро-задача C0: однострочное удаление; исполнителю не запускать тяжёлых инструментов сверх Verification.
- 2026-11-24, Бэкендер Тони: реализовано в ветке `task/test-remove-setaccessible-deprecation`. Удалена одна строка `$method->setAccessible(true);` (бывшая строка 151); переменная `$method` остаётся — используется в `invoke()`. `grep -rn "setAccessible" tests/ src/ apps/` — пусто. `make check` зелёный (PHPUnit: 1779 тестов, 5335 ассертов, 2 skipped; Psalm: No errors found). Первый прогон `make check` упал на несвязанном флаки-тесте `WatchSubagentScriptTest::stallDoesNotKillActiveProcessAndWaitsUntilSoftTimeout` (тайминги stall/heartbeat); изолированный прогон и повторный полный `make check` — зелёные. Коммит не выполнялся по запросу владельца.

## История изменений (Change History)

| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-24 03:50:21 (1795492221) | Тимлид Алекс (pi) | Создание задачи |
| 2026-11-24 11:42:55 (1795495375) | Бэкендер Тони (pi) | Реализация: удалён вызов `setAccessible(true)` из теста; `make check` зелёный; отметки выполнения проставлены |
