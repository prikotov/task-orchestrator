# Исследование: Claude Code — CLI-агент Anthropic (проприетарный)

> **Проект:** [code.claude.com/docs](https://code.claude.com/docs/en/overview)
> **Дата анализа:** 2026-04-22
> **Дата ревью:** 2026-04-22
> **Язык:** Закрытый исходный код (TypeScript/Node.js по наблюдаемому поведению)
> **Лицензия:** Проприетарный (Anthropic)
> **Аналитик:** Технический писатель (Гермиона)
> **Ревьюер:** Архитектор (Локи) — верификация по официальной документации code.claude.com/docs

---

## 1. Обзор проекта

Claude Code — официальный CLI-агент от Anthropic для агрессивного (agentic) кодинга в терминале, IDE, desktop app и браузере. Ключевая идея: автономный AI-ассистент, который работает непосредственно с файловой системой проекта, выполняет команды, ищет код и взаимодействует с git — всё через agent loop (LLM → tool call → наблюдение → LLM → ...).

Claude Code **не является** фреймворком оркестрации агентов. Это **агентный CLI-инструмент** с возможностью порождения sub-agent'ов и agent teams (параллельные сессии) для интерактивной и headless-работы с LLM в контексте проекта. В отличие от task-orchestrator, Claude Code не поддерживает цепочки шагов (chains), retry-механизмы с backoff, circuit breaker или quality gates. Однако его архитектура agent loop, расширенная система инструментов, Agent SDK и управление контекстом представляют значительный интерес.

### Архитектура

Claude Code — проприетарный продукт. Архитектура восстановлена по официальной документации [code.claude.com/docs](https://code.claude.com/docs/en/overview) и наблюдаемому поведению CLI.

```
claude                              CLI entry point (native binary, npm deprecated)
  agent loop                        Core: LLM → tool call → observation → LLM → ...
    tools/                          Встроенные инструменты (30+)
      Read                         Чтение файлов и изображений
      Write                        Создание/перезапись файлов
      Edit                         Точечное редактирование файлов (oldText → newText)
      Bash                         Выполнение shell-команд
      PowerShell                   Нативные PowerShell-команды (Windows)
      Glob                         Поиск файлов по glob-паттернам
      Grep                         Поиск по содержимому файлов (ripgrep)
      WebFetch                     HTTP-запросы к внешним ресурсам
      WebSearch                    Веб-поиск
      Agent                        Spawn sub-agent'а (до v2.1.63 — Task)
      LSP                          Language Server Protocol (definition, references, types)
      Monitor                      Фоновый мониторинг (log tailing, file watching)
      Skill                        Выполнение named skill'ов
      NotebookEdit                 Редактирование Jupyter-ноутбуков
      AskUserQuestion              Интерактивные вопросы к пользователю
      EnterPlanMode/ExitPlanMode   Управление plan mode
      EnterWorktree/ExitWorktree   Изолированные git worktree
      TodoWrite/TaskCreate/...     Управление задачами в сессии
      TeamCreate/TeamDelete        Agent teams (экспериментально)
      CronCreate/CronDelete/...    Scheduled tasks в сессии
      MCP tools                    Инструменты из MCP-серверов (расширяемые)
    context/                        Управление контекстом
      CLAUDE.md                    Иерархические контекстные файлы (root → subdirs)
      .claude/rules/*.md           Контекстные правила (ленивая загрузка)
      auto memory                  Автоматическая память между сессиями
      conversation history         История текущей сессии
      auto-compact                 Автосжатие контекста при переполнении
      checkpointing                Checkpoint/restore файловых изменений
    permissions/                    Система разрешений
      allow/deny lists             Файловые и командные паттерны
      permission modes             default / plan / acceptEdits / auto / dontAsk / bypassPermissions
    hooks/                          Lifecycle hooks (20+ событий, 4 типа: command/HTTP/prompt/agent)
      PreToolUse                   Перед выполнением инструмента
      PostToolUse                  После успешного выполнения инструмента
      PostToolUseFailure           После ошибки выполнения
      PermissionRequest            При запросе разрешения
      PermissionDenied             При отказе в разрешении
      UserPromptSubmit             Перед обработкой промпта
      Stop                         При завершении agent loop
      SessionStart/SessionEnd      Начало/конец сессии
      SubagentStart/SubagentStop   Начало/конец sub-agent'а
      Notification                 Уведомления о событиях
      ...и ещё ~10 событий (TaskCreated, FileChanged, PreCompact, и др.)
    session/                        Управление сессиями
      conversation threads         Множественные сессии (resumable/continue/fork)
      cost tracking                Подсчёт токенов и стоимости
      --max-budget-usd             Бюджетный лимит (print mode)
    config/                         Конфигурация (иерархическая)
      settings.json                Глобальные, проектные, локальные, managed настройки
      .claude/                     Директория проекта (agents, skills, commands, hooks)
    sdk/                             Agent SDK (Python + TypeScript)
      query()                      Программный API к agent loop
      hooks/callbacks              Программные hooks через callbacks
      subagents                    Программное определение sub-agent'ов
      MCP integration              Подключение MCP-серверов через SDK
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | CLI-агент с sub-agent'ами (через Agent tool) и agent teams |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **State management** | In-memory (conversation history), checkpointing, auto-compact, session resume/fork |
| **Провайдер** | Anthropic (Claude Sonnet, Opus, Haiku) + Amazon Bedrock, Google Vertex AI, Microsoft Azure |
| **Расширяемость** | Agent SDK (Python/TypeScript), MCP-серверы, hooks (command/HTTP/prompt/agent), CLAUDE.md, skills, custom slash commands, plugins |
| **Интерфейс** | Interactive terminal (REPL) + headless mode (`--print`, `--output-format json`) + VS Code + JetBrains + Desktop app + Web app |
| **Платформы** | macOS, Linux, Windows (native install, Homebrew, WinGet; npm deprecated) |

### Основные компоненты

| Компонент | Назначение |
|---|---|
| Agent loop | Ядро: итеративный вызов LLM с инструментами, до естественного завершения или лимита итераций (`--max-turns`) |
| Tools (30+ встроенных) | Read/Write/Edit/Bash/Glob/Grep/WebFetch/WebSearch/Agent/LSP/Monitor/Skill/... + неограниченно через MCP |
| CLAUDE.md | Иерархические контекстные файлы (global → project → directory-level) + `.claude/rules/*.md` |
| Permission system | 6 режимов: default / plan / acceptEdits / auto / dontAsk / bypassPermissions |
| Hooks | 20+ lifecycle events, 4 типа handlers: command, HTTP, prompt (LLM), agent (subagent) |
| Sub-agents (Agent tool) | Spawn дочерних агентов с изолированным контекстом, persistent memory, skills, hooks |
| Agent teams | Параллельные агенты в отдельных сессиях с координацией (экспериментально) |
| Agent SDK | Python/TypeScript SDK для программного создания агентов |
| Headless mode | Программный вызов через `claude --print` (stdin/stdout, JSON output) с `--max-budget-usd`, `--max-turns` |
| Auto-compact | Автосжатие контекста при приближении к context window |
| Checkpointing | Checkpoint/restore файловых изменений в сессии |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | Task Orchestrator | Claude Code | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет. Agent loop — один непрерывный поток | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ⚠️ Только базовый retry на уровне API (429/500) | ✅ У нас есть |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет (LLM сам оценивает результат) | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based лимиты) | ⚠️ `--max-budget-usd` в print mode + tracking. Нет бюджетных лимитов в interactive mode | ✅ У нас лучше |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ❌ Нет явных итерационных циклов | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ⚠️ `--fallback-model` в print mode (только при overload) | ✅ У нас есть |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ⚠️ Логирование через `--verbose`, нет JSONL | ✅ У нас есть |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ⚠️ CLAUDE.md — единый контекст, не ролевой | ✅ У нас лучше |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ⚠️ Claude (Sonnet, Opus, Haiku) + Amazon Bedrock, Google Vertex AI, Microsoft Azure | ✅ У нас есть |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Закрытый код | ✅ У нас есть |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов | ✅ У нас есть |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ✅ settings.json + CLAUDE.md | ✅ Паритет |
| **Session persistence** | ❌ Нет (in-memory) | ✅ Resume/continue/fork sessions, checkpointing (restore файловых изменений) | 🟡 У них лучше |
| **MCP-протокол** | ❌ Нет | ✅ Полная поддержка MCP (stdio, HTTP, SSE, WS) | 🟡 Позже |
| **Permission system** | ❌ Нет | ✅ 6 permission modes, allow/deny lists, managed policies, per-tool granularity | 🟡 Интересно |
| **Hooks system** | ❌ Нет | ✅ 20+ lifecycle events, 4 типа handlers (command/HTTP/prompt/agent) | 🟡 Интересно |
| **Sub-agents** | ❌ Нет | ✅ Agent tool — spawn sub-agent с изолированным контекстом, persistent memory, skills, hooks | 🟡 Интересно |
| **Agent teams** | ❌ Нет | ✅ Параллельные агенты в отдельных сессиях с координацией (экспериментально) | 🟡 Интересно |
| **Agent SDK** | ❌ Нет | ✅ Python/TypeScript SDK для программного создания агентов | 🟡 Интересно |
| **Context file discovery** | ✅ AGENTS.md, role .md | ✅ CLAUDE.md иерархический (global → project → dir), `.claude/rules/*.md`, auto memory | 🟡 Интересно |
| **Auto-compact (context management)** | ❌ Нет | ✅ Автоматическое сжатие контекста при переполнении | 🟡 Позже |
| **Headless mode** | ✅ CLI Symfony Console | ✅ `claude --print --output-format json` | ✅ Паритет |
| **Cost tracking** | ✅ Через budget/check | ✅ Per-conversation token/cost tracking | ✅ Паритет |
| **Custom slash commands** | ❌ Нет | ✅ `/command` — пользовательские макросы из .md файлов | 🟡 Интересно |
| **Extended thinking** | ❌ Нет | ✅ Claude 3.7+ extended thinking (reasoning tokens) | 🟢 Не берём |
| **Non-interactive CI mode** | ✅ CLI pipeline | ✅ `claude -p "..." --allowedTools --max-budget-usd --max-turns` — для CI/CD | ✅ Паритет |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Hooks System — lifecycle-перехватчики для tool execution

**Что у них:** Claude Code позволяет привязывать обработчики к событиям жизненного цикла. Поддерживается 4 типа обработчиков:

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": [
          {
            "type": "command",
            "command": "check-dangerous-cmd.sh",
            "timeout": 5000
          }
        ]
      }
    ],
    "PostToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "run-linter.sh",
            "timeout": 10000
          }
        ]
      }
    ],
    "Stop": [
      {
        "hooks": [
          {
            "type": "command",
            "command": "notify-completion.sh"
          }
        ]
      }
    ]
  }
}
```

**Механика:**
- `PreToolUse` — выполняется до вызова инструмента, может заблокировать (exit 2 = отмена)
- `PostToolUse` — выполняется после, результат добавляется в контекст LLM
- `PostToolUseFailure` — выполняется после ошибки инструмента
- `Stop` — выполняется при завершении agent loop
- `Notification` — уведомления о событиях (завершение, ожидание ввода)
- `matcher` — фильтр по имени инструмента (поддерживает regex, `|`-разделитель)
- `timeout` — максимальное время выполнения hook'а
- **4 типа обработчиков:** `command` (shell), `http` (POST запрос), `prompt` (LLM evaluation), `agent` (subagent-based)
- Hooks можно объявлять в settings.json, плагинах, skills и sub-agent frontmatter

**Почему нам интересно:** Это расширение нашего decorator pattern. Сейчас retry/circuit breaker/budget — декораторы на уровне runner. Hooks позволяют добавлять произвольные pre/post проверки для каждого шага цепочки без модификации core-кода. Например:
- `PreStepUse`: проверить, что файл существует до вызова шага редактирования
- `PostStepUse`: запустить линтер после шага кодогенерации
- `ChainStop`: отправить уведомление по завершении цепочки

**Отличие от нашей реализации:**
- У нас: decorator pattern в PHP (retry, circuit breaker, budget — программно)
- У них: 4 типа handlers (shell, HTTP, LLM prompt, subagent) — декларативно, без изменения кода

---

### 3.2 🟡 Sub-agent Pattern — Agent tool для изолированных подзадач

**Что у них:** Claude Code может порождать sub-agent'ов через инструмент `Agent` (до v2.1.63 — `Task`):

```
User → Claude Code (main agent)
         ├─ Agent: "Investigate auth module" → sub-agent с изолированным контекстом
         ├─ Agent: "Write unit tests" → sub-agent с изолированным контекстом
         └─ Agent: "Review code" → sub-agent с изолированным контекстом
```

Встроенные sub-agent'ы: `Explore` (Haiku, read-only), `Plan` (read-only для планирования), `General-purpose` (все инструменты). Можно создавать custom sub-agent'ы через `.md` файлы с YAML frontmatter.

**Механика:**
- Sub-agent получает отдельный context window (не разделяет с parent)
- По завершении sub-agent возвращает summary родителю
- Родитель может породить несколько sub-agent'ов параллельно
- Sub-agent может иметь собственный набор инструментов (ограниченный через tools/disallowedTools)
- Sub-agent может иметь persistent memory (cross-session learning)
- Sub-agent может иметь собственные skills, hooks и MCP-серверы
- Sub-agent не может порождать другие sub-agent'ы (нет вложенности)
- Agent teams: параллельные агенты в отдельных сессиях с координацией (экспериментально)

**Почему нам интересно:** Для dynamic chains — возможность делегировать подзадачу отдельному агенту с чистым контекстом. Сейчас у нас каждый шаг цепочки работает в общем контексте (payload). Sub-agent pattern позволяет:
- Изолировать контекст подзадачи (меньше token usage)
- Выполнять подзадачи параллельно
- Ограничивать бюджет sub-agent'а независимо

**Отличие:** У нас каждый шаг — вызов runner'а с payload. Sub-agent — это по сути «chain внутри chain» с собственным контекстом.

> **⚠️ Proprietary product risk:** Sub-agent'ы — ключевая архитектурная фича, детали реализации (контекст window, auto-compact, memory) восстановлены по документации, а не по исходному коду. Поведение может отличаться от описанного.

---

### 3.3 🟡 Hierarchical Context Files — CLAUDE.md discovery

**Что у них:** Claude Code автоматически обнаруживает контекстные файлы в иерархии:

```
~/.claude/CLAUDE.md           Global (все проекты)
  project/CLAUDE.md           Project-level
  project/src/CLAUDE.md       Directory-level (подгружается при работе с файлами в src/)
  project/src/auth/CLAUDE.md  Subdirectory-level
  project/.claude/rules/*.md  Контекстные правила (ленивая загрузка)
```

**Механика:**
- Global CLAUDE.md — всегда загружается
- Project CLAUDE.md — загружается при старте в директории проекта
- Directory-level CLAUDE.md — подгружается динамически при обращении к файлам в этой директории
- `.claude/rules/*.md` — контекстные правила с ленивой загрузкой
- Auto memory — автоматическая память между сессиями (learnings, build commands, debugging insights)

**Почему нам интересно:** Наша система `AGENTS.md` + role `.md` файлов загружает весь контекст upfront. Directory-level discovery позволяет загружать контекст «по требованию», экономя tokens. Для длинных цепочек с разными этапами (анализ → кодирование → тестирование) это может быть эффективнее.

**Отличие от нашей реализации:**
- У нас: все role .md загружаются в начале chain execution
- У них: контекст подгружается динамически по мере необходимости

---

### 3.4 🟡 Permission System — granular control для tool execution

**Что у них:** 6 режимов разрешений для каждого инструмента:

1. **default**: стандартные запросы разрешений
2. **plan**: read-only exploration (планирование)
3. **acceptEdits**: авто-подтверждение файловых правок и стандартных команд
4. **auto**: фоновый классификатор проверяет команды и записи в защищённые директории
5. **dontAsk**: авто-отказ (explicitly allowed tools still work)
6. **bypassPermissions**: пропуск всех запросов разрешений (кроме .git/.claude и др.)

Дополнительно:
- Allow/deny по path-паттернам: `"permissions.allow": ["Bash(npm test*)"]`
- `--allowedTools` flag для CI/CD: ограничение доступных инструментов
- `--tools` flag для явного указания доступных инструментов (строже, чем `--allowedTools`)
- Managed permissions: корпоративные политики (MDM, managed-settings.json)
- Persistent permissions: запоминание выбора пользователя в сессии

**Почему нам интересно:** Для автономного выполнения цепочек (без человека) — критически важная возможность. Если task-orchestrator запускает цепочку в CI/CD, нужно контролировать:
- Какие команды можно выполнять (например, запретить `rm -rf`)
- Какие файлы можно редактировать (например, только `src/`, не `config/`)
- Какие runner'ы доступны

**Отличие:** У нас нет ограничений на runner'ы и shell-команды. Quality gates проверяют результат, но не ограничивают действия.

---

### 3.5 🟡 Custom Slash Commands — макросы для повторяющихся задач

**Что у них:** Пользовательские команды в виде `.md` файлов:

```
.claude/commands/
  review.md        → /review — запускает code review
  test.md          → /test — запускает написание тестов
  fix-lint.md      → /fix-lint — исправляет lint-ошибки
```

```markdown
<!-- .claude/commands/review.md -->
Review the code in $ARGUMENTS for:
- Security vulnerabilities
- Performance issues
- Code style violations
```

**Механика:**
- `.md` файл в `.claude/commands/` = slash-команда
- Имя файла = имя команды
- `$ARGUMENTS` — placeholder для пользовательского ввода
- Проектные и персональные команды (`~/.claude/commands/`)

**Почему нам интересно:** Концептуально аналог наших YAML chains — именованная последовательность действий. Но реализация через .md-промпты проще для пользователя. Паттерн «файл = команда» может быть полезен для быстрого создания типовых цепочек без YAML.

---

### 3.6 🟡 Non-interactive CI/CD Mode — headless execution

**Что у них:** Claude Code поддерживает программное использование:

```bash
# Headless: prompt → result (JSON)
echo "Fix the failing tests" | claude --print --output-format json

# CI/CD с ограничением инструментов и бюджетом
claude -p "Run tests and fix failures" \
  --allowedTools "Read,Write,Edit,Bash(npm*)" \
  --max-turns 20 \
  --max-budget-usd 5.00

# Как Unix-pipe компонент
cat error.log | claude -p "Analyze these errors" --output-format stream-json

# Structured output с JSON Schema
claude -p "Classify this issue" --json-schema '{"type":"object","properties":{...}}'
```

**Механика:**
- `--print` / `-p`: non-interactive режим (stdin/stdout)
- `--output-format json|stream-json|text`: структурированный вывод
- `--allowedTools`: ограничение доступных инструментов
- `--tools`: явный список доступных инструментов (строже)
- `--max-turns`: лимит итераций agent loop
- `--max-budget-usd`: лимит бюджета в долларах (только print mode)
- `--model`: выбор модели (sonnet, opus, или полное имя модели)
- `--fallback-model`: автоматический fallback при перегрузке
- `--resume`/`--continue`: возобновление сессии
- Exit codes: 0 = успех, 1 = ошибка, 2 = блокировка permission

**Почему нам интересно:** Task-orchestrator работает как CLI pipeline. Headless mode Claude Code — пример того, как AI-агент может быть интегрирован в pipeline как один из runner'ов. Флаги `--max-turns`, `--max-budget-usd` и `--allowedTools` — аналоги наших `max_iterations`, `BudgetVO` и ограничений на шаги. Structured output через `--json-schema` — интересный паттерн для валидации результатов.

---

### 3.7 🟡 Agent SDK — программное создание агентов

**Что у них:** Claude Code предоставляет Agent SDK (Python + TypeScript) для программного создания AI-агентов:

```python
from claude_agent_sdk import query, ClaudeAgentOptions

for message in query(
    prompt="Find and fix the bug in auth.py",
    options=ClaudeAgentOptions(
        allowed_tools=["Read", "Edit", "Bash"],
        permission_mode="acceptEdits",
    ),
):
    print(message)
```

**Механика:**
- `query()` — основной API: prompt → agent loop → streaming output
- Программные hooks через Python/TypeScript callbacks
- Sub-agent'ы через `AgentDefinition`
- MCP-серверы через SDK-конфигурацию
- Session management (resume, fork)
- Checkpointing (откат файловых изменений)
- OpenTelemetry observability

**Почему нам интересно:** Agent SDK — пример программного API для agent orchestration. В отличие от нашего YAML-based подхода, SDK даёт полный контроль над agent loop через код. Паттерн streaming output + callbacks может быть полезен для нашего Application layer.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Ограниченный выбор провайдеров

Claude Code работает с моделями Anthropic (Sonnet, Opus, Haiku) и поддерживает три cloud-провайдера: Amazon Bedrock, Google Vertex AI, Microsoft Azure. Однако все они предоставляют доступ к одним и тем же моделям Claude. Task-orchestrator deliberately поддерживает несколько runner'ов (pi, codex) через interface, включая принципиально разные модели.

### 4.2 🟢 Extended Thinking / Reasoning Tokens

Claude 3.7+ поддерживает «extended thinking» — внутренние рассуждения модели перед генерацией ответа. Это особенность конкретной модели, а не архитектурный паттерн. Не применимо к оркестратору.

### 4.3 🟢 Interactive REPL-интерфейс

Claude Code — интерактивный терминальный ассистент (REPL: пользователь → LLM → инструмент → пользователь). Task-orchestrator — автоматический pipeline. Разные парадигмы взаимодействия.

### 4.4 🟢 CLAUDE.md как контекстный стандарт

CLAUDE.md — проприетарный формат контекстных файлов. Мы используем AGENTS.md (универсальный стандарт, поддерживаемый Crush, pi_agent_rust и другими инструментами). Нет смысла добавлять зависимость от проприетарного формата.

### 4.5 🟢 WebFetch — HTTP-инструмент внутри agent loop

Claude Code может делать HTTP-запросы как инструмент agent loop. В task-orchestrator HTTP-запросы выполняются через shell-команды (curl) или runner'ы — не нужны как отдельный тип шага.

### 4.6 🟢 Session resume / checkpointing / agent teams

Claude Code поддерживает resume/continue/fork сессий, checkpointing (откат файловых изменений) и agent teams (параллельные агенты в отдельных сессиях). Для автоматического pipeline task-orchestrator это не является критичным — каждая цепочка выполняется от начала до конца. Однако checkpointing может быть интересен для rollback при ошибках.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| Hooks system (pre/post step) | 🟡 P2 | Аналог decorator pattern, но декларативный. 4 типа handlers: shell, HTTP, LLM prompt, subagent |
| Permission system | 🟡 P2 | Для автономного выполнения в CI/CD: 6 permission modes, managed policies |
| Sub-agent pattern | 🟡 P2 | Для dynamic chains: изолированный контекст подзадач, persistent memory, skills, hooks |
| Agent SDK pattern | 🟡 P3 | Программный API для agent orchestration (streaming output + callbacks) |
| Agent teams | 🟡 P3 | Параллельные агенты в отдельных сессиях (экспериментально у Anthropic) |
| Slash commands как макросы | 🟡 P3 | Альтернативный формат определения типовых цепочек (микро-YAML через .md) |
| Hierarchical context discovery | 🟡 P3 | Динамическая загрузка контекста по директории — экономия tokens в длинных цепочках |
| Headless CI/CD mode | 🟡 P3 | Паттерн интеграции AI-агента в pipeline (флаги --max-turns, --max-budget-usd, --allowedTools) |
| MCP support | 🟡 P3 | Протокол расширения возможностей через внешние серверы |
| Extended thinking | 🟢 — | Особенность модели, не архитектуры |
| Interactive REPL | 🟢 — | Разная парадигма |
| CLAUDE.md format | 🟢 — | Проприетарный формат, AGENTS.md достаточно |
| WebFetch tool | 🟢 — | Задача shell-команд и runner'ов |
| Session resume / checkpointing | 🟢 — | Pipeline не нуждается в resume, но checkpointing может быть полезен для rollback |

---

## 6. Указатель источников для деталей

- [Claude Code Docs: Overview](https://code.claude.com/docs/en/overview) — официальная документация: установка, использование, инструменты, платформы
- [Claude Code Docs: Hooks Reference](https://code.claude.com/docs/en/hooks) — hooks system: 20+ событий, 4 типа handlers, JSON input/output
- [Claude Code Docs: Settings](https://code.claude.com/docs/en/settings) — конфигурация: permissions, managed policies, model selection
- [Claude Code Docs: Sub-agents](https://code.claude.com/docs/en/sub-agents) — Agent tool, custom subagents, persistent memory, skills
- [Claude Code Docs: Tools Reference](https://code.claude.com/docs/en/tools-reference) — полный список инструментов (30+)
- [Claude Code Docs: CLI Reference](https://code.claude.com/docs/en/cli-reference) — флаги CLI, headless mode, exit codes
- [Claude Code Docs: Agent SDK](https://code.claude.com/docs/en/agent-sdk/overview) — Python/TypeScript SDK для программного создания агентов
- [GitHub: anthropics/claude-code](https://github.com/anthropics/claude-code) — README, installation, bug reporting

---

📚 **Источники:**
1. [code.claude.com/docs/en/overview](https://code.claude.com/docs/en/overview) — официальная документация Claude Code
2. [code.claude.com/docs/en/hooks](https://code.claude.com/docs/en/hooks) — hooks reference
3. [code.claude.com/docs/en/sub-agents](https://code.claude.com/docs/en/sub-agents) — sub-agents, agent teams
4. [code.claude.com/docs/en/tools-reference](https://code.claude.com/docs/en/tools-reference) — полный список инструментов
5. [code.claude.com/docs/en/agent-sdk/overview](https://code.claude.com/docs/en/agent-sdk/overview) — Agent SDK
