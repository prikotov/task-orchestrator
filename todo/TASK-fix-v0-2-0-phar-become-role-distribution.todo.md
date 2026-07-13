---
type: fix
created: 2026-07-14
value: V4
complexity: C2
priority: P0
depends_on:
epic:
author: system_analyst_sherlock (Шерлок)
assignee: backend_developer_levsha
branch: task/fix-v0-2-0-phar-become-role-distribution
pr: https://github.com/prikotov/task-orchestrator/pull/307
status: review
---

# TASK-fix-v0-2-0-phar-become-role-distribution: Зафиксировать контракт become-role для Composer и PHAR

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

`agent:init` создаёт в host-проекте симлинк на каталог skill `become-role` внутри установленного пакета. Это корректно для source/Composer-дистрибутива, но не для PHAR: источник находится в виртуальной файловой системе `phar://`, а текущая команда пытается использовать его как обычный каталог и может перейти к созданию некорректного симлинка или копированию недоступных ресурсов.

Публичная инструкция skill также предлагает checkout-only путь `docs/agents/skills/become-role/scripts/become-role.sh`. После установки пакета в host-проект этот путь отсутствует в корне host-проекта, хотя `agent:init` устанавливает правильную точку входа в `.agents/skills/`.

### Варианты или путь решения (Solution Sketch)

Сохраняем действующий контракт RFC по каналам дистрибуции:

- Composer/Packagist — primary/full support;
- PHAR — secondary/best-effort;
- в `v0.2.0` команда `agent:init` поддерживается только в source/Composer-режиме;
- в PHAR команда остаётся зарегистрированной, но до любых записей завершается fail-fast с ненулевым кодом и предлагает установить пакет через Composer;
- полноценная установка `become-role` из PHAR в эту задачу не входит.

### Ожидаемый результат (Expected Result)

Composer-host получает рабочий `become-role` через `agent:init` и запускает его по установленному пути `.agents/skills/become-role/scripts/become-role.sh`. PHAR явно и безопасно сообщает об ограничении, не создаёт `.agents` и не пытается работать с `phar://` как с обычной файловой системой. Оба контракта защищены реальными distribution smoke tests.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)

> Когда я устанавливаю task-orchestrator через поддерживаемый канал, я хочу получить правдивый и проверенный контракт `become-role`, чтобы Composer-установка работала в реальном host-проекте, а PHAR не оставлял повреждённые или ложные артефакты.

### Goal (Цель по SMART)

До создания тега `v0.2.0` разграничить поведение `agent:init` для Composer и PHAR, исправить публичную точку запуска `become-role`, добавить Composer-host smoke и усилить PHAR smoke объективными проверками команд, кода завершения и отсутствия файловых записей.

## 2. Context and Scope (Контекст и Границы)

### Где делаем

- `apps/console/src/Module/Orchestrator/Command/InitCommand.php` — `InitCommand` ([консольная команда Presentation](../docs/conventions/layers/presentation/console_command.md));
- `tests/Integration/Module/Orchestrator/Command/InitCommandTest.php` и distribution smoke tests согласно [конвенции тестирования](../docs/conventions/testing/index.md);
- `bin/phar-smoke`, отдельный Composer-host smoke и их `Makefile`/CI entry points;
- `docs/agents/skills/become-role/SKILL.md` и связанная публичная документация;
- `README.md`, `README.en.md`, `README.zh.md`, `docs/guide/cli.md`, `docs/guide/troubleshooting.md`, `docs/agents/skills/become-role/README.md`, `docs/agents/skills/task-orchestrator/README.md` — по фактическим упоминаниям изменённого контракта.

### Текущее поведение

1. `agent:init` всегда вычисляет source как `<packageDir>/docs/agents/skills/become-role` и затем работает с ним через обычные операции файловой системы.
2. PHAR smoke проверяет `--version` и только две команды модулей: `agent:run`, `validate:connectivity`.
3. Integration test `InitCommandTest` создаёт команду напрямую с fixture package; реальная установка пакета Composer в изолированный host-проект не проверяется.
4. `docs/agents/skills/become-role/SKILL.md` запускает скрипт через checkout-only путь `docs/...`, а не через установленный `.agents/...`.

### Границы (Out of Scope)

