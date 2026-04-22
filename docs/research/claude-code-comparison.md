# Исследование: Claude Code — CLI-агент Anthropic (проприетарный)

> **Проект:** [docs.anthropic.com/en/docs/claude-code](https://docs.anthropic.com/en/docs/claude-code)
> **Дата анализа:** 2026-04-22
> **Язык:** Закрытый исходный код (TypeScript/Node.js по наблюдаемому поведению)
> **Лицензия:** Проприетарный (Anthropic)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

Claude Code — официальный CLI-агент от Anthropic для агрессивного (agentic) кодинга в терминале. Ключевая идея: автономный AI-ассистент, который работает непосредственно с файловой системой проекта, выполняет команды, ищет код и взаимодействует с git — всё через agent loop (LLM → tool call → наблюдение → LLM → ...).

Claude Code **не является** фреймворком оркестрации агентов. Это **одноагентный CLI-инструмент** для интерактивной и headless-работы с LLM в контексте проекта. В отличие от task-orchestrator, Claude Code не поддерживает цепочки шагов (chains), retry-механизмы с backoff, circuit breaker, budget control с лимитами или quality gates. Однако его архитектура agent loop, система инструментов и управление контекстом представляют значительный интерес.

### Архитектура

Claude Code — проприетарный продукт. Архитектура восстановлена по официальной документации, блог-постам Anthropic Engineering и наблюдаемому поведению CLI.

```
claude                              CLI entry point (Node.js binary)
  agent loop                        Core: LLM → tool call → observation → LLM → ...
    tools/                          Встроенные инструменты
      Read                         Чтение файлов и изображений
      Write                        Создание/перезапись файлов
      Edit                         Точечное редактирование файлов (oldText → newText)
      Bash                         Выполнение shell-команд
      Glob                         Поиск файлов по glob-паттернам
      Grep                         Поиск по содержимому файлов (ripgrep)
      WebFetch                     HTTP-запросы к внешним ресурсам
      Task                         Spawn sub-agent'а (дочерний агент)
      MCP tools                    Инструменты из MCP-серверов (расширяемые)
    context/                        Управление контекстом
      CLAUDE.md                    Иерархические контекстные файлы (root → subdirs)
      conversation history         История текущей сессии
      auto-compact                 Автосжатие контекста при переполнении
    permissions/                    Система разрешений
      allow/deny lists             Файловые и командные паттерны
      tiered prompts               auto-accept / ask-permission / deny
    hooks/                          Lifecycle hooks
      PreToolUse                   Перед выполнением инструмента
      PostToolUse                  После выполнения инструмента
      Notification                 Уведомления о событиях
      Stop                         При завершении agent loop
    session/                        Управление сессиями
      conversation threads         Множественные сессии (resumable)
      cost tracking                Подсчёт токенов и стоимости
    config/                         Конфигурация
      settings.json                Глобальные и проектные настройки
      .claude/                     Директория проекта
```

### Ключевые характеристики

| Характеристика | Значение |
|---|---|
| **Тип** | CLI-агент, одноагентный (с sub-agent'ами через Task tool) |
| **Модель выполнения** | Agent loop (LLM → tool call → observation → LLM → ...) |
| **State management** | In-memory (conversation history), auto-compact при переполнении |
| **Провайдер** | Только Anthropic (Claude Sonnet, Opus, Haiku) |
| **Расширяемость** | MCP-серверы, hooks (shell-скрипты), CLAUDE.md context files, custom slash commands |
| **Интерфейс** | Interactive terminal (REPL) + headless mode (`--print`, `--output-format json`) |
| **Платформы** | macOS, Linux (via npm) |

### Основные компоненты

| Компонент | Назначение |
|---|---|
| Agent loop | Ядро: итеративный вызов LLM с инструментами, до естественного завершения или лимита итераций |
| Tools (Read/Write/Edit/Bash/Glob/Grep/WebFetch/Task) | ~8 встроенных инструментов + неограниченно через MCP |
| CLAUDE.md | Иерархические контекстные файлы (global → project → directory-level) |
| Permission system | Tiered: auto-accept / ask / deny для каждого tool+path |
| Hooks | Shell-скрипты на lifecycle events (pre/post tool use, stop, notification) |
| Sub-agents (Task tool) | Spawn дочерних агентов с изолированным контекстом |
| Headless mode | Программный вызов через `claude --print` (stdin/stdout, JSON output) |
| Auto-compact | Автосжатие контекста при приближении к context window |

---

## 2. Сравнительная таблица: что у нас есть vs. чего нет

| Функция | Task Orchestrator | Claude Code | Статус |
|---|---|---|---|
| **Цепочки шагов (chains)** | ✅ YAML chains, статические и динамические | ❌ Нет. Agent loop — один непрерывный поток | ✅ У нас есть |
| **Retry с backoff** | ✅ RetryingAgentRunner | ⚠️ Только базовый retry на уровне API (429/500) | ✅ У нас есть |
| **Circuit Breaker** | ✅ CircuitBreakerAgentRunner | ❌ Нет | ✅ У нас есть |
| **Quality Gates** | ✅ Shell-команды как проверки | ❌ Нет (LLM сам оценивает результат) | ✅ У нас есть |
| **Бюджетный контроль** | ✅ BudgetVo (cost-based лимиты) | ⚠️ Только tracking (отображение стоимости), без автоматических лимитов | ✅ У нас лучше |
| **Итерационные циклы (fix_iterations)** | ✅ Группа шагов с max_iterations | ❌ Нет явных итерационных циклов | ✅ У нас есть |
| **Fallback routing** | ✅ Per-step fallback runner | ❌ Нет (единственный провайдер — Anthropic) | ✅ У нас есть |
| **Audit Trail (JSONL)** | ✅ JsonlAuditLogger | ⚠️ Логирование через `--verbose`, нет JSONL | ✅ У нас есть |
| **Ролевые промпты** | ✅ .md файлы (18+ ролей) | ⚠️ CLAUDE.md — единый контекст, не ролевой | ✅ У нас лучше |
| **Multiple runners** | ✅ Pi + Codex (через interface) | ❌ Только Claude (Sonnet, Opus, Haiku) | ✅ У нас есть |
| **DDD-архитектура** | ✅ Domain/Application/Infrastructure | ❌ Закрытый код | ✅ У нас есть |
| **Decorator pattern** | ✅ AgentRunnerInterface | ❌ Прямой вызов | ✅ У нас есть |
| **YAML-конфигурация** | ✅ Chains + roles в YAML | ✅ settings.json + CLAUDE.md | ✅ Паритет |
| **Session persistence** | ❌ Нет (in-memory) | ⚠️ Conversation resumable, но без явного persistence layer | 🟡 Паритет |
| **MCP-протокол** | ❌ Нет | ✅ Полная поддержка MCP (stdio, HTTP) | 🟡 Позже |
| **Permission system** | ❌ Нет | ✅ Allow/deny lists, tiered prompts, per-tool granularity | 🟡 Интересно |
| **Hooks system** | ❌ Нет | ✅ PreToolUse, PostToolUse, Stop, Notification (shell-скрипты) | 🟡 Интересно |
| **Sub-agents** | ❌ Нет | ✅ Task tool — spawn sub-agent с изолированным контекстом | 🟡 Интересно |
| **Context file discovery** | ✅ AGENTS.md, role .md | ✅ CLAUDE.md иерархический (global → project → dir), + AGENTS.md | 🟡 Интересно |
| **Auto-compact (context management)** | ❌ Нет | ✅ Автоматическое сжатие контекста при переполнении | 🟡 Позже |
| **Headless mode** | ✅ CLI Symfony Console | ✅ `claude --print --output-format json` | ✅ Паритет |
| **Cost tracking** | ✅ Через budget/check | ✅ Per-conversation token/cost tracking | ✅ Паритет |
| **Custom slash commands** | ❌ Нет | ✅ `/command` — пользовательские макросы из .md файлов | 🟡 Интересно |
| **Extended thinking** | ❌ Нет | ✅ Claude 3.7+ extended thinking (reasoning tokens) | 🟢 Не берём |
| **Non-interactive CI mode** | ✅ CLI pipeline | ✅ `claude -p "..." --allowedTools` — для CI/CD | ✅ Паритет |

---

## 3. Что полезно взять и почему

### 3.1 🟡 Hooks System — lifecycle-перехватчики для tool execution

**Что у них:** Claude Code позволяет привязывать shell-скрипты к событиям жизненного цикла:

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Bash",
        "command": "check-dangerous-cmd.sh",
        "timeout": 5000
      }
    ],
    "PostToolUse": [
      {
        "matcher": "Edit",
        "command": "run-linter.sh",
        "timeout": 10000
      }
    ],
    "Stop": [
      {
        "command": "notify-completion.sh"
      }
    ]
  }
}
```

**Механика:**
- `PreToolUse` — выполняется до вызова инструмента, может заблокировать (non-zero exit = отмена)
- `PostToolUse` — выполняется после, результат добавляется в контекст LLM
- `Stop` — выполняется при завершении agent loop
- `Notification` — уведомления о событиях (завершение, ожидание ввода)
- `matcher` — фильтр по имени инструмента
- `timeout` — максимальное время выполнения hook'а

**Почему нам интересно:** Это расширение нашего decorator pattern. Сейчас retry/circuit breaker/budget — декораторы на уровне runner. Hooks позволяют добавлять произвольные pre/post проверки для каждого шага цепочки без модификации core-кода. Например:
- `PreStepUse`: проверить, что файл существует до вызова шага редактирования
- `PostStepUse`: запустить линтер после шага кодогенерации
- `ChainStop`: отправить уведомление по завершении цепочки

**Отличие от нашей реализации:**
- У нас: decorator pattern в PHP (retry, circuit breaker, budget — программно)
- У них: shell-скрипты как hooks — декларативно, без изменения кода

---

### 3.2 🟡 Sub-agent Pattern — Task tool для изолированных подзадач

**Что у них:** Claude Code может порождать sub-agent'ов через инструмент `Task`:

```
User → Claude Code (main agent)
         ├─ Task: "Investigate auth module" → sub-agent с изолированным контекстом
         ├─ Task: "Write unit tests" → sub-agent с изолированным контекстом
         └─ Task: "Review code" → sub-agent с изолированным контекстом
