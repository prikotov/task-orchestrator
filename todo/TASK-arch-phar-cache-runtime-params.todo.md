---
type: feat
created: 2026-11-02 01:43:19 (1793583799)
due: 
started: 
completed: 
cancelled: 
value: V3
complexity: C3
priority: P2
cost_plan: 
cost_fact: 
depends_on: TASK-arch-phar-cache-roots-prevention
epic: 
author: Аналитик Шерлок (pi)
assignee: 
branch: 
pr: 
status: todo
---

# TASK-arch-phar-cache-runtime-params: Runtime-разрешение phar-зависимых параметров ядра

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Параметры ядра `task_orchestrator.package_dir` и `task_orchestrator.phar_path` вычисляются при компиляции контейнера и «замораживаются» в его персистентном кеше. После перемещения PHAR в новое расположение сохранённый кеш продолжает ссылаться на мёртвый `phar://<старый путь>` — это прямо зафиксировано в коде («замороженные в кеш `package_dir`/`phar_path` не переживают перемещения PHAR», `src/Kernel.php:188-191`). Это мешает запускам перемещённого PHAR в production-режиме (`debug=false`): устаревший контейнер может переиспользоваться вслепую (гипотеза D3, требует проверки). К чему приводит: layout-резолвер ролей/skills/package-ресурсов получает мёртвый package root и бросает исключение «ресурс не найден», а команда `agent:init` пишет привязку `.phar-binding` к несуществующему архиву. Дополнительно заморозка — блокер утверждённого шага (B) решения П2 (упрощение ключа изоляции кеша): без устранения заморозки шаг (B) некорректен (раздел 2.4 исследования).

### Варианты или путь решения (Solution Sketch)

Направление утверждено исследованием (решение пользователя 2026-11-01: П2 поэтапно — (A) затем (B)): перевести `package_dir`/`phar_path` с заморозки на runtime-разрешение по образцу уже существующих env-процессоров `Component/Locale` и `Component/Layout` (прецедент в кодовой базе: `src/Kernel.php:56-60`). Внутренний вариант проводки выбирает исполнитель в плане реализации: (1) отдельный env-процессор phar-зависимых путей либо (2) расширение существующего layout-процессора. Внешний контракт `agent:init` сохраняется (`.phar-binding` = путь запущенного PHAR).

### Ожидаемый результат (Expected Result)

После перемещения PHAR все потребители phar-зависимых путей получают значения от **запущенного** архива в runtime — без очистки кеша, без мёртвых `phar://`-ссылок и без тихих ошибок в `debug=false`. Класс проблемы «замороженные phar-пути» устранён (шаг (A) решения П2 выполнен); поведение при отсутствии перемещения не меняется.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)
> **Job Story:** Когда PHAR переносится в новое расположение, а персистентный кеш контейнера переиспользуется (`debug=false`), я хочу, чтобы `package_dir`/`phar_path` разрешались в runtime от запущенного архива, чтобы потребители (layout-резолвер, `agent:init`) не получали мёртвый `phar://<старый путь>`, а работали корректно без очистки кеша.

### Цель по SMART (Goal)

