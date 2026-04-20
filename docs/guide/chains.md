# Цепочки

Цепочки определены в YAML-конфигурации (по умолчанию — путь из параметра `%task_orchestrator.chains_yaml%`).

## Static-цепочки (линейные)

Фиксированный набор шагов, выполняются последовательно. Каждый шаг передаёт контекст следующему.
Поддерживают **итерационные циклы** — именованные группы шагов с лимитом итераций.

```yaml
chains:
  implement:
    description: "Полный цикл реализации фичи с тестами"
    steps:
      - { type: agent, role: system_analyst }
      - { type: agent, role: system_architect }
      - type: agent
        role: backend_developer
        name: implement
      - type: agent
        role: code_reviewer_backend
        name: review
      - type: quality_gate
        command: 'make lint-php'
        label: 'PHP CodeSniffer'
        timeout_seconds: 60
      - type: quality_gate
        command: 'make tests-unit'
        label: 'Unit Tests'
        timeout_seconds: 120
      - type: agent
        role: qa_backend
        name: test
    fix_iterations:
      - group: dev-review
        steps: [implement, review]
        max_iterations: 3

  analyze:
    description: "Анализ без реализации"
    steps:
      - { type: agent, role: system_analyst }
      - { type: agent, role: system_architect }

  hotfix:
    description: "Срочный фикс"
    steps:
      - { type: agent, role: backend_developer }
      - { type: agent, role: code_reviewer_backend }
```

### Итерационные циклы (fix_iterations)

Секция `fix_iterations` определяет именованные группы шагов, образующих итерационный цикл.
Каждая группа ссылается на имена шагов (поле `name` в `steps`) и задаёт `max_iterations`.

Когда цепочка доходит до **последнего шага группы** — выполнение возвращается к **первому шагу группы**.
Цикл повторяется, пока итерация < `max_iterations`.

**Как это работает:**

```
Step 0: analyst          ← без имени, вне группы
Step 1: architect        ← без имени, вне группы
Step 2: developer        ← name: "implement", начало группы "dev-review"
Step 3: reviewer          ← name: "review", последний шаг группы "dev-review"
  ↓ iteration 1 < 3 → jump back to step 2
Step 2: developer        ← iteration 2
Step 3: reviewer          ← iteration 2
  ↓ iteration 2 < 3 → jump back to step 2
Step 2: developer        ← iteration 3
Step 3: reviewer          ← iteration 3 = max → warning, продолжаем
Step 4: qa_backend       ← name: "test"
```

**Поля шага для итераций:**

| Поле | Тип | По умолчанию | Описание |
|---|---|---|---|
| `name` | `?string` | `null` | Имя шага для ссылок из `fix_iterations` |

**Поля группы итераций (`fix_iterations`):**

| Поле | Тип | Обязательное | Описание |
|---|---|---|---|
| `group` | `string` | да | Уникальное имя группы |
| `steps` | `list<string>` | да | Имена шагов (≥ 2), ссылаются на `name` шагов |
| `max_iterations` | `int` | нет (3) | Лимит итераций (≥ 1) |

**Валидация при загрузке YAML:**
- Все `steps` из группы существуют среди именованных шагов цепочки
- Имена шагов не пересекаются между группами
- Группа содержит ≥ 2 шагов
- `max_iterations` ≥ 1

**Поля результатов:**

- `StepResultDto::iterationNumber` (`?int`) — номер итерации (null для шагов вне группы).
- `StepResultDto::iterationWarning` (`bool`) — `true` на последнем шаге группы при достижении `max_iterations`.
- `OrchestrateChainResultDto::totalIterations` (`int`) — суммарное количество retry-итераций за всю цепочку.

**Текущее ограничение:** retry срабатывает безусловно (итерация < max), без анализа вывода reviewer.
Это зафиксировано как `@techdebt` — в будущем планируется анализ вывода (regex/classifier)
для условного retry.

**Несколько независимых групп:** в одной цепочке можно определить несколько групп.
Каждая группа итерирует независимо со своим счётчиком и своим `max_iterations`.

## Отключение контекстных файлов (no_context_files)

По умолчанию `pi` при запуске агента автоматически загружает контекстные файлы проекта — `AGENTS.md`, `CLAUDE.md` и аналогичные — из рабочей директории. Это полезно, когда агент должен понимать архитектуру, конвенции и правила конкретного проекта.

