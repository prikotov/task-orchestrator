# Install Task Orchestrator

Инструкция по установке `task-orchestrator` — CLI-утилиты для оркестрации AI-агентов.

## Требования

- **PHP >= 8.4**
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

## Вариант B: Phar (альтернатива)

Скачать из [GitHub Releases](https://github.com/prikotov/task-orchestrator/releases):

```bash
curl -L -o task-orchestrator.phar https://github.com/prikotov/task-orchestrator/releases/latest/download/task-orchestrator.phar
chmod +x task-orchestrator.phar
mv task-orchestrator.phar /usr/local/bin/task-orchestrator
task-orchestrator --version
```

> **Note:** Phar публикуется на best-effort основе. Для автообновления используйте Composer.

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
| `php: command not found` | Установить PHP 8.4+, добавить в `$PATH` |
| `composer: command not found` | Установить Composer: [getcomposer.org](https://getcomposer.org) |
| `task-orchestrator: command not found` | Добавить `~/.composer/vendor/bin` в `$PATH` или использовать полный путь |
| `Could not find package` | Пакет не опубликован на Packagist — использовать Phar |
| `Chain not found` | Проверить путь к `chains.yaml` в конфигурации |
