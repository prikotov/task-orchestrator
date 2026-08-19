---
# Metadata (Метаданные)
type: refactor
created: 2026-08-16
started: 2026-08-19
completed: 2026-08-19
due:
started:
completed:
cancelled:
value: V1
complexity: C2
priority: P3
cost_plan:
cost_fact:
depends_on:
epic:
author: Бэкендер Левша (pi)
assignee: Бэкендер Левша (pi)
branch: task/techdebt-phpmd-lengths
pr:
status: done
---

# TASK-techdebt-phpmd-lengths: Устранение нарушений длины методов и классов из phpmd.baseline

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Три файла библиотеки нарушают пороги PHPMD по длине: методы `run()` в `CodexAgentRunnerService` (121 строка) и `PiAgentRunnerService` (124 строки) превышают порог 80 строк, класс `ChainDefinitionVo` (546 строк) превышает порог 500. Нарушения скрыты в `phpmd.baseline.xml`, поэтому регресс по длине кода больше не отслеживается для этих файлов.

### Варианты или путь решения (Solution Sketch)
1. Выделить шаги подготовки запуска, парсинга вывода и обработки результата в отдельные приватные методы/сервисы для обоих `run()`.
2. Разделить `ChainDefinitionVo` на более мелкие value object'ы (например, по группам полей) или вынести нескалярные операции в доменный сервис.

### Ожидаемый результат (Expected Result)
Все три записи удалены из `phpmd.baseline.xml`, `vendor/bin/phpmd` проходит без нарушений, поведение покрыто существующими тестами.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)
> **User Story:** Как бэкендер, я хочу, чтобы код движка не превышал пороги PHPMD по длине, чтобы baseline не маскировал новые регрессии.

### Цель по SMART (Goal)
Рефакторинг без изменения поведения: `run()` каждого раннера — не более 80 строк, `ChainDefinitionVo` — не более 500 строк, записи удалены из baseline, `make check` зелёный.

## 2. Контекст и Границы (Context and Scope)
* **In Scope (Что делаем):**
    * Декомпозиция `CodexAgentRunnerService::run()` и `PiAgentRunnerService::run()`
    * Сокращение `ChainDefinitionVo`
    * Удаление трёх записей из `phpmd.baseline.xml`
* **Out of Scope (Чего НЕ делаем):**
    * Изменение поведения раннеров и доменной модели
    * Изменение правил `phpmd.xml`

## 3. Требования, MoSCoW (Requirements)

### 🔴 Блокирующие требования (Must Have)
- [x] Нарушения устранены, записи удалены из `phpmd.baseline.xml`
- [x] Существующие тесты проходят без изменений assertions

### 🟡 Важные требования (Should Have)
- [x] Покрытие новых методов тестами не ниже текущего

### 🟢 Желательно (Could Have)
- [x] Общий шаблон декомпозиции для обоих раннеров (симметричные приватные методы `createConfiguredProcess`/`attachProxyEnvironment`/`stopProcessAndBridge`/`buildResult`; вынос общего Infrastructure-хелпера — отдельный техдолг, см. комментарий)

### ⚫ Не в этот раз (Won't Have)
- [x] Переписывание JSONL-парсинга — не выполнялось (out of scope)

## 4. План реализации (Implementation Plan)
- [x] Декомпозиция `CodexAgentRunnerService::run()`
- [x] Декомпозиция `PiAgentRunnerService::run()`
- [x] Сокращение `ChainDefinitionVo`
- [x] Удаление записей из baseline, полный `make check`

## 5. Критерии приёмки (Definition of Done)
- [x] `vendor/bin/phpmd` без нарушений при пустом baseline для этих файлов
- [x] `make check` зелёный

## 6. Самопроверка (Verification)
- Запуск: `vendor/bin/phpunit` — все тесты зелёные.
- Запуск: `vendor/bin/phpmd analyze src --format=text --ruleset=phpmd.xml --baseline-file=phpmd.baseline.xml` — без нарушений.

## 7. Риски и зависимости (Risks and Dependencies)
- Раннеры — критичный путь исполнения цепочек: изменения покрывать интеграционными тестами

## 8. Источники (Sources)
- `phpmd.baseline.xml` — три записи LongMethod/LongClass
- TASK-chore-update-prikotov-deps — фиксация нарушений в baseline

## 9. Комментарии (Comments)
Задача создана по регламенту работы с техдолгом при обновлении зависимостей.

**Результат (2026-08-19):** `run()` обоих раннеров декомпозирован до 64 строк (порог 80) симметричными приватными методами `createConfiguredProcess`/`attachProxyEnvironment`/`stopProcessAndBridge`/`buildResult`; `ChainDefinitionVo` сжат до 489 строк по метрике phpmd (порог 500) композицией существующих sub-VO `SharedChainDefinitionVo` + `PromptConfigurationVo` (ADR-008), публичный API сохранён. Три записи удалены из `phpmd.baseline.xml`. Поведение не изменено; добавлены тесты на env-подмену прокси без моста и hard-cap таймаут. Пройдены самопроверка и ревью (Ревьювер Бэка Пуаро, финальное одобрение); правки по замечаниям ревью внесены. Техдолг-кандидат: выделение общего Infrastructure-хелпера жизненного цикла раннеров (дублирование декомпозиции Codex/Pi).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-16 | Бэкендер Левша (pi) | Создание задачи в backlog по итогам обновления prikotov/*. |
| 2026-08-19 | Тимлид Алекс (pi) | Конвейер task-via-subagents: реализация (Бэкендер Левша), самопроверка, ревью (Ревьювер Бэка Пуаро) — выполнено. |
