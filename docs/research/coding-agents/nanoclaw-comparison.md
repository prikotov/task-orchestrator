# NanoClaw — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-07-28
**Объект:** NanoClaw v2.1.53 (`nanocoai/nanoclaw`, TypeScript/Node.js + Bun-container, 30.4k★ GitHub)
**Задача:** [TASK-research-nanoclaw](../../../todo/TASK-research-nanoclaw.todo.md)

> **Примечание о репозитории.** Канонический репозиторий — `github.com/nanocoai/nanoclaw` (README, package.json `v2.1.53`, лицензия MIT, Copyright © 2026 Gavriel). URL `github.com/gavrielc/nanoclaw` из постановки задачи — ранний/зеркальный адрес (упоминается в LinkedIn-посте автора Рэнди Байаса); контент идентичен. В отчёте используется канонический `nanocoai/nanoclaw`.

---

## Сводка

NanoClaw — это **self-hosted personal AI assistant gateway** с multi-channel поддержкой (Telegram, WhatsApp, Discord, Slack, Microsoft Teams, iMessage, Matrix, Google Chat, Webex, Linear, GitHub, Signal, Delta Chat, Emacs, WeChat, email via Resend), написанный на TypeScript (Node.js). Лицензия MIT. Автор — Gavriel Cohen (Gemini-профиль «Gavriel» в `LICENSE`). Позиционируется как **security-first lightweight альтернатива OpenClaw**: вместо ~430k строк OpenClaw — кодовая база, умещающаяся в контекстное окно long-context модели (~226k токенов по `docs/introduction`); вместо application-level permission checks — изоляция в Linux-контейнерах (Docker).

Архитектурно NanoClaw — это **persistent daemon (host process)** плюс **по одному Docker-контейнеру на активную сессию**. Host-процесс (`src/index.ts`) владеет каналами, роутингом и lifecycle контейнеров; сам агент (Claude via **Anthropic Claude Agent SDK** по умолчанию; опционально OpenCode, Codex или Ollama через provider-skills) исполняется в контейнере и общается с хостом **только через пару SQLite-файлов** (`inbound.db` / `outbound.db`) — без RPC, без shared memory, без stdin-piping. CLI `ncl` управляет инсталляцией (группы, wirings, сессии, задачи) через Unix-сокет `data/ncl.sock`; `pnpm run chat` — one-shot чат с агентом через `data/cli.sock`, отдающий **plain text**.

**Ключевое отличие от Pi:** как и OpenClaw, NanoClaw — это **не CLI-coding agent**. Это gateway-платформа персонального AI-ассистента. CLI `pnpm run chat <message>` отправляет сообщение в работающий демон (через Unix-сокет), а не запускает автономный процесс кодинга. **Главный блокер OpenClaw — отсутствие CLI JSON/JSONL headless-режима (К6) — полностью сохранён в NanoClaw**: ни один CLI-surface не отдаёт структурированный поток событий агента, нет ephemeral-режима, нет pipe-управления. По архитектурному классу NanoClaw ≡ OpenClaw.

> **Уточнение постановки.** В задаче указано «запуск агентов в Apple containers / Docker с sandboxed execution; построен на Anthropic's Agents SDK». v2.x — **Docker-only** (FAQ README: «Docker is the default runtime and works on macOS, Linux, and Windows via WSL2»); Apple containers были в v1 и в v2 заменены на Docker (см. `docs/migrate-from-v1`). Anthropic Agents SDK подтверждён (`extend/providers.md`: «The Claude provider wraps the Claude Agent SDK»).

---

## Критерий 1. Системный промпт

### Возможности

| Механизм | Поведение |
|----------|-----------|
| `groups/<folder>/instructions.prepend.md` | Персона/standing instructions агента — единственный редактируемый пользователем файл. Инлайнит «первым» в compose-фрагмент `persona.md` (см. `guides/customize-an-agent`). |
| Composed `groups/<folder>/CLAUDE.md` | **Генерируется заново при каждом spawn** хостом (`src/claude-md-compose.ts`) и монтируется read-only в контейнер. Заголовок: `<!-- Composed at spawn - do not edit. Standing instructions: instructions.prepend.md. Memory: memory/. -->`. Содержит только `@import`-импорты: persona → shared base → per-skill фрагменты → MCP-instructions. Любая ручная правка этого файла clobber-ится при следующем spawn. |
| `container_configs.assistant_name` | Имя агента, инжектируемое в системный промпт («Your name is **X**…»). Меняется через `ncl groups config update --assistant-name` (значение в DB, применяется при следующем spawn). |
| Self-customize (контейнерный skill) | Агент сам может править свой `instructions.prepend.md` без approval (free edit) — это встроенный container skill. |
| `ANTHROPIC_BASE_URL` (env) | Редирект Claude provider на любой Anthropic-compatible endpoint (например, Ollama); реальный токен остаётся в OneCLI vault. |

### Архитектура

Системный промпт агента формируется **хостом при spawn контейнера** из `instructions.prepend.md` + общего базового файла (`container/CLAUDE.md`, read-only на `/app/CLAUDE.md`) + фрагментов выбранных skills и MCP-инструкций. **Нет CLI-флагов** для динамической замены/дополнения промпта при вызове агента (`--system-prompt`, `--append-system-prompt` отсутствуют — grep по `docs/` и исходникам `src/cli/client.ts`, `scripts/chat.ts` подтверждает отсутствие `--print`, `--mode`, `--output-format`, `stream-json`, `--json` для вывода агента; `--json` в `ncl` — только для admin-ответов команд).

### Примеры

