# Установка Task Orchestrator

Инструкция по установке `task-orchestrator` — утилиты командной строки для оркестрации ИИ-агентов.

## Требования

- **PHP >= 8.4.1**
- **Расширение PHP OpenSSL (`ext-openssl`)**
- **Расширение PHP Zlib (`ext-zlib`)**
- **Composer** (для варианта A)

## Вариант A: Composer (рекомендуется)

Глобальная установка (CLI доступен везде):

```bash
composer global require prikotov/task-orchestrator
```

В существующий проект:

```bash
composer require prikotov/task-orchestrator
```

Если Composer не установлен — см. [официальную инструкцию](https://getcomposer.org/).

Проверка:

```bash
~/.composer/vendor/bin/task-orchestrator --version   # глобальная установка
vendor/bin/task-orchestrator --version                # установка в проекте
```

## Вариант B: PHAR (альтернатива)

Скачать из [релизов GitHub](https://github.com/prikotov/task-orchestrator/releases):

```bash
curl -L -o task-orchestrator.phar https://github.com/prikotov/task-orchestrator/releases/latest/download/task-orchestrator.phar
chmod +x task-orchestrator.phar
mv task-orchestrator.phar /usr/local/bin/task-orchestrator
task-orchestrator --version
```

> **Примечание:** PHAR публикуется по мере возможности. Для полной поддержки, включая установку `become-role`, используйте Composer.

## Матрица возможностей `v0.2.0`

| Возможность | Исходники/Composer | PHAR |
|---|---|---|
| Основные CLI-команды | Полная поддержка | Вторичная поддержка по мере возможности |
| `agent:init` и установка `become-role` | Поддерживаются полностью | Не поддерживаются: команда зарегистрирована, но завершается с кодом `1` до любых записей в файловую систему |
| Запуск установленного `become-role` | `.agents/skills/become-role/scripts/become-role.sh <role\|file>` | Недоступен |

`--force` не снимает ограничение PHAR. При попытке `agent:init` команда рекомендует Composer и не создаёт `.agents`.

## Подключение `become-role`

В проекте-потребителе Composer после установки пакета выполните:

```bash
php vendor/bin/task-orchestrator agent:init
.agents/skills/become-role/scripts/become-role.sh <role|file>
```

В локальной копии исходников используйте `bin/console agent:init`, затем тот же установленный путь `.agents/skills/become-role/scripts/become-role.sh`. PHAR не устанавливает этот навык в `v0.2.0`.

## Первый запуск

Проверить конфигурацию цепочек:

```bash
php vendor/bin/task-orchestrator agent:orchestrate --validate-config "check"
```

Проверить, что роли из `chains.yaml` запускаются и отвечают:

```bash
php vendor/bin/task-orchestrator validate:connectivity --dry-run
php vendor/bin/task-orchestrator validate:connectivity --timeout=30
```

Запустить цепочку:

```bash
php vendor/bin/task-orchestrator agent:orchestrate "Ваша задача"
```

## Конфигурация (`chains.yaml`)

Конфигурация состоит из двух секций: `roles` (роли агентов) и `chains` (цепочки).

Полный пример со всеми возможностями (повторные попытки, бюджет, альтернативный путь, контрольные точки качества, динамические цепочки, разные CLI-раннеры) — в [assets/chains-example.yaml](assets/chains-example.yaml).

По умолчанию используется путь к `chains.yaml` из Symfony-конфигурации. Чтобы указать произвольный конфиг — опция `--config`:

```bash
php vendor/bin/task-orchestrator agent:orchestrate --config=path/to/chains.yaml "Задача"
```

## Устранение неполадок

| Проблема | Решение |
|----------|---------|
| `php: command not found` | Установить PHP >= 8.4.1, добавить в `$PATH` |
| Composer сообщает об отсутствии `ext-openssl` | Установить и включить расширение PHP OpenSSL |
| Composer сообщает об отсутствии `ext-zlib` | Установить и включить расширение PHP Zlib |
| `composer: command not found` | Установить Composer: [getcomposer.org](https://getcomposer.org) |
| `task-orchestrator: command not found` | Добавить `~/.composer/vendor/bin` в `$PATH` или использовать полный путь |
| `Could not find package` | Проверьте имя и доступность пакета на Packagist; PHAR подходит только для возможностей с вторичной поддержкой из матрицы выше |
| `Chain not found` | Проверьте путь к `chains.yaml` в конфигурации |
