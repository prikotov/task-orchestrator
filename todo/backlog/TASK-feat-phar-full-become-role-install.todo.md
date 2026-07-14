---
type: feat
created: 2026-07-14
value: V2
complexity: C4
priority: P2
depends_on: TASK-fix-v0-2-0-phar-become-role-distribution
epic:
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: backlog
---

# TASK-feat-phar-full-become-role-install: Полная поддержка `agent:init`/`become-role` в PHAR

## 0. Простое описание (Human Brief)

### Problem

Сейчас в v0.2.0 `agent:init` в PHAR-режиме завершает работу с `Command::FAILURE` и не устанавливает `become-role` (осознанный ограниченный контракт релиза), поэтому пользователи PHAR вынуждены переключаться на Composer-дистрибуцию для полной настройки навыков.

### Solution Sketch

Нужно добавить полноценный сценарий установки из PHAR в безопасной модели: `agent:init`/`agent:init --force` должны корректно подготовить `.agents/skills/become-role` и сделать доступным `become-role` без изменения source/Composer поведения.

### Expected Result

После доработки PHAR сможет дать тот же контракт и UX для инициализации `become-role`, который сейчас есть у source/Composer.
## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)

Когда пользователь использует `task-orchestrator.phar`, я хочу запускать `agent:init` и получать рабочий `become-role`, чтобы все поддерживаемые каналы дистрибуции давали одинаковый путь настройки.

### Goal (Цель по SMART)

К концу задачи `agent:init`/`agent:init --force` в PHAR должен быть полностью работоспособным: создать/обновить `.agents/skills/become-role` и корректно обеспечить запуск `.agents/skills/become-role/scripts/become-role.sh`. Поведение для source/Composer не меняется, PHAR-проект получает явную гарантию full support в отдельном релизном контуре.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** `apps/console/src/Module/Orchestrator/Command/InitCommand.php`, соответствующие сервисы установки/поиска путей, `bin/phar-smoke`, тесты и документация дистрибуции (`README.md`, `README.en.md`, `README.zh.md`, `docs/guide/cli.md`, `docs/guide/troubleshooting.md`).
- **Текущее поведение:** при запуске `php task-orchestrator.phar agent:init` команда немедленно завершается с `1` и рекомендует Composer.
- **Что НЕ делаем:** менять контракт `agent:role-skills`/`agent:token` и не возвращаться к прежнему, неконтролируемому PHAR-писанию.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have
- [ ] PHAR-режим запускает `agent:init` и `agent:init --force` без аварий, с созданием/обновлением корректного `.agents/skills/become-role`.
- [ ] Установка `become-role` в PHAR работает через поддерживаемый механизм работы с `phar://` и не использует некорректные симлинки на внутренний путь PHAR.
- [ ] В PHAR `become-role` запускается через установленный путь `.agents/skills/become-role/scripts/become-role.sh <role|file>`.
- [ ] Источник файлов skill из PHAR корректно распознаётся и копируется/извлекается только в безопасной модели.
- [ ] Добавлены автоматические проверки для PHAR success-сценария `agent:init` и regression-тест на сохранение idempotency/`--force`.
- [ ] Документация описывает новый PHAR-контракт как full support (без скрытых ограничений).

### 🟡 Should Have
- [ ] Поддержка для CI: отдельный PHAR smoke path, проверяющий позитивный сценарий `agent:init` после сборки.
- [ ] Диагностика: понятное сообщение при невозможности установки с диагностикой причины.

### 🟢 Could Have
- [ ] Обновить примеры сценариев для разных директорий запуска PHAR.

### ⚫ Won't Have
- [ ] Изменение текущего ограничения для v0.2.0 в уже выпущенном релизе.
- [ ] Поддержка произвольного managed-copy инсталлятора без отдельного согласования архитектуры.

## 4. Implementation Plan (План реализации)
1. [ ] Исследовать возможные подходы установки ресурсов `become-role` из PHAR в безопасный PHAR-safe pipeline.
2. [ ] Реализовать PHAR-путь `agent:init` и `--force` (без обратной неконсистентности с source/Composer).
3. [ ] Добавить/обновить интеграционные тесты PHAR и unit тесты критичных ветвей.
4. [ ] Обновить `bin/phar-smoke` для позитивной проверки `agent:init`.
5. [ ] Актуализировать RU/EN/ZH docs и matrix возможностей дистрибуций.
6. [ ] Прогнать проверочный набор для релизного контура.

## 5. Definition of Done (Критерии приёмки)
- [ ] `task-orchestrator.phar agent:init` и `... --force` возвращают code `0`.
- [ ] `.agents/skills/become-role` и `.agents/skills/become-role/scripts/become-role.sh` доступны после выполнения.
- [ ] Успешно выполняется `bash .agents/skills/become-role/scripts/become-role.sh <role|file>` для тестовой роли.
- [ ] Доказаны regression-тестами idempotency и поведение при существующем/искажённом `become-role` каталоге.
- [ ] Документация не содержит устаревших ограничений для PHAR.
- [ ] Обновлён и прозрачен в changelog/эпике релиза для следующего релиза.

## 6. Verification (Самопроверка)

```bash
# PHAR-дистрибутив
php vendor/bin/task-orchestrator agent:init
php task-orchestrator.phar list
php task-orchestrator.phar agent:init
php task-orchestrator.phar agent:init --force
php task-orchestrator.phar agent:init --help

# Тесты
vendor/bin/phpunit tests/Integration/Module/Orchestrator/Command/InitCommandTest.php
vendor/bin/phpunit tests/Unit/Console/Module/Orchestrator/Command/InitCommandTest.php
```

## 7. Risks and Dependencies (Риски и зависимости)
- Риск: безопасная работа с `phar://` и записью ресурсов в host-файловую систему без ложных/небезопасных side-effects.
- Риск: изменения в PHAR-installer могут потребовать доп. изменений в release workflow и smoke-сценариях.

## 8. Sources (Источники)
- `TASK-fix-v0-2-0-phar-become-role-distribution`
- `docs/guide/cli.md`

## 9. Comments (Комментарии)
- Это задача будущего (post-v0.2): цель — убрать осознанное ограничение, введённое как безопасный contract для текущего релиза.