(S) Перевести параметры `task_orchestrator.package_dir` и `task_orchestrator.phar_path` с заморозки в персистентном кеше контейнера на runtime-разрешение env-процессором по образцу `Component/Locale`/`Component/Layout`, не меняя внешний контракт `agent:init` (`.phar-binding` = путь запущенного PHAR); (M) результат измерим: инвентаризация всех мест заморозки (закрытие ОВ-4 для шага A), проверенный факт по гипотезе D3, unit-тесты нового процессора, интеграционный тест сценария «перемещение PHAR» через швы `resolvePharPath()`/`pharCacheBaseDir()`, обновлённый `docs/guide/architecture.md` (только в части шага A); (A) прецеденты env-процессоров уже в кодовой базе (`src/Kernel.php:56-60`, факт 14 исследования); (R) утверждённое решение П2 поэтапно A→B (2026-11-01, раздел 2.9 исследования); (T) одна задача — только шаг (A); шаг (B), standalone-PHAR и очистка кеша — вне объёма.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `src/Kernel.php` (`getKernelParameters()`), `config/services.yaml:51-52,83-86`, `apps/console/src/Module/Orchestrator/Skill/Service/InstallBecomeRoleService.php` (только внутренняя проводка), новый env-процессор в `src/Component/` (по образцу `src/Component/Locale/TaskOrchestratorLocaleEnvVarProcessor.php` и `src/Component/Layout/TaskOrchestratorLayoutEnvVarProcessor.php`), тесты `tests/Unit/` и `tests/Integration/`, `docs/guide/architecture.md`.
*   **Текущее поведение:** `task_orchestrator.package_dir`/`task_orchestrator.phar_path` вычисляются при конструировании контейнера и замораживаются в его персистентном кеше (факты 10–13 исследования); потребители — `config/services.yaml:52` (layout-процессор `$packageRoot`), `config/services.yaml:83,86` (`InstallBecomeRoleService`, команда `agent:init`, `.phar-binding`). Контраст: layout-пути (`roles_dir`/`skills_dir`/`chains_yaml`) и локаль AI-ролей уже разрешаются в runtime env-процессорами и НЕ запекаются (факт 14, `src/Kernel.php:56-60`).
*   **Scope (только шаг (A)):** устранение заморозки `package_dir`/`phar_path` + обязательная инвентаризация мест заморозки + проверка гипотезы D3 + тесты + docs в части шага A.
*   **Границы (Out of Scope):**
    - шаг (B) — упрощение ключа изоляции `Kernel::pharCacheRootName()` (dual-context) — отдельная задача после аудита инвентаризации заморозки (ОВ-4); формулу имени корня кеша в этой задаче НЕ менять;
    - standalone-PHAR (D4) — не покрывается (путь архива возвращается в ключ изоляции через сам `projectRoot`);
    - очистка `src/Component/PharCache` (PR #443) — НЕ менять (граница достижима: компонент параметризован — D5);
    - CLI-контракты, конфигурация цепей, `module.*.module_dir` (D1 — не потребляются) — НЕ менять.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] **Инвентаризация всех мест заморозки phar-зависимых значений ДО правки кода** (закрыть ОВ-4 для шага A). Стартовая карта — D2 исследования: `config/services.yaml:51-52,83-86`; `task_orchestrator.chains_session_dir` и `base_path` (phar-зависимы только в standalone) — учитывать отдельно; `module.*.module_dir` не трогать (D1). Результат зафиксировать в описании PR; места сверх D2 — обработать либо вынести открытым вопросом с решением владельца (A).
