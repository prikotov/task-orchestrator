---
type: refactor
created: 2026-11-22 06:39:05 (1795329545)
due: 
started: 
completed: 
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Левша (pi)
assignee: 
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: todo
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
*   **Где делаем:** `src/Kernel.php`; `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php`; `phpmd.baseline.xml` (удаление записей).
*   **Текущее поведение:** функциональность корректна, но классы перегружены: Kernel совмещает регистрацию бандлов, раскладку хост-проекта и компиляцию; YamlChainLoaderService — загрузку YAML-цепочек, валидацию и маппинг конфигураций.
*   **Прецеденты для переиспользования:** split по тому же паттерну, что в PR #356 (`refactor: eliminate phpmd length violations from baseline`); compiler pass'ы в `src/Component/ModuleSystem/`.
*   **Критичные конвенции:** `docs/conventions/index.md` (Сервис, Компонент, Модульная архитектура).
*   **Границы (Out of Scope):** изменение поведения загрузки цепочек и ядра; рефакторинг других длинных классов ниже порога предупреждений; смена конфигурации phpmd.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] `src/Kernel.php` — не более 500 LOC по PHPMD (счётчик с `ignore-whitespace=false`).
- [ ] `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php` — не более 500 LOC по PHPMD.
- [ ] Записи `PHPMD\Rule\Design\ExcessiveClassLength` удалены из `phpmd.baseline.xml`.
- [ ] Поведение сохранено: все существующие тесты зелёные без правок assertions (правки фикстур допустимы).

### 🟡 Желательно (Should Have)
- [ ] Новые части покрываются отдельными unit-тестами наравне с прежним покрытием.

### 🟢 Опционально (Could Have)
- [ ] Сокращение прочих классов-кандидатов, близких к порогу 500 LOC.

### ⚫ Не будем делать (Won't Have)
- [ ] Ослабление порога `ExcessiveClassLength` в `phpmd.xml` или постоянное исключение файлов из правила.
- [ ] Переписывание ядра на другой механизм сборки (изменение архитектуры).

## 4. План реализации (Implementation Plan)
1. [ ] Профилировать состав `Kernel.php`: выделить группу методов раскладки хост-проекта (host layout) в compiler pass/сервис.
2. [ ] Разделить `YamlChainLoaderService`: выделить резолвер команд и загрузку prompt-конфигурации в отдельные сервисы того же модуля.
3. [ ] Прогнать тесты и статический анализ; убрать baseline-записи `ExcessiveClassLength`.
4. [ ] Обновить документацию, если меняются публичные контракты классов.

## 5. Критерии приёмки (Definition of Done)
- [ ] `vendor/bin/phpmd analyze src --format=text --ruleset=phpmd.xml --baseline-file=phpmd.baseline.xml` — 0 нарушений.
- [ ] `make check` зелёный полностью.
- [ ] В `phpmd.baseline.xml` нет записей `ExcessiveClassLength`.
- [ ] Сценарий протестирован (Unit/Integration); нет регрессий в смежных модулях.

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

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-22 06:39:05 (1795329545) | Бэкендер Левша (pi) | Создание задачи |