```bash
# Нет прямого CLI-механизма замены промпта. Кастомизация — через файлы группы:
#   groups/<folder>/instructions.prepend.md   — standing instructions (персона)
#   groups/<folder>/memory/                    — долговременная память агента

# Поменять имя (инжектится в system prompt):
ncl groups config update --id <group-id> --assistant-name "Scout"
ncl groups restart --id <group-id>
```

### Сравнение с Pi

| Возможность | Pi | NanoClaw |
|-------------|-----|----------|
| Полная замена через CLI-флаг | `--system-prompt <text>` | ❌ Нет |
| Дополнение через CLI-флаг | `--append-system-prompt <text>` | ❌ Нет |
| Файловая замена (проект) | `.pi/SYSTEM.md` | `instructions.prepend.md` (compose-фрагмент persona) |
| Файловое дополнение | `.pi/APPEND_SYSTEM.md` | Только через `instructions.prepend.md` + `memory/` |

### Оценка: ⚠️ Частичная поддержка

Кастомизация системного промпта существует через `instructions.prepend.md` (файл-персона) и `assistant_name` в DB, но **нет CLI-флагов** для динамической замены/дополнения при запуске. Для сценария сабагента это означает: нужно предварительно записать файл в `groups/<folder>/` (и рестартнуть контейнер-группу), что менее гибко, чем Pi с `--system-prompt`. По классу поддержки эквивалентно OpenClaw (workspace-файлы), но даже слабее — в OpenClaw есть несколько специализированных файлов (`SOUL.md`, `AGENTS.md`, `TOOLS.md`, `IDENTITY.md`, `USER.md`); в NanoClaw всё сведено к одному `instructions.prepend.md` + сгенерированному `CLAUDE.md`.

---

## Критерий 2. Промпт агента / Роль

### Подход

NanoClaw не имеет механизма инъекции «роли» через CLI-аргумент. Поддерживается **multi-agent routing через agent groups**: каждая agent group — изолированный мир со своей папкой `groups/<folder>/`, своим `instructions.prepend.md`, своим `memory/`-деревом, своим `CLAUDE.md`, своей container config (пакеты, mounts, model, skills) и своим scope credentials в OneCLI vault.

| Механизм | Назначение |
|----------|------------|
| `ncl groups create --name <name> --folder <folder>` | Создать изолированную agent group («роль»). |
| `ncl wirings create --messaging-group-id ... --agent-group-id ... --session-mode shared\|per-thread\|agent-shared` | Привязать канал к группе с выбором уровня изоляции (`docs/concepts/isolation-levels`). |
| `create_agent` MCP tool | Агент может породить sub-agent изнутри контейнера — через approval gate, с собственным контейнером и памятью (`docs/guides/multi-agent-swarm`). |
| Provider per-group | Каждой группе можно назначить свой provider/model (`ncl groups config update --provider --model`). |

### Ограничения

1. **Нет CLI-инъекции роли** — нельзя передать файл роли в команде запуска агента.
2. **Статическая конфигурация через DB** — agent groups создаются/удаляются через `ncl` (admin-операции, для host-caller — без approval), а не передаются динамически при вызове.
3. **Контейнерно-ресурсная изоляция** — каждая группа = свой контейнер, своя память, свой credential scope. Это сильная изоляция, но тяжеловесная (Docker spawn на сессию).

### Оценка: ⚠️ Частичная поддержка (через agent groups config)

Multi-agent routing через agent groups даёт сильную изоляцию ролей (отдельный контейнер, память, credentials), но это статическая конфигурация инсталляции, а не динамическая инъекция через CLI при вызове сабагента. По классу эквивалентно OpenClaw.

---

## Критерий 3. Скиллы

### Концепция скиллов в NanoClaw — **отличается** от agentskills.io runtime-стандарта

В NanoClaw «skill» = **`SKILL.md` workflow, который исполняет coding harness** (Claude Code / Codex / OpenCode) **в checkout'е пользователя** для аддитивной установки каналов/провайдеров/tools. Это **не** рантайм-скиллы агента в смысле Pi/omp/OpenClaw.

| Категория | Локация | Назначение |
|-----------|---------|------------|
| **Host-side workspace skills** | `.claude/skills/<name>/SKILL.md` | 48 штук: 17 channel installs (`/add-telegram`, `/add-discord`, …), 3 provider installs (`/add-codex`, `/add-opencode`, `/add-ollama-provider`), 11 tool installs (`/add-gmail-tool`, `/add-gcal-tool`, …), 17 operational (`/customize`, `/debug`, `/manage-mounts`, `/update-nanoclaw`, …). Исполняются harness'ом в dev-checkout'е, **не** агентом в рантайме. |
| **Container skills** | `container/skills/` → монтируются read-only на `/app/skills` в каждый контейнер | 6 штук: `agent-browser`, `frontend-engineer`, `onecli-gateway`, `self-customize`, `vercel-cli`, `welcome`. Грузятся агентом в рантайме. |

### Управление через конфигурацию

Выбор container skills per-group — через колонку `container_configs.skills` в DB (строка `"all"` или массив имён); хост синкает symlink'и в `groups/<folder>/.claude-shared/skills/` перед монтированием. **Нет `ncl groups config` subcommand для этого поля** (на v2.1.38) — нужно редактировать JSON-колонку БД напрямую. Host-side skills вообще не управляются per-agent — это инструменты оператора в checkout'е.

### Отсутствующие возможности

1. **Нет CLI-флага `--skill` / `--no-skills`** — нельзя динамически загрузить/отключить скилл при вызове агента.
2. **Нет рантайм-стандарта agentskills.io** — `.agents/skills/` из проекта не автосканируется default Claude-provider'ом (только Codex-provider монтирует `.agents/skills` — см. К5).
3. **Нет per-role разных рантайм-скиллов через CLI** — только per-group DB-колонка; «роль» = agent group.

