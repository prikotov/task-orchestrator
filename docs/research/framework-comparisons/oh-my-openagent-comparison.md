# Исследование: Oh My OpenAgent (OmO) — plugin-оркестратор поверх OpenCode (TypeScript/Bun)

> **Проект:** [github.com/code-yeongyu/oh-my-openagent](https://github.com/code-yeongyu/oh-my-openagent)
> **Дата анализа:** 2026-05-13
> **Язык:** TypeScript (Bun), plugin для OpenCode CLI v1.4.0+
> **Лицензия:** SUL-1.0 (Sustainable Use License — некоммерческое использование, internal business OK)
> **Аналитик:** Аналитик (Шерлок)
> **Архитектурное ревью:** Архитектор (Гэндальф) — [oh-my-openagent-review.md](../coding-agents/oh-my-openagent-review.md)
> **Смежные исследования:** OpenCode CLI (#22) — [opencode-comparison.md](opencode-comparison.md), Kilo Code (#21) — [opencode-orchestrator-comparison.md](opencode-orchestrator-comparison.md)

---

## 1. Обзор проекта

Oh My OpenAgent (OmO) — **plugin для OpenCode**, а не standalone фреймворк. Устанавливается поверх OpenCode через `bunx oh-my-openagent install`, регистрируется как plugin в `opencode.json`. Добавляет систему Discipline Agents, Team Mode для параллельной мультиагентной координации, IntentGate, Category System, Skill-Embedded MCPs, Hash-Anchored Edit Tool и 50+ hooks.

OmO **не является** фреймворком оркестрации цепочек в классическом смысле. Он работает на уровне **agent loop** — расширяет интерактивный AI-кодинг-агент механизмами делегирования, классификации намерений и параллельной координации. Все базовые возможности OpenCode (75+ провайдеров, JSON-режим, кастомные агенты, MCP, ACP) сохраняются.

### Архитектура

```
oh-my-openagent/                     # Plugin package (npm: oh-my-opencode)
 src/
  agents/                             # 11 Discipline Agents с промптами и моделями
   sisyphus/                          # Главный оркестратор (Claude Opus / Kimi K2.6 / GLM-5)
   hephaestus/                        # Автономный глубокий воркер (GPT-5.5)
   prometheus/                        # Стратегический планировщик (dual-prompt: Claude/GPT)
   atlas/                             # Оркестратор выполнения (dual-prompt)
   oracle/                            # Архитектурный консультант, read-only (GPT-5.5)
   librarian/                         # Поиск по документации (GPT-5.4-mini-fast)
   explore/                           # Быстрый поиск по коду (GPT-5.4-mini-fast)
   metis/                             # Gap analyzer (Claude Sonnet)
   momus/                             # Безжалостный ревьюер (GPT-5.5)
   multimodal-looker/                 # Визуальный анализ (GPT-5.5)
   sisyphus-junior/                   # Executor делегированных задач
  categories/                         # Category System (8 категорий → автоматический выбор модели)
  hooks/                              # 52 hook'а для контроля поведения агента
  intent-gate/                        # IntentGate — классификация намерений пользователя
  team-mode/                          # Team Mode — Lead + до 8 параллельных members
   tools/                             # 12 team_* инструментов
   mailbox/                           # Shared mailbox для координации
  runner/                             # Non-interactive runner (bunx oh-my-openagent run)
  edit-tool/                          # Hash-Anchored Edit Tool (LINE#ID)
  context/                            # Context management (pruning, compaction, injection)
  skills/                             # Skill-Embedded MCPs + built-in skills
  config/                             # oh-my-openagent.jsonc — конфигурация plugin'а
  providers/                          # Agent-Model matching + fallback-цепочки
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | CLI-agent + multi-agent (plugin поверх OpenCode) |
| **Базовый продукт** | OpenCode CLI v1.4.0+ (MIT, #22 в сводной таблице) |
| **Модель выполнения** | Agent loop (LLM → tool call → LLM → ...) + Team Mode (lead + members) |
| **State management** | Persistent (SQLite через OpenCode) + shared mailbox + shared task list |
| **Провайдеры** | 75+ через OpenCode (models.dev) + 9 подписок/провайдеров OmO |
| **Расширяемость** | 50+ hooks, Skill-Embedded MCPs, custom agents, categories, plugins |
| **Интерфейс** | OpenCode TUI / Desktop / `bunx oh-my-openagent run` (non-interactive) |
| **Лицензия** | SUL-1.0 (ограничивает коммерческое распространение) |

### Основные компоненты

| Компонент | Назначение |
| --- | --- |
| Discipline Agents (11) | Специализированные агенты с промптами, моделями, permissions |
| Category System (8) | Классификация задач → автоматический выбор модели |
| IntentGate | Классификация намерения пользователя до начала работы |
| Team Mode | Lead + до 8 параллельных members с shared mailbox/task list |
| 50+ Hooks | Тонкий контроль поведения: comment checker, context monitor, compaction |
| Skill-Embedded MCPs | Скиллы несут собственные MCP-серверы, изолированные per-session |
| Hash-Anchored Edit Tool | Валидация строк через content hash перед edit |
| OmO Runner | Non-interactive runner с контролем завершения (todos + background idle) |

---

## 2. Возможности оркестрации — обзор

| Функция | OmO |
| --- | --- |
| **Agent Loop (унаследован от OpenCode)** | ✅ LLM → tool call → LLM → ... |
| **11 Discipline Agents** | ✅ Специализированные агенты: Sisyphus, Hephaestus, Prometheus, Atlas, Oracle и др. |
| **Category System** | ✅ 8 категорий → автоматический routing к модели |
| **IntentGate** | ✅ Классификация намерения (research/implementation/investigation/fix) до начала работы |
| **Team Mode** | ✅ Lead + до 8 параллельных members, 12 `team_*` инструментов, shared mailbox |
| **Background Agents** | ✅ Параллельный запуск 5+ агентов с per-provider/per-model concurrency control |
| **Dual-Prompt System** | ✅ Claude-optimized / GPT-optimized auto-switch для Prometheus и Atlas |
| **Hash-Anchored Edit Tool** | ✅ LINE#ID — content hash для каждой строки, валидация перед edit |
| **Skill-Embedded MCPs** | ✅ SKILL.md объявляет MCP-серверы, запускаемые по требованию, изолированные per-session |
| **50+ Hooks** | ✅ context-window-monitor, preemptive-compaction, directory-agents-injector и др. |
| **Non-interactive Runner** | ✅ `bunx oh-my-openagent run` с контролем завершения (todos + background idle) |
| **`/init-deep`** | ✅ Автогенерация иерархических AGENTS.md по всему проекту |
| **Runtime Fallback** | ✅ Автоматическое переключение моделей при 429, 503, 529 |
| **Dynamic Context Pruning** | ✅ (экспериментальное) Дедупликация, supersede_writes, purge_errors |
| **Claude Code Compatibility** | ✅ Загрузка hooks/commands/skills/agents/MCPs/plugins из `.claude/` |
| **Agent-Model Matching** | ✅ Per-agent fallback-цепочки провайдеров |
| **75+ LLM-провайдеров** | ✅ Через OpenCode (models.dev), BYOK |
| **Doom Loop Detection** | ✅ (унаследовано от OpenCode) 3 идентичных tool call → permission ask |
| **Error Classification** | ✅ (унаследовано от OpenCode) ContextOverflow/API 5xx/rate limit |
| **Context Compaction** | ✅ (унаследовано от OpenCode) 7-секционный structured summary + pruning + replay |
| **Бюджетный контроль** | ⚠️ Cost tracking (запись), нет лимитов и прерывания |
| **Chain / Pipeline** | ❌ Нет декларативных цепочек шагов |
| **Quality Gates** | ❌ Нет формализованных проверок качества |
| **Circuit Breaker** | ❌ Нет механизма размыкания цепи |

---

## 3. Детальный анализ по осям оркестрации

### 3.1 🟢 Модель оркестрации — Discipline Agents + Team Mode + IntentGate

**Паттерн:** Multi-layer Orchestration — трёхуровневая модель оркестрации: IntentGate (pre-routing) → Discipline Agents (specialized execution) → Team Mode (parallel coordination).

**Как реализовано:**

OmO добавляет три ортогональных механизма оркестрации поверх базового OpenCode agent loop:

#### IntentGate — Pre-orchestration routing

IntentGate классифицирует истинное намерение пользователя **до начала работы**:

| Тип намерения | Стратегия |
| --- | --- |
| `research` | Режим поиска и анализа, минимум модификаций |
| `implementation` | Полноценная реализация с edit/bash |
| `investigation` | Диагностика проблемы, read-heavy |
| `fix` | Целевое исправление, минимальные изменения |

Результат IntentGate определяет:
- Выбор Discipline Agent (Sisyphus для implementation, Explore для investigation)
- Выбор Category (`deep` для research, `quick` для fix)
- Набор доступных инструментов

**Оркестрационная значимость:** IntentGate — это **pre-orchestration routing**, аналог нашего `OrchestrateChainCommandHandler`, но на уровне выбора стратегии до выполнения. У нас задача → цепочка, у OmO задача → классификация → стратегия → агент. Это повышает качество: правильный агент + правильная модель = меньше токенов, выше точность.

#### Discipline Agents — Specialized execution

11 фиксированных Discipline Agents — каждый с собственным системным промптом, моделью по умолчанию, permissions и fallback-цепочкой:

| Агент | Модель по умолчанию | Роль | Permissions |
| --- | --- | --- | --- |
| **Sisyphus** | claude-opus-4-7 → kimi-k2.6 → glm-5 | Главный оркестратор | Full |
| **Hephaestus** | gpt-5.5 (medium) | Автономный глубокий воркер | Full |
| **Prometheus** | claude-opus-4-7 / gpt-5.5 (dual) | Стратегический планировщик | Full |
| **Atlas** | claude-sonnet-4-6 (dual) | Оркестратор выполнения | Full |
| **Oracle** | gpt-5.5 → gemini-3.1-pro → claude-opus-4-7 | Архитектурный консультант | **Read-only** |
| **Librarian** | gpt-5.4-mini-fast | Поиск по документации | Read-only |
| **Explore** | gpt-5.4-mini-fast → minimax-m2.7 → gpt-5.4-nano | Быстрый поиск по коду | Read-only |
| **Metis** | claude-sonnet-4-6 | Gap analyzer | Read-only |
| **Momus** | gpt-5.5 | Ревьюер | Read-only |
| **Multimodal-Looker** | gpt-5.5 | Визуальный анализ | Full |
| **Sisyphus-Junior** | _(из категории)_ | Executor делегированных задач | Partial |

**Agent-Model Matching:** Каждый агент имеет собственную fallback-цепочку провайдеров. Sisyphus: `anthropic → opencode/claude-opus-4-7 → opencode-go/kimi-k2.6 → kimi-for-coding/k2p5 → openai/gpt-5.5 → zai-coding-plan/glm-5 → opencode/big-pickle`. Oracle: `openai/gpt-5.5 → google/gemini-3.1-pro → anthropic/claude-opus-4-7 → opencode-go/glm-5.1`.

**Category System — Task-level routing:**

8 категорий с автоматическим выбором модели:

| Категория | Модель | Применение |
| --- | --- | --- |
| `visual-engineering` | gemini-3.1-pro (high) | UI/Frontend |
| `ultrabrain` | gpt-5.5 (xhigh) | Сложные задачи, архитектура |
| `deep` | gpt-5.5 (medium) | Глубокий анализ |
| `quick` | gpt-5.4-mini | Быстрые задачи |
| `unspecified-high` | claude-opus-4-7 (max) | Неопределённые сложные задачи |
| `writing` | gemini-3-flash | Документация, текст |
| `git` | _(кастомизируемая)_ | Git операции |
| `default` | _(из категории агента)_ | Общие задачи |

**Оркестрационная значимость:** Discipline Agents + Category System — двухмерная маршрутизация: по вертикали (агент → модель/permissions), по горизонтали (категория → модель). Это богаче нашего простого `role → runner` mapping. Однако жёсткая привязка agent → model нарушает принцип разделения Domain и Infrastructure (Гэндальф: «модель — Infrastructure-деталь, оркестратор о ней не знает»).

#### Team Mode — Parallel multi-agent coordination

Team Mode (v4.0, OFF по умолчанию) — параллельная мультиагентная координация:

**Архитектура:**
```
Lead Agent (Sisyphus)
  ├─ Member 1 (category: deep, prompt: "Security audit")
  ├─ Member 2 (category: quick, prompt: "Performance analysis")
  ├─ Member 3 (custom agent: explore, prompt: "Dependency check")
  └─ ... до 8 members
```

**Координация через 12 `team_*` инструментов:**

| Инструмент | Назначение |
| --- | --- |
| `team_create` | Создание команды с Lead + Members |
| `team_delete` | Удаление команды |
| `team_send_message` | Отправка сообщения в shared mailbox |
| `team_task_create/list/update/get` | Управление shared task list |
| `team_status` | Статус команды и участников |
| `team_list` | Список всех активных команд |
| `team_shutdown_request/approve/reject` | Координированное завершение |

**Shared coordination:**
- **Shared mailbox** — все участники читают/пишут в общий почтовый ящик
- **Shared task list** — Lead создаёт задачи, Members берут и выполняют
- **Tmux visualization** — каждый Member отображается в отдельной tmux-панели

**Ограничения:**
- Нет явного timeout для Members
- Нет автоматического budget-координирования (8× потребление токенов)
- State-машина координации (create → active → shutting_down → done) — простая, без recovery
- Нет shared memory между параллельными агентами — только mailbox + task list

**Оркестрационная значимость:** Team Mode — это **Blackboard Architecture** (Hearsay-II, 1973): общее пространство данных (mailbox + task list), параллельные «специалисты» читают/пишут, Lead координирует. Это ближе к нашему DynamicLoop, чем к линейным static-цепочкам. Однако архитектурная цена высока: 8× токены, нет budget-координации, нет recovery. Гэндальф рекомендует «наблюдать, не заимствовать сейчас».

---

### 3.2 🟡 State management — SQLite + shared mailbox + context pruning

**Паттерн:** Persistent Session State + Shared Coordination State + Proactive Context Management.

**Как реализовано:**

#### Базовое state (унаследовано от OpenCode)

- **SQLite** (Drizzle ORM) — персистентное хранение сессий, сообщений, tool calls, file snapshots
- **Event-sourced sync** — все изменения через события
- **Session resume** — восстановление сессии по session_id

#### Team Mode state

- **Shared mailbox** — асинхронные сообщения между Lead и Members
- **Shared task list** — задачи с состояниями (pending → in_progress → done)
- **Member state** — статус каждого участника (active → idle → shutting_down)

#### Context management

OmO добавляет три уровня управления контекстом поверх OpenCode:

**1. Context-window-monitor hook** — мониторинг использования context window в реальном времени:

```jsonc
{
  "hooks": {
    "context-window-monitor": {
      "enabled": true,
      "warning_threshold": 0.8,  // Предупреждение при 80% заполнения
      "critical_threshold": 0.95  // Критический порог
    }
  }
}
```

**2. Preemptive-compaction hook** — проактивная компакция **до** достижения лимитов. Сохраняет критический контекст через `compaction-context-injector`:

```jsonc
{
  "hooks": {
    "preemptive-compaction": {
      "enabled": true,
      "trigger_at_tokens": 150000,  // Compaction при 150K токенов
      "preserve_recent_turns": 3     // Сохранить 3 последних хода
    }
  }
}
```

**3. Dynamic context pruning** (экспериментальное) — автоматическая обрезка старых tool outputs:

```jsonc
{
  "experimental": {
    "dynamic_context_pruning": {
      "enabled": true,
      "strategies": {
        "deduplication": { "enabled": true },
        "supersede_writes": { "enabled": true, "aggressive": false },
        "purge_errors": { "enabled": true, "turns": 5 }
      }
    }
  }
}
```

| Стратегия | Поведение |
| --- | --- |
| `deduplication` | Удаление дублирующихся tool outputs |
| `supersede_writes` | Замена старых file-write результатов новыми (агрессивный режим: удалять все промежуточные) |
| `purge_errors` | Удаление ошибочных tool outputs старше N ходов |

**Оркестрационная значимость:** Proactive context management — это уникальная комбинация: (1) мониторинг в реальном времени, (2) проактивная компакция **до** ошибки, (3) intelligent pruning с тремя стратегиями. Большинство систем (Crush, Kilo Code) делают compaction **реактивно** — при достижении лимита. OmO — проактивно. Для длинных цепочек и dynamic loops это ценный паттерн: лучше сжать контекст заранее, чем потерять контекст при переполнении.

---

### 3.3 🟡 Error handling — runtime fallback + doom loop detection

**Паттерн:** Runtime Model Failover + Doom Loop Detection (унаследовано от OpenCode).

**Как реализовано:**

#### Runtime Fallback (OmO-specific)

Автоматическое переключение на резервные модели при ошибках API:

```jsonc
{
  "runtime_fallback": {
    "enabled": true,
    "retry_on_errors": [400, 429, 503, 529],
    "max_fallback_attempts": 3,
    "cooldown_seconds": 60
  }
}
```

**Механика:**
1. Основной провайдер возвращает ошибку (429, 503, 529)
2. OmO переключается на следующий провайдер в fallback-цепочке агента
3. Cooldown 60 секунд для упавшего провайдера
4. Максимум 3 попытки fallback перед остановкой

**Fallback-цепочки per-agent:**
- Sisyphus: `anthropic → opencode/claude-opus-4-7 → kimi-k2.6 → gpt-5.5 → glm-5 → big-pickle` (5 fallback)
- Oracle: `gpt-5.5 → gemini-3.1-pro → claude-opus-4-7 → glm-5.1` (3 fallback)
- Explore: `gpt-5.4-mini-fast → qwen3.5-plus → minimax-m2.7 → claude-haiku-4-5 → gpt-5.4-nano` (4 fallback)

#### Doom Loop Detection (унаследовано от OpenCode)

3 идентичных tool call подряд → permission ask пользователю. Простая, но эффективная эвристика против зацикливания.

#### Error Classification (унаследовано от OpenCode)

| Тип ошибки | Реакция |
| --- | --- |
| ContextOverflow | → Context compaction (сжатие) |
| API 5xx | → Retry с exponential backoff |
| Rate limit (429) | → Retry с Retry-After header parsing |
| FreeUsageLimitError | → Переключение на платный провайдер |
| GoUsageLimitError | → Переключение на другой провайдер |

**Что отсутствует:**
- **Circuit breaker** — нет размыкания цепи при повторных ошибках провайдера
- **Error classification для retry policy** — OmO не отличает FATAL от TRANSIENT: все ошибки в `retry_on_errors` обрабатываются одинаково
- **Budget-enforced stop** — нет прерывания при превышении лимита стоимости

**Оркестрационная значимость:** Runtime fallback с per-agent fallback-цепочками — мощнее нашего `RetryingAgentRunner` (у нас: retry того же runner'а, у OmO: переключение на другой провайдер/модель). Однако отсутствие circuit breaker и budget enforcement — значимые пробелы. OmO может «долбить» упавший провайдер через cooldown (60 сек), а не разомкнуть цепь. Для production CI/CD это недостаточно.

---

### 3.4 🟢 Extensibility — 50+ hooks + Skill-Embedded MCPs + plugins

**Паттерн:** Hook-based Extensibility + Declarative Skill System + Plugin Architecture.

**Как реализовано:**

#### 50+ Hooks

OmO предоставляет 52 базовых hook'а для контроля поведения агента:

| Категория hook'ов | Примеры | Назначение |
| --- | --- | --- |
| Context management | `context-window-monitor`, `preemptive-compaction`, `compaction-context-injector` | Управление context window |
| Code quality | `comment-checker`, `todo-enforcer`, `git-status-checker` | Контроль качества |
| File management | `directory-agents-injector`, `file-change-tracker` | Инъекция контекста |
| Safety | `command-validator`, `path-validator` | Валидация команд |
| Workflow | `session-lifecycle`, `task-completion-checker` | Управление сессией |

**Конфигурация hook'ов:**

```jsonc
{
  "hooks": {
    "context-window-monitor": {
      "enabled": true,
      "warning_threshold": 0.8
    },
    "comment-checker": {
      "enabled": false  // Отключение встроенного hook'а
    }
  },
  "disabled_hooks": ["directory-agents-injector"]  // Глобальное отключение
}
```

**Custom hooks:** OmO поддерживает загрузку кастомных hook'ов из `.claude/` директорий (Claude Code compatibility).

#### Skill-Embedded MCPs

SKILL.md может объявлять MCP-серверы в YAML frontmatter:

```yaml
# .opencode/skills/my-skill/SKILL.md
---
name: my-skill
description: My special custom skill
mcp:
  my-mcp:
    command: npx
    args: ["-y", "my-mcp-server"]
---

# My Skill Prompt
This content will be injected into the agent's system prompt.
```

**Механика:**
- MCP-серверы запускаются **по требованию** при активации скилла
- Изолированы per-session: ключ `${sessionID}:${skillName}:${serverName}`
- Устраняют «context bloat» от глобальных MCP — инструменты доступны только когда скилл активен

**Приоритет загрузки скиллов:** `project > opencode > user > builtin`

#### Plugin Architecture

OmO регистрируется как plugin в `opencode.json`:

```jsonc
{
  "plugins": {
    "oh-my-openagent": {
      "enabled": true,
      "config": ".opencode/oh-my-openagent.jsonc"
    }
  }
}
```

Plugin может:
- Регистрировать hook'и
- Добавлять инструменты
- Расширять промпты агентов
- Объявлять MCP-серверы
- Конфигурировать Category System и agents

**Оркестрационная значимость:** Hook-based extensibility — мощный паттерн, аналогичный Mastra AI Processor pipeline, но декларативный (через конфигурацию, не код). Skill-Embedded MCPs — уникальная инновация: скилл = промпт + инструменты, автоматически подключаемые при активации. Это устраняет проблему «global MCP bloat» (когда 20 MCP-серверов забивают context window).

---

## 4. Сравнение с task-orchestrator

### 4.1 Каркасное сравнение

| Аспект | task-orchestrator | OmO |
| --- | --- | --- |
| **Парадигма** | Декларативные YAML-цепочки шагов | Интерактивный agent loop с расширениями |
| **Шаги** | agent / quality_gate / tool — типизированные | tool calls внутри agent loop — нетипизированные |
| **Координация** | Линейное выполнение (static chains) | Agent loop + Team Mode (parallel) |
| **Retry** | ✅ С exponential backoff, per-step | ⚠️ Runtime fallback (switch provider, не retry) |
| **Circuit breaker** | ✅ Per-runner, с порогами | ❌ Нет |
| **Quality gates** | ✅ Shell-based, с fix_iterations | ❌ Нет (только hook-based validation) |
| **Budget control** | ✅ ExecutionBudgetVo, hard stop | ⚠️ Cost tracking без лимитов |
| **Audit trail** | ✅ JSONL per-step | ⚠️ SQLite session, нет специализированного audit |
| **Fix iterations** | ✅ Loop с until_condition | ❌ Нет (agent loop до completion) |
| **Context management** | ⚠️ Общий payload без сжатия | ✅ Proactive compaction + pruning + monitoring |
| **Pre-routing** | ❌ Нет (задача → цепочка вручную) | ✅ IntentGate (автоматическая классификация) |
| **Parallel execution** | ❌ Линейные цепочки | ✅ Team Mode (8 параллельных members) |
| **Fallback routing** | ⚠️ RetryingAgentRunner (retry того же runner'а) | ✅ Per-agent fallback-цепочки провайдеров |
| **Skills** | ✅ SKILL.md (информационные) | ✅ Skill-Embedded MCPs (промпт + инструменты) |
| **Extensibility** | ✅ Конфигурация YAML + runner interface | ✅ 50+ hooks + plugins + categories |

### 4.2 Что OmO делает лучше

1. **IntentGate** — pre-orchestration routing: задача автоматически классифицируется до выбора стратегии. У нас задача → цепочка — вручную.

2. **Per-agent fallback-цепочки** — при ошибке провайдера OmO переключается на следующий в цепочке. У нас RetryingAgentRunner retry'ит тот же runner с backoff, но не переключает провайдера.

3. **Proactive context management** — мониторинг, компакция и pruning **до** ошибки. У нас контекст — общий payload без ограничения размера.

4. **Team Mode** — параллельная мультиагентная координация через shared mailbox + task list. У нас только линейное выполнение.

5. **Skill-Embedded MCPs** — скиллы несут собственные MCP-серверы, автоматически подключаемые при активации. У нас скиллы — только промпт-инструкции.

6. **50+ hooks** — декларативный контроль поведения агента через конфигурацию. У нас — через код (decorator pattern на AgentRunnerInterface).

### 4.3 Что task-orchestrator делает лучше

1. **Декларативные цепочки** — YAML-определение шагов с типами (agent / quality_gate / tool). OmO не имеет цепочек — только agent loop.

2. **Retry с exponential backoff** — per-step retry с настраиваемыми параметрами. OmO — только fallback (switch provider).

3. **Circuit breaker** — размыкание цепи при повторных ошибках. OmO не имеет circuit breaker.

4. **Quality gates** — shell-based проверки качества между шагами + fix_iterations. OmO — только hook-based validation.

5. **Бюджетный контроль** — ExecutionBudgetVo с hard stop. OmO — cost tracking без лимитов.

6. **JSONL audit trail** — специализированный аудит каждого шага. OmO — SQLite session persistence.

7. **Слоистая DDD-архитектура** — Domain / Application / Infrastructure с чёткими границами. OmO — plugin с плоской структурой.

---

## 5. Вердикт

### 🟡 Заимствовать отдельные паттерны

OmO — **не фреймворк оркестрации** в нашем понимании. Это plugin, расширяющий интерактивный AI-кодинг-агент механизмами делегирования, классификации и координации. Однако OmO содержит **оригинальные паттерны**, которые ценны для архитектуры task-orchestrator.

**Уникальная ценность OmO (отсутствующая у других 22 проектов):**
1. IntentGate — pre-orchestration routing через классификацию намерений
2. Skill-Embedded MCPs — скиллы с собственными MCP-серверами
3. Proactive context management — мониторинг + компакция + pruning до ошибки
4. Category System — двухмерная маршрутизация (агент × категория → модель)

**Критические ограничения:**
1. Лицензия SUL-1.0 — коммерческое распространение запрещено
2. Нет цепочек шагов, retry с backoff, circuit breaker, quality gates
3. Нет бюджетного контроля (cost tracking без лимитов)
4. Зависимость от OpenCode — обновления OpenCode могут ломать plugin

---

## 6. Паттерны для заимствования

> На основе архитектурного ревью Гэндальфа ([oh-my-openagent-review.md](../coding-agents/oh-my-openagent-review.md)).

### ✅ Заимствовать — Quick wins (P2)

#### 6.1 IntentGate — Pre-orchestration routing

**Паттерн:** Классификация намерения задачи (research / implementation / fix / investigation) перед выбором цепочки.

**Реализация в task-orchestrator:**

```
Presentation (Console Command)
  → IntentClassifierService (Application)
    → выбрать цепочку: research-chain / implementation-chain / fix-chain
    → OrchestrateChainCommandHandler.execute(selectedChain)
```

**Архитектурные границы:**
- `IntentClassifierService` — Application-слой
- `IntentVo` — новый VO в `ChainExecution.Domain`
- Не требует изменений в Domain или Infrastructure

**Этапы:**
1. Эвристический классификатор (ключевые слова) — без LLM
2. LLM-based классификатор (quick model) — через `AgentRunnerInterface`

**Обоснование:** Правильная цепочка = правильный результат. Quick win с высоким ROI.

#### 6.2 Skill-Embedded Requires — декларативные зависимости скиллов

**Паттерн:** SKILL.md объявляет необходимые условия (инструменты, env-переменные), валидация при загрузке цепочки.

**Реализация в task-orchestrator:**
- Расширение SKILL.md YAML frontmatter полем `requires`
- `SkillRequiresVo` — новый VO в `ChainDefinition.Domain`
- Валидация в `ValidateChainConfigQueryHandler`

**Обоснование:** Безопасность выполнения — скилл может требовать PHPUnit для quality gate, и это проверяется до запуска цепочки.

### 🔍 Наблюдать — Среднесрочные (P2–P3)

#### 6.3 Category-based Runner Resolution

**Паттерн:** Категория задачи (deep / quick / default) → routing к оптимальному runner'у.

**Если реализовывать:**
- `ChainStepCategoryEnum` (новый enum в `ChainDefinition.Domain`)
- Расширение `ChainStepVo` полем `category`
- `ResolveChainRunnerService` учитывает category при резолюции

**Требует:** ADR + анализ влияния на existing chains.

#### 6.4 Per-role Permissions

**Паттерн:** Ограничение допустимых операций для конкретной роли (read-only, file-write, shell-exec).

**Если реализовывать:**
- `RolePermissionsVo` в `ChainExecution.Domain`
- Влияет на `AgentRunRequestVo` и `ResolveStepRunnerService`

**Источник:** Discipline Agent Oracle — read-only permissions.

#### 6.5 Team Mode Architecture для DynamicLoop

**Паттерн:** Lead + параллельные members с shared mailbox + shared task list.

**Архитектурные препятствия:**
- 8× потребление токенов — Budget-механизм не готов
- JSONL audit рассчитан на линейные шаги
- PHP/Symfony не рассчитаны на параллельные процессы с shared state
- Требует внешней координации (Redis, IPC)

**Рекомендация:** Research-задача для пост-MVP. DynamicLoop — естественное место для экспериментов.

### ❌ Не заимствовать

#### 6.6 Dual-Prompt (Claude/GPT auto-switch)

**Причина:** Смешивает Domain и Infrastructure — решение о промпте принимается на основе Infrastructure-детали (имя модели). Нарушает слоистую архитектуру.

**Альтернатива:** Универсальный промпт, работающий с любой моделью. Если конкретная модель требует особого формата — это ответственность runner'а (Infrastructure).

---

## 7. Сводка по оркестрации

| Возможность | Статус | Паттерн | Значимость для task-orchestrator |
| --- | --- | --- | --- |
| IntentGate (3.1) | ✅ | Pre-orchestration routing | ✅ Заимствовать: Application-сервис, без Domain-изменений |
| Discipline Agents (3.1) | ✅ | Specialized execution agents | 🔍 Наблюдать: per-role permissions, не agent→model binding |
| Category System (3.1) | ✅ | Task-level model routing | 🔍 Наблюдать: требует ADR |
| Team Mode (3.1) | ✅ | Blackboard Architecture (Lead + Members) | 🔍 Наблюдать: слишком рано, высокий риск |
| Proactive Context Mgmt (3.2) | ✅ | Monitor + Compact + Prune | 🔍 Наблюдать: ценный для длинных цепочек, P3 |
| Dynamic Context Pruning (3.2) | ✅ | Dedup + Supersede + Purge | 🔍 Наблюдать: экспериментальное |
| Runtime Fallback (3.3) | ✅ | Per-agent provider fallback chains | 🔍 Наблюдать: мощнее нашего retry, но без CB |
| Doom Loop Detection (3.3) | ✅ | 3 identical calls → ask | ✅ Заимствовать: простая эвристика для fix_iterations |
| Error Classification (3.3) | ✅ | Унаследовано от OpenCode | ✅ Уже в рекомендациях OpenCode (#22) |
| 50+ Hooks (3.4) | ✅ | Declarative behavior control | 🔍 Наблюдать: hook'и vs decorator pattern |
| Skill-Embedded MCPs (3.4) | ✅ | Skill = prompt + tools | ✅ Заимствовать (ограниченно): requires без MCP |
| Plugin Architecture (3.4) | ✅ | Config-driven extensibility | 🔍 Наблюдать |
| Dual-Prompt | ✅ | Claude/GPT auto-switch | ❌ Антипаттерн: нарушает слоистую архитектуру |
| Budget Control (лимиты) | ⚠️ | — | — |
| Chain / Pipeline | ❌ | — | — |
| Circuit Breaker | ❌ | — | — |
| Quality Gates | ❌ | — | — |
| Retry with Backoff | ❌ | — | — |

---

## 8. Указатель источников для деталей

Все ссылки ведут к репозиторию OmO:

- [oh-my-openagent — GitHub](https://github.com/code-yeongyu/oh-my-openagent) — репозиторий, README, документация
- [docs/guide/installation.md](https://github.com/code-yeongyu/oh-my-openagent/blob/dev/docs/guide/installation.md) — установка и конфигурация
- [docs/reference/features.md](https://github.com/code-yeongyu/oh-my-openagent/blob/dev/docs/reference/features.md) — полный справочник фич
- [LICENSE.md](https://github.com/code-yeongyu/oh-my-openagent/blob/dev/LICENSE.md) — полный текст SUL-1.0
- [oh-my-openagent-comparison.md](../coding-agents/oh-my-openagent-comparison.md) — отчёт по 10 критериям сабагента (Шерлок)
- [oh-my-openagent-review.md](../coding-agents/oh-my-openagent-review.md) — архитектурное ревью паттернов (Гэндальф)
- [opencode-comparison.md](opencode-comparison.md) — исследование OpenCode (#22)
- [opencode-orchestrator-comparison.md](opencode-orchestrator-comparison.md) — исследование Kilo Code (#21)

---

📚 **Источники:**
1. [Oh My OpenAgent — GitHub](https://github.com/code-yeongyu/oh-my-openagent) — репозиторий проекта
2. [OpenCode CLI — GitHub](https://github.com/anomalyco/opencode) — базовый продукт
3. [LICENSE.md](https://github.com/code-yeongyu/oh-my-openagent/blob/dev/LICENSE.md) — SUL-1.0
4. [docs/reference/features.md](https://github.com/code-yeongyu/oh-my-openagent/blob/dev/docs/reference/features.md) — справочник фич
5. [oh-my-openagent-review.md](../coding-agents/oh-my-openagent-review.md) — архитектурное ревью (Гэндальф)