Но не всегда это нужно. Когда агент решает **универсальную задачу** — генерация текстов, brainstorming, анализ данных, перевод — контекст проекта только мешает: отвлекает модель, расходует токены и может внести нежелательный bias.

Опция `no_context_files` отключает загрузку контекстных файлов. При её включении агент запускается как «чистый LLM» — без знания о проекте.

### Уровни настройки

Опция работает на двух уровнях с наследованием:

| Уровень | Ключ в YAML | Область действия |
|---|---|---|
| **Цепочка** | `no_context_files: true` (в корне цепочки) | Все agent-шаги цепочки по умолчанию |
| **Шаг** | `no_context_files: true` (в шаге) | Только этот шаг, переопределяет значение цепочки |

**Правило наследования:** шаг наследует значение от цепочки, но может его переопределить. Если нигде не указано — `false` (контекст загружается).

### Примеры

**Все шаги без контекста:**

```yaml
chains:
  brainstorm:
    no_context_files: true      # ← все шаги цепочки без контекста
    steps:
      - { type: agent, role: system_analyst }
      - { type: agent, role: marketer }
```

**Выборочно — только один шаг без контекста:**

```yaml
chains:
  implement:
    steps:
      - { type: agent, role: system_analyst }            # контекст загружен
      - { type: agent, role: backend_developer }         # контекст загружен
      - type: agent
        role: translator
        no_context_files: true                            # ← без контекста проекта
```

**Переопределение на уровне шага:**

```yaml
chains:
  mixed:
    no_context_files: true      # все шаги без контекста...
    steps:
      - { type: agent, role: system_analyst }             # ← без контекста (наследует)
      - type: agent
        role: backend_developer
        no_context_files: false                           # ← контекст загружен (переопределено)
```

### CLI

Аналогичная опция доступна из командной строки:

```bash
# Запуск одного агента без контекстных файлов
php bin/console app:agent:run -r system_analyst -t "Analyze this text" --no-context-files

# Запуск цепочки без контекстных файлов
php bin/console app:agent:orchestrate implement --no-context-files
```

CLI-флаг `--no-context-files` комбинируется с YAML-настройкой через OR-логику: если включён хотя бы один из них — контекст отключается.

### Применимость

- Влияние на `quality_gate`-шаги **нет** — gates не используют контекстные файлы.
- **Dynamic-цепочки** не поддерживают опцию на уровне конфигурации (только через CLI).

### Поддержка runner'ами

Поле `noContextFiles` — часть Domain VO (`AgentRunRequestVo`) и не привязано к конкретному CLI-инструменту. Каждый runner решает самостоятельно, как (и поддерживает ли вообще) отключение контекста.

### Поддержка runner'ами

| Runner | Контекстные файлы | Можно отключить? | Механизм в runner'е |
|---|---|---|---|
| **pi** (>= v0.67.4) | `AGENTS.md`, `CLAUDE.md` из рабочей директории | ✅ Да | CLI-флаг `-nc` / `-no-context-files` |
| **Codex CLI** | `AGENTS.md` из рабочей директории, `~/.codex/instructions.md` глобально | ❌ Нет флага | Обход: запуск из пустой директории (`--cd /tmp`) или удалить/переименовать `AGENTS.md` перед запуском |
| **Gemini CLI** | `GEMINI.md` из рабочей директории и `~/.gemini/GEMINI.md` глобально | ❌ Нет флага | Обход: запуск из пустой директории (`--sandbox` без `GEMINI.md`) или удалить файл перед запуском |
| **OpenCode** | `OPENCODE.md` из рабочей директории | ❌ Нет флага | Обход: запуск из пустой директории или `--pure` (отключает плагины, но не контекстные файлы) |
| **Kilocode** | `KILOCODE.md` из рабочей директории | ❌ Нет флага | Обход: аналогично OpenCode (запуск из пустой директории) |

> **Примечание:** Codex CLI, Gemini CLI, OpenCode и Kilocode пока не имеют аналога `-nc`. Для обхода runner может подменять рабочую директорию на `/tmp` или временную пустую директорию — это не даёт CLI найти контекстные файлы проекта. Это workaround; при появлении нативной поддержки в CLI — переключиться на флаг.

### Как добавить поддержку нового runner'а

При реализации `*AgentRunner implements AgentRunnerInterface`:

