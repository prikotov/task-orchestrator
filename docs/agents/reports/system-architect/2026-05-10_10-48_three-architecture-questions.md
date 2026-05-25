# Архитектурный анализ: три вопроса по развитию task-orchestrator

**Роль:** Архитектор Гэндальф
**Дата:** 2026-05-10
**Объект:** Модули AgentRunner, ChainDefinition, ChainExecution, Presentation (RunCommand, OrchestrateCommand, watch-subagent.sh)
**Задача:** Ответить на три архитектурных вопроса: (1) делегирование сабагентов, (2) мульти-раннерный watch-subagent.sh, (3) детерминированные шаги в цепочках

---

## Рефлексия

🧩 сложность запроса: **7 из 10** — три архитектурных вопроса с пересекающимися доменами, требующих глубокого понимания текущей архитектуры

🗂️ уровень контекста: **8 из 10** — кодовая база полностью доступна, формулировки конкретные

🛡️️ риск ошибки: **4 из 10** — вопросы проектные (архитектурные решения), не меняют runtime

---

## Вопрос 1: Делегирование сабагентов — нужна ли новая команда `delegate`?

### Анализ текущего `agent:run`

Текущая команда `RunCommand` (`agent:run`) уже делает почти всё, что нужно для делегирования:

```php
// RunCommand.php — уже поддерживает:
--role     → роль агента
--task     → задача
--runner   → движок (pi/codex)
--model    → модель LLM
--tools    → инструменты
--working-dir → рабочая директория
--no-context-files → отключить контекст
```

Единственный пробел: **нет `--reasoning-effort` / `--thinking`**. Сейчас reasoning level конфигурируется только через `chains.yaml` (в `command` роли: `--thinking high` для pi, `-c 'model_reasoning_effort="xhigh"'` для codex). Это per-role static-конфигурация, а не per-invocation CLI-параметр.

### Рекомендация: **`agent:run` достаточно, без новой команды `delegate`**

Обоснование:

1. **Семантическое сходство.** Делегирование задачи роли = запуск одного агента с ролью. Это именно то, что делает `agent:run`. Новая команда `delegate` дублировала бы 90% кода `RunCommand`.

2. **Разные уровни параметров уже разделены.** `chains.yaml` задаёт defaults (command, provider, model, thinking) для роли. CLI-опции `--runner`, `--model` переопределяют их per-invocation. Это правильная архитектура: статические defaults + динамические overrides.

3. **Отсутствие `delegate`-специфичной логики.** Делегирование не требует специального контекста (в отличие от оркестрации, которая управляет шагами, итерациями, бюджетом). Любая специфика — в prompt'е (`--task`), а не в инфраструктуре.

### Что добавить в `agent:run`

| Добавление | Обоснование |
|---|---|
| `--reasoning-effort` (string: low/medium/high/xhigh) | Переопределение reasoning level per-invocation. PiAgentRunner/CodexAgentRunner уже строят команду динамически — добавить флаг тривиально. |
| `--provider` (string) | Переопределение провайдера (zai, openai-codex). Актуально, когда роль сконфигурирована под zai, но нужно переключиться на openai-codex для конкретного запуска. |
| Вывод отчёта агента в файл (`--report-file`) | Сейчас результат выводится только в stdout. При делегировании часто нужно сохранить результат для последующей обработки. `OrchestrateCommand` уже имеет `--report-file` — добавить аналогичную опцию. |

**Не нужно:**
- `--timeout-per-turn` — это infra-concern, не бизнес-параметр делегирования. Лучше оставить в `chains.yaml`.
- `--max-turns` — аналогично, per-role конфигурация.

### Реализация `--reasoning-effort`

Добавить поле в `RunAgentCommand` DTO и `AgentRunRequestVo`:

```php
// RunAgentCommand.php — добавить поле
public function __construct(
    public string $role,
    public string $task,
    public ?string $runner = null,
    public ?string $model = null,
    public ?string $tools = null,
    public ?string $workingDir = null,
    public int $timeout = 300,
    public bool $noContextFiles = false,
    public ?string $reasoningEffort = null,  // NEW
) {}

// AgentRunRequestVo — добавить поле
private ?string $reasoningEffort = null,  // NEW

// PiAgentRunner::buildCommand — добавить reasoning flag
if ($request->getReasoningEffort() !== null && !in_array('--thinking', $command, true)) {
    $command[] = '--thinking';
    $command[] = $request->getReasoningEffort();
}

// CodexAgentRunner::buildCommand — добавить reasoning flag
if ($request->getReasoningEffort() !== null) {
    $command[] = '-c';
    $command[] = sprintf('model_reasoning_effort="%s"', $request->getReasoningEffort());
}
```

### Итог по Вопросу 1

