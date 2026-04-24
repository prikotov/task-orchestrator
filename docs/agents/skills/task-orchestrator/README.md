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
task-orchestrator app:agent:orchestrate --validate-config "check"
```

Запустить цепочку:

```bash
task-orchestrator app:agent:orchestrate "Ваша задача"
```

## Troubleshooting

| Проблема | Решение |
|----------|---------|
| `php: command not found` | Установить PHP 8.4+, добавить в `$PATH` |
| `composer: command not found` | Установить Composer: [getcomposer.org](https://getcomposer.org) |
| `task-orchestrator: command not found` | Добавить `~/.composer/vendor/bin` в `$PATH` или использовать полный путь |
| `Could not find package` | Пакет не опубликован на Packagist — использовать Phar |
| `Chain not found` | Проверить путь к `chains.yaml` в конфигурации |