1. **Есть нативный флаг** — передать его при `getNoContextFiles() === true`
2. **Нет нативного флага** — подменить рабочую директорию на временную пустую (`$request->getWorkingDir()` → `/tmp/...`), чтобы CLI не нашёл контекстные файлы
3. **Оба варианта** — логировать debug-сообщение о применённом механизме

## Quality Gates (автоматические проверки)

Quality gates — настраиваемые shell-команды, представленные как **отдельный тип шага** в цепочке (`type: quality_gate`).
Позволяют верифицировать качество результата AI-агента (lint, type-check, tests и т.д.)
детерминированным образом — вне зависимости от AI.

### Два типа шагов

| Тип | Определяемость | Поля | Описание |
|---|---|---|---|
| `agent` | Недетерминированный (AI) | `role`, `name`, `runner`, `model`, `tools` | AI-агент выполняет задачу |
| `quality_gate` | Детерминированный (tool) | `command`, `label`, `timeout_seconds` | Shell-команда, pass/fail |

### Как это работает

```
Step: backend_developer (type: agent) → результат AI-агента
  ↓
Step: Lint (type: quality_gate) → make lint-php → exit code 0 → ✓
  ↓
Step: Unit Tests (type: quality_gate) → make tests-unit → exit code 1 → ✗ (warning)
  ↓
Цепочка продолжается, failed gate логируется как warning
```

**Важно:** Failed quality gate **не прерывает** выполнение цепочки.
Результат gate-шага помечается `passed: false`, оркестратор логирует warning.

### Конфигурация в YAML

```yaml
chains:
  implement:
    description: "Полный цикл реализации с тестами и проверками"
    steps:
      - { type: agent, role: system_analyst }
      - { type: agent, role: system_architect }
      - type: agent
        role: backend_developer
        name: implement
      - type: agent
        role: code_reviewer_backend
        name: review
      - type: quality_gate
        command: 'make lint-php'
        label: 'PHP CodeSniffer'
        timeout_seconds: 60
      - type: quality_gate
        command: 'make tests-unit'
        label: 'Unit Tests'
        timeout_seconds: 120
    fix_iterations:
      - group: dev-review
        steps: [implement, review]
        max_iterations: 3
```

**Поток выполнения:** analyst → architect → developer ↔ reviewer (итерации) → lint → unit tests.

### Поля quality_gate-шага

| Поле | Тип | Обязательное | По умолчанию | Описание |
|---|---|---|---|---|
| `type` | `string` | да | — | `quality_gate` |
| `command` | `string` | да | — | Shell-команда для выполнения |
| `label` | `string` | да | — | Человекочитаемое название |
| `timeout_seconds` | `int` | нет | `120` | Таймаут выполнения в секундах |

**Валидация:** шаг без `type` вызывает ошибку загрузки цепочки. Gate без `command` или `label` вызывает ошибку загрузки.
Domain VO (`QualityGateVo`) выбрасывает `InvalidArgumentException` при пустых значениях.

### CLI-вывод

При выполнении цепочки в консоли agent-шаги и quality_gate-шаги отображаются по-разному:

```
[1/6] system_analyst @ pi ... ✓ (↑3.1k ↓1.2k $0.0089, 8s)
[2/6] system_architect @ pi ... ✓ (↑4.5k ↓2.0k $0.0156, 11s)
[3/6] backend_developer @ pi ... ✓ (↑12.3k ↓5.8k $0.0421, 25s)
[4/6] code_reviewer_backend @ pi ... ✓ (↑2.1k ↓0.9k $0.0078, 6s)
🔍 [5/6] PHP CodeSniffer ... ✓ (0.1s)
🔍 [6/6] Unit Tests ... ✗ (0.5s, exit 1)
```

### Результаты в StepResultDto

Для quality_gate-шага `StepResultDto` содержит:
- `role` = `quality_gate`
- `passed` (`bool`) — прошёл ли gate
- `exitCode` (`int`) — код завершения команды
- `label` (`string`) — название gate
- `outputText` (`string`) — вывод команды

### Архитектура

```
Domain:
  ChainStepTypeEnum — agent | quality_gate
  ChainStepVo — factory methods: agent() / qualityGate()
  QualityGateVo (command, label, timeoutSeconds) — валидация инвариантов
  QualityGateResultVo (label, passed, exitCode, output, durationMs)
  QualityGateRunnerInterface — run(QualityGateVo): QualityGateResultVo
    ↑
Application:
  ExecuteStaticChainService — routing по ChainStepTypeEnum:
    agent → ExecuteStaticStepService
    quality_gate → QualityGateRunnerInterface
  StepResultDto — passed, exitCode, label для gate-результатов
    ↑
Infrastructure:
  QualityGateRunner — Symfony Process, таймаут, обработка исключений
  YamlChainLoader — парсинг type: agent / type: quality_gate из YAML
```