```

**Механика:**
- Sub-agent получает отдельный context window (не разделяет с parent)
- По завершении sub-agent возвращает summary родителю
- Родитель может породить несколько sub-agent'ов параллельно
- Sub-agent ограничен тем же набором инструментов

**Почему нам интересно:** Для dynamic chains — возможность делегировать подзадачу отдельному агенту с чистым контекстом. Сейчас у нас каждый шаг цепочки работает в общем контексте (payload). Sub-agent pattern позволяет:
- Изолировать контекст подзадачи (меньше token usage)
- Выполнять подзадачи параллельно
- Ограничивать бюджет sub-agent'а независимо

**Отличие:** У нас каждый шаг — вызов runner'а с payload. Sub-agent — это по сути «chain внутри chain» с собственным контекстом.

---

### 3.3 🟡 Hierarchical Context Files — CLAUDE.md discovery

**Что у них:** Claude Code автоматически обнаруживает контекстные файлы в иерархии:

```
~/.claude/CLAUDE.md           Global (все проекты)
  project/CLAUDE.md           Project-level
  project/src/CLAUDE.md       Directory-level (подгружается при работе с файлами в src/)
  project/src/auth/CLAUDE.md  Subdirectory-level
```

**Механика:**
- Global CLAUDE.md — всегда загружается
- Project CLAUDE.md — загружается при старте в директории проекта
- Directory-level CLAUDE.md — подгружается динамически при обращении к файлам в этой директории
- Поддержка и других форматов: `AGENTS.md`, `.cursorrules`, `README.md`

**Почему нам интересно:** Наша система `AGENTS.md` + role `.md` файлов загружает весь контекст upfront. Directory-level discovery позволяет загружать контекст «по требованию», экономя tokens. Для длинных цепочек с разными этапами (анализ → кодирование → тестирование) это может быть эффективнее.

**Отличие от нашей реализации:**
- У нас: все role .md загружаются в начале chain execution
- У них: контекст подгружается динамически по мере необходимости

---

### 3.4 🟡 Permission System — granular control для tool execution

**Что у них:** Трёхуровневая система разрешений для каждого инструмента:

1. **Auto-accept** (yolo mode): все инструменты выполняются без подтверждения
2. **Allow-list** (settings.json): `"allowedTools": ["Read", "Glob", "Grep"]` — автоматическое разрешение
3. **Per-request**: пользователь подтверждает каждый tool call в интерактивном режиме

Дополнительно:
- Allow/deny по path-паттернам: `"permissions.allow": ["Bash(npm test*)"]`
- `--allowedTools` flag для CI/CD: ограничение доступных инструментов
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

# CI/CD с ограничением инструментов
claude -p "Run tests and fix failures" \
  --allowedTools "Read,Write,Edit,Bash(npm*)" \
  --max-turns 20

# Как Unix-pipe компонент
cat error.log | claude -p "Analyze these errors" --output-format stream-json
```

