---
name: install-task-orchestrator
description: Пошаговая установка task-orchestrator для AI-агента: проверка окружения, установка через Composer или Phar, минимальный конфиг, запуск первой цепочки
---

# Install Task Orchestrator

Пошаговая инструкция для AI-агента по установке и настройке `task-orchestrator` — CLI-утилиты для оркестрации AI-агентов в цепочки.

## Когда использовать

- Пользователь просит «установи task-orchestrator», «настрой task-orchestrator» или аналогичное
- Нужно развернуть task-orchestrator на новом окружении
- Требуется минимальная конфигурация цепочки для первого запуска

## Как использовать

### Шаг 1: Проверить PHP

Убедиться, что PHP >= 8.4 установлен и доступен в `$PATH`:

```bash
php -v
```

**Ожидаемый результат:** строка вида `PHP 8.4.x (cli) ...` или новее.

Если PHP < 8.4 или не установлен — установить:
- **Ubuntu/Debian:** `sudo add-apt-repository ppa:ondrej/php && sudo apt install php8.4-cli`
- **macOS (Homebrew):** `brew install php@8.4`
- **Windows:** скачать с [php.net](https://php.net) и добавить в `%PATH%`

### Шаг 2: Проверить Composer

Убедиться, что Composer установлен:

```bash
composer --version
```

**Ожидаемый результат:** строка вида `Composer version 2.x.x ...`.

Если Composer не установлен:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php')"
php composer-setup.php
php -r "unlink('composer-setup.php')"
mv composer.phar /usr/local/bin/composer
```

### Шаг 3: Установить task-orchestrator

#### Вариант A: Composer (primary)

Установить как глобальную CLI-утилиту:

```bash
composer global require prikotov/task-orchestrator
```

Или как зависимость проекта:

```bash
composer require prikotov/task-orchestrator
```

Опции:

| Способ | Команда | Когда использовать |
|--------|---------|-------------------|
| Global | `composer global require prikotov/task-orchestrator` | CLI-утилита доступна везде (`~/.composer/vendor/bin/`) |
| Проект | `composer require prikotov/task-orchestrator` | Integration в существующий PHP-проект (`vendor/bin/`) |

#### Вариант B: Phar (альтернатива)

Скачать Phar из GitHub Releases:

```bash
curl -L -o task-orchestrator.phar https://github.com/prikotov/task-orchestrator/releases/latest/download/task-orchestrator.phar
chmod +x task-orchestrator.phar
mv task-orchestrator.phar /usr/local/bin/task-orchestrator
```

### Шаг 4: Проверить установку

```bash
# Composer global
~/.composer/vendor/bin/task-orchestrator --version

# Composer project
vendor/bin/task-orchestrator --version

# Phar
task-orchestrator --version
```

**Ожидаемый результат:** строка вида `task-orchestrator x.y.z`.

Если команда не найдена — добавить бинарник в `$PATH` или использовать полный путь.

### Шаг 5: Создать минимальный конфиг цепочки

Создать файл `chains.yaml` с одной цепочкой и одной ролью:

```yaml
roles:
  assistant:
    prompt_file: prompts/assistant.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --model
      - gpt-4o
      - --system-prompt
      - "@system-prompt"

chains:
  hello:
    description: "Minimal chain: single agent step"
    steps:
      - type: agent
        role: assistant
```

Создать файл промпта роли `prompts/assistant.md`:

```markdown
You are a helpful AI assistant. Follow the user's instructions precisely.
```

### Шаг 6: Запустить цепочку

```bash
task-orchestrator orchestrate hello --task "Say hello"
```

Заменить `task-orchestrator` на полный путь к бинарнику, если он не в `$PATH`.

**Ожидаемый результат:** CLI выводит ход выполнения шага и итоговый ответ агента.

## Результат

- Бинарник `task-orchestrator` доступен в `$PATH` или через `vendor/bin/task-orchestrator`
- Команда `task-orchestrator --version` возвращает номер версии
- Файл `chains.yaml` содержит минимум одну цепочку с одной ролью
- Цепочка запускается через `task-orchestrator orchestrate <chain> --task "<задача>"`

## Troubleshooting

| Проблема | Причина | Решение |
|----------|---------|---------|
| `php: command not found` | PHP не установлен или не в `$PATH` | Установить PHP 8.4+ и добавить в `$PATH` |
| `composer: command not found` | Composer не установлен | Установить Composer (шаг 2) |
| `Could not find package prikotov/task-orchestrator` | Пакет не опубликован или сеть недоступна | Проверить подключение к интернет; использовать Phar (вариант B) |
| `task-orchestrator: command not found` | Бинарник не в `$PATH` | Для global: добавить `~/.composer/vendor/bin` в `$PATH`. Для проекта: использовать `vendor/bin/task-orchestrator` |
| `PHP Fatal error: Uncaught LogicException: Dependencies are missing` | Не выполнен `composer install` | Выполнить `composer install` в корне проекта |
| `Chain "hello" not found` | Не найден файл `chains.yaml` | Убедиться, что конфиг существует и путь указан корректно |
| `Role "assistant" not found` | Роль не описана в секции `roles` конфига | Добавить роль в `chains.yaml` (секция `roles`) |
| `prompt_file not found` | Указанный файл промпта не существует | Создать файл по пути из `prompt_file` |
| Phar не скачивается | Релиз не содержит Phar-файла | Использовать Composer (вариант A); Phar доступен не для всех версий |