- Не извлекать роли, skills или скрипты из PHAR во внешнюю файловую систему.
- Не реализовывать managed-copy installer, manifest установленных файлов, обновление или удаление таких копий.
- Не добавлять fallback с копированием вместо симлинка.
- Не рефакторить файловую логику `agent:init` и не переносить её между слоями без необходимости для принятого fail-fast контракта.
- Не менять публичные контракты `agent:role-skills` и `agent:token`, кроме проверки наличия команд в PHAR.
- Не расширять гарантии PHAR с best-effort до full support.
- Не создавать сейчас отдельную будущую задачу на полноценную PHAR-установку ролей/skills, managed-copy installer или рефакторинг файловой логики.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] `agent:init` определяет запуск из PHAR до первой потенциальной записи (`mkdir`, `remove`, `symlink`, `copy` или их эквивалентов).
- [ ] В PHAR `agent:init` завершается с `Command::FAILURE`/exit code `1`, а сообщение явно содержит: ограничение PHAR, рекомендацию использовать Composer и рабочую Composer-команду `php vendor/bin/task-orchestrator agent:init`.
- [ ] В PHAR обычный запуск и запуск с `--force` не создают `.agents`, не изменяют существующий sentinel в `.agents` и не пытаются создавать симлинк/копию с источником `phar://`.
- [ ] Source/Composer-поведение не регрессирует: `agent:init` создаёт корректный относительный симлинк, остаётся идемпотентным и сохраняет действующий контракт `--force`.
- [ ] PHAR smoke из checkout CWD и произвольного временного CWD проверяет наличие `agent:init`, `agent:role-skills` и `agent:token` в дополнение к уже проверяемым командам.
- [ ] PHAR smoke реально запускает `agent:init` из временного CWD, ожидает exit code `1`, проверяет диагностическое сообщение и подтверждает отсутствие любых созданных `.agents`-артефактов.
- [ ] Добавлен реальный Composer-host smoke: пакет устанавливается Composer в изолированный временный host-проект как физическая копия, а не как симлинк на checkout; запускается установленный `vendor/bin/task-orchestrator agent:init`; проверяются симлинк, доступность `SKILL.md` и запуск `.agents/skills/become-role/scripts/become-role.sh <role>`.
- [ ] Composer-host smoke не использует checkout-only `docs/...` из CWD и падает, если установленный skill или его скрипт фактически отсутствуют в Composer package.
- [ ] Composer-host smoke имеет явную локальную команду в `Makefile` и выполняется в CI как обязательная проверка до релиза.
- [ ] Публичная инструкция в `docs/agents/skills/become-role/SKILL.md` запускает только установленный путь `.agents/skills/become-role/scripts/become-role.sh <role|file>`.
- [ ] Документация содержит feature matrix с отдельными колонками Source/Composer и PHAR: `agent:init`/установка `become-role` поддерживаются только Source/Composer; PHAR — best-effort, команда зарегистрирована, но установка fail-fast не поддерживается.
- [ ] Все упоминания `agent:init`, `become-role.sh` и PHAR-поддержки найдены поиском и согласованы в русской, английской и китайской версиях README, CLI guide, troubleshooting и документации skills.

### 🟡 Should Have (Желательно)

- [ ] Диагностика PHAR-кейса короткая, детерминированная и пригодна для assert по устойчивым фрагментам, без вывода внутренних абсолютных `phar://`-путей.
- [ ] Smoke scripts удаляют временные каталоги и собранный тестовый state через `trap`, включая аварийное завершение.

### 🟢 Could Have (Опционально)

- [ ] Нет: задача release-blocking, опциональный scope намеренно исключён.

### ⚫ Won't Have (Не будем делать)

- [ ] Полноценное извлечение ролей/skills из PHAR.
- [ ] Managed-copy installer или fallback `symlink -> copy`.
- [ ] Рефакторинг всей файловой логики установки.
- [ ] Обещание full support для PHAR.
- [ ] Новая задача на перечисленные будущие улучшения в рамках текущей работы.

## 4. Implementation Plan (План реализации)

1. [ ] Добавить в `InitCommand` минимальную раннюю проверку PHAR runtime и вернуть детерминированную ошибку до любых файловых мутаций; не менять Composer-ветку выполнения.
2. [ ] Дополнить integration tests `InitCommand` регрессиями поддерживаемого source/Composer-поведения и `--force` там, где это необходимо для фиксации контракта.
3. [ ] Усилить `bin/phar-smoke`: проверить регистрацию `agent:init`, `agent:role-skills`, `agent:token` из двух CWD и негативный no-write сценарий `agent:init` в distributable CWD.
4. [ ] Добавить воспроизводимый Composer-host smoke через изолированный temp project и Composer path repository с отключённым symlink-режимом; запустить установленный CLI, `agent:init` и установленный `.agents/.../become-role.sh`.
5. [ ] Подключить Composer-host smoke как именованный `Makefile` target и обязательную CI-проверку.
6. [ ] Заменить checkout-only команду в `docs/agents/skills/become-role/SKILL.md`, описать feature matrix и синхронизировать все документы, найденные поиском по `agent:init`, `become-role.sh`, `PHAR`/`Phar`.
7. [ ] Выполнить целевые smoke tests, полный `make check`, `todo-md-validate` и `git diff --check`.

