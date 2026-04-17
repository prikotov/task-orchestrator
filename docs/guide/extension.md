# Расширение

Orchestrator спроектирован для расширения без изменения существующего кода (Open-Closed Principle).
Три оси расширения: **движки (runner'ы)**, **цепочки** и **роли**.

## Содержание

- [Добавление нового движка (runner)](#добавление-нового-движка-runner)
- [Добавление новой цепочки](#добавление-новой-цепочки)
- [Использование существующей роли](#использование-существующей-роли)
- [Добавление новой роли](#добавление-новой-роли)
- [Требования](#требования)

---

## Добавление нового движка (runner)

Движок инкапсулирует specifics CLI-инструмента (pi, Codex CLI, Qwen CLI и др.).
Каждый движок реализует `AgentRunnerInterface` и автоматически регистрируется в реестре через тег `agent.runner`.

### Шаг 1. Создать класс

Разместить реализацию в `Infrastructure/Service/AgentRunner/`:

```php
<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\AgentRunner\Codex;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\AgentRunRequestVo;
use Override;
use Symfony\Component\Process\Process;

/**
 * Реализация AgentRunnerInterface для Codex CLI.
 *
 * Запускает codex через Symfony Process в headless-режиме (--full-auto).
 * Вывод парсится из stdout.
 */
final readonly class CodexAgentRunner implements AgentRunnerInterface
{
    #[Override]
    public function getName(): string
    {
        return 'codex';
    }

    #[Override]
    public function isAvailable(): bool
    {
        $process = new Process(['which', 'codex']);
        $process->run();

        return $process->isSuccessful();
    }

    #[Override]
    public function run(AgentRunRequestVo $request): AgentResultVo
    {
        $command = $request->getCommand();

        if ($command === []) {
            $command = ['codex', '--full-auto', '--format', 'json'];
        }

        // System prompt
        if ($request->getSystemPrompt() !== null && !in_array('--system-prompt', $command, true)) {
            $command[] = '--system-prompt';
            $command[] = $request->getSystemPrompt();
        }

        // Model
        if ($request->getModel() !== null && !in_array('--model', $command, true)) {
            $command[] = '--model';
            $command[] = $request->getModel();
        }

        // Task prompt
        $command[] = $request->getTask();

        $process = new Process($command);
        $process->setTimeout($request->getTimeout());

        if ($request->getWorkingDir() !== null) {
            $process->setWorkingDirectory($request->getWorkingDir());
        }

        $process->run();

        if (!$process->isSuccessful()) {
            return AgentResultVo::createFromError(
                $process->getErrorOutput() ?: sprintf('codex exited with code %d.', $process->getExitCode() ?? 1),
                $process->getExitCode() ?? 1,
            );
        }

        // Парсинг stdout Codex CLI (адаптировать под реальный формат вывода)
        $output = $process->getOutput();

        return AgentResultVo::createFromSuccess(
            outputText: $output,
        );
    }
}
```

### Шаг 2. Зарегистрировать в контейнере

Движок регистрируется **автоматически** через `_instanceof` в `config/services.yaml`:

```yaml
services:
  _instanceof:
    TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface:
      tags: ['agent.runner']
```

Все классы, реализующие `AgentRunnerInterface`, попадают в `AgentRunnerRegistryService` через `!tagged_iterator agent.runner`.

> **Важно:** декораторы (`RetryingAgentRunner`, `CircuitBreakerAgentRunner`) исключены из auto-discovery — не добавляйте их в реестр.

### Шаг 3. Использовать в цепочке

В YAML-конфигурации цепочек указать движок через поле `runner` в шаге:

```yaml
chains:
  codex_implement:
    description: "Реализация через Codex CLI"
    steps:
      - { type: agent, role: backend_developer, runner: codex }
```

Или в конфигурации роли, указав соответствующую CLI-команду:

```yaml
roles:
  backend_developer_codex:
    prompt_file: docs/agents/roles/team/backend_developer.ru.md
    command:
      - codex
      - --full-auto
      - --format
      - json
      - --model
      - gpt-4o
      - --system-prompt
      - "@system-prompt"
```

### Шаг 4. Написать тесты

**Unit-тесты** — базовые проверки контракта:

```php
<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\AgentRunner\Codex;

use TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\AgentRunner\Codex\CodexAgentRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodexAgentRunner::class)]
final class CodexAgentRunnerTest extends TestCase
{
    #[Test]
    public function getNameReturnsCodex(): void
    {
        $runner = new CodexAgentRunner();
        self::assertSame('codex', $runner->getName());
    }

    #[Test]
    public function isAvailableReturnsBool(): void
    {
        $runner = new CodexAgentRunner();
        self::assertIsBool($runner->isAvailable());
    }
}
```

Разместить в `tests/Unit/Infrastructure/Service/AgentRunner/Codex/CodexAgentRunnerTest.php`.

**Минимальный набор тестов для нового runner'а:**

| Тест | Что проверяет |
|---|---|
| `getName` возвращает уникальное имя | Идентификация в реестре |
| `isAvailable` возвращает bool | Проверка доступности CLI |
| `run` с валидным `AgentRunRequestVo` | Основной сценарий (если можно замокать Process) |
| `run` при таймауте процесса | Обработка `ProcessTimedOutException` |
| `run` при ненулевом exit code | Обработка ошибок CLI |

### Краткий чеклист

- [ ] Класс в `Infrastructure/Service/AgentRunner/<Name>/`
- [ ] Реализует `AgentRunnerInterface` (`getName`, `isAvailable`, `run`)
- [ ] Возвращает `AgentResultVo` (success или error)
- [ ] Имя runner'а уникально среди зарегистрированных
- [ ] Unit-тесты покрывают контракт интерфейса
- [ ] CLI-инструмент документирован в конфигурации цепочек (закомментированный пример)

---

## Добавление новой цепочки

Цепочки определяются в YAML-конфигурации (параметр `%task_orchestrator.chains_yaml%`).
Два типа: **static** (линейные, с поддержкой итераций и quality gates) и **dynamic** (фасилитатор).

### Static-цепочка

```yaml
chains:
  review_only:
    description: "Только ревью существующего кода"
    steps:
      - { type: agent, role: code_reviewer_backend }
```

С итерационным циклом (review → fix → review):

```yaml
chains:
  review_fix:
    description: "Ревью с автоматическим исправлением"
    steps:
      - type: agent
        role: code_reviewer_backend
        name: review
      - type: agent
        role: backend_developer
        name: fix
    fix_iterations:
      - group: review-fix
        steps: [review, fix]
        max_iterations: 3
```

С quality gates (детерминированные проверки):

```yaml
chains:
  implement_verified:
    description: "Реализация с проверками качества"
    steps:
      - { type: agent, role: backend_developer }
      - type: quality_gate
        command: 'make lint-php'
        label: 'PHP CodeSniffer'
        timeout_seconds: 60
      - type: quality_gate
        command: 'make tests-unit'
        label: 'Unit Tests'
        timeout_seconds: 120
```

**Поля шага static-цепочки:**

| Поле | Тип | Обязательное | По умолчанию | Описание |
|---|---|---|---|---|
| `type` | `string` | да | — | `agent` или `quality_gate` |
| `role` | `string` | да (для `agent`) | — | Имя роли из секции `roles` |
| `name` | `?string` | нет | `null` | Имя шага для ссылок из `fix_iterations` |
| `runner` | `?string` | нет | default | Имя runner'а |
| `model` | `?string` | нет | из роли | Модель LLM |
| `tools` | `?string` | нет | из роли | Список инструментов |
| `command` | `string` | да (для `quality_gate`) | — | Shell-команда |
| `label` | `string` | да (для `quality_gate`) | — | Человекочитаемое название |
| `timeout_seconds` | `int` | нет | `120` | Таймаут (только `quality_gate`) |

Подробнее — в [Цепочки](chains.md).

### Dynamic-цепочка

```yaml
chains:
  design_review:
    type: dynamic
    description: "Итеративное design review"
    facilitator: system_architect
    participants: [system_analyst, backend_developer, code_reviewer_backend]
    max_rounds: 8
```

С кастомными промптами:

```yaml
chains:
  strategy_session:
    type: dynamic
    description: "Стратегическая сессия"
    facilitator: team_lead
    participants: [product_owner, marketer, system_analyst]
    max_rounds: 15
    prompts:
      brainstorm_system: prompts/strategy/system.txt
      facilitator_append: prompts/strategy/facilitator_append.txt
      facilitator_start: prompts/strategy/facilitator_start.txt
      facilitator_continue: prompts/strategy/facilitator_continue.txt
      facilitator_finalize: prompts/strategy/facilitator_finalize.txt
      participant_append: prompts/strategy/participant_append.txt
      participant_user: prompts/strategy/participant_user.txt
```

**Поля dynamic-цепочки:**

| Поле | Обязательное | Описание |
|---|---|---|
| `type` | да | `dynamic` |
| `facilitator` | да | Роль фасилитатора |
| `participants` | да | Список ролей-участников (≥ 1) |
| `max_rounds` | нет | Лимит раундов (default: 10) |
| `description` | нет | Описание |
| `prompts` | нет | Маппинг из 7 именных промптов в .txt файлы. Если указан — все ключи обязательны (см. список ниже) |

**Обязательные ключи `prompts`:**

| Ключ | Описание |
|---|---|
| `brainstorm_system` | Системный промпт для dynamic-цепочки |
| `facilitator_append` | Дополнительный промпт фасилитатора |
| `facilitator_start` | Промпт начала дискуссии |
| `facilitator_continue` | Промпт продолжения дискуссии |
| `facilitator_finalize` | Промпт завершения дискуссии |
| `participant_append` | Дополнительный промпт участника |
| `participant_user` | Пользовательский промпт участника |

### Краткий чеклист

- [ ] Цепочка добавлена в YAML-конфигурацию
- [ ] Все роли из шагов существуют в секции `roles` (или как `.md` файлы)
- [ ] `fix_iterations` ссылается на существующие имена шагов (поле `name`)
- [ ] Quality gates содержат `command` и `label`
- [ ] Цепочка проверена через `--dry-run` (если доступно)

---

## Использование существующей роли

Роли — это `.md` файлы, которые загружаются через `RolePromptBuilder`. Путь к роли — параметр `%task_orchestrator.roles_dir%`.

### Как работает маппинг

1. В YAML-конфигурации шаг ссылается на роль по имени: `role: backend_developer`
2. `RolePromptBuilder` ищет файл `<roles_dir>/backend_developer.ru.md`
3. Содержимое файла становится system prompt для агента
4. `@system-prompt` в `command` резолвится в содержимое `prompt_file`

### Конфигурация роли в YAML

Каждая роль определяет `prompt_file` и `command`:

```yaml
roles:
  system_analyst:
    prompt_file: docs/agents/roles/team/system_analyst.ru.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --model
      - glm-4.7
      - --system-prompt
      - "@system-prompt"           # резолвится в содержимое prompt_file
      - --append-system-prompt
      - "@append-system-prompt"   # опционально
      - --tools
      - "read,grep,find,ls"
```

**Поля:**

| Поле | Обязательное | Описание |
|---|---|---|
| `prompt_file` | да | Путь к `.md` файлу (system prompt) |
| `command` | да | CLI-команда. `@system-prompt` → содержимое файла |
| `fallback` | нет | Альтернативная команда при недоступности основного runner |

### Переопределение модели для cross-model verification

Можно использовать одну и ту же роль с другой моделью, создав отдельную запись:

```yaml
roles:
  verifier:
    prompt_file: docs/agents/roles/team/code_reviewer_backend.ru.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --model
      - glm-4.7               # другая модель
      - --system-prompt
      - "@system-prompt"
```

---

## Добавление новой роли

### Шаг 1. Создать `.md` файл

Разместить файл в директории ролей (параметр `%task_orchestrator.roles_dir%`) с суффиксом локали:

```
docs/agents/roles/team/data_engineer.ru.md
```

### Шаг 2. Формат файла

Файл — Markdown с описанием роли. Первая строка с `# ` используется как описание в CLI-выводе (`RolePromptBuilder::extractDescription`).

```markdown
---
role: data_engineer
title: "Data Engineer"
name: "Алексей"
personality:
  jung: ["Expert"]
  disc: "D3 I2 S4 C8"
  belbin: ["Completer Finisher"]
description: "Проектирует pipelines, оптимизирует запросы и управляет миграциями данных."
---

# Data Engineer (`Инженер данных`)

**Цель:** Обеспечение надёжного и эффективного потока данных.

## Описание
Отвечает за проектирование ETL/ELT pipelines, оптимизацию SQL-запросов,
управление схемами данных и миграциями.

## Задачи
1. **ETL/ELT:** Проектирование и реализация pipelines для обработки данных.
2. **Оптимизация:** Анализ и оптимизация медленных запросов.
3. **Миграции:** Управление изменениями схемы базы данных.

## Входные данные
* Требования к данным от аналитиков и продактов.
* Текущая схема базы данных.
* Метрики производительности запросов.

## Выходные данные
* SQL-миграции.
* Конфигурация pipelines.
* Документация на data-слой.

## Стиль работы
"Данные должны течь как вода — быстро, чисто и без потерь."

## Предпочтения
Ценит воспроизводимость, версионирование и мониторинг.
```

### Шаг 3. Зарегистрировать в YAML-конфигурации

```yaml
roles:
  data_engineer:
    prompt_file: docs/agents/roles/team/data_engineer.ru.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --model
      - glm-5-turbo
      - --system-prompt
      - "@system-prompt"
```

### Шаг 4. Использовать в цепочке

```yaml
chains:
  data_pipeline:
    description: "Проектирование data pipeline"
    steps:
      - { type: agent, role: system_analyst }
      - { type: agent, role: data_engineer }
      - { type: agent, role: backend_developer }
```

### Краткий чеклист

- [ ] Файл `<role_name>.ru.md` существует в директории ролей
- [ ] Первая строка — заголовок `# Role Name` (используется как описание)
- [ ] Роль зарегистрирована в секции `roles` YAML-конфигурации
- [ ] Команда роли корректна (CLI-флаги, модель, `@system-prompt`)
- [ ] Роль тестируется через `app:agent:run --role=data_engineer "Тестовая задача"`

---

## Требования

- `pi` (или другой CLI) установлен и доступен в PATH
- API-ключи для LLM провайдера (конфигурируются в pi)
- PHP 8.4+, Symfony 7.3+