**Механика:**
- `--print` / `-p`: non-interactive режим (stdin/stdout)
- `--output-format json`: структурированный JSON-вывод
- `--allowedTools`: ограничение доступных инструментов
- `--max-turns`: лимит итераций agent loop
- `--model`: выбор модели (sonnet, opus, haiku)
- Exit codes: 0 = успех, 1 = ошибка, 2 = блокировка permission

**Почему нам интересно:** Task-orchestrator работает как CLI pipeline. Headless mode Claude Code — пример того, как AI-агент может быть интегрирован в pipeline как один из runner'ов. Флаги `--max-turns` и `--allowedTools` — аналоги наших `max_iterations` и ограничений на шаги.

---

## 4. Что НЕ берём и почему

### 4.1 🟢 Единственный провайдер (Anthropic lock-in)

Claude Code работает **только** с моделями Anthropic (Sonnet, Opus, Haiku). Task-orchestrator deliberately поддерживает несколько runner'ов (pi, codex) через interface. Vendor lock-in противоречит нашей архитектуре.

### 4.2 🟢 Extended Thinking / Reasoning Tokens

Claude 3.7+ поддерживает «extended thinking» — внутренние рассуждения модели перед генерацией ответа. Это особенность конкретной модели, а не архитектурный паттерн. Не применимо к оркестратору.