## 5. Definition of Done (Критерии приёмки)

- [ ] `php task-orchestrator.phar agent:init` и вариант с `--force` возвращают `1`, рекомендуют Composer и не меняют файловую систему host-проекта.
- [ ] `php task-orchestrator.phar list` из обоих CWD содержит `agent:init`, `agent:role-skills`, `agent:token`, `agent:run`, `validate:connectivity`.
- [ ] В изолированном Composer-host `vendor/bin/task-orchestrator agent:init` возвращает `0`, создаёт рабочий `.agents/skills/become-role`, а установленный `.agents/skills/become-role/scripts/become-role.sh` успешно резолвит тестовую роль.
- [ ] Ни один Composer-host smoke assert не разрешается через исходный checkout вместо физически установленной package copy.
- [ ] `docs/agents/skills/become-role/SKILL.md` не содержит исполняемый checkout-only путь `docs/agents/skills/become-role/scripts/become-role.sh`.
- [ ] Feature matrix и все пользовательские инструкции не обещают `agent:init`/установку `become-role` для PHAR в `v0.2.0`.
- [ ] Целевые тесты, `make composer-host-smoke`, `make phar-smoke`, полный `make check` и `git diff --check` проходят.

## 6. Verification (Самопроверка)

```bash
php vendor/prikotov/todo-md/bin/todo-md-validate todo/TASK-fix-v0-2-0-phar-become-role-distribution.todo.md
vendor/bin/phpunit tests/Integration/Module/Orchestrator/Command/InitCommandTest.php
make composer-host-smoke
make phar-smoke
make check
git diff --check
rg -n "agent:init|become-role\\.sh|PHAR|Phar" README.md README.en.md README.zh.md docs/guide docs/agents/skills
```

## 7. Risks and Dependencies (Риски и зависимости)

- Задача блокирует выпуск `v0.2.0`: без неё публичный дистрибутив обещает неподдерживаемое поведение и PHAR может писать некорректные артефакты в host-проект.
- Composer path repository по умолчанию может установить пакет симлинком и дать ложнозелёный smoke. Для проверки дистрибутива требуется физическая копия (`options.symlink: false` или эквивалент с тем же проверяемым результатом).
- Проверка только `list` недостаточна для `agent:init`: команда может быть зарегистрирована, но разрушительно отработать. Негативный PHAR-сценарий должен запускать команду реально.
- Проверка только прямого `new InitCommand(...)` недостаточна для Composer-host: она не проверяет состав установленного пакета, vendor binary и путь установленного skill.
- Composer-host smoke зависит от Composer, PHAR smoke — от Box; CI обязан явно устанавливать необходимые инструменты.
- PHAR остаётся secondary/best-effort по действующему RFC; эта задача устраняет ложное обещание, а не повышает уровень поддержки канала.

## 8. Sources (Источники)

- [RFC: дистрибуция task-orchestrator как CLI-утилиты](../docs/research/rfc/cli-distribution-rfc.md) — Composer full, PHAR best-effort.
- [Конвенция консольных команд](../docs/conventions/layers/presentation/console_command.md).
- [Конвенция тестирования](../docs/conventions/testing/index.md).
- [Исходная задача PHAR-сборки](done/TASK-chore-phar-build.todo.md).
- [Исходная задача become-role](done/TASK-feat-become-role-skills.todo.md).
- `apps/console/src/Module/Orchestrator/Command/InitCommand.php`.
- `bin/phar-smoke` и `box.json.dist`.

## 9. Comments (Комментарии)

Архитектурное решение уже принято: для `v0.2.0` не искать универсальный installer поверх виртуальной файловой системы PHAR. Безопасный минимальный контракт — честный fail-fast в PHAR и полностью проверенная установка через Composer.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-14 | system_analyst_sherlock (Шерлок) | Создание release-blocking задачи по принятому контракту Composer/PHAR. |