### Применимость

- Quality gate шаги выполняются только в **static-цепочках**.
- В **dynamic-цепочках** gates не поддерживаются (фасилитатор управляет потоком).
- Если `QualityGateRunnerInterface` не внедрён — gate-шаг завершается как passed (graceful skip).

## Cross-model Verification (кросс-модельная верификация)

Кросс-модельная верификация позволяет проверить результат agent-шага другим агентом с другой моделью для снижения ошибок и галлюцинаций. Реализуется как **обычный agent-шаг** с любой подходящей ролью (например, `code_reviewer_backend`) — никаких специальных механизмов не требуется.

Используются существующие механизмы цепочек: roles, context passing, audit trail, fix iterations.

### Как это работает

```
Step 2: system_architect @ pi → результат архитектора
  ↓ previousContext = результат архитектора
Step 3: code_reviewer_backend @ pi (--model glm-4.7) → получает результат через context
  ↓ проверяет на корректность, отвечает замечания или одобрение
  ↓ previousContext = ответ верификатора
Step 4: backend_developer → получает контекст верификатора
```

**Ключевое отличие:** верификатор использует **другую модель** (через параметр `model` роли в YAML). Это снижает эффект систематических ошибок одной модели.

### Конфигурация

Определите роль-верификатор в YAML-конфигурации с другой моделью:

```yaml
roles:
  verifier:
    prompt_file: docs/agents/roles/team/code_reviewer_backend.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --model
      - glm-4.7              # ← другая модель для независимой проверки
      - --system-prompt
      - "@system-prompt"
```

Затем добавьте шаг-верификатор после проверяемого шага:

```yaml
chains:
  implement_verified:
    description: "Полный цикл с верификацией архитектурных решений"
    steps:
      - { type: agent, role: system_analyst }
      - type: agent
        role: system_architect
        name: architect
      - type: agent
        role: verifier              # ← роль с другой моделью
        name: verify_architect
      - type: agent
        role: backend_developer
        name: implement
      - type: agent
        role: code_reviewer_backend
        name: review
    fix_iterations:
      - group: architect-verify
        steps: [architect, verify_architect]
        max_iterations: 2
      - group: dev-review
        steps: [implement, review]
        max_iterations: 3
```

### CLI-вывод

Верификатор отображается как обычный шаг:

```
[1/5] system_analyst @ pi ... ✓ (↑3.1k ↓1.2k $0.0089, 8s)
[2/5] system_architect @ pi ... ✓ (↑4.5k ↓2.0k $0.0156, 11s)
[3/5] verifier @ pi ... ✓ (↑1.2k ↓0.5k $0.003, 4s)
[4/5] backend_developer @ pi ... ✓ (↑12.3k ↓5.8k $0.0421, 25s)
[5/5] code_reviewer_backend @ pi ... ✓ (↑2.1k ↓0.9k $0.0078, 6s)
```

### Преимущества подхода «обычный шаг»

- **Audit trail** работает из коробки — верификатор логируется как обычный шаг
- **Fix iterations** — можно включить верификатор в итерационную группу (architect ↔ verifier)
- **Budget** — токены верификатора учитываются в бюджете как обычный шаг
- **Fallback** — верификатор может использовать fallback runner как любая другая роль
- **Контекст** — следующий шаг получает замечания верификатора, что полезнее сырого текста

### Применимость

- Верификация работает в **static** и **dynamic** цепочках (как любой agent-шаг)
- Можно использовать **любую существующую роль** в качестве верификатора, указав другую модель

## Dynamic-цепочки (фасилитатор)

Фасилитатор-агент получает весь накопленный контекст и в рантайме решает:
- дать слово участнику (`{"next_role": "architect"}`)
- или подвести итог и завершить (`{"done": true, "synthesis": "..."}`)

Цикл ограничен `max_rounds`. Если фасилитатор возвращает неизвестную роль — цикл продолжает запрашивать фасилитатора.

Поддерживает **resume** — промежуточное состояние сессии сохраняется в JSONL-файлы,
позволяя возобновить прерванную dynamic-цепочку.