| Решение | Обоснование |
|---|---|
| ❌ Новая команда `app:agent:delegate` | Дублирование, нет уникальной логики |
| ✅ Расширить `agent:run` | Добавить `--reasoning-effort`, `--provider`, `--report-file` |

---

## Вопрос 2: Мульти-раннерный watch-subagent.sh

### Анализ текущего скрипта

`watch-subagent.sh` — bash-скрипт, который:
- Хардкодит `pi --mode json` как движок
- Реализует stall-детектор (нет событий N секунд → kill)
- Реализует hard timeout
- Фильтрует JSONL-вывод (raw/text/tools/files)
- Передаёт system prompt через `--system-prompt` и роль через `--append-system-prompt`

### Рекомендация: **Параметризовать `--runner`, но не переусложнять**

**Стоит ли вообще?** — Да, с оговоркой. Скрипт позиционируется как fallback, если оркестратор сломан. Но если оркестратор жив, запуск через `agent:run` всегда лучше: retry, circuit breaker, fallback, audit, budget. Поэтому скрипт — emergency tool, не основной путь.

### Предлагаемые изменения

#### 1. Добавить `--runner` (default: `pi`)

```bash
RUNNER="pi"

while [[ $# -gt 0 ]]; do
    case "$1" in
        # ... existing flags ...
        --runner) RUNNER="$2"; shift 2 ;;
    esac
done
```

#### 2. Разные стратегии для разных runners

Скрипту нужна **функция-диспетчер** для построения команды:

```bash
build_runner_command() {
    local runner="$1"
    shift

    case "$runner" in
        pi)
            echo pi --mode json --no-session --system-prompt "$SYSTEM_PROMPT_FILE" "$@"
            ;;
        codex)
            echo codex exec --dangerously-bypass-approvals-and-sandbox --json --skip-git-repo-check --ephemeral --ignore-rules "$@"
            ;;
        *)
            echo "Ошибка: неизвестный runner '$runner' (допустимо: pi, codex)" >&2
            exit 1
            ;;
    esac
}
```

#### 3. Флаги для per-runner специфики

Для pi:

| Флаг | Описание |
|---|---|
| `--model <model>` | Модель LLM |
| `--thinking <level>` | Reasoning effort (low/medium/high/xhigh) |
| `--provider <provider>` | Провайдер (zai, openai-codex) |

Для codex:

| Флаг | Описание |
|---|---|
| `--model <model>` | Модель LLM |
| `--reasoning <level>` | Reasoning effort через `-c model_reasoning_effort` |

Скрипт не обязан знать все тонкости каждого runner — он может передавать лишние аргументы как есть:

```bash
# Универсальный подход: собрать base-команду + extra args
PI_CMD=()
case "$RUNNER" in
    pi)
        PI_CMD+=(pi --mode json --no-session --system-prompt "$SYSTEM_PROMPT_FILE")
        ;;
    codex)
        PI_CMD+=(codex exec --dangerously-bypass-approvals-and-sandbox --json --skip-git-repo-check --ephemeral --ignore-rules)
        # codex не имеет --system-prompt — инжектить через -c model_instructions_file
        PI_CMD+=(-c "model_instructions_file=\"$SYSTEM_PROMPT_FILE\"")
        ;;
esac

# Дополнительные CLI-параметры после --
EXTRA_ARGS=()
while [[ $# -gt 0 ]]; do
    EXTRA_ARGS+=("$1")
    shift
done
PI_CMD+=("${EXTRA_ARGS[@]}")
```

#### 4. Разница в обработке JSONL-вывода

Это главный риск. Pi и codex имеют **разный формат JSONL-вывода**:

- pi: `{"type": "message_end", "message": {"role": "assistant", "content": [{"type": "text", "text": "..."}]}}`
- codex: `{"type": "message", "role": "assistant", "content": [{"type": "text", "text": "..."}]}` (пример, реальный формат может отличаться)

Скрипт использует `jq`-фильтры, заточенные под pi. Для codex нужны другие фильтры.

**Решение:** сделать функции фильтрации per-runner:

```bash
filter_text_pi() { ... }     # текущая логика
filter_text_codex() { ... }  # codex-специфичная логика

filter_text() {
    case "$RUNNER" in
        pi) filter_text_pi "$@" ;;
        codex) filter_text_codex "$@" ;;
    esac
}
```

#### 5. Переименовать skill

`run-pi-subagent` → `run-subagent`. Если скрипт перестаёт быть pi-only, имя скилла должно отражать это.

### Чего НЕ делать

| ❌ | Обоснование |
|---|---|
| Не делать скрипт «умным» (retry, circuit breaker) | Это дублирование оркестратора. Скрипт — emergency fallback. |
| Не поддерживать все runners из оркестратора | Только pi и codex — основные. Остальное — через оркестратор. |
| Не добавлять audit, budget | Это ответственность оркестратора. |

### Итог по Вопросу 2

