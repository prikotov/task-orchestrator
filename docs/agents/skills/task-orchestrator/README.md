# Install Task Orchestrator

Инструкция по установке `task-orchestrator` — CLI-утилиты для оркестрации AI-агентов.

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
~/.composer/vendor/bin/task-orchestrator --version   # global
vendor/bin/task-orchestrator --version                # project
```

## Вариант B: PHAR (альтернатива)

Скачать из [GitHub Releases](https://github.com/prikotov/task-orchestrator/releases):

```bash
curl -L -o task-orchestrator.phar https://github.com/prikotov/task-orchestrator/releases/latest/download/task-orchestrator.phar
chmod +x task-orchestrator.phar
mv task-orchestrator.phar /usr/local/bin/task-orchestrator
task-orchestrator --version
```

> **Примечание:** PHAR публикуется на best-effort основе. Для полной поддержки, включая установку `become-role`, используйте Composer.

## Матрица возможностей `v0.2.0`

| Возможность | Source/Composer | PHAR |
|---|---|---|
| Основные CLI-команды | Полная поддержка | Secondary/best-effort |
| `agent:init` и установка `become-role` | Поддерживаются полностью | Не поддерживаются: команда зарегистрирована, но завершается с кодом `1` до любых записей в файловую систему |
| Запуск установленного `become-role` | `.agents/skills/become-role/scripts/become-role.sh <role\|file>` | Недоступен |

`--force` не снимает ограничение PHAR. При попытке `agent:init` команда рекомендует Composer и не создаёт `.agents`.

## Подключение `become-role`

В Composer host-проекте после установки пакета выполните:

```bash
php vendor/bin/task-orchestrator agent:init
.agents/skills/become-role/scripts/become-role.sh <role|file>
```

В source checkout (локальной копии исходников) используйте `bin/console agent:init`, затем тот же установленный путь `.agents/skills/become-role/scripts/become-role.sh`. PHAR не устанавливает этот skill в `v0.2.0`.

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

Полный пример со всеми возможностями (retry, budget, fallback, quality gates, dynamic chains, разные CLI-раннеры) — в [assets/chains-example.yaml](assets/chains-example.yaml).

По умолчанию используется путь к `chains.yaml` из Symfony-конфигурации. Чтобы указать произвольный конфиг — опция `--config`:

```bash
php vendor/bin/task-orchestrator agent:orchestrate --config=path/to/chains.yaml "Задача"
```

## Troubleshooting

| Проблема | Решение |
|----------|---------|
| `php: command not found` | Установить PHP >= 8.4.1, добавить в `$PATH` |
| Composer сообщает об отсутствии `ext-openssl` | Установить и включить расширение PHP OpenSSL |
| Composer сообщает об отсутствии `ext-zlib` | Установить и включить расширение PHP Zlib |
| `composer: command not found` | Установить Composer: [getcomposer.org](https://getcomposer.org) |
| `task-orchestrator: command not found` | Добавить `~/.composer/vendor/bin` в `$PATH` или использовать полный путь |
| `Could not find package` | Проверьте имя и доступность пакета на Packagist; PHAR подходит только для best-effort возможностей из матрицы выше |
| `Chain not found` | Проверить путь к `chains.yaml` в конфигурации |
