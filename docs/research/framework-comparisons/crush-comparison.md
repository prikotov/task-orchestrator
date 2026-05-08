# Исследование: Charmbracelet Crush — терминальный AI-агент (Go)

> **Проект:** [github.com/charmbracelet/crush](https://github.com/charmbracelet/crush)
> **Дата анализа:** 2026-04-21
> **Язык:** Go
> **Лицензия:** FSL-1.1-MIT (Functional Source License, MIT через 2 года)
> **Аналитик:** Технический писатель (Гермиона)

---

## 1. Обзор проекта

Crush — терминальный AI-кодинг-ассистент от Charmbracelet (авторы Bubbletea, Lip Gloss, VHS). Ключевая идея: интерактивный TUI-клиент для работы с LLM-моделями в контексте проекта, с доступом к файловой системе, инструментам, LSP и MCP-серверам.

Crush **не является** фреймворком оркестрации агентов. Это **CLI-инструмент** для интерактивной работы разработчика с LLM с поддержкой субагентов (Coder → Task). Crush не поддерживает декларативные цепочки шагов, retry-механизмы с backoff, circuit breaker, бюджетные лимиты или quality gates. Однако реализация agent loop, sub-agent делегирования и контекстного управления представляет интерес с точки зрения паттернов оркестрации.

### Архитектура

```
main.go CLI entry point (cobra)
internal/
 app/app.go Top-level wiring: DB, config, agents, LSP, MCP, events
 cmd/ CLI commands (root, run, login, models, stats, session, server, ...)
 config/ Config: crush.json загрузка, provider, model resolution
 agent/
 agent.go SessionAgent: запуск LLM-сессий, tool calls, summarization
 coordinator.go Coordinator: управление агентами, моделями, провайдерами, sub-agent'ы
 agent_tool.go Tool «agent»: Coder → Task sub-agent
 loop_detection.go Обнаружение повторяющихся tool calls (SHA-256 сигнатуры)
 prompts.go Обёртка для загрузки Go-template промптов
 prompt/ Prompt builder: системные промпты, skills injection
 templates/ coder.md.tpl, task.md.tpl, initialize.md.tpl, summary.md, title.md, ...
 tools/ Все встроенные инструменты (bash, edit, view, grep, glob, ...) + MCP
 mcp/ MCP-клиент (stdio, HTTP/SSE транспорта)
 backend/ Backend для server mode (API over Unix socket)
 server/ HTTP API server (Unix socket / Windows named pipe)
 workspace/ Workspace management (config, data dirs, OAuth)
 session/ Session CRUD (SQLite)
 message/ Message model, content types
 db/ SQLite через sqlc + миграции
 lsp/ LSP client manager, auto-discovery
 ui/ Bubble Tea v2 TUI
 permission/ Tool permission checking, allow-lists, persistent grants
 skills/ Skill discovery (SKILL.md), загрузка, валидация, дедупликация
 shell/ Bash execution с background job support
 event/ Telemetry (PostHog)
 pubsub/ Внутренний pub/sub для кросс-компонентного обмена
 filetracker/ Отслеживание изменённых файлов в сессии
 history/ Prompt history
```

### Ключевые характеристики

| Характеристика | Значение |
| --- | --- |
| **Тип** | CLI-агент (TUI), мультиагентный (Coder → Task) |
| **Модель выполнения** | Agent loop (LLM → tool call → LLM → ...) |
| **State management** | SQLite (сессии, сообщения, файл-трекинг) |
| **Провайдеры** | 20+ LLM-провайдеров (Anthropic, OpenAI, Google, Bedrock, Azure, OpenRouter и т.д.) |
| **Расширяемость** | MCP-серверы, Agent Skills (SKILL.md), LSP |
| **Интерфейс** | Bubble Tea v2 TUI, интерактивный терминал |
| **Платформы** | macOS, Linux, Windows, FreeBSD, OpenBSD, NetBSD, Android |

### Основные компоненты

| Компонент | Назначение |
| --- | --- |
| [`internal/agent/agent.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/agent.go) | SessionAgent: LLM-сессия, streaming, tool calls, auto-summarization |
| [`internal/agent/coordinator.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/coordinator.go) | Coordinator: модели, провайдеры, инструменты, sub-agent'ы (Coder → Task) |
| [`internal/skills/skills.go`](https://github.com/charmbracelet/crush/blob/main/internal/skills/skills.go) | Agent Skills: обнаружение, парсинг, валидация SKILL.md |
| [`internal/permission/permission.go`](https://github.com/charmbracelet/crush/blob/main/internal/permission/permission.go) | Permission: проверка разрешений для tool calls |
| [`internal/config/config.go`](https://github.com/charmbracelet/crush/blob/main/internal/config/config.go) | Конфигурация: providers, models, LSP, MCP, options |
| [`internal/session/session.go`](https://github.com/charmbracelet/crush/blob/main/internal/session/session.go) | Session CRUD (SQLite), todos, cost tracking |
| [`internal/agent/tools/`](https://github.com/charmbracelet/crush/blob/main/internal/agent/tools/) | ~25 инструментов (bash, edit, view, grep, glob, write, fetch и т.д.) |

---

## 2. Возможности оркестрации — обзор

| Функция | Crush |
| --- | --- |
| **Agent Loop (основной цикл)** | ✅ LLM → tool call → LLM → ... через `fantasy.Agent.Stream()` |
| **Dual Model Architecture** | ✅ Large model (основная работа) + Small model (заголовки, summary) |
| **Sub-agent / multi-agent** | ✅ Coder → Task (иерархический, через tool `agent`) + Agentic Fetch |
| **Message Queue (последовательная обработка)** | ✅ Очередь промптов внутри сессии, обработка по завершении текущего |
| **Auto-summarization** | ✅ Адаптивные пороги по context window, с сохранением todo-состояния |
| **Loop detection** | ✅ SHA-256 сигнатуры tool calls, скользящее окно (10/5) |
| **Permission system** | ✅ Многоуровневая: yolo / allow-list / session / per-request / hook-pre-approval |
| **PreToolUse Hooks** | ✅ Shell-хуки: allow/deny/rewrite/halt,Decorator-обёртка над инструментами |
| **Orphaned tool call recovery** | ✅ Синтетические результаты для прерванных tool calls |
| **Cost tracking** | ✅ Per-session с hierarchical propagation от субагентов |
| **Agent Skills (SKILL.md)** | ✅ Обнаружение, валидация, дедупликация, lazy loading, XML injection |
| **Ролевые промпты** | ✅ Go-template промпты (coder, task, summary, title) |
| **Context file discovery** | ✅ AGENTS.md, CRUSH.md, CLAUDE.md, GEMINI.md, .cursorrules |
| **Tool self-documentation** | ✅ Каждый инструмент = .go + .md файл, встраивание в system prompt |
| **Multiple runners** | ✅ 20+ провайдеров через `charm.land/fantasy` |
| **Session persistence** | ✅ SQLite: сессии, сообщения, миграции |
| **LSP-интеграция** | ✅ LSP Manager, auto-discovery, diagnostics/references |
| **MCP-протокол** | ✅ stdio, HTTP, SSE транспорта |
| **TUI-интерфейс** | ✅ Bubble Tea v2, богатый TUI |
| **Конфигурация** | ✅ crush.json (JSON Schema) |
| **Бюджетный контроль** | ⚠️ Cost tracking (только запись, без лимитов) |

---

## 3. Оркестрационные возможности

### 3.1 🟡 Agent Loop — основной цикл выполнения (`internal/agent/agent.go`)

**Паттерн:** ReAct Loop (Reasoning + Acting) — фундаментальный оркестрационный паттерн, при котором LLM и инструменты чередуются в замкнутом цикле до достижения терминального условия.

**Как реализовано:**

Цикл реализован через метод `agent.Stream()` из библиотеки `charm.land/fantasy`. На каждой итерации (step):

1. LLM получает полную историю сообщений + системный промпт + описания инструментов
2. LLM возвращает либо текстовый ответ (конец цикла), либо tool calls
3. Каждый tool call выполняется, результат добавляется в историю
4. Цикл повторяется

Управление итерациями осуществляется через два коллбэка-перехватчика:
- `PrepareStep` — модификация сообщений и инструментов **перед** каждым шагом (обновление инструментов из горячего конфига, инъекция queued messages, workaround provider limitations)
- `StopWhen` — массив условий остановки (auto-summarization, loop detection)

Каждый шаг (step) порождает каскад событий:
- `OnReasoningStart/Delta/End` — стриминг reasoning (thinking)
- `OnTextDelta` — стриминг текстового ответа
- `OnToolInputStart` — начало ввода tool call (для UI)
- `OnToolCall` — завершённый tool call с параметрами
- `OnToolResult` — результат выполнения инструмента
- `OnStepFinish` — завершение шага, обновление usage-статистики сессии

**Конкретные детали реализации:**
- Параллельная инициализация: системный промпт и инструменты строятся concurrently через `errgroup.Group`
- Hot-reload инструментов: `PrepareStep` берёт актуальный срез инструментов через `a.tools.Copy()`, что позволяет обновлять инструменты (например, MCP) между шагами
- Провайдерные workarounds: media в tool results не поддерживается OpenAI/Google — автоматически конвертируется в user message с file attachment

**Терминальные условия:**

Условия завершения agent loop в Crush:

| Условие | Тип | Реакция |
| --- | --- | --- |
| LLM вернул текст без tool calls | Нормальное завершение | Результат возвращается пользователю, цикл завершается |
| Auto-summarization порог | Управление контекстом | Остановка → summarization → возможный continuation (см. §3.5) |
| Loop detection | Защита от зацикливания | Принудительная остановка, агент не продолжает (см. §3.6) |
| Cancel (пользователь/API) | Внешнее прерывание | `ctx.Done()` → очистка очереди → возврат (см. §3.15) |

**Отсутствует:** явный **max steps limit** — верхний предел итераций agent loop. Единственная защита от бесконечного выполнения без повторов — loop detection (окно 10/порог 5), которое срабатывает только на циклических паттернах. Теоретически агент может выполнять уникальные, но не ведущие к результату шаги неограниченно долго, пока не упрётся в context window и не вызовет summarization. Это **архитектурный зазор**: нет жёсткого ceiling на количество итераций.

**Оркестрационная значимость:** Agent Loop — это базовый строительный блок любой системы оркестрации AI-агентов. От качества реализации цикла зависит устойчивость всей системы. Ключевые аспекты: (1) условия остановки — без них агент может выполняться бесконечно, (2) hot-reload инструментов — позволяет менять «на лету» доступные агенту возможности, (3) event-каскад — обеспечивает observability и возможность перехвата на каждом этапе. Важно: **набор терминальных условий определяет модель отказоустойчивости** — Crush использует «мягкие» условия (context window, loop detection), но не «жёсткое» ограничение по шагам.

---

### 3.2 🟡 Dual Model Architecture — двухмодельная архитектура (`internal/agent/coordinator.go`, `internal/agent/agent.go`)

**Паттерн:** Tiered Model Usage — разделение «тяжёлой» и «лёгкой» моделей для разных типов задач, оптимизирующее стоимость и задержку.

**Как реализовано:**

Каждый SessionAgent инициализируется с двумя моделями:
- **Large model** — основная модель для agent loop, генерации кода, рассуждений
- **Small model** — для вспомогательных задач: генерация заголовка сессии (40 токенов max), summarization

```go
type SessionAgentOptions struct {
    LargeModel Model
    SmallModel  Model
    // ...
}
```

**Алгоритм выбора для генерации заголовка:**
1. Попытка через small model
2. При ошибке — fallback на large model
3. При ошибке обеих — дефолтное имя «Untitled Session»

**Стоимость:** для генерации заголовка используется отдельный вызов с ограничением `MaxOutputTokens: 40` (или `DefaultMaxTokens` если small model поддерживает reasoning).

**Оркестрационная значимость:** Разделение моделей по «весу» задачи — важнейший паттерн оптимизации стоимости в системах оркестрации. Заголовки сессий и саммаризация не требуют capabilities большой модели, но могут составлять значительную долю вызовов. Двухмодельная архитектура позволяет снизить стоимость на 30–50% для типичного сценария использования.

---

### 3.3 🟡 Sub-Agent архитектура — иерархическое делегирование (`internal/agent/coordinator.go`, `internal/agent/agent_tool.go`, `internal/agent/agentic_fetch_tool.go`)

**Паттерн:** Hierarchical Delegation — основной агент порождает субагентов с ограниченными возможностями для специализированных задач.

**Как реализовано:**

Crush реализует три типа агентов в иерархии:

1. **Coder agent** (корневой) — полный набор инструментов (edit, bash, write, view, ...), имеет доступ к MCP/LSP, запускает PreToolUse hooks
2. **Task agent** (дочерний) — read-only инструменты (glob, grep, ls, sourcegraph, view), **без** доступа к MCP/LSP, **без** hook interception
3. **Agentic Fetch agent** (дочерний) — специализированный агент для анализа URL и веб-поиска, использует small model, ограниченный набор инструментов (web_fetch, web_search, glob, grep, sourcegraph, view)

**Механика запуска субагента (на примере Task agent):**

```
Coder agent loop → tool call "agent" → agent_tool.go
  1. Coordinator создаёт отдельный SessionAgent (isSubAgent=true)
  2. Агент получает другой системный промпт (task.md.tpl — краткий, аналитический)
  3. Фильтрация инструментов: resolveReadOnlyTools() → только glob, grep, ls, sourcegraph, view
  4. Создаётся дочерняя сессия (session.CreateTaskSession)
  5. Агент запускается в NonInteractive=true режиме
  6. После завершения — cost propagation: parentSession.Cost += childSession.Cost
  7. Результат возвращается как ToolResponse в родительский agent loop
```

**Конкретные детали реализации:**
- Субагенты помечаются `isSubAgent=true` — это отключает: system reminder о todo list, hook interception, уведомления об окончании
- Tool `agent` реализован через `fantasy.NewParallelAgentTool` — позволяет параллельное выполнение
- Agentic Fetch использует отдельный `tmpDir` и `AutoApproveSession` — все инструменты в дочерней сессии авто-одобрены
- MCP-инструменты для Task agent полностью заблокированы: `AllowedMCP: map[string][]string{}`

**Конфигурация через crush.json:**
```json
{
  "agents": {
    "coder": {
      "model": "large",
      "allowed_tools": ["all tools except disabled"]
    },
    "task": {
      "model": "large",
      "allowed_tools": ["glob", "grep", "ls", "sourcegraph", "view"],
      "allowed_mcp": {}
    }
  }
}
```

**Параллелизм субагентов:**

Tool `agent` реализован через `fantasy.NewParallelAgentTool` — это означает, что Coder может инициировать **несколько Task-агентов параллельно**. Однако реализация имеет ограничения:
- Task-агенты работают в `NonInteractive=true` режиме без доступа к MCP/LSP
- Каждый Task-агент создаёт **отдельную дочернюю сессию** (SQLite) — нет shared state между параллельными субагентами
- Cost propagation выполняется после завершения каждого субагента — полная стоимость доступна только после завершения всех

**Глубина вложенности:** в текущей реализации **только один уровень делегирования** — Task agent не имеет tool `agent` в своём наборе инструментов (только read-only: glob, grep, ls, sourcegraph, view). Рекурсивное делегирование **невозможно**. Это осознанное архитектурное решение: ограничение глубины предотвращает неконтролируемый рост сложности и стоимости.

**Timeout субагентов:** отдельный timeout для субагентов **не документирован**. Task-агент работает до нормального завершения (LLM без tool calls) или до срабатывания loop detection / auto-summarization. В отсутствие явного timeout, Task-агент потенциально может выполняться неограниченно долго, потребляя токены.

**Оркестрационная значимость:** Иерархическое делегирование с ограничением возможностей субагентов — критический паттерн безопасности и эффективности в мультиагентных системах. Ключевые принципы: (1) **Principle of Least Privilege** — Task agent не может модифицировать файлы, (2) **Cost Propagation** — стоимость субагента не «теряется», а аккумулируется на родителе, (3) **Isolation** — субагенты не видят контекст родителя, получают только переданный промпт, (4) **Non-interactive mode** — субагенты не могут запрашивать подтверждение у пользователя, (5) **Fixed depth** — только один уровень вложенности, нет рекурсивного делегирования. **Ограничения:** отсутствие явного timeout для субагентов и невозможность координации между параллельными субагентами (no shared state) — это архитектурные компромиссы, типичные для tool-based делегирования в отличие от workflow-based оркестрации.

---

### 3.4 🟡 Message Queue — последовательная обработка промптов (`internal/agent/agent.go`)

**Паттерн:** Request Queue with Sequential Processing — обеспечение строгой последовательности обработки запросов в рамках одной сессии с автоматическим продолжением.

**Как реализовано:**

Когда новый промпт поступает для занятой сессии (агент ещё выполняет предыдущий запрос), промпт добавляется в очередь:

```go
if a.IsSessionBusy(call.SessionID) {
    existing, ok := a.messageQueue.Get(call.SessionID)
    if !ok {
        existing = []SessionAgentCall{}
    }
    existing = append(existing, call)
    a.messageQueue.Set(call.SessionID, existing)
    return nil, nil  // Не блокируем — вернёмся позже
}
```

После завершения текущего agent loop, если в очереди есть сообщения, агент рекурсивно вызывает `Run()` для первого сообщения из очереди:

```go
queuedMessages, ok := a.messageQueue.Get(call.SessionID)
if ok && len(queuedMessages) > 0 {
    firstQueuedMessage := queuedMessages[0]
    a.messageQueue.Set(call.SessionID, queuedMessages[1:])
    return a.Run(ctx, firstQueuedMessage)  // Рекурсивный вызов
}
```

**Особенности:**
- Очередь реализована через `csync.Map` (concurrent-safe структура)
- Активные запросы отслеживаются через `activeRequests` map (sessionID → cancelFunc)
- Поддержка отмены: `Cancel()` вызывает `cancel()` + очищает очередь
- Queued messages инъектируются в `PrepareStep` — они добавляются в историю сообщений **до** вызова LLM, что позволяет модели видеть все накопленные запросы

**Оркестрационная значимость:** Последовательная обработка запросов в рамках сессии — фундаментальное требование для согласованности контекста. Без очереди: либо параллельные запросы порождают race conditions в истории сообщений, либо запросы теряются. Паттерн queue + recursive continuation обеспечивает целостность сессии и работу в порядке поступления (FIFO).

---

### 3.5 🟡 Auto-summarization — адаптивное сжатие контекста (`internal/agent/agent.go`)

**Паттерн:** Context Window Management with Automatic Compression — автоматическое сжатие истории при приближении к лимиту контекстного окна модели.

**Как реализовано:**

Условие остановки проверяется на каждом шаге через `StopWhen`:

```go
StopWhen: []fantasy.StopCondition{
    func(_ []fantasy.StepResult) bool {
        cw := int64(largeModel.CatwalkCfg.ContextWindow)
        if cw == 0 { return false }  // Неизвестное окно → пропускаем
        tokens := currentSession.CompletionTokens + currentSession.PromptTokens
        remaining := cw - tokens
        var threshold int64
        if cw > 200_000 {
            threshold = 20_000                          // Фиксированный буфер для больших окон
        } else {
            threshold = int64(float64(cw) * 0.2)        // 20% для малых окон
        }
        if remaining <= threshold && !a.disableAutoSummarize {
            shouldSummarize = true
            return true
        }
        return false
    },
}
```

**Алгоритм summarization:**
1. Агент останавливается с флагом `shouldSummarize=true`
2. Создаётся сообщение с `IsSummaryMessage: true`
3. LLM (large model) получает специальный промпт `summary.md` с инструкциями:
   - Обязательные секции: Current State, Files & Changes, Technical Context, Strategy & Approach, Exact Next Steps
   - Включение текущего состояния todo-списка
4. История заменяется: все сообщения до summary отбрасываются, summary становится первым user-message
5. Токены сбрасываются: `CompletionTokens = usage.OutputTokens`, `PromptTokens = 0`
6. Если у агент loop были незавершённые tool calls — создаётся continuation prompt и ставится в очередь

**Граничные случаи и ограничения:**

1. **Recursive summarization:** после summarization агент продолжает работу. Если контекст снова заполняется — summarization запускается повторно. Однако каждый цикл summarization — это полный LLM-вызов со всем оставшимся контекстом, что означает дополнительные затраты. Системе не угрожает бесконечный цикл summarization (каждый раз контекст сжимается), но качество может деградировать при множественных последовательных summarization.

2. **Стоимость summarization:** summarization использует **large model** (не small), что делает его одним из самых дорогих операций в сессии — полный контекст + генерация summary. Это не отмечено в cost tracking отдельно.

3. **Потеря granularности:** при summarization детали предыдущих tool calls (параметры, промежуточные результаты) теряются — summary содержит только «Current State», «Files & Changes», «Technical Context», «Strategy & Approach», «Exact Next Steps». Если агенту потребуется вернуться к конкретному результату tool call из сжатой части истории — это невозможно.

**Оркестрационная значимость:** Автоматическое управление контекстным окном — необходимый механизм для систем оркестрации, работающих с длительными сессиями или цепочками. Без него: при превышении лимита модель либо обрезает контекст (теряя важную информацию), либо возвращает ошибку. Адаптивные пороги (фиксированный буфер для больших окон, пропорциональный для малых) — эмпирически обоснованный подход, учитывающий разный размер контекстного окна у разных моделей (от 8K до 200K+ токенов). **Ключевой trade-off:** summarization необратимо сжимает контекст, жертвуя детализацией ради возможности продолжения. Это отличие от подходов с sliding window (где старые сообщения просто удаляются) и от подходов с external memory (где полный контекст сохраняется во внешнем хранилище).

---

### 3.6 🟡 Loop Detection — обнаружение повторяющихся tool calls (`internal/agent/loop_detection.go`)

**Паттерн:** Sliding Window Anomaly Detection — обнаружение циклического поведения агента через анализ сигнатур действий в скользящем окне.

**Как реализовано:**

Алгоритм работает в три этапа:

1. **Вычисление сигнатуры шага:** для каждого шага (step) вычисляется SHA-256 хеш от конкатенации всех tool calls и их результатов:

```go
func getToolInteractionSignature(content fantasy.ResponseContent) string {
    h := sha256.New()
    for _, tc := range toolCalls {
        io.WriteString(h, tc.ToolName)    // имя инструмента
        io.WriteString(h, "\x00")          // NUL-разделитель
        io.WriteString(h, tc.Input)        // параметры вызова
        io.WriteString(h, "\x00")
        io.WriteString(h, output)          // результат выполнения
        io.WriteString(h, "\x00")
    }
    return hex.EncodeToString(h.Sum(nil))
}
```

Сигнатура включает **полный цикл**: инструмент → параметры → результат. Это означает, что если агент вызывает тот же инструмент с теми же параметрами и получает тот же результат — сигнатуры совпадут.

2. **Скользящее окно:** анализируются последние `windowSize=10` шагов:

```go
window := steps[len(steps)-windowSize:]
counts := make(map[string]int)
for _, step := range window {
    sig := getToolInteractionSignature(step.Content)
    if sig == "" { continue }  // Пропускаем шаги без tool calls
    counts[sig]++
    if counts[sig] > maxRepeats {  // maxRepeats = 5
        return true  // LOOP DETECTED
    }
}
```

3. **Условие срабатывания:** если любая сигнатура встречается более `maxRepeats=5` раз в окне из `windowSize=10` шагов — цикл обнаружен, агент останавливается.

**Константы:** `loopDetectionWindowSize = 10`, `loopDetectionMaxRepeats = 5`. Это означает: если агент 6 раз из последних 10 шагов повторил идентичный вызов инструмента с идентичным результатом — он «зациклился».

**Реакция на обнаружение цикла:**

При срабатывании loop detection:
1. `StopWhen` возвращает `true` → agent loop останавливается **немедленно**
2. Результат последнего шага возвращается в вызывающий код
3. Пользователь видит последний ответ модели и может продолжить сессию новым промптом

Важно: **цикл не завершает сессию** — пользователь может отправить новый промпт, который направит агента в другом направлении. Loop detection — это «soft guardrail», а не fatal error.

**Ограничения сигнатурного метода:**
- Детектируются только **полные дубликаты** (tool + params + result). Если агент вызывает тот же инструмент с немного другими параметрами или получает другой результат — сигнатуры различаются, цикл не обнаруживается
- Не детектируются **семантические циклы** — агент может рассуждать по кругу, используя разные инструменты
- Не детектируются **циклы с прогрессом** — агент продвигается минимально, но этого достаточно для разных сигнатур

**Оркестрационная значимость:** Защита от зацикливания — критический механизм безопасности для автономных AI-агентов. LLM склонны к циклическому поведению при работе с ошибками: модель пытается исправить → не получается → повторяет тот же подход. Сигнатурный метод на основе SHA-256 — детерминированный и вычислительно дешёвый (O(window_size) на каждом шаге). Выбор параметров (10/5) означает, что агенту даётся 5 попыток на один и тот же подход, после чего принудительная остановка. **Архитектурный trade-off:** сигнатурный метод — fast & deterministic, но ловит только exact-match циклы. Более продвинутые подходы (семантическое сравнение, embedding-based similarity) шире по охвату, но дороже и недетерминированы.

---

### 3.7 🟡 PreToolUse Hooks — перехват и управление инструментами (`internal/agent/hooked_tool.go`, `internal/hooks/`)

**Паттерн:** Decorator + Interceptor Chain — обёртка над инструментами, позволяющая внешним обработчикам перехватывать, модифицировать или блокировать вызовы.

**Как реализовано:**

Все инструменты верхнеуровневого агента (не субагенты!) оборачиваются в `hookedTool`:

```go
func wrapToolsWithHooks(tools []fantasy.AgentTool, runner *hooks.Runner, isSubAgent bool) []fantasy.AgentTool {
    if runner == nil || isSubAgent {
        return tools  // Субагенты без hooks!
    }
    // ...
}
```

При вызове инструмента:
1. Запускаются все зарегистрированные shell-хуки для события `PreToolUse`
2. Каждый хук может вернуть решение: `none` (нейтрально), `allow` (разрешить), `deny` (запретить)
3. Агрегация: `deny` > `allow` > `none`
4. Дополнительные возможности:
   - **Halt** (exit code 49) — немедленная остановка всего шага агента
   - **Input rewrite** — хук может модифицировать JSON-параметры tool call (shallow merge)
   - **Context injection** — хук может добавить контекст в результат инструмента
   - **Hook pre-approval** — если хук вернул `allow`, permission prompt для этого tool call пропускается

**Оркестрационная значимость:** Перехват вызовов инструментов — мощный механизм расширения оркестрации без изменения кода агента. Паттерн decorator позволяет добавлять кросс-сечения (cross-cutting concerns): аудит, валидацию, автоматическую коррекцию параметров, интеграцию с внешними системами. Особенно ценна возможность **input rewrite** — хук может скорректировать параметры вызова (например, добавить флаги безопасности) до выполнения. Ключевой дизайн-принцип: субагенты не обёртываются hooks — это предотвращает N-кратное срабатывание пользовательских хуков при делегировании.

---

### 3.8 🟡 Permission System — многоуровневое управление доступом (`internal/permission/`)

**Паттерн:** Layered Authorization with Graceful Degradation — многоуровневая система авторизации, где каждый уровень предоставляет больше автономности, но меньше контроля.

**Как реализовано:**

Система имеет 6 уровней авторизации, проверяемых последовательно:

```
Запрос → Skip all (yolo)? → Allow-list? → Hook pre-approval? → Session grant? → Auto-approve? → Per-request UI prompt
```

| Уровень | Механизм | Когда срабатывает |
| --- | --- | --- |
| 1. Skip all | `--yolo` флаг / `SetSkipRequests(true)` | Все tool calls без запросов |
| 2. Allow-list | `allowed_tools` в конфиге | Совпадение по `tool_name` или `tool_name:action` |
| 3. Hook pre-approval | `WithHookApproval(ctx, toolCallID)` | PreToolUse hook вернул `allow` |
| 4. Session grant | `sessionPermissions[]` | Ранее разрешённый `tool+action+session+path` |
| 5. Auto-approve | `autoApproveSessions[sessionID]` | Для субагентных сессий (agentic_fetch) |
| 6. Per-request | Pub/sub → UI диалог | Пользователь подтверждает вручную |

**Конкретные детали реализации:**
- Permission service реализует интерфейс `pubsub.Subscriber[PermissionRequest]` — запросы идут через pub/sub брокер
- Ожидание ответа — через channel: `respCh := make(chan bool, 1)` с `select` на `ctx.Done()` и ответ
- Persistent grants хранятся в памяти (`sessionPermissions` slice) — переживают только текущую сессию
- Thread-safety через `sync.RWMutex` для session permissions и auto-approve map

**Persistence и ограничения:**

Session grants хранятся **в памяти** (`sessionPermissions` slice) — это означает:
- Grants переживают несколько запросов в рамках одной сессии, но **не переживают перезапуск приложения**
- При перезапуске Crush пользователь должен заново подтверждать все permissions
- Нет механизма persistent allow-list между сессиями (кроме конфигурационного `allowed_tools` в `crush.json`)

Thread-safety обеспечивается через `sync.RWMutex`, но нет atomicity для grant → tool execution — теоретически возможен race между двумя goroutine при параллельной проверке.

**Оркестрационная значимость:** Управление доступом инструментов — ключевой аспект безопасности в автономных агентных системах. Многоуровневая модель позволяет гибко настраивать баланс между автономностью и контролем: от полностью автономного режима (yolo) для CI/CD до интерактивного подтверждения каждого действия. Особенно важен паттерн **session grant** — однажды подтвердив `edit:file_a.txt`, агент не будет спрашивать повторно для того же файла в той же сессии, что критично для продуктивности при длительных задачах. **Ограничение:** in-memory storage grants — это trade-off между безопасностью (grants не «прилипают» надолго) и удобством (нужно повторно подтверждать после перезапуска).

---

### 3.9 🟡 Orphaned Tool Call Recovery — восстановление прерванных вызовов (`internal/agent/agent.go`)

**Паттерн:** Self-Healing Conversation History — автоматическое восстановление целостности истории сообщений после прерываний.

**Как реализовано:**

Проблема: если сессия прерывается (ошибка, cancel, timeout) в момент, когда LLM вернул tool calls, но не все результаты записаны, LLM API потребует `tool_result` для каждого `tool_use` — иначе сессия заблокирована.

Crush решает это двумя механизмами:

1. **Filter orphaned tool results** — удаление tool results, у которых нет matching tool call:
```go
func filterOrphanedToolResults(m message.Message, knownToolCallIDs map[string]struct{}) (fantasy.Message, bool) {
    // Оставляем только tool results с известными tool_call_id
}
```

2. **Synthetic tool results for orphaned calls** — создание синтетических результатов для tool calls без ответа:
```go
func syntheticToolResultsForOrphanedCalls(m message.Message, knownToolResultIDs map[string]struct{}) (fantasy.Message, bool) {
    // Для каждого tool call без результата:
    // "tool call was interrupted and did not produce a result, you may retry this call if the result is still needed"
}
```

Оба механизма работают при подготовке истории сообщений в `preparePrompt()` — то есть при каждом вызове LLM.

**Ограничения и side effects:**

Ключевое ограничение: synthetic result говорит модели «you may retry this call if the result is still needed», но **не гарантирует идемпотентность**. Если tool call уже имел side effects (создание файла, выполнение bash-команды), retry:
- Может **повторить side effects** — например, повторно создать файл или дважды выполнить команду
- Не имеет механизма rollback — откат предыдущих side effects не предусмотрен

Это осознанный trade-off: лучше позволить модели продолжить с предупреждением, чем заблокировать сессию навсегда. Ответственность за идемпотентность лежит на **модели** (LLM должна оценить, стоит ли повторять).

**Оркестрационная значимость:** Восстановление после прерываний — необходимый механизм для устойчивости агентных систем. Без него: одна неудачная итерация может навсегда «заблокировать» сессию, потому что LLM API (OpenAI, Anthropic) возвращает ошибку валидации на mismatched tool_use/tool_result. Паттерн «synthetic result» — элегантное решение: модель получает объяснение и может решить, стоит ли повторить вызов. **Архитектурный trade-off:** целостность сессии приоритетнее строгой идемпотентности — это типичный выбор для интерактивных систем, где пользователь может intervene.

---

### 3.10 🟡 Agent Skills — стандарт SKILL.md (`internal/skills/`)

**Паттерн:** Capability Discovery with Lazy Loading — автоматическое обнаружение и ленивая загрузка «способностей» агента с дедупликацией и приоритетами.

**Как реализовано:**

Каждая «способность» (skill) — это директория с файлом `SKILL.md` в формате YAML frontmatter + markdown body:

```yaml
# SKILL.md
---
name: shell-builtins
description: Use when creating a new shell builtin command for Crush
---
# Shell Builtins
Crush's shell uses `mvdan.cc/sh/v3`...
```

**Пайплайн discovery:**
1. **Scan** — параллельный обход директорий через `fastwalk` (с поддержкой symlinked directories)
2. **Parse** — YAML frontmatter + markdown body, обработка UTF-8 BOM, нормализация line endings
3. **Validate** — проверка: name обязателен, regex `^[a-zA-Z0-9]+(-[a-zA-Z0-9]+)*$`, max 64 символа; description обязателен, max 1024 символа; имя должно совпадать с именем директории
4. **Deduplicate** — user skills override builtin skills с тем же именем (last-wins)
5. **Filter** — исключение disabled skills по конфигу
6. **Inject** — активные skills сериализуются в XML и встраиваются в system prompt через `<available_skills>` блок

**Lazy Loading:** при наличии matching skill, agent получает инструкцию в system prompt:
```
<available_skills>
  <skill>
    <name>shell-builtins</name>
    <description>Use when creating a new shell builtin command</description>
    <location>/path/to/SKILL.md</location>
    <type>builtin</type>
  </skill>
</available_skills>
```
Модель instructed: «If any entry matches the current task, you MUST call `view` on its `<location>` before taking any other action». Это двухфазный паттерн: сначала модель видит **метаданные** (name + description), и только если задача релевантна — загружает полное содержимое SKILL.md через инструмент `view`.

**Tracker:** `skills.Tracker` отслеживает, какие skills были загружены в течение текущего agent turn, и логирует «loaded_this_turn» для диагностики.

**Оркестрационная значимость:** Структурированный discovery + lazy loading skills — мощный паттерн для масштабируемых агентных систем. Без lazy loading: при 50+ skills system prompt раздувается на тысячи токенов, даже если задача использует один skill. Двухфазный подход (metadata → full load) решает проблему «context pollution». Стандарт [agentskills.io](https://agentskills.io) предлагает унифицированный формат, совместимый с разными AI-инструментами.

---

### 3.11 🟡 Context File Discovery — каскадный поиск контекста (`internal/config/`)

**Паттерн:** Cascading Context Discovery — автоматическое обнаружение и загрузка контекстных файлов из множества источников с поддержкой разных AI-инструментов.

**Как реализовано:**

Crush сканирует набор предопределённых путей для обнаружения файлов инструкций:

```go
var defaultContextPaths = []string{
    ".github/copilot-instructions.md",
    ".cursorrules",
    ".cursor/rules/",
    "CLAUDE.md", "CLAUDE.local.md",
    "GEMINI.md", "gemini.md",
    "crush.md", "crush.local.md",
    "CRUSH.md", "CRUSH.local.md",
    "AGENTS.md", "agents.md", "Agents.md",
    // ...
}
```

**Алгоритм:**
1. Для каждого пути из конфигурации: expansion (`~`, env vars) → проверка файл/директория
2. Если директория — рекурсивный обход всех файлов внутри
3. Dedup по lowercase path
4. Содержимое файлов внедряется в `PromptDat.ContextFiles` — часть данных для Go-template системного промпта

Каждый агент (Coder, Task) может иметь свой `ContextPaths` override, что позволяет разделять контекст между агентами.

**Оркестрационная значимость:** Каскадный discovery контекстных файлов — паттерн совместимости (compatibility pattern) с экосистемой AI-инструментов. Поддержка форматов разных продуктов (Claude, Cursor, Copilot, Gemini) снижает барьер входа: проект не нужно «подготавливать» под конкретный инструмент. Паттерн расширяем — добавление нового формата требует одной строки в массиве путей.

---

### 3.12 🟡 Tool Self-Documentation (`internal/agent/tools/`)

**Паттерн:** Dual-File Tool Definition — каждый инструмент определяется парой файлов: реализация (код) + описание для LLM (markdown), что обеспечивает самодокументируемость инструментов.

**Как реализовано:**

Каждый инструмент — это два файла:
- `tool_name.go` — реализация (Go-код, интерфейс `fantasy.AgentTool`)
- `tool_name.md` — описание для LLM (markdown с примерами использования)

Исключение: инструмент `bash` использует `bash.tpl` (Go template с переменными вместо статического .md).

Описание инструмента встраивается в системный промпт через механизм `fantasy.AgentTool.Info()` — модель видит имя, описание и JSON-схему параметров.

**Оркестрационная значимость:** Разделение «машино-читаемого» описания (JSON schema) и «LLM-читаемого» описания (markdown с примерами) — паттерн, улучшающий качество tool use. Модели лучше используют инструменты, когда видят конкретные примеры в естественном языке, а не только сухую схему. Это особенно важно для сложных инструментов (например, `edit` с правилами exact match).

---

### 3.13 🟡 Cost Tracking & Propagation — иерархический учёт стоимости (`internal/agent/coordinator.go`)

**Паттерн:** Hierarchical Cost Accumulation — учёт стоимости LLM-вызовов с propagate от дочерних агентов к родительским.

**Как реализовано:**

Стоимость вычисляется на каждом шаге (step) по формуле:

```
cost = CostPer1MInCached/1M × cache_creation_tokens
     + CostPer1MOutCached/1M × cache_read_tokens
     + CostPer1MIn/1M × input_tokens
     + CostPer1MOut/1M × output_tokens
```

При наличии OpenRouter — используется override cost из provider metadata.

Для субагентов стоимость propagates вверх:
```go
func (c *coordinator) updateParentSessionCost(ctx, childSessionID, parentSessionID) error {
    childSession.Cost += childSession.Cost
    parentSession.Cost += childSession.Cost
    // ...
}
```

Также поддерживается **hot cost reload** при summarization — после сжатия контекста токены пересчитываются.

**Оркестрационная значимость:** Иерархический учёт стоимости — необходимый механизм для мультиагентных систем. Без propagation: стоимость субагентов «исчезает», и пользователь не видит реальных затрат. Принцип прост: стоимость всегда аккумулируется на корневой сессии, независимо от глубины иерархии вызовов. Однако в текущей реализации **нет лимитов** — cost tracking фиксирует расходы постфактум, но не прерывает выполнение при достижении порога.

---

### 3.14 🟡 Error Handling & Provider Resilience (`internal/agent/agent.go`, `charm.land/fantasy`)

**Паттерн:** Provider Abstraction with Transparent Retry — обработка ошибок LLM-провайдеров на уровне библиотеки абстракции, скрытая от агентной логики.

**Как реализовано:**

Crush делегирует error handling библиотеке `charm.land/fantasy`, которая абстрагирует 20+ LLM-провайдеров. Ключевые аспекты:

1. **Provider-level retry:** `fantasy` обрабатывает retryable ошибки (rate limit 429, server errors 5xx, timeouts) с exponential backoff. Retry происходит прозрачно — agent loop не видит промежуточных ошибок.

2. **Dual model fallback (ограниченный):** реализован **только** для вспомогательных операций — генерация заголовка сессии (small model → large model при ошибке). Для основного agent loop fallback между моделями **не предусмотрен** — ошибка провайдера приводит к завершению шага с ошибкой.

3. **Provider media workarounds:** при обнаружении провайдера, не поддерживающего media в tool results (OpenAI, Google), автоматически конвертируется в user message с file attachment. Это preventive error handling — не ждём ошибки, а упреждающе адаптируем формат.

4. **Graceful degradation при summarization:** если summarization вызов терпит неудачу — агент не падает, а использует текущее состояние контекста (с возможным превышением лимита, что приведёт к ошибке на следующем шаге LLM).

**Что отсутствует:**
- **Cross-provider fallback:** при недоступности основного провайдера (Anthropic) нет автоматического переключения на резервный (OpenAI). Пользователь должен вручную переключить модель.
- **Circuit breaker:** нет механизма размыкания цепи при повторных ошибках провайдера — каждый новый запрос идёт к тому же провайдеру.
- **Error classification:** нет различения retryable vs non-retryable ошибок на уровне агентной логики (только на уровне `fantasy`).

**Оркестрационная значимость:** Error handling — критический паттерн для устойчивости оркестрации. В отличие от классических retry/pattern (cron, queue workers), AI-агенты имеют уникальную особенность: **LLM-провайдер может вернуть успешный, но бессмысленный ответ** (hallucination). Это означает, что retry по HTTP-status code недостаточен — нужен semantic validation, которого в Crush нет. **Архитектурный вывод:** provider-level retry + отсутствующий cross-provider fallback — это модель «single provider dependency» для каждой сессии, приемлемая для интерактивного CLI (пользователь видит ошибку и может intervene), но проблемная для автономной оркестрации.

---

### 3.15 🟡 Cancel & Graceful Shutdown (`internal/agent/agent.go`, `internal/agent/coordinator.go`)

**Паттерн:** Context-based Cancellation with Cascading Cleanup — отмена через Go context propagation с каскадной очисткой ресурсов.

**Как реализовано:**

Cancel работает через стандартный Go-механизм `context.Context`:

1. **Инициация отмены:** пользователь нажимает Escape в TUI или вызывается `Cancel(sessionID)`
2. **Cancel propagation:** `cancel()` вызывается на `activeRequests[sessionID]` — context отменяется для текущего agent loop
3. **Очистка очереди:** `messageQueue.Delete(sessionID)` — все queued промпты удаляются
4. **In-flight tool calls:** при отмене `ctx.Done()` срабатывает в tool execution — инструмент получает cancelled context и прерывается. Однако если инструмент уже выполнил side effect (создал файл, запустил bash), **откат не предусмотрен**
5. **Субагенты:** дочерние агенты получают производный context — при отмене родительского отменяются и дочерние. Cost propagation выполняется до отмены — частичная стоимость субагента учитывается

**Гарантии:**
- Очистка очереди — **гарантирована** (synchronous map delete)
- Прерывание текущего шага — **best effort** (зависит от cooperation tool implementation)
- Rollback side effects — **не гарантируется**

**Оркестрационная значимость:** Graceful shutdown — необходимый паттерн для систем с длительными операциями. Context-based cancellation — идиоматичный Go-подход, обеспечивающий propagation отмены через всю call stack. **Ключевое ограничение:** side effects не откатываются — это фундаментальное ограничение для агентов, работающих с файловой системой и shell. Системы с transactional подходом (например, Git-based sandboxing, где все изменения коммитятся в ветку и можно откатить целиком) решают эту проблему иначе.

---

## 4. Прочие возможности (вне оркестрации)

### 4.1 🟢 TUI-интерфейс (Bubble Tea v2)

Crush — интерактивный TUI-клиент на базе Bubble Tea v2 (собственный фреймворк Charmbracelet). Реализует rich-интерфейс: streaming-вывод, reasoning display, tool call progress, permission dialogs, file diff view, session management. Интерактивная парадигма: пользователь общается с агентом в реальном времени.

### 4.2 🟢 LSP-интеграция

LSP (Language Server Protocol) — интеграция через `lsp.Manager` с auto-discovery серверов в проекте. Предоставляет diagnostics, references, code intelligence как инструменты (`lsp_diagnostics`, `lsp_references`, `lsp_restart`). Актуально для интерактивного кодинг-ассистента.

### 4.3 🟢 SQLite Session Persistence

Полная персистенция: сессии, сообщения, tool calls, file tracking, todos — всё в SQLite (через sqlc + миграции). Позволяет возобновлять сессии после перезапуска приложения.

### 4.4 🟢 Multi-provider abstraction (charm.land/fantasy)

Библиотека `charm.land/fantasy` абстрагирует 20+ LLM-провайдеров: Anthropic, OpenAI, Google, Bedrock, Azure, OpenRouter, Vercel, OpenAI-compatible, Hyper. Единый интерфейс для streaming, tool calls, reasoning, caching. Провайдерные options merge: catwalk defaults → provider config → model config.

### 4.5 🟢 MCP (Model Context Protocol) поддержка

MCP-клиент с поддержкой stdio, HTTP, SSE транспорта. MCP-инструменты автоматически обнаруживаются и добавляются в agent tools. Поддержка `list_mcp_resources` и `read_mcp_resource`.

### 4.6 🟢 Server Mode (Unix socket / Windows named pipe)

Crush может работать как сервер через Unix socket (Linux/macOS) или named pipe (Windows), предоставляя HTTP API для внешних клиентов. Позволяет интеграцию с IDE и другими инструментами.

---

## 5. Сводка по оркестрации

| Возможность | Статус | Паттерн | Значимость для области оркестрации |
| --- | --- | --- | --- |
| Agent Loop (3.1) | ✅ | ReAct Loop | Базовый строительный блок; условия остановки, event-каскад, hot-reload инструментов |
| Dual Model Architecture (3.2) | ✅ | Tiered Model Usage | Оптимизация стоимости: large для работы, small для заголовков/summary |
| Sub-agent / Hierarchical Delegation (3.3) | ✅ | Hierarchical Delegation | Principle of Least Privilege, cost propagation, non-interactive mode |
| Message Queue (3.4) | ✅ | Request Queue + Recursive Continuation | Последовательность обработки в рамках сессии, FIFO, race condition prevention |
| Auto-summarization (3.5) | ✅ | Context Window Management | Адаптивные пороги, structured summary, continuation после сжатия |
| Loop Detection (3.6) | ✅ | Sliding Window Anomaly Detection | SHA-256 сигнатуры, окно 10/порог 5, защита от бесконечных циклов |
| PreToolUse Hooks (3.7) | ✅ | Decorator + Interceptor Chain | Allow/deny/rewrite/halt, input rewrite, кросс-сечения без изменения агента |
| Permission System (3.8) | ✅ | Layered Authorization | 6 уровней: yolo → allow-list → hook → session → auto-approve → per-request |
| Orphaned Tool Call Recovery (3.9) | ✅ | Self-Healing History | Synthetic results для прерванных вызовов, предотвращение блокировки сессии |
| Agent Skills / SKILL.md (3.10) | ✅ | Capability Discovery + Lazy Loading | Двухфазный: metadata → full load, стандарт agentskills.io, дедупликация |
| Context File Discovery (3.11) | ✅ | Cascading Context Discovery | Совместимость с Claude/Cursor/Copilot/Gemini форматами |
| Tool Self-Documentation (3.12) | ✅ | Dual-File Tool Definition | LLM-читаемое описание + JSON schema, улучшает качество tool use |
| Cost Tracking & Propagation (3.13) | ✅ | Hierarchical Cost Accumulation | Propagate от субагентов к корню, hot reload при summarization |
| Error Handling & Provider Resilience (3.14) | ⚠️ | Provider Abstraction + Transparent Retry | Retry на уровне fantasy, но нет cross-provider fallback и circuit breaker |
| Cancel & Graceful Shutdown (3.15) | ✅ | Context-based Cancellation | Cascading cleanup, queue flush, best-effort tool interrupt; side effects не откатываются |
| Budget Control (лимиты) | ⚠️ | — | Только запись, без лимитов и прерывания по порогу |
| Chain / Pipeline | ❌ | — | Нет декларативных цепочек шагов |
| Retry with Backoff | ❌ | — | Только провайдерный retry (в fantasy) |
| Circuit Breaker | ❌ | — | Нет механизма размыкания цепи |
| Quality Gates | ❌ | — | Нет формализованных проверок качества между шагами |

---

## 6. Указатель источников для деталей

Все ссылки ведут к конкретным файлам в репозитории Crush:

- [`internal/agent/agent.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/agent.go) — SessionAgent: agent loop, streaming, tool calls, auto-summarization, loop detection, message queue, orphaned tool call recovery, provider media workarounds
- [`internal/agent/coordinator.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/coordinator.go) — Coordinator: управление моделями, провайдерами, инструментами, sub-agent'ами (Coder → Task), cost propagation, skill discovery, cancel propagation
- [`internal/agent/agent_tool.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/agent_tool.go) — Tool «agent»: создание и запуск Task sub-agent из Coder
- [`internal/agent/agentic_fetch_tool.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/agentic_fetch_tool.go) — Tool «agentic_fetch»: специализированный субагент для анализа URL и веб-поиска
- [`internal/agent/hooked_tool.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/hooked_tool.go) — Decorator-обёртка для PreToolUse hooks: allow/deny/rewrite/halt
- [`internal/agent/loop_detection.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/loop_detection.go) — Loop detection: SHA-256 сигнатуры tool calls, скользящее окно (10/5)
- [`internal/hooks/hooks.go`](https://github.com/charmbracelet/crush/blob/main/internal/hooks/hooks.go) — PreToolUse hooks: aggregation, input rewrite, shallow merge
- [`internal/skills/skills.go`](https://github.com/charmbracelet/crush/blob/main/internal/skills/skills.go) — Agent Skills: обнаружение, парсинг, валидация, дедупликация SKILL.md, XML injection
- [`internal/permission/permission.go`](https://github.com/charmbracelet/crush/blob/main/internal/permission/permission.go) — Permission system: 6 уровней авторизации, allow-list, persistent grants, auto-approve, hook pre-approval
- [`internal/config/config.go`](https://github.com/charmbracelet/crush/blob/main/internal/config/config.go) — Конфигурация: providers, models, agents (Coder/Task), AllowedTools, AllowedMCP, context paths
- [`internal/agent/prompt/prompt.go`](https://github.com/charmbracelet/crush/blob/main/internal/agent/prompt/prompt.go) — Prompt builder: Go-template системные промпты, context files, skills XML injection
- [`internal/session/session.go`](https://github.com/charmbracelet/crush/blob/main/internal/session/session.go) — Session persistence (SQLite), todos, cost tracking
- [`internal/agent/templates/coder.md.tpl`](https://github.com/charmbracelet/crush/blob/main/internal/agent/templates/coder.md.tpl) — Системный промпт для coder-агента (Go template): правила, workflow, skills
- [`internal/agent/templates/task.md.tpl`](https://github.com/charmbracelet/crush/blob/main/internal/agent/templates/task.md.tpl) — Системный промпт для task-субагента: concise, read-only
- [`internal/agent/templates/summary.md`](https://github.com/charmbracelet/crush/blob/main/internal/agent/templates/summary.md) — Промпт для summarization: обязательные секции, todo-сохранение
- [`internal/config/load.go`](https://github.com/charmbracelet/crush/blob/main/internal/config/load.go) — Загрузка конфигурации, skills paths, context paths
- [`AGENTS.md`](https://github.com/charmbracelet/crush/blob/main/AGENTS.md) — Документация для AI-агентов (архитектура, style guide, commands)
- [`README.md`](https://github.com/charmbracelet/crush/blob/main/README.md) — Документация: features, конфигурация, MCP, LSP, skills
- [`schema.json`](https://github.com/charmbracelet/crush/blob/main/schema.json) — JSON Schema для crush.json конфигурации

---

📚 **Источники:**
1. [github.com/charmbracelet/crush](https://github.com/charmbracelet/crush) — репозиторий проекта
2. [agentskills.io](https://agentskills.io) — стандарт Agent Skills
3. [charm.land](https://charm.land) — экосистема Charmbracelet
4. [FSL-1.1-MIT License](https://github.com/charmbracelet/crush/blob/main/LICENSE.md) — лицензия