| Решение | Обоснование |
|---|---|
| ✅ Параметризовать `--runner` | Fallback-скрипт должен уметь запускать оба движка |
| ✅ Per-runner функции для build + filter | Разные CLI-интерфейсы и JSONL-форматы |
| ✅ Передавать model/thinking через generic extra args | Минимальная абстракция, без переусложнения |
| ❌ Не превращать bash в мини-оркестратор | Retry, audit, budget — прерогатива PHP-оркестратора |
| ✅ Переименовать skill: `run-pi-subagent` → `run-subagent` | Имя должно отражать мульти-раннерность |

---

## Вопрос 3: Детерминированные шаги в цепочках

### Анализ текущих типов шагов

Сейчас в `ChainStepTypeEnum` два типа:

```php
enum ChainStepTypeEnum: string
{
    case agent = 'agent';           // AI-шаг (недетерминированный)
    case qualityGate = 'quality_gate'; // Shell-команда (pass/fail)
}
```

`quality_gate` выполняет shell-команду и возвращает `passed: bool`. Его семантика — **проверка**, не **действие**. Failed gate = warning, цепочка продолжается.

### Проблема: тимлиду нужны действия, не только проверки

Из перечисленных операций:

| Операция | Тип | Важно ли: результат (stdout)? | Важно ли: pass/fail? |
|---|---|---|---|
| `git checkout -b task/xxx` | Действие | Нет (или минимально) | Да (код возврата) |
| `git commit -m "feat: ..."` | Действие | Нет | Да |
| `git push origin task/xxx` | Действие | Нет | Да |
| `gh pr create ...` | Действие | **Да** (URL PR, номер) | Да |
| `gh pr merge ...` | Действие | **Да** (результат merge) | Да |
| Проверка DoR (front matter) | Проверка | Частично (ошибки) | Да |
| Перенос файла в done/ | Действие | Нет | Да |

Ключевое отличие: **некоторым действиям важен stdout как результат**, который нужно передать дальше по цепочке.

### Рекомендация: ввести тип `shell` (не расширять `quality_gate`)

**Почему не расширять `quality_gate`:**

1. **Нарушение SRP.** Quality gate имеет чёткую семантику: проверка → pass/fail → warning. Если превратить его в «универсальный shell-шаг», теряется ясность намерения. Читатель chains.yaml не поймёт, проверка это или действие.

2. **Разная обработка результата.** Quality gate игнорирует stdout (важен только exit code). Shell-шагу stdout важен — он передаётся в `previousContext` следующему шагу.

3. **Разные политики ошибок.** Quality gate: fail → warning, цепочка продолжается. Shell: fail → можно настроить (fail цепочку / warning / retry).

### Предлагаемая модель типов шагов

```
ChainStepTypeEnum:
  ├── agent          — AI-шаг (недетерминированный, token cost, retry)
  ├── quality_gate   — Проверка (pass/fail, fail = warning)
  └── shell          — Детерминированное действие (stdout = контекст для след. шага)
```

### Конфигурация `shell` в YAML

```yaml
steps:
  # Действие: создание ветки
  - type: shell
    command: 'git checkout -b task/{{task.slug}} && git push -u origin task/{{task.slug}}'
    label: 'Create task branch'
    capture_output: false           # default: false — stdout не важен

  # Действие: создание PR (stdout = URL PR)
  - type: shell
    command: 'gh pr create --title "{{task.title}}" --body-file PR_BODY.md'
    label: 'Create Pull Request'
    capture_output: true            # stdout → previousContext для след. шага
    output_key: pr_url              # именованный ключ в контексте

  # Проверка DoR
  - type: quality_gate
    command: 'bin/check-dor.sh {{task.file}}'
    label: 'DoR Check'
    timeout_seconds: 30

  # Действие: перенос задачи в done/
  - type: shell
    command: 'mv {{task.file}} todo/done/'
    label: 'Move task to done'
    capture_output: false
```

### Что нужно изменить в коде

#### 1. Domain: расширить `ChainStepTypeEnum`

```php
enum ChainStepTypeEnum: string
{
    case agent = 'agent';
    case qualityGate = 'quality_gate';
    case shell = 'shell';  // NEW
}
```

#### 2. Domain: добавить `ShellStepVo`

```php
// Аналог QualityGateVo, но с capture_output и output_key
final readonly class ShellStepVo
{
    public function __construct(
        public string $command,
        public string $label,
        public int $timeoutSeconds = 120,
        public bool $captureOutput = false,  // передать stdout в контекст?
        public ?string $outputKey = null,    // именованный ключ контекста
        public ShellErrorPolicy $errorPolicy = ShellErrorPolicy::fail, // fail | warn | retry
    ) {}
}
```

#### 3. Domain: добавить `ShellStepRunnerInterface`

