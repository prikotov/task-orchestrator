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
claude CLI entry point (native binary, npm deprecated)
 agent loop Core: LLM → tool call → observation → LLM → ...
 tools/ Встроенные инструменты (30+)
 Read Чтение файлов и изображений
 Write Создание/перезапись файлов
 Edit Точечное редактирование файлов (oldText → newText)
 Bash Выполнение shell-команд
 PowerShell Нативные PowerShell-команды (Windows)
 Glob Поиск файлов по glob-паттернам
 Grep Поиск по содержимому файлов (ripgrep)
 WebFetch HTTP-запросы к внешним ресурсам
 WebSearch Веб-поиск
 Agent Spawn sub-agent'а (до v2.1.63 — Task)
 LSP Language Server Protocol (definition, references, types)
 Monitor Фоновый мониторинг (log tailing, file watching)
 Skill Выполнение named skill'ов
 NotebookEdit Редактирование Jupyter-ноутбуков
 AskUserQuestion Интерактивные вопросы к пользователю
 EnterPlanMode/ExitPlanMode Управление plan mode
 EnterWorktree/ExitWorktree Изолированные git worktree
 TodoWrite/TaskCreate/... Управление задачами в сессии
 TeamCreate/TeamDelete Agent teams (экспериментально)
 CronCreate/CronDelete/... Scheduled tasks в сессии
 MCP tools Инструменты из MCP-серверов (расширяемые)
 context/ Управление контекстом
 CLAUDE.md Иерархические контекстные файлы (root → subdirs)
 .claude/rules/*.md Контекстные правила (ленивая загрузка)
 auto memory Автоматическая память между сессиями
 conversation history История текущей сессии
 auto-compact Автосжатие контекста при переполнении
 checkpointing Checkpoint/restore файловых изменений
 permissions/ Система разрешений
 allow/deny lists Файловые и командные паттерны
 permission modes default / plan / acceptEdits / auto / dontAsk / bypassPermissions
 hooks/ Lifecycle hooks (20+ событий, 4 типа: command/HTTP/prompt/agent)
 PreToolUse Перед выполнением инструмента
 PostToolUse После успешного выполнения инструмента
 PostToolUseFailure После ошибки выполнения
 PermissionRequest При запросе разрешения
 PermissionDenied При отказе в разрешении
 UserPromptSubmit Перед обработкой промпта
 Stop При завершении agent loop
 SessionStart/SessionEnd Начало/конец сессии
 SubagentStart/SubagentStop Начало/конец sub-agent'а
 Notification Уведомления о событиях
 ...и ещё ~10 событий (TaskCreated, FileChanged, PreCompact, и др.)
 session/ Управление сессиями
 conversation threads Множественные сессии (resumable/continue/fork)
 cost tracking Подсчёт токенов и стоимости
 --max-budget-usd Бюджетный лимит (print mode)
 config/ Конфигурация (иерархическая)
 settings.json Глобальные, проектные, локальные, managed настройки
 .claude/ Директория проекта (agents, skills, commands, hooks)
 sdk/ Agent SDK (Python + TypeScript)
 query() Программный API к agent loop
 hooks/callbacks Программные hooks через callbacks
 subagents Программное определение sub-agent'ов
 MCP integration Подключение MCP-серверов через SDK
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | CLI-агент с sub-agent'ами (через Agent tool) и agent teams |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **State management** | In-memory (conversation history), checkpointing, auto-compact, session resume/fork |
| **Провайдер** | Anthropic (Claude Sonnet, Opus, Haiku) + Amazon Bedrock, Google Vertex AI, Microsoft Azure |
| **Расширяемость** | Agent SDK (Python/TypeScript), MCP-серверы, hooks (command/HTTP/prompt/agent), CLAUDE.md, skills, custom slash commands, plugins |
| **Интерфейс** | Interactive terminal (REPL) + headless mode (`--print`, `--output-format json`) + VS Code + JetBrains + Desktop app + Web app |
| **Платформы** | macOS, Linux, Windows (native install, Homebrew, WinGet; npm deprecated) |

### Основные компоненты

| Компонент | Назначение |
| --- | --- |
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

## 2. Возможности оркестрации — обзор

| Функция | Claude Code |
| --- | --- |
| **Бюджетный контроль** | ⚠️ `--max-budget-usd` в print mode + tracking. Нет бюджетных лимитов в interactive mode |
| **Ролевые промпты** | ⚠️ CLAUDE.md — единый контекст, не ролевой |
| **YAML-конфигурация** | ✅ settings.json + CLAUDE.md |
| **Session persistence** | ✅ Resume/continue/fork sessions, checkpointing (restore файловых изменений) |
| **MCP-протокол** | ✅ Полная поддержка MCP (stdio, HTTP, SSE, WS) |
| **Permission system** | ✅ 6 permission modes, allow/deny lists, managed policies, per-tool granularity |
| **Hooks system** | ✅ 20+ lifecycle events, 4 типа handlers (command/HTTP/prompt/agent) |
| **Sub-agents** | ✅ Agent tool — spawn sub-agent с изолированным контекстом, persistent memory, skills, hooks |
| **Agent teams** | ✅ Параллельные агенты в отдельных сессиях с координацией (экспериментально) |
| **Agent SDK** | ✅ Python/TypeScript SDK для программного создания агентов |
| **Context file discovery** | ✅ CLAUDE.md иерархический (global → project → dir), `.claude/rules/*.md`, auto memory |
| **Auto-compact (context management)** | ✅ Автоматическое сжатие контекста при переполнении |
| **Headless mode** | ✅ `claude --print --output-format json` |
| **Cost tracking** | ✅ Per-conversation token/cost tracking |
| **Custom slash commands** | ✅ `/command` — пользовательские макросы из .md файлов |
| **Extended thinking** | ✅ Claude 3.7+ extended thinking (reasoning tokens) |
| **Non-interactive CI mode** | ✅ `claude -p "..." --allowedTools --max-budget-usd --max-turns` — для CI/CD |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Hooks System — lifecycle-перехватчики для tool execution

Claude Code позволяет привязывать обработчики к событиям жизненного цикла agent loop. Поддерживается 4 типа обработчиков:

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

**Значение для оркестрации:** Hooks реализуют паттерн interceptor/decorator — произвольные pre/post проверки для каждого вызова инструмента без модификации agent loop. Типовые сценарии:
- Pre-condition: валидация входных данных до выполнения (проверка существования файла, допустимости команды)
- Post-action: линтинг, тестирование, нотификация после выполнения
- Guard rails: блокировка опасных операций (удаление файлов, вызов production API)
- Observability: логирование и метрики на каждый tool call

**Ограничения:**
- Hooks выполняются синхронно и блокируют agent loop; без `timeout` hook может зависнуть
- Нет встроенных механизмов retry, backoff или circuit breaker — только «выполнить один раз» или «отменить»
- Нельзя композиционировать результаты нескольких hook'ов (каждый работает независимо)
- `prompt`-тип hook'а (LLM evaluation) сам потребляет токены и бюджет

---

### 3.2 🟡 Sub-agent Pattern — Agent tool для изолированных подзадач

Claude Code может порождать sub-agent'ов через инструмент `Agent` (до v2.1.63 — `Task`):

```
User → Claude Code (main agent)
 ├─ Agent: "Investigate auth module" → sub-agent с изолированным контекстом
 ├─ Agent: "Write unit tests" → sub-agent с изолированным контекстом
 └─ Agent: "Review code" → sub-agent с изолированным контекстом
```

Встроенные sub-agent'ы: `Explore` (Haiku, read-only), `Plan` (read-only для планирования), `General-purpose` (все инструменты, кроме Agent — вложенный spawn запрещён). Можно создавать custom sub-agent'ы через `.md` файлы с YAML frontmatter.

**Механика:**
- Sub-agent получает отдельный context window (не разделяет с parent)
- По завершении sub-agent возвращает summary родителю
- Родитель может породить несколько sub-agent'ов параллельно
- Sub-agent может иметь собственный набор инструментов (ограниченный через tools/disallowedTools)
- Sub-agent может иметь persistent memory (cross-session learning)
- Sub-agent может иметь собственные skills, hooks и MCP-серверы
- Sub-agent не может порождать другие sub-agent'ы (нет вложенности)
- Agent teams: параллельные агенты в отдельных сессиях с координацией (экспериментально)

**Значение для оркестрации:** Sub-agent pattern реализует fan-out с изоляцией контекста — каждая подзадача выполняется в чистом context window, что снижает token usage и устраняет «загрязнение» контекста между задачами. Типовые сценарии:
- Делегирование узких задач: анализ модуля, написание тестов, code review
- Параллельное выполнение независимых подзадач
- Изоляция «шумного» контекста (например, поиск в большом кодовом репозитории) от основного agent loop

**Ограничения:**
- Только один уровень вложенности — sub-agent не может породить свой sub-agent. Для многоуровневых DAG-оркестраций паттерн не подходит
- Возврат только summary: полный контекст sub-agent'а теряется после завершения, parent получает только текстовое резюме
- Нет координации между параллельными sub-agent'ами — каждый работает независимо, shared state отсутствует
- Agent teams (параллельные сессии) — экспериментальная фича; стабильность и гарантии координации не документированы

> **Примечание:** Архитектура sub-agent'ов восстановлена по официальной документации, а не по исходному коду. Детали реализации (размер context window, стратегия auto-compact, формат persistent memory) могут отличаться от описанных.

---

### 3.3 🟡 Hierarchical Context Files — CLAUDE.md discovery

Claude Code автоматически обнаруживает контекстные файлы в иерархии:

```
~/.claude/CLAUDE.md Global (все проекты)
 project/CLAUDE.md Project-level
 project/src/CLAUDE.md Directory-level (подгружается при работе с файлами в src/)
 project/src/auth/CLAUDE.md Subdirectory-level
 project/.claude/rules/*.md Контекстные правила (ленивая загрузка)
```

**Механика:**
- Global CLAUDE.md — всегда загружается
- Project CLAUDE.md — загружается при старте в директории проекта
- Directory-level CLAUDE.md — подгружается динамически при обращении к файлам в этой директории
- `.claude/rules/*.md` — контекстные правила с ленивой загрузкой
- Auto memory — автоматическая память между сессиями (learnings, build commands, debugging insights)

**Значение для оркестрации:** Иерархический lazy-loading контекста — альтернатива upfront-загрузке всех инструкций. Контекст подгружается по мере необходимости, что экономит tokens при длинных цепочках с разными этапами (анализ → кодирование → тестирование). Directory-level discovery позволяет специализировать инструкции агента для разных частей проекта без ручного управления контекстом.

**Ограничения:**
- Загрузка контекста управляется LLM, а не декларативно — нет гарантии, что агент обратится к нужному файлу
- Auto-compact может удалить ранее загруженный directory-level контекст при нехватке context window
- Нет явного управления областью видимости: нельзя декларативно указать «для этого шага загрузить контекст X, а для следующего — Y»
- Auto memory не версионируется и не контролируется — агент может «научиться» некорректным паттернам

---

### 3.4 🟡 Permission System — granular control для tool execution

6 режимов разрешений для каждого инструмента:

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

**Значение для оркестрации:** Permission system решает задачу безопасности при автономном выполнении: без человека в цикле агент ограничен в действиях. Для CI/CD-пайплайнов комбинация `--allowedTools` + `--max-budget-usd` + permission mode позволяет создать изолированный sandbox с предсказуемыми границами. Managed policies дают корпоративный контроль на уровне организации.

**Ограничения:**
- Режим разрешений — session-wide; нельзя динамически менять permission mode в рамках одной сессии (например, «на этом шаге — read-only, на следующем — write»)
- `--max-budget-usd` доступен только в print mode; в interactive mode бюджетных лимитов нет
- `auto` mode полагается на эвристический классификатор — возможны false positives (блокировка безопасных команд) и false negatives (пропуск опасных)
- Нет условных разрешений: нельзя выразить «разрешить Write только если файл не в .git/» через declarative config (только через hooks)

---

### 3.5 🟡 Custom Slash Commands — макросы для повторяющихся задач

Пользовательские команды в виде `.md` файлов:

```
.claude/commands/
 review.md → /review — запускает code review
 test.md → /test — запускает написание тестов
 fix-lint.md → /fix-lint — исправляет lint-ошибки
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
- Имя файла = имя команды (поддиректории формируют namespace: `subdir/cmd.md` → `/project:subdir:cmd`)
- `$ARGUMENTS` — placeholder для пользовательского ввода
- YAML frontmatter с полем `description` — описание отображается в списке доступных команд
- Проектные (`.claude/commands/`) и персональные (`~/.claude/commands/`) команды

**Значение для оркестрации:** Паттерн «файл = команда» — упрощённый способ определения именованных последовательностей действий через prompt-инструкции. Концептуально близок к макросам или template-методам: пользователь задаёт intent и критерии, агент определяет конкретные шаги самостоятельно. Типовые сценарии: стандартизированные code review, генерация тестов, исправление lint-ошибок.

**Ограничения:**
- Нет параметризации кроме `$ARGUMENTS` — нельзя передать структурированные параметры (список файлов, конфигурация, флаги)
- Нет условной логики и ветвления — .md-файл это просто промпт, не алгоритм
- Нет гарантии детерминированности: один и тот же slash command может дать разные результаты при разных запусках
- Нет явного управления инструментами: slash command наследует текущий permission mode сессии

---

### 3.6 🟡 Non-interactive CI/CD Mode — headless execution

Claude Code поддерживает программное использование через headless mode:

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

**Значение для оркестрации:** Headless mode превращает AI-агент в Unix-pipeline-компонент: stdin → agent loop → stdout. Три ключевых механизма контроля — `--max-turns` (лимит итераций), `--max-budget-usd` (лимит стоимости), `--allowedTools` (sandbox) — вместе образуют guard rails для автономного выполнения. `--json-schema` позволяет валидировать выход агента на соответствие заданной схеме, что критично для программной интеграции. `--fallback-model` обеспечивает graceful degradation при перегрузке основной модели.

**Ограничения:**
- Бюджетный контроль (`--max-budget-usd`) доступен только в print mode; в interactive mode — только tracking без лимита
- Нет встроенного retry при ошибке: exit code 1 означает неудачу, повторный запуск — ответственность вызывающей стороны
- Exit codes ограничены тремя значениями (0/1/2) — нет детализации причины ошибки
- `--json-schema` валидирует финальный текстовый ответ, но не промежуточные tool calls

---

### 3.7 🟡 Agent SDK — программное создание агентов

Claude Code предоставляет Agent SDK (Python + TypeScript) для программного создания AI-агентов:

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

**Значение для оркестрации:** Agent SDK предоставляет программный контроль над agent loop через код, а не через конфигурацию. Streaming output + callbacks позволяют интегрировать агент в произвольные оркестрационные сценарии: перехватывать промежуточные результаты, динамически модифицировать поведение, реализовывать fan-out/fan-in через `AgentDefinition`. OpenTelemetry даёт observability на уровне распределённой трассировки.

**Ограничения:**
- SDK оборачивает CLI, не является standalone runtime — требует установленного Claude Code
- Нет встроенных оркестрационных примитивов: нет DAG, dependency graph, parallel fan-out/fan-in как first-class constructs
- Sub-agent'ы через SDK наследуют ограничение одного уровня вложенности
- Proprietary lock-in: SDK жёстко привязан к Claude-моделям (Anthropic API)

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 Ограниченный выбор провайдеров

Claude Code работает с моделями Anthropic (Sonnet, Opus, Haiku) и поддерживает три cloud-провайдера: Amazon Bedrock, Google Vertex AI, Microsoft Azure. Однако все они предоставляют доступ к одним и тем же моделям Claude. Task-orchestrator целенаправленно поддерживает несколько runner'ов (pi, codex) через interface, включая принципиально разные модели.

### 4.2 🟢 Extended Thinking / Reasoning Tokens

Claude 3.7+ поддерживает «extended thinking» — внутренние рассуждения модели перед генерацией ответа. Это особенность конкретной модели, а не архитектурный паттерн. Не применимо к оркестратору.

### 4.3 🟢 Interactive REPL-интерфейс

Claude Code — интерактивный терминальный ассистент (REPL: пользователь → LLM → инструмент → пользователь). Task-orchestrator — автоматический pipeline. Разные парадигмы взаимодействия.

### 4.4 🟢 CLAUDE.md как контекстный стандарт

CLAUDE.md — проприетарный формат контекстных файлов. Мы используем AGENTS.md (универсальный стандарт, поддерживаемый Crush, pi_agent_rust и другими инструментами). Нет смысла добавлять зависимость от проприетарного формата.

### 4.5 🟢 WebFetch — HTTP-инструмент внутри agent loop

Claude Code может делать HTTP-запросы как инструмент agent loop.

### 4.6 🟢 Session resume / checkpointing / agent teams

Claude Code поддерживает resume/continue/fork сессий, checkpointing (откат файловых изменений) и agent teams (параллельные агенты в отдельных сессиях). Для автоматического pipeline task-orchestrator это не является критичным — каждая цепочка выполняется от начала до конца. Однако checkpointing может быть интересен для rollback при ошибках.

---

## 5. Сводка по оркестрации

| Возможность | Статус в продукте | Описание |
| --- | --- | --- |
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
