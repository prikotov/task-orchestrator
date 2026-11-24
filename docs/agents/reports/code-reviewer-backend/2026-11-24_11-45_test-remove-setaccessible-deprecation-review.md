# RV-F ревью: удаление deprecated ReflectionMethod::setAccessible из теста FinalizeReserve

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-24
**Объект:** ветка `task/test-remove-setaccessible-deprecation` (незакоммиченные рабочие изменения поверх коммита `39bf4b78`):
- `tests/Unit/Domain/Service/Chain/Dynamic/RunDynamicLoopServiceFinalizeReserveTest.php` (−1 строка)
- `todo/TASK-test-remove-setaccessible-deprecation.todo.md` (отметки выполнения, комментарий, Change History)

**Задача:** [`todo/TASK-test-remove-setaccessible-deprecation.todo.md`](../../../../todo/TASK-test-remove-setaccessible-deprecation.todo.md)

---

## Вердикт: ✅ APPROVE (одобрено)

Замечаний нет. Изменение ровно то, что предписано постановкой: однострочное удаление без побочных правок.

## 1. Соответствие постановке и границам scope

| Проверка | Результат |
| :--- | :--- |
| Must Have: вызов `$method->setAccessible(true);` удалён (бывшая строка 151) | ✅ Diff содержит ровно удаление этой строки в `shouldReserveForFinalizeReturnsFalseWhenMaxTimeIsNull()` |
| Must Have: PHPUnit без deprecation про `setAccessible` | ✅ Точечный прогон файла с `--display-deprecations` на PHP 8.5.12: OK (23 tests, 40 assertions), deprecation-вывода нет |
| Must Have: поведение теста не изменилось | ✅ Ассерты, стабы, структура теста не тронуты; `$method` по-прежнему используется в `invoke($service, null, $startTime)` — лишних переменных не осталось |
| Should Have: подчистка пустых конструкций | ✅ Обоснованно не потребовалась (отмечено в задаче) |
| Won't Have: продакшн-код и конфигурации PHPUnit не тронуты | ✅ Diff затрагивает только тест и файл задачи |
| Won't Have: без suppressions/фильтров deprecation | ✅ Ничего подобного не добавлено |
| Без «заодно»-правок | ✅ `git diff --stat`: 2 файла; правки todo-файла — штатное ведение задачи (отметки, комментарий исполнителя, Change History), не код |

Единственность вхождения подтверждена: `grep -rn "setAccessible" tests/ src/ apps/ config/` — пусто.

## 2. Корректность удаления (PHP 8.4/8.5)

С PHP 8.1 `ReflectionMethod::setAccessible()` — no-op (операция без эффекта): reflection-методы доступны для `invoke()` без условия доступности; с PHP 8.5 вызов помечен deprecated. Удаление вызова семантически нейтрально для проекта (минимум PHP 8.4, фактический рантайм — PHP 8.5.12). Оставшийся blank line между созданием `ReflectionMethod` и `ReflectionClass` — приемлемое визуальное разделение, стилевых нарушений нет.

## 3. Точечные проверки (полный `make check` не повторялся — уже зелёный по контексту задачи: 1779 тестов, Psalm без ошибок)

| Проверка | Команда | Результат |
| :--- | :--- | :--- |
| Целевой тест | `vendor/bin/phpunit tests/.../RunDynamicLoopServiceFinalizeReserveTest.php --display-deprecations` | OK, 23 tests, 40 assertions, без deprecations |
| Стиль (PHPCS) | `vendor/bin/phpcs tests/.../RunDynamicLoopServiceFinalizeReserveTest.php` | Чисто, exit 0 |
| Psalm (файл) | `vendor/bin/psalm tests/.../RunDynamicLoopServiceFinalizeReserveTest.php --no-cache` | No errors found, exit 0 |
| Валидация задачи | `php vendor/bin/todo-md validate TASK-test-remove-setaccessible-deprecation` | 0 errors, 0 warnings |

Примечание: Psalm по отдельному файлу не детектирует неиспользуемые suppressions — в данном diff подавлений нет, риск отсутствует; полный Psalm подтверждён зелёным в `make check` исполнителя.

## 4. Безопасность, качество, конвенции тестирования

- Секретов, пользовательского ввода, внешних сервисов изменение не касается.
- Тест сохраняет реальный ассерт (`assertFalse` с осмысленным сообщением) — ложного покрытия нет.
- Reflection для приватного метода доменного сервиса — предсуществующий паттерн этого теста; постановка прямо запрещает его рефакторинг (Out of Scope), оснований для замечания нет.

## 5. Организационные наблюдения (не замечания к коду)

1. Изменения не закоммичены — по явному запросу владельца (зафиксировано в комментарии задачи). Коммит и PR — следующий шаг по запросу владельца; формат коммита — Conventional Commits (например, `test(dynamic-loop): remove deprecated setAccessible call`).
2. Пункт 5 плана и последний критерий DoD (перенос в `todo/done/`, поле `pr`) закономерно открыты — выполняются после создания PR, до запроса апрува на merge.
3. В комментарии задачи исполнитель зафиксировал флаки-тест `WatchSubagentScriptTest::stallDoesNotKillActiveProcessAndWaitsUntilSoftTimeout` (тайминги stall/heartbeat), упавший на первом прогоне `make check`. К данному изменению отношения не имеет; рекомендую тимлиду рассмотреть отдельную техдолг-задачу на стабилизацию таймингов, если падения повторятся. Блокирующим для этого ревью не является.

## Итог

Изменение минимально, точно соответствует постановке, границы scope/Won't Have соблюдены, поведение теста неизменно, точечные проверки зелёные. **Одобрено.**