```php
interface ShellStepRunnerInterface
{
    public function run(ShellStepVo $step): ShellStepResultVo;
}
```

#### 4. Infrastructure: `ShellStepRunner`

```php
final readonly class ShellStepRunner implements ShellStepRunnerInterface
{
    public function run(ShellStepVo $step): ShellStepResultVo
    {
        $process = Process::fromShellCommandline($step->command);
        $process->setTimeout($step->timeoutSeconds);
        $process->run();

        return new ShellStepResultVo(
            label: $step->label,
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput() . $process->getErrorOutput(),
            success: $process->isSuccessful(),
            capturedOutput: $step->captureOutput ? $process->getOutput() : null,
        );
    }
}
```

#### 5. Execution: интеграция в `RunStaticChainService`

Добавить ветку для `shell`-шагов аналогично `quality_gate`:

```php
// В ExecuteStaticStepService или RunStaticChainService
if ($step->isShell()) {
    return $this->shellRunner->run($step->toShellStepVo());
}
```

### Полная типология шагов для task/epic workflow

Для оцифровки workflow тимлида (создание ветки → реализация → ревью → PR → merge) мне видятся три типа шагов **достаточными**:

| Тип | Семантика | Результат | Ошибка |
|---|---|---|---|
| `agent` | AI-шаг | outputText + tokens + cost | fail → retry / fallback / stop |
| `quality_gate` | Проверка | pass/fail (stdout игнорируется) | fail → warning, цепочка продолжает |
| `shell` | Действие | exit code + output (опционально в контекст) | настраиваемая политика (fail/warn/retry) |

**Чего пока НЕ нужно:**

| Тип | Почему не сейчас |
|---|---|
| `template` (рендеринг шаблона) | Пока нет реальной потребности. Jinja/mustache в YAML — premature. Если появится — сделать `shell`-шагом с `bin/render-template.sh`. |
| `input` (ожидание пользовательского ввода) | Цепочки — автономные. Интерактивность нарушает модель. |
| `parallel` (параллельные шаги) | Сложность. Пока все цепочки линейные. Вынести в отдельный ADR при необходимости. |
| `http` (HTTP-запрос) | Overengineering. `shell` с `curl` покрывает. |
| `transform` (преобразование контекста) | Это Domain logic, не тип шага. Если появится — сделать через `shell` + jq или `agent` с микро-промптом. |

### Template variables в shell-командах

Для операций типа `git checkout -b task/{{task.slug}}` нужен механизм подстановки переменных. Это отдельная задача, но она **ортогональна типу шага** — template variables могут работать во всех типах (agent task, shell command, quality_gate command).

Предлагаю минимальный подход:
- `{{task.slug}}` → slug задачи
- `{{task.file}}` → путь к файлу задачи
- `{{task.title}}` → заголовок задачи
- `{{prev.output_key}}` → output предыдущего shell-шага с output_key

Это можно реализовать в `PromptFormatterService` или отдельном `VariableResolver`.

### Итог по Вопросу 3

| Решение | Обоснование |
|---|---|
| ✅ Новый тип `shell` | Семантика действия ≠ семантика проверки. SRP. |
| ❌ Расширять `quality_gate` | Нарушение SRP, потеря ясности намерения |
| ✅ `capture_output` + `output_key` | Передача stdout в контекст следующих шагов |
| ✅ Настраиваемая политика ошибок | `fail` / `warn` / `retry` — разные сценарии |
| ❌ Template variables в этом PR | Отдельная задача, не блокирует shell-шаги |
| ❌ `parallel`, `http`, `template` типы | Преждевременная абстракция |

---

## Общая сводка

| Вопрос | Решение | Приоритет | Сложность реализации |
|---|---|---|---|
| Q1: Делегирование | Расширить `agent:run` (+ `--reasoning-effort`, `--provider`, `--report-file`) | Высокий | Низкая (1-2 дня) |
| Q2: watch-subagent.sh | Параметризовать `--runner` + per-runner фильтры | Средний (fallback) | Низкая (0.5-1 день) |
| Q3: Детерминированные шаги | Новый тип `shell` в `ChainStepTypeEnum` | Высокий | Средняя (3-5 дней: Domain + Execution + YAML-парсер + тесты) |

### Зависимости между решениями

- Q3 (shell-шаги) не зависит от Q1 и Q2 — можно делать параллельно.
- Q1 не зависит от Q2 и Q3.
- Q2 (watch-subagent.sh) не зависит от Q1 и Q3, но полезно делать после Q1, чтобы CLI-параметры были консистентны.

### Рекомендуемый порядок

1. **Q1** (расширение `agent:run`) — быстро, высокая ценность, минимальный риск.
2. **Q3** (`shell` тип шага) — ключевая фича для автоматизации task workflow.
3. **Q2** (параметризация watch-subagent.sh) — можно в любой момент, низкий приоритет.
