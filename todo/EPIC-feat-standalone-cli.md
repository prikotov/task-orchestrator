---
# Metadata (Метаданные)
type: epic
created: 2026-04-21
value: V3
complexity: C4
priority: P1
author: Бэкендер (Левша)
assignee: Тимлид (Алекс)
status: in_progress
branch: epic/feat-standalone-cli
pr:
---

# EPIC-feat-standalone-cli: Task Orchestrator как независимое CLI-приложение

## 1. Concept and Goal (Концепция и цель)
### Story (User Story)
> Как разработчик/DevOps-инженер, я хочу установить task-orchestrator одной командой (`composer global require` или скачать Phar) и использовать его как CLI-утилиту в Linux, чтобы оркестрировать AI-агентные цепочки без необходимости создавать Symfony-проект.

### Goal (Цель по SMART)
Реализовать возможность установки и запуска task-orchestrator как самостоятельной CLI-утилиты (подобно `composer`, `deptrac`, `psalm`). Пользователь должен иметь возможность: установить пакет → настроить цепочку через YAML → запустить оркестрацию из терминала. Формат дистрибутива и архитектуру — определить на этапе исследования.

## 2. Context and Scope (Контекст и границы)
*   **In Scope (Что делаем):**
    *   Исследование вариантов дистрибуции (Phar, binary через Composer, Docker,的其他)
    *   Выбор и согласование архитектурного решения
    *   Реализация выбранного подхода
    *   CLI-интерфейс для запуска оркестрации
    *   Документация по установке и использованию
