---
type: refactor
created: 2026-11-22 06:39:05 (1795329545)
due: 
started: 2026-11-23 12:24:39 (1795411479)
completed: 2026-11-23 19:05:45 (1795435545)
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Левша (pi)
assignee: Бэкендер Левша (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/refactor-overlength-services
pr: https://github.com/prikotov/task-orchestrator/pull/490
status: done
---

# TASK-refactor-overlength-services: Разбить классы, переросшие порог 500 LOC (Kernel, YamlChainLoaderService)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Два класса проекта переросли лимит читабельности в 500 строк кода: `src/Kernel.php` (564 LOC) и `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php` (556 LOC).
- Статический анализ PHPMD (`make check`) падает с `ExcessiveClassLength` на этих файлах: правило `phpmd.xml` настроено на порог 500, а политика проекта (см. PR #356) запрещает держать length-нарушения в baseline на постоянной основе.
- Пока классы не разделены, каждый запуск `make check` требует временных записей в `phpmd.baseline.xml` — это маскирует рост классов дальше и сбивает сигнал анализатора для всех разработчиков.

### Варианты или путь решения (Solution Sketch)
- Вынести из `Kernel.php` логику компиляции/раскладки хост-проекта в отдельный compiler pass или сервис ядра, оставив в Kernel только сборку бандлов.
- Разбить `YamlChainLoaderService` на загрузку/валидацию/маппинг (например, выделить резолвер командных профилей и загрузчик prompt-конфигурации).
- После снижения обоих классов ниже 500 LOC — удалить записи `ExcessiveClassLength` из `phpmd.baseline.xml`.

### Ожидаемый результат (Expected Result)
- `make check` зелёный на актуальном `main` без записей `ExcessiveClassLength` в baseline: анализатор снова сигнализирует о росте классов, а временные suppression-записи убраны.
- Оба класса читаются и сопровождаются без прокрутки на несколько экранов; изменения в них проходят ревью быстрее.

## 1. Концепция и Цель (Concept and Goal)
- Вернуть проекту жёсткий сигнал PHPMD по длине классов: убрать временные baseline-записи, разделив перегруженные классы на связные единицы без изменения поведения.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `src/Kernel.php`; `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php`; `src/Module/DynamicLoop/Domain/Service/Dynamic/ExecuteDynamicTurnService.php` (третий класс, добавлен в скоуп по факту: живое нарушение порога на момент старта — 541 LOC без записи в baseline, поэтому разбивка необходима для DoD «0 нарушений PHPMD»); вынесенные из него `src/Module/DynamicLoop/Domain/Helper/DynamicRoleCommandHelper.php` и `DynamicRoundResultHelper.php`; `phpmd.baseline.xml` (удаление записей).
*   **Текущее поведение:** функциональность корректна, но классы перегружены: Kernel совмещает регистрацию бандлов, раскладку хост-проекта и компиляцию; YamlChainLoaderService — загрузку YAML-цепочек, валидацию и маппинг конфигураций.
*   **Прецеденты для переиспользования:** split по тому же паттерну, что в PR #356 (`refactor: eliminate phpmd length violations from baseline`); compiler pass'ы в `src/Component/ModuleSystem/`.
*   **Критичные конвенции:** `docs/conventions/index.md` (Сервис, Компонент, Модульная архитектура).
*   **Границы (Out of Scope):** изменение поведения загрузки цепочек и ядра; рефакторинг других длинных классов ниже порога предупреждений; смена конфигурации phpmd.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] `src/Kernel.php` — не более 500 LOC по PHPMD (счётчик с `ignore-whitespace=false`).
- [x] `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php` — не более 500 LOC по PHPMD.
- [x] Записи `PHPMD\Rule\Design\ExcessiveClassLength` удалены из `phpmd.baseline.xml`.
- [x] Поведение сохранено: все существующие тесты зелёные без правок assertions (правки фикстур допустимы).

### 🟡 Желательно (Should Have)
- [x] Новые части покрываются отдельными unit-тестами наравне с прежним покрытием.

### 🟢 Опционально (Could Have)
- [x] Сокращение прочих классов-кандидатов, близких к порогу 500 LOC — выполнено частично (три класса): помимо Kernel и `YamlChainLoaderService` сокращён `ExecuteDynamicTurnService` (541 → 454 LOC, вынос разбора команды роли и маппинга результата в Helper'ы `Domain/Helper`, доработка по ревью RV-F; см. раздел 9).
- [ ] Инкапсуляция `runFacilitator` в VO — не выполнялась, перенесена в техдолг (@techdebt, см. раздел 9).

### ⚫ Не будем делать (Won't Have)
- [ ] Ослабление порога `ExcessiveClassLength` в `phpmd.xml` или постоянное исключение файлов из правила.
- [ ] Переписывание ядра на другой механизм сборки (изменение архитектуры).

## 4. План реализации (Implementation Plan)
1. [ ] Профилировать состав `Kernel.php`: выделить группу методов раскладки хост-проекта (host layout) в compiler pass/сервис.
2. [ ] Разделить `YamlChainLoaderService`: выделить резолвер команд и загрузку prompt-конфигурации в отдельные сервисы того же модуля.
3. [ ] Прогнать тесты и статический анализ; убрать baseline-записи `ExcessiveClassLength`.
4. [ ] Обновить документацию, если меняются публичные контракты классов.

## 5. Критерии приёмки (Definition of Done)
- [x] `vendor/bin/phpmd analyze src --format=text --ruleset=phpmd.xml --baseline-file=phpmd.baseline.xml` — 0 нарушений.
- [x] `make check` зелёный полностью.
- [x] В `phpmd.baseline.xml` нет записей `ExcessiveClassLength`.
- [x] Сценарий протестирован (Unit/Integration); нет регрессий в смежных модулях.

## 6. Самопроверка (Verification)
```bash
make check                                            # lint + валидация + тесты
php vendor/bin/todo-md validate todo/TASK-refactor-overlength-services.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- Kernel — точка сборки приложения: ошибка в выносе раскладки хост-проекта ломает запуск в host-режиме; закрывать integration-тестами ядра.
- `YamlChainLoaderService` используется провайдером цепочек: сохранить обратную совместимость public-контракта или обновить все точки вызова в одном PR.

## 8. Источники (Sources)
- [ ] Прецедент политики baseline: PR #356 `refactor: eliminate phpmd length violations from baseline`.
- [ ] Точка роста классов: коммиты b02b40ef (2026-11-06), 493b7de6 (2026-11-07).
- [ ] Зафиксировавший долг PR: TASK-fix-resume-task-arg-not-in-facilitator-prompt (записи в `phpmd.baseline.xml` с 2026-11-22).

## 9. Комментарии (Comments)
- Записи в `phpmd.baseline.xml` добавлены 2026-11-22 как временное решение (@techdebt), чтобы `make check` задачи resume-фикса был зелёным без масштабного рефакторинга в том же PR.
- 2026-11-22, замечание ревью PR resume-фикса (Пуаро): `RunDynamicLoopAgentServiceInterface::runFacilitator()` вырос до 15 позиционных скалярных параметров (long parameter list) — в скоуп этого рефакторинга включить инкапсуляцию хвоста сигнатуры в VO (Could Have).
- 2026-11-23, Бэкендер Левша (доработка по самопроверке): Could Have не выполнялся — инкапсуляция хвоста сигнатуры `runFacilitator()` в VO перенесена в техдолг (@techdebt, причина: объём с демпфированием контракта во всех точках вызова, вне скоупа этого PR; отдельную задачу на устранение — завести по согласованию с тимлидом).
- 2026-11-23, Бэкендер Левша (доработка по самопроверке): актуализированы устаревшие комментарии `config/console_services.yaml` и `config/services.yaml` (адресат регистрации консольных сервисов — `ConsoleServicesRegistrar` вместо удалённого `Kernel::registerConsoleServices()`); ADR-012 дополнен примечанием. Чистка baseline по Kernel.php: `UnusedPrivateMethod isEnvironmentIncluded` — метод не мёртвый (его единственный вызов — из `ModuleKernelTrait`, PHPMD-фалспозит), перенесён в сам трейт (Kernel больше не требует метод от использующего класса); `StaticAccess` — живое нарушение на `\Phar::running()` (дорефакторное), static API изолирован в `Component/PharRuntime/PharRunningPathResolver.php`, запись перенесена на новый файл. Итог: Kernel.php без baseline — 0 нарушений; добавлен unit-тест `PharRunningPathResolverTest`.
- 2026-11-23, замечания ревью (Пуаро, RV-F):
  - CR-1 (решение тимлида — вариант «Helper»): статические классы `DynamicRoleCommandParser`/`DynamicRoundResultMapper` приведены к паттерну Helper по docs/conventions/core-patterns/helper.md — переименованы в `DynamicRoleCommandHelper`/`DynamicRoundResultHelper`, перенесены в `Domain/Helper`, актуализированы namespace и PHPDoc; вызовы обновлены в `ExecuteDynamicTurnService` и тестах, имена методов `parse*`/`from*`/`normalize*` сохранены.
  - CR-2: добавлен unit-тест на ветку `RuntimeException` `readFile()` в `YamlDynamicPromptsResolverServiceTest` (сценарий «файл существует по file_exists, file_get_contents возвращает false»); тест детерминирован через потоковый wrapper, т.к. поведение `file_get_contents()` на каталоге зависит от версии PHP (8.4 — false, 8.5 — пустая строка).
  - CR-3: актуализирован этот todo-файл (раздел 2 «Где делаем» — третий класс; Could Have — сокращение выполнено частично).
- 2026-11-23, доработка по ревью RV-A (Локи, APPROVE WITH CHANGES):
  - CR-1 (решение тимлида — единый механизм «инлайн-подавление»): StaticAccess для `\Phar::running()` закрыт `@SuppressWarnings` с комментарием-причиной в `PharRunningPathResolver::runningPath()`; запись удалена из `phpmd.baseline.xml`; подавления `ReleaseVersionResolver` проверены — комментарии-причины присутствуют у обоих.
  - CR-2 (решение тимлида — вариант «а»): переименованы по конвенции `service.md` (`{Action}{Target}Service`, Action — глагол): `YamlDynamicPromptsResolverServiceInterface` → `ResolveYamlDynamicPromptsServiceInterface`, `YamlDynamicPromptsResolverService` → `ResolveYamlDynamicPromptsService`; обновлены файлы, alias в `Resource/config/services.yaml`, use/вызовы в `YamlChainLoaderService` и 9 тестовых файлах, PHPDoc.
  - CR-3 (решение тимлида — только метка, задачу заведёт тимлид): `@techdebt 2026-11-23` добавлен к `RunDynamicLoopAgentServiceInterface::runFacilitator()` с причиной (15 позиционных скалярных параметров).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-22 06:39:05 (1795329545) | Бэкендер Левша (pi) | Создание задачи |
| 2026-11-23 17:24:15 (1795429455) | Бэкендер Левша (pi) | Доработка по самопроверке: Must Have/Should Have/DoD выполнены; Could Have (VO для `runFacilitator`) → техдолг (@techdebt, раздел 9); актуализация конфиг-комментариев и ADR-012; чистка baseline по Kernel.php (метод в трейт, `\Phar` в обёртку) |
| 2026-11-23 17:59:37 (1795431577) | Бэкендер Левша (pi) | Доработка по ревью RV-F: Parser/Mapper → Helper по конвенции (переименование в `DynamicRoleCommandHelper`/`DynamicRoundResultHelper`, перенос в `Domain/Helper`); unit-тест на ветку `RuntimeException` `readFile()` в `YamlDynamicPromptsResolverServiceTest`; актуализация задачи: третий класс `ExecuteDynamicTurnService` в «Где делаем», Could Have — сокращение выполнено частично |
| 2026-11-23 18:50:11 (1795434611) | Бэкендер Левша (pi) | Доработка по ревью RV-A: CR-1 — единый механизм «инлайн-подавление» StaticAccess (`@SuppressWarnings` в `PharRunningPathResolver` вместо записи в baseline); CR-2 — переименование `YamlDynamicPromptsResolverService(Interface)` → `ResolveYamlDynamicPromptsService(Interface)` по `service.md` (alias, use/вызовы, тесты, PHPDoc); CR-3 — `@techdebt 2026-11-23` на `runFacilitator()` (задачу на устранение заведёт тимлид) |