### 4.3 🟢 Interactive REPL-интерфейс

Claude Code — интерактивный терминальный ассистент (REPL: пользователь → LLM → инструмент → пользователь). Task-orchestrator — автоматический pipeline. Разные парадигмы взаимодействия.

### 4.4 🟢 CLAUDE.md как контекстный стандарт

CLAUDE.md — проприетарный формат контекстных файлов. Мы используем AGENTS.md (универсальный стандарт, поддерживаемый Crush, pi_agent_rust и другими инструментами). Нет смысла добавлять зависимость от проприетарного формата.

### 4.5 🟢 WebFetch — HTTP-инструмент внутри agent loop

Claude Code может делать HTTP-запросы как инструмент agent loop. В task-orchestrator HTTP-запросы выполняются через shell-команды (curl) или runner'ы — не нужны как отдельный тип шага.

### 4.6 🟢 Conversation persistence / session resume

Claude Code сохраняет сессии и позволяет возобновлять разговоры. Для автоматического pipeline это не нужно — каждая цепочка выполняется от начала до конца.

---

## 5. Сводка рекомендаций

| Фича | Приоритет | Обоснование |
|---|---|---|
| Chain orchestration | ✅ Уже есть | Core-функциональность task-orchestrator |
| Retry + Circuit Breaker | ✅ Уже есть | Устойчивость при сбоях |
| Quality Gates | ✅ Уже есть | Автоматическая проверка кода |
| Budget control | ✅ Уже есть | Предотвращение runaway spending |
| Fix iterations | ✅ Уже есть | Closed-loop цикл разработки |
| Hooks system (pre/post step) | 🟡 P2 | Аналог decorator pattern, но декларативный. Shell-скрипты для pre/post проверок без модификации PHP-кода |
| Permission system | 🟡 P2 | Для автономного выполнения в CI/CD: ограничение доступных runner'ов и команд |
| Sub-agent pattern | 🟡 P2 | Для dynamic chains: изолированный контекст подзадач, потенциально параллельное выполнение |
| Slash commands как макросы | 🟡 P3 | Альтернативный формат определения типовых цепочек (микро-YAML через .md) |
| Hierarchical context discovery | 🟡 P3 | Динамическая загрузка контекста по директории — экономия tokens в длинных цепочках |
| Headless CI/CD mode | 🟡 P3 | Паттерн интеграции AI-агента в pipeline (флаги --max-turns, --allowedTools) |
| MCP support | 🟡 P3 | Протокол расширения возможностей через внешние серверы |
| Extended thinking | 🟢 — | Особенность модели, не архитектуры |
| Interactive REPL | 🟢 — | Разная парадигма |
| CLAUDE.md format | 🟢 — | Проприетарный формат, AGENTS.md достаточно |
| WebFetch tool | 🟢 — | Задача shell-команд и runner'ов |
| Session resume | 🟢 — | Pipeline не нуждается в resume |

---

## 6. Указатель источников для деталей

- [Anthropic Docs: Claude Code Overview](https://docs.anthropic.com/en/docs/claude-code) — официальная документация: установка, использование, инструменты
- [Anthropic Docs: Claude Code Hooks](https://docs.anthropic.com/en/docs/claude-code/hooks) — hooks system: PreToolUse, PostToolUse, Stop, Notification
- [Anthropic Docs: Claude Code Settings](https://docs.anthropic.com/en/docs/claude-code/settings) — конфигурация: permissions, allow/deny, model selection
- [Anthropic Docs: Claude Code Memory](https://docs.anthropic.com/en/docs/claude-code/memory) — CLAUDE.md иерархия, context management, auto-compact
- [Anthropic Engineering: Claude Code Best Practices](https://www.anthropic.com/engineering/claude-code-best-practices) — архитектурные принципы, agent loop, tool use

---

📚 **Источники:**
1. [docs.anthropic.com/en/docs/claude-code](https://docs.anthropic.com/en/docs/claude-code) — официальная документация Claude Code
2. [www.anthropic.com/engineering/claude-code-best-practices](https://www.anthropic.com/engineering/claude-code-best-practices) — engineering blog: best practices, архитектура agent loop
3. [docs.anthropic.com/en/docs/claude-code/hooks](https://docs.anthropic.com/en/docs/claude-code/hooks) — hooks system
4. [docs.anthropic.com/en/docs/claude-code/memory](https://docs.anthropic.com/en/docs/claude-code/memory) — CLAUDE.md, context management
5. [docs.anthropic.com/en/docs/claude-code/settings](https://docs.anthropic.com/en/docs/claude-code/settings) — permissions, configuration