- [ ] **Проверить гипотезу D3** (отсутствие freshness-проверки персистентного контейнера в `debug=false`) — до мержа; результат зафиксировать как факт (гипотеза → подтверждена/опровергнута). Гипотеза критична для порядка (A)→(B) и оценки риска тихой некорректности (раздел 2.10 исследования).
- [ ] **Runtime-разрешение `package_dir`/`phar_path`** по образцу env-процессоров `Component/Locale` и `Component/Layout`: параметры в `Kernel::getKernelParameters()` становятся динамическими env-плейсхолдерами (НЕ запекаются в скомпилированный контейнер); аргументы env-процессоров — только plain-параметры контейнера, `%env(...)%` в аргументах запрещён (рекурсия `Container::getEnv()`, см. PHPDoc `TaskOrchestratorLayoutEnvVarProcessor`).
- [ ] **Потребители переведены на новую проводку:** `config/services.yaml:52` (layout-процессор `$packageRoot`), `config/services.yaml:83,86` (`InstallBecomeRoleService`) — внутренняя проводка; внешний контракт `agent:init` сохранён: `.phar-binding` = физический путь **запущенного** PHAR (паритет; `apps/console/src/Module/Orchestrator/Skill/Service/InstallBecomeRoleService.php:190,200`).
- [ ] **Тесты:** unit-тесты нового процессора; интеграционный тест сценария «перемещение PHAR» через защищённые швы `resolvePharPath()`/`pharCacheBaseDir()` (факт 4 исследования); dual-context поведение при отсутствии перемещения сохраняется — регрессия (падение существующих тестов) недопустима.
- [ ] **Docs:** `docs/guide/architecture.md` (ключ изоляции описан — `docs/guide/architecture.md:542`) — обновить только в части шага A: актуализировать утверждения о заморозке `package_dir`/`phar_path`; описание ключа изоляции («пара») НЕ менять — это часть шага (B).
### 🟡 Желательно (Should Have)
### 🟢 Опционально (Could Have)
### ⚫ Не будем делать (Won't Have)
- [ ] Шаг (B) — упрощение ключа изоляции `pharCacheRootName()` (dual-context `hash(projectRoot)`) — отдельная задача после аудита инвентаризации заморозки (ОВ-4).
- [ ] Standalone-PHAR (D4) — не покрывается; оговорка фиксируется честно (раздел 2.4 исследования).
- [ ] Очистку `src/Component/PharCache` (PR #443) не менять.
- [ ] CLI-контракты, конфигурацию цепей и `module.*.module_dir` не менять.

## 4. План реализации (Implementation Plan)
1. [ ] **Инвентаризация заморозки** (закрытие ОВ-4 для шага A) по стартовой карте D2: `config/services.yaml:51-52,83-86`, `chains_session_dir`/`base_path` (phar-зависим только в standalone), `module.*.module_dir` (D1 — исключить). Результат — зафиксированный список в описании PR; новые места сверх D2 — обработать либо вынести вопросом владельцу (A).
2. [ ] **Проверка гипотезы D3** (свежесть контейнера в `debug=false`) — до мержа; результат зафиксировать (гипотеза → факт).
3. [ ] **Новый env-процессор** phar-зависимых путей в `src/Component/` по образцу `TaskOrchestratorLocaleEnvVarProcessor`/`TaskOrchestratorLayoutEnvVarProcessor` (prefix, `getProvidedTypes()`, PHPDoc-контракт; аргументы — только plain-параметры) + unit-тесты.
4. [ ] **Проводка:** `src/Kernel.php::getKernelParameters()` — `task_orchestrator.package_dir`/`task_orchestrator.phar_path` → динамические env-плейсхолдеры; `config/services.yaml:51-52,83-86`; внутренняя проводка `InstallBecomeRoleService` (ctor-аргументы → резолвер). Внешний контракт `agent:init` без изменений (`.phar-binding` = путь запущенного PHAR).
5. [ ] **Интеграционный тест** сценария «перемещение PHAR» через швы `resolvePharPath()`/`pharCacheBaseDir()`; полный прогон тестов (регрессия dual-context).
6. [ ] **Docs:** `docs/guide/architecture.md` — только часть шага A (утверждения о заморозке параметров); поиском по ключевым словам проверить, что устаревших утверждений не осталось.
7. [ ] **Предварительные проверки** (`vendor/bin/phpunit`, `vendor/bin/psalm`, `make deptrac`) и перевод задачи в `done` по процессу проекта.

## 5. Критерии приёмки (Definition of Done)
- [ ] Инвентаризация заморозки выполнена и зафиксирована (ОВ-4 для шага A закрыта); места сверх D2 обработаны либо явно зафиксированы с решением владельца (A).
- [ ] Гипотеза D3 проверена до мержа, результат зафиксирован фактом (не гипотезой).
- [ ] `task_orchestrator.package_dir`/`task_orchestrator.phar_path` разрешаются в runtime и не замораживаются в персистентном кеше контейнера (unit-тесты процессора зелёные).
- [ ] Интеграционный тест «перемещение PHAR» зелёный; существующие тесты dual-context зелёные (регрессий нет).
- [ ] Внешний контракт `agent:init` сохранён: `.phar-binding` = физический путь запущенного PHAR.
- [ ] `docs/guide/architecture.md` обновлена только в части шага A; описание ключа изоляции и `src/Component/PharCache` не затронуты.
- [ ] PHPUnit и Psalm зелёные; тесты соответствующего уровня покрывают изменения (Domain/Application — минимум 80% по затронутым участкам).

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate todo/TASK-arch-phar-cache-runtime-params.todo.md
vendor/bin/phpunit
vendor/bin/psalm
make deptrac
```

## 7. Риски и зависимости (Risks and Dependencies)
- **Неполнота инвентаризации заморозки (ОВ-4) → тихая некорректность в `debug=false` (D3):** необнаруженный потребитель замороженного значения получит мёртвый `phar://<старый путь>` без явной ошибки. Смягчение: инвентаризация — обязательный шаг до правки (Must 1); проверка D3 — до мержа (Must 2).
- **Регрессия dual-context:** при отсутствии перемещения PHAR поведение должно остаться идентичным текущему; нарушение проявится падением существующих тестов (Must 5).
- **Запрет `%env(...)%` в аргументах env-процессоров** (рекурсия `Container::getEnv()`, PHPDoc `TaskOrchestratorLayoutEnvVarProcessor`): нарушение ломает компиляцию контейнера.
- **Зависимость (depends_on):** `todo/done/TASK-arch-phar-cache-roots-prevention.todo.md` — исследование с утверждённым решением (П2 поэтапно A→B, 2026-11-01).
- **Зависимость (следующий шаг):** задача шага (B) (упрощение ключа изоляции `pharCacheRootName()`) зависит от этой задачи и от аудита инвентаризации заморозки (ОВ-4); создаётся отдельной задачей.
- **Неблокирующий открытый вопрос:** поддерживаемость standalone-PHAR (D4, открытый вопрос 2 исследования) не проверена; на шаг (A) не влияет (out of scope), но определяет объём шага (B).

## 8. Источники (Sources)

- [ ] `docs/research/phar-cache-roots-prevention.md` — утверждённое исследование (решение пользователя 2026-11-01): раздел 2.4 (суть шага A), 2.1 (D1–D5), 2.10 (не проверено)
- [ ] PR #445 — <https://github.com/prikotov/task-orchestrator/pull/445> — исследовательская задача (контекст решения)
- [ ] PR #443 — <https://github.com/prikotov/task-orchestrator/pull/443> — реализация очистки `src/Component/PharCache` (граница: не менять)
- [ ] `todo/done/TASK-arch-phar-cache-roots-prevention.todo.md` — завершённая исследовательская задача (depends_on)
- [ ] `todo/done/TASK-fix-phar-runtime-layout-cache.todo.md` — прецедент runtime-разрешения layout-параметров (факт 14, QA-3)
- [ ] `src/Component/Locale/TaskOrchestratorLocaleEnvVarProcessor.php`, `src/Component/Layout/TaskOrchestratorLayoutEnvVarProcessor.php` — образцы env-процессоров

## 9. Комментарии (Comments)

Метаданные: value V3 (высокая ценность — устранение класса проблемы «замороженные phar-пути» на главной оси dual-context); complexity C3 (архитектурная проводка Kernel → config → потребители, новый компонент, интеграционный тест сценария перемещения); priority P2 (плановая работа после P1; по пересечению V3/C3 ближе всего к ориентиру «средняя ценность + средняя/высокая сложность»; подтверждено запросом пользователя). Тип `feat`: валидатор todo-md не поддерживает тип `arch` (допустимые: fix, feat, build, chore, ci, docs, style, refactor, perf, test, revert); выбран `feat` — новое поведение runtime-разрешения. Формат `author` — «Роль (агент)» по `.todo-md.php`/`AI_AGENTS.md`: указан `Аналитик Шерлок (pi)` (команда create передавала `--author="Аналитик Шерлок"`; агент добавлен для устранения предупреждения валидатора). Гипотеза D3 не выдаётся за факт — проверка включена в Must до мержа; прочие непроверенные гипотезы — раздел 2.10 исследования.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-11-02 01:43:19 (1793583799) | Аналитик Шерлок (pi) | Создание задачи |