### Сравнение с Pi / OpenClaw

| Возможность | Pi | OpenClaw | NanoClaw |
|-------------|-----|----------|----------|
| SKILL.md формат | ✅ рантайм | ✅ рантайм (agentskills.io) | ⚠️ Есть SKILL.md, но **иной концепт** (install workflows + 6 container skills) |
| `--skill <path>` | ✅ | ❌ | ❌ |
| `--no-skills` | ✅ | ❌ | ❌ |
| Per-role разные скиллы | ✅ через CLI | ⚠️ через config allowlist | ⚠️ через DB-колонку `skills` per-group |
| `.agents/skills/` автоскан (default provider) | ✅ | ✅ workspace-scoped | ❌ (только для Codex provider — см. К5) |

### Оценка: ⚠️ Частичная поддержка

SKILL.md-формат присутствует и есть per-group выбор container skills, но концепция скиллов принципиально иная (install workflows для harness'а + фиксированный набор container skills), нет CLI-управления, нет рантайм-стандарта agentskills.io для default Claude-provider'а. Для нашего сценария «разным ролям — разные рантайм-скиллы» это слабее, чем у OpenClaw (у которого хотя бы per-agent allowlist рантайм-скиллов).

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Поведение

NanoClaw **не автосканирует** `AGENTS.md` из CWD проекта. Поведение зависит от provider'а:

| Provider | Поведение с AGENTS.md / CLAUDE.md |
|----------|------------------------------------|
| **Claude** (default) | Хост **генерирует** `groups/<folder>/CLAUDE.md` при каждом spawn (`src/claude-md-compose.ts`) из `instructions.prepend.md` (persona) + shared base + skill-фрагменты + MCP-instructions. Монтируется read-only. Ручное редактирование clobber-ится. Автообнаружения пользовательского `AGENTS.md`/`CLAUDE.md` из CWD **нет**. |
| **Codex** (`/add-codex`) | Provider владеет agent surfaces (`providesAgentSurfaces`): **сам композитит `AGENTS.md`** при каждом spawn (`codex-agents-md.ts`) из инструкций группы + выбранных skills, с 32 KB Codex-cap (деградирует выбрасыванием крупнейших секций, persona exempt). Монтируется read-only поверх group dir. |

### Где задавать инструкции проекта

Единственная редактируемая поверхность — `groups/<folder>/instructions.prepend.md` (standing instructions) и `groups/<folder>/memory/` (долговременная память). Это **аналог** `AGENTS.md`, но не auto-discovery из CWD — нужно класть файл в папку группы NanoClaw.

### Отключение

Прямого флага отключения автообнаружения нет (его и нет — compose-процесс всегда генерирует `CLAUDE.md`). Можно обнулить `instructions.prepend.md`, но базовый `container/CLAUDE.md` всё равно монтируется.

### Сравнение с Pi / OpenClaw

| Возможность | Pi | OpenClaw | NanoClaw |
|-------------|-----|----------|----------|
| AGENTS.md из CWD | ✅ авто walk-up | ❌ только из workspace | ❌ нет автообнаружения |
| CLAUDE.md | ✅ `.claude/CLAUDE.md` авто | — | ⚠️ только **генерируемый** (read-only), не читает пользовательский |
| Собственный файл | `.pi/SYSTEM.md` | `SOUL.md` (workspace) | `instructions.prepend.md` (group folder) |
| Отключение | `--no-context-files` | `skipBootstrap` | Нет (compose всегда работает) |

### Оценка: ⚠️ Частичная поддержка

Кастомизация инструкций есть через `instructions.prepend.md` (group folder), Codex-provider композитит `AGENTS.md`. Но **нет автообнаружения `AGENTS.md`/`CLAUDE.md` из CWD проекта** — для сабагент-интеграции потребуется копировать/symlink'ать инструкции проекта в папку группы NanoClaw. Слабее, чем у Pi (auto walk-up) и даже слабее, чем у OpenClaw (который хотя бы читает `AGENTS.md` из workspace).

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Поведение per-provider

| Provider | `.agents/skills/` поддержка | Детали |
|----------|------------------------------|--------|
| **Claude** (default) | ❌ Не сканируется из CWD | Skills монтируются из `container/skills/` → `/app/skills` (6 container skills) + symlink'и выбранных skills в `groups/<folder>/.claude-shared/skills/` → `/workspace/agent/.claude-shared/skills`. Claude Code внутри контейнера находит их через свою discovery. |
| **Codex** (`/add-codex`) | ✅ Монтируется дважды (read-only) | `.agents/skills` маунтится на `/workspace/agent/.agents` **и** на `/home/node/.agents` — потому что Codex сканирует workspace-level `.agents/skills` только если workspace — git-репозиторий (а workspace агента таковым не является); `$HOME`-level mount и делает skills discoverable. Skill-ссылки синкаются к выбранным группы skills + template skills. |

### Дополнительные пути / personal location

`~/.agents/skills` (personal, user-level) — **не упоминается** в документации. Все skills — per-group (в `groups/<folder>/`) или container-level (`/app/skills`). Наша структура `docs/agents/skills/` не будет обнаружена автоматически никаким provider'ом — потребуется ручное копирование в `groups/<folder>/.claude-shared/skills/` (для Claude) или `.agents/skills` (для Codex).

### Оценка: ⚠️ Частичная поддержка

`.agents/skills/` поддержан **только** для Codex-provider'а (через двойной mount); для default Claude-provider'а используется собственная схема `.claude-shared/skills/`. Локации scoped к группе, не к CWD; personal `~/.agents/skills` отсутствует. Слабее, чем у OpenClaw (который автосканирует `.agents/skills` из workspace из коробки).

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### CLI-surface'ы

```bash
# (1) Admin CLI — управляет инсталляцией, НЕ вызывает агента:
ncl <resource> <verb> [--key value ...] [--json]   # через Unix-сокет data/ncl.sock
ncl groups list
ncl sessions list
ncl tasks create --prompt "..." --recurrence "0 9 * * 1-5"

# (2) One-shot чат с агентом — plain text, НЕ structured:
pnpm run chat <message...>   # через Unix-сокет data/cli.sock
```

`pnpm run chat` (исходник `scripts/chat.ts`) — **one-shot, не интерактивный**: соединяет все argv в одно сообщение, отправляет `{"text": "..."}` JSON-строкой в сокет, печатает `msg.text` каждой строки ответа, выходит после 2 секунд тишины после первого ответа (hard timeout: 120 секунд без ответа → `exit(3)`). Вывод — **только plain text**.

### `--json` в `ncl` — НЕ для вывода агента

`--json` в `ncl` (исходник `src/cli/client.ts`) — это флаг формата **admin-ответов** команд (печатает raw response frame вместо human-readable таблицы). Он **не** запускает агента и **не** отдаёт поток его событий. Ресурсы `ncl` — `approvals`, `destinations`, `dropped-messages`, `groups`, `members`, `messaging-groups`, `policies`, `roles`, `sessions` (read-only), `tasks`, `user-dms`, `users`, `wirings`. Ни один не «вызывает агента с structured output».

### Критические ограничения для сабагента

1. **Нет JSON/JSONL-стриминга событий агента** — нет эквивалента Pi `--mode json`. `pnpm run chat` отдаёт plain text; `ncl` — admin-only.
2. **Нет ephemeral-режима** — нет эквивалента Pi `--no-session`. Сессии персистентны в SQLite (`inbound.db`/`outbound.db`), continuation-IDs провайдера сохраняются между контейнерами.
3. **Требует запущенного host-демона** — `pnpm run chat` соединяется с Unix-сокетом `data/cli.sock`, который биндит host-процесс (launchctl/systemd). Без демона — `exit(2)` с «NanoClaw daemon not reachable».
4. **Нет pipe-управления агентом** — нет эквивалента Pi `--mode rpc` через stdin/stdout. Контейнер агента не имеет stdin/stdout-маркеров вообще (`docs/concepts/architecture`: «no stdin, no stdout markers»).
5. **Нет `--print` / non-interactive structured mode** — `pnpm run chat` это и есть one-shot, но с plain text.
6. **Нет контроля таймаутов per-invocation** — `pnpm run chat` имеет фиксированный hard timeout 120s (только для no-reply). Host-sweep убивает контейнер по stale heartbeat (>max(30 min, объявленный Bash timeout)) или по claim age — это внутренняя SLA, не внешний CLI-контроль. Ранее существовавшие env `CONTAINER_TIMEOUT`, `IDLE_TIMEOUT`, `MAX_CONCURRENT_CONTAINERS` **удалены** и больше не читаются (`docs/reference/environment-variables`).

### Sub-agent spawning (внутренний)

`create_agent` MCP tool позволяет агенту породить sub-agent изнутри контейнера — с approval gate, собственным контейнером и памятью (`docs/guides/multi-agent-swarm`). Но это **внутренний** механизм gateway, не внешний CLI-интерфейс для оркестратора.

### Пример потока данных

```mermaid
sequenceDiagram
    participant Orchestrator
    participant pnpm run chat (CLI)
    participant Host Daemon (data/cli.sock)
    participant Router → inbound.db
    participant Container (Bun + Claude Agent SDK)
    participant outbound.db → Delivery
    participant LLM Provider

    Orchestrator->>pnpm run chat (CLI): pnpm run chat "Выполни задачу"
    pnpm run chat (CLI)->>Host Daemon (data/cli.sock): {"text":"..."} (Unix socket)
    Host Daemon (data/cli.sock)->>Router → inbound.db: insert row, wake container
    Router → inbound.db->>Container (Bun + Claude Agent SDK): poll (1s), format batch, query provider
    Container (Bun + Claude Agent SDK)->>LLM Provider: Claude Agent SDK streaming
    LLM Provider-->>Container (Bun + Claude Agent SDK): Stream
    Container (Bun + Claude Agent SDK)->>outbound.db → Delivery: insert messages_out
    outbound.db → Delivery-->>Host Daemon (data/cli.sock): poll (1s), deliver
    Host Daemon (data/cli.sock)-->>pnpm run chat (CLI): {"text":"..."} lines (plain text)
    pnpm run chat (CLI)-->>Orchestrator: Plain text (exit after 2s silence)
    Note over Orchestrator: Нет JSONL, нет событий tool calls, нет ephemeral, нет контроля таймаутов, нужен запущенный демон
```

### Оценка: ❌ Не поддерживается

NanoClaw **не подходит** для запуска как CLI-сабагент. Это тот же архитектурный блокер, что у OpenClaw: нет JSON-режима, нет ephemeral-сессий, нет pipe-управления, нет структурированного вывода, требуется запущенный демон. Внутренний sub-agent spawning (`create_agent` MCP) работает внутри gateway, но не предоставляет внешний CLI-интерфейс для программного управления. **К6 — критический блокер для сабагент-интеграции, не закрыт.**

---

## Критерий 7. Токены и стоимость

### Доступные метрики

| Механизм | Поведение |
|----------|-----------|
| Claude Agent SDK transcripts (`.jsonl`) | Provider Claude пишет on-disk `.jsonl`-транскрипты (подтверждено `docs/extend/providers`: «a provider whose harness already keeps an on-disk transcript (the Claude Agent SDK writes `.jsonl`)»). Доступны только чтением файлов внутри/рядом с контейнером. |
| Conversation archives (Markdown) | Pre-compaction транскрипты архивируются как Markdown в `/workspace/agent/conversations/` (`NANOCLAW_CONVERSATIONS_DIR`). Это нарратив, не structured usage. |
| `CLAUDE_TRANSCRIPT_ROTATE_BYTES` (12 MiB) / `CLAUDE_TRANSCRIPT_ROTATE_AGE_DAYS` (14) | Container-side env для ротации транскриптов — не телеметрия стоимости. |

### CLI-команды для usage/cost

**Нет.** В `ncl` нет ресурса `usage`/`stats`/`cost`. В документации нет chat-команды `/usage` (как у OpenClaw) и нет `models status`. Grep по `docs/` не находит `stats`, `usage`, `cost_usd`, `total_tokens` как CLI-surface.

### Оценка: ⚠️ Частичная поддержка (слабее OpenClaw)

Транскрипты `.jsonl` существуют на диске (как внутренний формат Claude Agent SDK), но **нет CLI-surface для programmatic извлечения usage/cost** при вызове агента — нельзя получить токены/стоимость из `pnpm run chat` или `ncl`. Это слабее, чем у OpenClaw (у которого хотя бы `/usage` chat-command и `openclaw models status`).

---

## Критерий 8. Free tier / лицензия / BYOK / Ollama-LM Studio

### NanoClaw как продукт

NanoClaw — **open-source** (MIT), полностью бесплатный. Стоимость определяется LLM-provider'ом.

### Бесплатные модели / провайдеры

| Provider | Бесплатные возможности |
|----------|----------------------|
| **Claude** (default) | Требует Anthropic API key / Claude Code OAuth — по тарифу Anthropic |
| **Codex** (`/add-codex`) | ChatGPT subscription или OpenAI API key — по тарифу OpenAI |
| **OpenCode** (`/add-opencode`) | Маршрутизирует в OpenRouter, DeepSeek, OpenAI, Google, Anthropic, OpenCode Zen — по тарифу upstream; у части есть free tier |
| **Ollama** (`/add-ollama-provider`) | Полностью бесплатно при локальных open-weight моделях (Gemma 4 / Qwen 3 Coder рекомендуется; маленькие 3B — нет) |
| LM Studio | Напрямую не задокументирован; OpenCode может маршрутизировать в локальные модели |

### BYOK

Полная поддержка — через **OneCLI Agent Vault**: credentials живут на хосте, gateway инжектит их в outbound HTTPS in-transit. Контейнер агента **никогда не видит raw API keys** — только stub со значением `onecli-managed`. Если vault недоступен, spawn падает, а не падает обратно на raw keys (`docs/concepts/security`, `docs/operate/credentials`). Опция `/use-native-credential-proxy` позволяет opt-out и читать Anthropic credentials из `.env` напрямую.

### Оценка: ✅ Бесплатный инструмент + BYOK + Ollama + несколько free-tier путей

Бесплатный MIT-инструмент, self-hosted, BYOK через vault, локальные модели через Ollama, маршрутизация в free-tier провайдеры через OpenCode.

---

## Критерий 9. Провайдеры и модели

### Поддерживаемые providers

NanoClaw не использует plugin-систему OpenClaw с 40+ провайдерами. Trunk ships только **`claude`** (default, Claude Agent SDK) + **`mock`** (для тестов). Альтернативные provider'ы — drop-in через **provider-skills** (3 шт.):

| Provider | Skill | Backend | Auth |
|----------|-------|---------|------|
| **Claude** (default) | baked in | Anthropic Claude Agent SDK | Anthropic API key / Claude Code OAuth (vault) |
| **OpenCode** | `/add-opencode` | OpenCode CLI + SDK (pinned 1.4.17) — маршрутизирует в OpenRouter, DeepSeek, OpenAI, Google, Anthropic, OpenCode Zen | per-provider API keys в vault |
| **Codex** | `/add-codex` | OpenAI Codex CLI + AppServer (pinned `@openai/codex`) — JSON-RPC over stdio | ChatGPT subscription token или OpenAI API key (vault) |
| **Ollama** | `/add-ollama-provider` | Ollama native `/v1/messages` (Anthropic-compatible) — env overrides, без provider code | placeholder key `ollama` |

### Разрешение provider per-group

Precedence at spawn (`resolveProviderName`, `src/container-runner.ts`): `sessions.agent_provider` (per-session override, но ничего на trunk его не пишет; `ncl sessions` read-only) → `container_configs.provider` → `'claude'`. Сменить provider группы: `ncl groups config update --provider opencode --model deepseek/deepseek-chat && ncl groups restart`.

### Привязка к Anthropic

Архитектурно **сильно Anthropic-centric**: default provider — Claude Agent SDK, tightly integrated (native slash-commands, mid-turn input streaming, transcript rotation — только Claude provider это реализует; OpenCode/Codex объявляют `supportsNativeSlashCommands = false`). Другие provider'ы — drop-in skills с ограниченной интеграцией (нет mid-turn input, нет transcript rotation, нет `@-import` expansion у Codex). Прямого OpenRouter-провайдера нет — только через OpenCode.

### Сравнение с Pi / OpenClaw

| Возможность | Pi | OpenClaw | NanoClaw |
|-------------|-----|----------|----------|
| Кол-во провайдеров | 20+ | 40+ | 4 пути (Claude/OpenCode/Codex/Ollama); OpenCode маршрутизирует ещё ~6 |
| OpenRouter (прямой) | ✅ | ✅ | ❌ (только через OpenCode) |
| Локальные модели | ✅ Ollama/LM Studio/vLLM | ✅ | ✅ Ollama (`/add-ollama-provider`); LM Studio не задокументирован |
| Default provider | OpenAI/Anthropic/Google на выбор | Pi core (40+) | **Claude** (Anthropic-centric) |
| Anthropic lock-in | ❌ нет | ❌ нет | ⚠️ default + tightest integration |

### Оценка: ⚠️ Частичная поддержка

4 provider-пути (плюс маршрутизация OpenCode в ~6 upstream) — значительно меньше, чем у OpenClaw (40+) или Pi (20+). Сильно Anthropic-centric: Claude — tightly-integrated default, остальные — drop-in skills с урезанной функциональностью. Прямого OpenRouter нет. Для нашего сценария это не блокер (мы BYOK), но заметно уже, чем у OpenClaw.

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Пакет | `nanoclaw` (не npm-published; git-clone + `pnpm install`) v2.1.53 |
| Лицензия | **MIT** (Copyright © 2026 Gavriel) |
| Репозиторий | https://github.com/nanocoai/nanoclaw |
| Автор | Gavriel Cohen (nanocoai) |
| Stars | ~30.4k (30386 на момент проверки) |
| Fork | ❌ Не fork OpenClaw — независимая кодовая база, написана с нуля как «лёгкая альтернатива» |
| Зависимости runtime | `@clack/core`, `@clack/prompts`, `@onecli-sh/sdk`, `better-sqlite3`, `chat` (Chat SDK), `cron-parser`, `kleur`, `yaml` — ~7 runtime deps (контраст с 70+ у OpenClaw) |

### Условия

MIT разрешает коммерческое использование, модификацию, распространение, private use. «Customization = code changes» — философия проекта: пользователь форкает и правит код (через Claude Code), а не настраивает config-сплэш.

### Vendor lock-in

⚠️ Умеренный: (1) default + tightest integration — Anthropic Claude; (2) credential-путь по умолчанию — OneCLI Agent Vault (отдельный проект `github.com/onecli/onecli`, того же автора). Обязательного cloud-backend **нет** (self-hosted, Ollama работает локально), но практическая зависимость от Anthropic + OneCLI заметна. Опция `/use-native-credential-proxy` снижает vault-зависимость.

### Оценка: ✅ Open source, MIT — максимальная свобода

MIT, независимая кодовая база (не fork), self-hosted, ~7 runtime deps. Небольшой caveat — Anthropic-centric default и OneCLI как credential-путь, но без жёсткого lock-in.

---

## Вердикт

### ❌ Не подходит как CLI-сабагент (Score: 4/10)

Сумма по 10 критериям: **21/30** (К1⚠️ К2⚠️ К3⚠️ К4⚠️ К5⚠️ К6❌ К7⚠️ К8✅ К9⚠️ К10✅ = 2+2+2+2+2+1+2+3+2+3 = 21). Формально сумма попадает в диапазон «Частично» (17–26), но **К6 (JSON/JSONL headless-режим) — критический блокер**, и по методологии эпика при его отсутствии вердикт не выше ❌ «Не подходит» (нормировка 4/10, как у OpenClaw и ZCode с тем же блокером).

**Фундаментальная причина:** NanoClaw — это **personal AI assistant gateway** (daemon + per-session Docker-контейнеры), а **не CLI-coding agent**. Это тот же архитектурный класс, что OpenClaw, с тем же главным блокером — отсутствием программного CLI-интерфейса для запуска агента со structured output. Позиционирование «security-first lightweight alternative to OpenClaw» точно: NanoClaw легче и безопаснее (Docker-изоляция вместо application-level checks), но **архитектурно эквивалентен** — не закрывает К6.

### Причины (по блокерам)

| # | Проблема | Влияние |
|---|----------|---------|
| 1 | **Нет JSON/JSONL-режима** | Невозможно получить structured-поток событий агента для мониторинга прогресса сабагента. `pnpm run chat` — plain text; `ncl --json` — только admin-ответы. |
| 2 | **Нет ephemeral-режима** | Сессии персистентны в SQLite; continuation-IDs провайдера сохраняются между контейнерами. Нет изоляции между вызовами сабагентов. |
| 3 | **Требует запущенного демона** | `pnpm run chat` — клиент к Unix-сокету `data/cli.sock`; без host-процесса — `exit(2)`. |
| 4 | **Нет CLI-флагов для промпта** | Нельзя передать system prompt / роль / skills при вызове агента. Только `instructions.prepend.md` (файл группы) + `ncl groups config update`. |
| 5 | **Нет pipe-управления** | Контейнер агента не имеет stdin/stdout-маркеров; нет эквивалента Pi `--mode rpc`. |
| 6 | **Нет контроля таймаутов per-invocation** | `pnpm run chat` — фиксированный 120s no-reply timeout; host-sweep — внутренняя SLA. Удалённые env `CONTAINER_TIMEOUT`/`IDLE_TIMEOUT` больше не читаются. |

### Сильные стороны (для других сценариев)

1. **Security-by-isolation** — агенты в Docker-контейнерах (`--cap-drop=ALL`, `--security-opt no-new-privileges`, `--init`, `--pids-limit`, setuid-stripped image), только allowlist-mounts, egress-lockdown опция. Сильнее OpenClaw (application-level checks).
2. **OneCLI Agent Vault** — raw credentials никогда не попадают в контейнер; injection in-transit; per-request approval опция. Сильная credential-модель.
3. **Лёгкая кодовая база** — ~226k токенов, ~7 runtime deps (vs 70+ у OpenClaw), умещается в контекст long-context модели; «customize = code changes».
4. **Multi-channel (16+)** — Telegram, WhatsApp, Discord, Slack, Teams, iMessage, Matrix, Google Chat, Webex, Linear, GitHub, Signal, Delta Chat, Emacs, WeChat, email via Resend.
5. **Per-group provider/model/effort** —Claude/OpenCode/Codex/Ollama, `ncl groups config update --provider --model --effort`.
6. **Three-level isolation model** — separate agent groups / shared-session / agent-shared; per-group credential scope.
7. **Host-sweep SLA** — heartbeat-based liveness (нет wall-clock idle timeout — долгие задачи не убиваются по таймеру).
8. **MIT, 30.4k★, активное развитие** (v2.1.53 на момент проверки).
9. **Self-modification с approval gate** — `create_agent`, `install_packages`, `add_mcp_server` через approval cards.

### Сравнение с OpenClaw (по каждому блокеру)

| Блокер OpenClaw | NanoClaw | Вердикт |
|-----------------|----------|---------|
| Нет CLI JSON-режима (К6) | Тоже нет (`pnpm run chat` plain text; `ncl --json` — admin-only) | **Паритет** (оба ❌) |
| Требует запущенного gateway-демона | Требует host-процесс (`data/cli.sock`) | **Паритет** |
| Нет ephemeral-режима | Тоже нет (персистентные SQLite-сессии) | **Паритет** |
| Нет CLI-флагов промпта | Тоже нет (только `instructions.prepend.md`) | **Паритет** |
| Нет pipe-управления | Тоже нет | **Паритет** |
| Нет контроля таймаутов | `runTimeoutSeconds` был у OpenClaw (внутренний); у NanoClaw — host-sweep SLA, env-timeout удалены | **Незначительный регресс** (меньше внешних ручек) |
| Security: application-level checks | **Docker-изоляция** (`--cap-drop=ALL`, egress lockdown, mount allowlist, vault) | **Улучшение** (главный selling-point NanoClaw) |
| 40+ провайдеров (К9) | 4 provider-пути (Claude/OpenCode/Codex/Ollama) | **Регресс** (значительно уже) |
| `.agents/skills/` из workspace (К5) | Только для Codex-provider; для Claude — `.claude-shared/skills/` | **Регресс** |
| `AGENTS.md` из workspace (К4) | Нет автообнаружения; только `instructions.prepend.md` группы | **Регресс** |
| `/usage` chat-command, `models status` (К7) | Ничего подобного нет | **Регресс** |

**Итог по OpenClaw:** NanoClaw **улучшает security** (Docker-изоляция вместо application-level checks — это его главный и оправданный selling-point) и **облегчает кодовую базу** (~226k токенов vs ~430k строк), но по **всем блокерам сабагент-интеграции (К6, ephemeral, pipe, prompt-CLI) — паритет**, а по **нескольким вспомогательным критериям (К4, К5, К7, К9) — регресс**. Главный блокер К6 не закрыт. Вердикт: ❌ Не подходит (4/10) — идентично OpenClaw.

### Паттерны для заимствования

| Паттерн | Описание |
|---------|----------|
| **SQLite inbox/outbox IPC** | Host↔container через пару `inbound.db`/`outbound.db` (один writer на файл, opposite directions) — без RPC, без shared memory, без stdin. Crash container не корраптит очередь хоста. |
| **Compose-at-spawn `CLAUDE.md`** | Системный промпт генерируется из `instructions.prepend.md` + shared base + skill/MCP фрагментов при каждом spawn, read-only. Решает «clobber-проблему» ручных правок. |
| **OneCLI credential injection in-transit** | Raw keys живут в vault на хосте; контейнер видит только stub `onecli-managed`; gateway инжектит на wire. Compromised agent не может extract keys. |
| **Heartbeat-based liveness (no wall-clock idle)** | Контейнер не убивается по таймеру — только по stale heartbeat или claim age. Долгие легитимные задачи не прерываются. |
| **Mount allowlist deny-by-default** | `~/.config/nanoclaw/mount-allowlist.json` вне project root; нет файла = нет дополнительных mounts; blocked patterns (`.ssh`, `.aws`, `.env`, `id_rsa`) неотключаемы. |
| **Three-level isolation (session_mode)** | `shared` / `per-thread` / `agent-shared` — гранулярный контроль изоляции разговоров при shared filesystem. |

---

## Приложение А. Сравнение с Pi по ключевым критериям

| Критерий | Pi | NanoClaw |
|----------|-----|----------|
| Тип | CLI-coding agent | Gateway daemon + multi-channel assistant (per-session Docker containers) |
| Запуск | Автономный CLI-процесс | RPC-клиент к host-демону (Unix-сокет) |
| JSON-режим | `--mode json` (JSONL) | ❌ Нет |
| Ephemeral | `--no-session` | ❌ Нет |
| System prompt (CLI) | `--system-prompt` + `--append-system-prompt` | ❌ Только `instructions.prepend.md` (файл группы) |
| Skills (CLI) | `--skill` + `--no-skills` | ❌ Только DB-колонка `skills` per-group |
| AGENTS.md | Автообнаружение из CWD | ❌ Нет автообнаружения |
| Pipe-управление | `--mode rpc` через stdin/stdout | ❌ Нет |
| Контроль таймаутов | `watch-subagent.sh` (-s, -m, -t) | ❌ Внутренняя host-sweep SLA |
| Провайдеры | 20+ | 4 пути (Anthropic-centric) |
| Лицензия | MIT | MIT |

---

## Приложение Б. Сводка оценок

| Критерий | Оценка | Балл | Обоснование |
|----------|--------|------|-------------|
| К1. Системный промпт | ⚠️ | 2 | `instructions.prepend.md` + `assistant_name` в DB; нет CLI-флагов |
| К2. Роль | ⚠️ | 2 | Multi-agent groups (сильная изоляция), но статическая DB-конфигурация, нет CLI-инъекции |
| К3. Скиллы | ⚠️ | 2 | SKILL.md есть, но иной концепт (install workflows + 6 container skills); нет CLI, нет agentskills.io runtime для default provider |
| К4. AGENTS.md | ⚠️ | 2 | `instructions.prepend.md` + composed CLAUDE.md; нет автообнаружения из CWD |
| К5. `.agents/skills/` | ⚠️ | 2 | Только для Codex-provider; для Claude — `.claude-shared/skills/`; group-scoped |
| К6. JSON-режим | ❌ | 1 | **Нет JSON/JSONL, нет ephemeral, нет pipe, нужен демон** — критический блокер |
| К7. Токены и стоимость | ⚠️ | 2 | `.jsonl`-транскрипты на диске; нет CLI-surface для usage/cost |
| К8. Free / BYOK / Ollama | ✅ | 3 | MIT, self-hosted, OneCLI vault BYOK, Ollama, OpenCode-маршрутизация |
| К9. Провайдеры | ⚠️ | 2 | 4 provider-пути; сильно Anthropic-centric; уже, чем OpenClaw/Pi |
| К10. Лицензия | ✅ | 3 | MIT, независимая кодовая база, self-hosted, ~7 runtime deps |
| **Итого** | | **21/30** | **❌ Не подходит (4/10)** — К6 блокер, архитектурный класс gateway |

---

## Источники

1. [NanoClaw — GitHub (nanocoai/nanoclaw)](https://github.com/nanocoai/nanoclaw) — README, package.json v2.1.53, LICENSE (MIT © 2026 Gavriel)
2. [docs.nanoclaw.dev/introduction](https://docs.nanoclaw.dev/introduction) — Что такое NanoClaw, архитектурная сводка (~226k токенов)
3. [docs.nanoclaw.dev/concepts/architecture](https://docs.nanoclaw.dev/concepts/architecture) — Host process + per-session containers, inbox/outbox SQLite IPC
4. [docs.nanoclaw.dev/concepts/security](https://docs.nanoclaw.dev/concepts/security) — Threat model, Docker-изоляция, mount allowlist, egress lockdown
5. [docs.nanoclaw.dev/concepts/isolation-levels](https://docs.nanoclaw.dev/concepts/isolation-levels) — Separate agent groups / shared / per-thread / agent-shared
6. [docs.nanoclaw.dev/channels/cli](https://docs.nanoclaw.dev/channels/cli) — CLI channel (`pnpm run chat`, Unix-socket `data/cli.sock`, plain text)
7. [docs.nanoclaw.dev/reference/ncl-cli](https://docs.nanoclaw.dev/reference/ncl-cli) — `ncl` admin CLI (resources, verbs, `--json`, access control)
8. [docs.nanoclaw.dev/extend/providers](https://docs.nanoclaw.dev/extend/providers) — Claude (default, Agent SDK) / OpenCode / Codex / Ollama; `.jsonl` transcripts
9. [docs.nanoclaw.dev/reference/environment-variables](https://docs.nanoclaw.dev/reference/environment-variables) — все env (включая удалённые `CONTAINER_TIMEOUT`/`IDLE_TIMEOUT`)
10. [docs.nanoclaw.dev/reference/container-config](https://docs.nanoclaw.dev/reference/container-config) — `provider`/`model`/`effort`/`skills`/`cli_scope` per-group
11. [docs.nanoclaw.dev/reference/skills-catalog](https://docs.nanoclaw.dev/reference/skills-catalog) — 48 host skills + 6 container skills
12. [docs.nanoclaw.dev/extend/overview](https://docs.nanoclaw.dev/extend/overview) — Концепция skill = SKILL.md workflow для coding harness
13. [docs.nanoclaw.dev/guides/customize-an-agent](https://docs.nanoclaw.dev/guides/customize-an-agent) — `instructions.prepend.md` vs composed `CLAUDE.md`
14. [docs.nanoclaw.dev/migrate-from-v1](https://docs.nanoclaw.dev/migrate-from-v1) — v2 Docker-only; миграция из OpenClaw (`IDENTITY.md`/`SOUL.md` → `instructions.prepend.md`)
15. [src/cli/client.ts](https://github.com/nanocoai/nanoclaw/blob/main/src/cli/client.ts) — `--json` только для admin-ответов; нет agent-invocation flags
16. [scripts/chat.ts](https://github.com/nanocoai/nanoclaw/blob/main/scripts/chat.ts) — one-shot plain-text чат, 120s hard timeout
17. [awesome-cli-coding-agents — секция OpenClaw ecosystem](https://github.com/bradAGI/awesome-cli-coding-agents) — контекст позиционирования
18. [5 Best OpenClaw Alternatives in 2026 (Safer & Lighter)](https://www.shareuhack.com/en/posts/openclaw-alternatives-guide) — обзорная статья
19. [OpenClaw comparison (наш ресерч)](openclaw-agent-comparison.md) — эталон сравнения (❌ 4/10, К6 блокер)
20. [omp comparison (наш ресерч)](omp-comparison.md) — эталон 10/10 (Pi-совместимый CLI-surface)
