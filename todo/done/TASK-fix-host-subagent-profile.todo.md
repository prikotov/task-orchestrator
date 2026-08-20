---
type: fix
created: 2026-08-20
value: V2
complexity: C2
priority: P1
depends_on:
epic:
author: Бэкендер Левша (codex-cli)
assignee: Бэкендер Левша (codex-cli)
branch: task/fix-host-subagent-profile
pr: https://github.com/prikotov/task-orchestrator/pull/359
status: done
---

# TASK-fix-host-subagent-profile: Загружать профиль роли из потребляющего проекта

## 0. Простое описание (Human Brief)
### Проблема простыми словами (Problem)
После установки `task-orchestrator` (оркестратор задач) через Composer скрипт `watch-subagent.sh` читает профиль роли из каталога пакета, а не из проекта, который его использует. Делегирование запускается с неверным консольным агентом или моделью.

### Варианты или путь решения (Solution Sketch)
Определять корень потребляющего проекта по Composer `vendor`-каталогу и использовать его `config/chains.yaml`, `.env.local` и Composer autoload (автозагрузчик). Для запуска из checkout (рабочей копии) сохранять текущий путь.

### Ожидаемый результат (Expected Result)
Роль, вызываемая через установленный Composer пакет, запускается с профилем из `config/chains.yaml` потребляющего проекта.

## 1. Концепция и Цель (Concept and Goal)
### История (Job Story)
> **Job Story:** Когда Тимлид делегирует работу из проекта с установленным `task-orchestrator`, я хочу, чтобы применялся профиль роли этого проекта, чтобы выбранные консольный агент и модель были предсказуемыми.

### Цель по SMART (Goal)
В рамках одного Pull Request (запроса на слияние) исправить разрешение путей `watch-subagent.sh` и добавить интеграционный регрессионный тест физической Composer-установки без реального вызова LLM.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`, `tests/Integration/Docs/Agents/Skills/RunSubagent/WatchSubagentScriptTest.php`.
* **Текущее поведение:** корень проекта вычисляется как корень пакета в `vendor/prikotov/task-orchestrator`.
* **Границы (Out of Scope):** не меняем YAML-схему профилей ролей и не выполняем реальные вызовы AI-сервисов.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Установленный Composer пакет читает `config/chains.yaml` потребляющего проекта.
- [x] Для разбора YAML используется автозагрузчик потребляющего проекта.
- [x] Запуск из checkout пакета сохраняет работоспособность.
- [x] Есть регрессионный интеграционный тест с fake runner (фиктивный движок).

### 🟡 Желательно (Should Have)
- [x] Проверка воспроизводится в `make check`.

### 🟢 Опционально (Could Have)
- [ ] Не требуется.

### ⚫ Не будем делать (Won't Have)
- [ ] Не добавляем локальные обёртки в потребляющие проекты.
- [ ] Не меняем назначение моделей в `config/chains.yaml`.

## 4. План реализации (Implementation Plan)
1. [x] Выявить неверное разрешение project root (корня проекта) при запуске скрипта из Composer `vendor`.
2. [x] Определить consumer project root (корень потребляющего проекта) и его Composer autoload.
3. [x] Добавить регрессионный интеграционный тест через установленный путь пакета.
4. [x] Запустить `make check`.

## 5. Критерии приёмки (Definition of Done)
- [x] Профиль роли потребляющего проекта задаёт runner (движок), model (модель) и reasoning (уровень рассуждения) запуска.
- [x] Тест не использует реальные внешние сервисы.
- [x] `make check` завершается успешно.

## 6. Самопроверка (Verification)
```bash
make tests-integration
make check
```

## 7. Риски и зависимости (Risks and Dependencies)
- Путь Composer `vendor` может быть нестандартным; определение должно опираться на расположение пакета и `autoload.php`, а не на имя корневого каталога.

## 8. Источники (Sources)
- `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`
- `tests/Integration/Docs/Agents/Skills/RunSubagent/WatchSubagentScriptTest.php`

## 9. Комментарии (Comments)
Исправление заменяет локальную обёртку в потребляющем проекте корректным поведением пакета.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-20 | Бэкендер Левша (codex-cli) | Создание задачи и начало реализации. |
| 2026-08-20 | Бэкендер Левша (codex-cli) | Исправлено разрешение путей; `make check` успешно завершён. |
| 2026-08-20 | Бэкендер Левша (codex-cli) | Создан Pull Request #359, задача передана на ревью. |
| 2026-08-20 | Девопс Сэм (codex-cli) | Задача одобрена; подготовлена к слиянию. |