*   **Out of Scope (Чего НЕ делаем):**
    *   Web/API-интерфейс (только CLI)
    *   Демонизация (daemon mode) — отдельная задача на будущее
    *   Плагинная система для сторонних runners — пока не требуется

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Блокирующие требования)
- [ ] Исследованы и задокументированы варианты дистрибуции CLI-приложения на PHP
- [ ] Выбранное решение согласовано с владельцем проекта
- [ ] Пользователь может установить и запустить CLI одной командой
- [ ] CLI принимает конфигурацию цепочки (YAML) и путь к файлу цепочки
- [ | CLI корректно выводит результат оркестрации в терминал (stdout)
- [ ] Документация по установке и использованию

### 🟡 Should Have (Важные требования)
- [ ] Поддержка `composer global require` как способа установки
- [ ] Вывод версии (`--version`)
- [ ] Справка по командам (`--help`)
- [ ] Настройка через файл конфигурации (~/.task-orchestrator.yaml или аналогичный)

### 🟢 Could Have (Желательно)
- [ ] Автодополнение (bash/zsh completion)
- [ ] Цветной вывод в терминале
- [ ] Phar-архив как альтернативный способ установки

### ⚫ Won't Have (Не в этот раз)
- [ ] Web/API интерфейс
- [ ] Daemon mode / long-running процесс
- [ ] Плагинная система
- [ ] Система обновления (self-update)

## 4. Solution Design (Техническое решение)
*Определяется на этапе исследования (Фаза 1).*

Возможные варианты (предварительно):
1. **Symfony Console Application** — отдельный bin-скрипт в пакете, запускаемый через `vendor/bin/task-orchestrator`
2. **Phar-архив** — сборка всего бандла в один исполняемый файл (чтобы не нужен был Composer в целевой системе)
3. **Гибридный подход** — Composer binary для разработчиков + Phar для CI/CD и простых пользователей
4. **Docker image** — контейнер с предустановленным CLI

Финальное решение фиксируется после мозгового штурма.

## 5. Implementation Plan (План реализации)

### Фаза 1: Исследование и выбор решения
- [x] [TASK-research-cli-distribution-options](TASK-research-cli-distribution-options.todo.md) — Мозговой штурм: исследовать варианты дистрибуции, оформить RFC, согласовать с владельцем проекта

### Фаза 2: Реализация (P0)
- [x] [TASK-chore-composer-library-type](done/TASK-chore-composer-library-type.todo.md) — Изменить type на library + добавить bin в composer.json (~1.5 ч)
- [ ] [TASK-chore-packagist-register](TASK-chore-packagist-register.todo.md) — Регистрация пакета на Packagist (~10 мин)
- [x] [TASK-feat-typed-exit-codes](done/TASK-feat-typed-exit-codes.todo.md) — Typed exit codes для CLI-команды (~2–2.5 ч)

### Фаза 3: Реализация (P1)
- [x] [TASK-feat-validate-config](done/TASK-feat-validate-config.todo.md) — Флаг --validate-config для проверки конфигурации (~2–2.5 ч)
- [ ] [TASK-chore-phar-build](done/TASK-chore-phar-build.todo.md) — Настройка сборки Phar через box-project/box (~45 мин)
- [ ] [TASK-docs-install-skill](done/TASK-docs-install-skill.todo.md) — SKILL.md для установки AI-агентами + README (~2 ч)

### Backlog (P2, после v1.0)
- [x] [TASK-refactor-validate-config-dedup](done/TASK-refactor-validate-config-dedup.todo.md) — Вынести валидационные инварианты в Domain Specification
- [ ] [TASK-feat-cli-config-option](TASK-feat-cli-config-option.todo.md) — CLI-опция --config для указания chains.yaml
- [ ] [TASK-feat-timeout-exit-code](done/TASK-feat-timeout-exit-code.todo.md) — Propagation таймаута в CLI exit code 6
- [ ] [TASK-chore-presentation-domain-decouple](TASK-chore-presentation-domain-decouple.todo.md) — Presentation→Domain dependency removal
- [ ] GPG-подпись Phar
- [ ] Windows CI для Phar
- [ ] JSON Schema для chains.yaml
- [ ] Programmatic API (вход через PHP-код, не CLI)

## 6. Definition of Done (Критерии приёмки эпика)
- [ ] Проведён мозговой штурм, результаты задокументированы
- [ ] Выбрано решение, согласовано с владельцем проекта
- [ ] CLI-утилита устанавливается и запускается одной командой
- [ ] Оркестрация цепочки запускается через CLI с YAML-конфигурацией
- [ ] Результат выводится в терминал
- [ ] Документация по установке и использованию добавлена в README.md
- [ ] `make check` проходит

## 7. Release Notes and Deployment (Инструкция по релизу)
- [ ] Определяется по результатам Фазы 1

## 8. Risks and Dependencies (Риски и зависимости)
- Выбор формата дистрибуции влияет на всю дальнейшую реализацию — важно не торопиться с Фазой 1
- Symfony Console уже является зависимостью проекта — риск минимальный
- Phar-сборка может потребовать дополнительных инструментов (box-project/box) и настройки автозагрузки
- Текущая структура бандла (DependencyInjection, Extension) рассчитана на интеграцию в Symfony-приложение — может потребоваться адаптация для standalone-режима

## 9. Sources (Источники)
- [ ] [composer.json](../composer.json) — текущие зависимости и автозагрузка
- [ ] [Symfony Console Component](https://symfony.com/doc/current/components/console.html)
- [ ] [Box — Phar builder](https://github.com/box-project/box)
- [ ] [Composer binary vendor bins](https://getcomposer.org/doc/articles/vendor-binaries.md)
- [ ] Пример: [deptrac](https://github.com/qossmic/deptrac) — PHP-утилита с Phar + Composer binary

## 10. Comments (Комментарии)
Эпик разбит на две фазы намеренно: сначала исследование (Фаза 1), затем реализация (Фаза 2). Задачи Фазы 2 создаются только после утверждения решения владельцем проекта.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-21 | Бэкендер (Левша) | Создание эпика |
| 2026-04-23 | Тимлид (Алекс) | Фаза 1 завершена (RFC). Добавлены задачи Фазы 2/3 + backlog |