```yaml
chains:
  brainstorm:
    type: dynamic
    description: "Фасилитируемый brainstorm с динамическим routing"
    facilitator: team_lead
    participants: [product_owner, system_analyst, marketer, system_architect]
    max_rounds: 20
    prompts:
      brainstorm_system: prompts/brainstorm/brainstorm_system.txt
      facilitator_append: prompts/brainstorm/facilitator_append.txt
      facilitator_start: prompts/brainstorm/facilitator_start.txt
      facilitator_continue: prompts/brainstorm/facilitator_continue.txt
      facilitator_finalize: prompts/brainstorm/facilitator_finalize.txt
      participant_append: prompts/brainstorm/participant_append.txt
      participant_user: prompts/brainstorm/participant_user.txt
```

**Поток данных:**
```
ROUND 1: Facilitator(topic) → {next_role: "architect"}
ROUND 2: Architect(topic + history) → architect_response
ROUND 3: Facilitator(full_context) → {next_role: "marketer"}
ROUND 4: Marketer(context) → marketer_response
...
ROUND N: Facilitator → {done: true, synthesis: "..."}
```

### Поля dynamic-цепочки

| Поле | Обязательное | Описание |
|---|---|---|
| `type` | да | `dynamic` |
| `facilitator` | да | Роль фасилитатора |
| `participants` | да | Список ролей-участников (min 1) |
| `max_rounds` | нет | Лимит раундов (default: 10) |
| `timeout` | нет | Таймаут цепочки в секундах |
| `description` | нет | Описание |
| `prompts` | нет | Маппинг именных промптов (файлы .txt). Если указан — все 7 ключей обязательны |

### Chain-level timeout

Параметр `timeout` задаёт максимальное время выполнения цепочки (в секундах).
Доступен на уровне цепочки в YAML-конфигурации.

**Приоритет fallback (от высшего к низшему):**

```
CLI --timeout → chain.timeout → 1800 (default)
```

1. **CLI `--timeout`** — если передан из командной строки, переопределяет всё.
2. **`chain.timeout`** — значение из YAML-конфигурации цепочки.
3. **`1800`** — дефолт, если нигде не указано.

**Пример:**

```yaml
chains:
  brainstorm:
    type: dynamic
    timeout: 600              # ← 10 минут на всю цепочку
    facilitator: team_lead
    participants: [architect, marketer]
    max_rounds: 20
```

Без `timeout` в YAML:
```yaml
chains:
  brainstorm:
    type: dynamic
    facilitator: team_lead
    participants: [architect, marketer]
```

В этом случае таймаут берётся из CLI `--timeout`, а если и он не задан — используется **1800 секунд** (30 минут).

Таймаут действует одинаково для начального запуска и для **resume** (возобновления прерванной сессии).

## Кастомная static-цепочка

Добавьте запись в YAML-конфигурацию цепочек:

```yaml
chains:
  my_chain:
    description: "My custom chain"
    steps:
      - { role: backend_developer, tools: "read,write,grep" }
      - { role: code_reviewer_backend }
```

С итерационным циклом:

```yaml
chains:
  my_chain_with_review:
    description: "Разработка с циклом ревью"
    steps:
      - type: agent
        role: backend_developer
        name: implement
      - type: agent
        role: code_reviewer_backend
        name: review
    fix_iterations:
      - group: dev-review
        steps: [implement, review]
        max_iterations: 2
```

С quality gates:

```yaml
chains:
  my_chain_with_gates:
    description: "Разработка с ревью и автоматическими проверками"
    steps:
      - type: agent
        role: backend_developer
        name: implement
      - type: agent
        role: code_reviewer_backend
        name: review
      - type: quality_gate
        command: 'make lint-php'
        label: 'PHP CodeSniffer'
        timeout_seconds: 60
      - type: quality_gate
        command: 'vendor/bin/phpunit --testsuite unit'
        label: 'Unit Tests'
      - type: quality_gate
        command: 'vendor/bin/psalm --no-cache'
        label: 'Psalm'
        timeout_seconds: 90
    fix_iterations:
      - group: dev-review
        steps: [implement, review]
        max_iterations: 2
```

## Кастомная dynamic-цепочка

```yaml
chains:
  design_review:
    type: dynamic
    description: "Итеративное design review"
    facilitator: system_architect
    participants: [system_analyst, backend_developer, code_reviewer_backend]
    max_rounds: 8
```
