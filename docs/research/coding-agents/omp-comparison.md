# omp (Oh My Pi) — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-07-24
**Объект:** omp v17.1.1 (`@oh-my-pi/pi-coding-agent`, `github.com/can1357/oh-my-pi`, TypeScript + Bun + Rust)
**Задача:** [TASK-research-omp-coding-agent](../../../todo/done/TASK-research-omp-coding-agent.todo.md)

---

## Сводка

omp (Oh My Pi) — CLI-агент кодинга (инструмент командной строки для AI-разработки) от Can Bölük (`can1357`), форк Pi (`badlogic/pi-mono`) от Mario Zechner. Пакет npm (реестр пакетов Node.js) — `@oh-my-pi/pi-coding-agent` v17.1.1, лицензия MIT, бинарник `omp`, runtime (среда выполнения) Bun `>=1.3.14`. На момент проверки GitHub показывает ~19.5k stars (звёзд) и ~1.8k forks (форков), npm downloads (загрузки) за последнюю неделю — ~67k.

**Архитектура:** TypeScript monorepo (монорепозиторий) с Bun workspaces (рабочие области Bun) + Rust-crates (пакеты Rust): `pi-natives`, `pi-shell`, `pi-ast`, `pi-iso`, `pi-walker`, `pi-uu-grep`, `pi-uu-diff`, `pi-uutils-ctx`. По README, в Rust-core около 55k строк; он закрывает hot path (частый путь выполнения) для grep/search/shell/AST без постоянного `fork/exec` (создания внешних процессов).

**Ключевой вывод:** omp — фактическое надмножество Pi по возможностям: сохраняет ключевой CLI-surface (интерфейс командной строки) для системного промпта и JSON/RPC-режимов, добавляет LSP (Language Server Protocol), DAP (Debug Adapter Protocol), нативные subagents (сабагенты) через `task`, hindsight memory (память по прошлым сессиям), hashline edits (правки с hash-якорями), time-traveling stream rules (правила, инжектируемые при отклонении генерации), advisor-модель (пассивный ревьюер), ACP (Agent Client Protocol), 40+ провайдеров и импорт контекста/правил из множества экосистем.

**Важная оговорка по совместимости:** в v17.1.1 первоисточники и пакет подтверждают `--system-prompt`, `--append-system-prompt`, `--mode json`, `--mode rpc`, `--no-session`, `--no-skills`, но **не подтверждают Pi-флаг `--skill <path>` и `--no-context-files`**. Вместо этого есть `--skills <glob[,glob]>` как фильтр скиллов, `skills.customDirectories` в config (конфигурации) и отключение discovery providers (поставщиков обнаружения) через `disabledProviders`. Поэтому перед production-заменой `pi` → `omp` нужно прогнать smoke test (быструю проверку) нашего wrapper (обёртки), особенно если он передаёт явные пути скиллов.

---

## Критерий 1. Системный промпт

### Возможности

| Механизм | Поддержка | Поведение |
|----------|-----------|-----------|
| `--system-prompt <text-or-file>` | ✅ | Заменяет stable default instructions (базовые инструкции), но сохраняет dynamic project footer (динамический контекст проекта). |
| `--append-system-prompt <text-or-file>` | ✅ | Добавляет блок к промпту; если custom system prompt (пользовательский системный промпт) задан, добавляется после него и до footer. |
| `.omp/SYSTEM.md`, `~/.omp/agent/SYSTEM.md` | ✅ | Файловая замена системного промпта. |
| `.omp/APPEND_SYSTEM.md`, `~/.omp/agent/APPEND_SYSTEM.md` | ✅ | Файловое дополнение к промпту. |
| Аналоги в `.claude`, `.codex`, `.gemini` | ✅ | Поддержка сторонних форматов через discovery (обнаружение). |
| Time-traveling stream rules | ✅ | Regex-trigger (регулярное выражение-триггер) прерывает stream (поток), инжектирует rule (правило) как system reminder (системное напоминание) и ретраит с той же точки. |

### Примеры CLI

```bash
# Замена стабильного системного промпта
omp --system-prompt "Ты — экспертный AI-ассистент." "Опиши архитектуру проекта"

# Дополнение к системному промпту
omp --append-system-prompt "Следуй стандартам PSR-12." "Отрефактори код"

# Сценарий роли task-orchestrator
omp --mode json --no-session \
  --system-prompt "scripts/subagent_system.txt" \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/backend_developer_levsha.ru.md" \
  "Выполни задачу"
```

### Оценка: ✅ Полная поддержка

omp покрывает оба ключевых механизма Pi (`--system-prompt`, `--append-system-prompt`) и добавляет rule-инжекции на уровне stream (потока), что полезно для enforcement (принудительного соблюдения) проектных правил без постоянного context tax (затрат контекста).

---

## Критерий 2. Промпт агента / Роль

### Механизмы

| Механизм | Назначение | Релевантность для ролей |
|----------|------------|-------------------------|
| `--append-system-prompt` | Инъекция роли как дополнительной системной инструкции | ✅ Совместимо с нашим подходом «прочитай файл роли». |
| Model roles | Роутинг моделей по intent (намерению): `default`, `smol`, `slow`, `plan`, `commit` | ✅ Можно назначать дешёвые/сильные модели под тип задачи. |
| Advisor role | Отдельная модель пассивно ревьюит каждый turn (ход) и вставляет aside/concern/blocker | ✅ Похоже на встроенный quality gate (контроль качества). |
| `task` subagents | Изолированные worker (исполнители), schema-validated output (вывод по схеме), worktree-изоляция | ✅ Сильная нативная альтернатива внешним сабагентам. |
| Agent files | Встроенные агенты и пользовательские agent-spec (описания агентов) | ✅ Можно описывать специализированные роли внутри omp. |

### Пример роли

```bash
omp --mode json --no-session \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/code_reviewer_backend_puaro.ru.md" \
  "Проведи ревью изменений"
```

### Оценка: ✅ Полная поддержка

Для нашей системы ролей omp не хуже Pi: роль можно инжектировать через `--append-system-prompt`. Сверх этого доступны model roles, advisor и нативные subagents.

---

## Критерий 3. Скиллы

### Поддержка Agent Skills standard

omp поддерживает skill (скилл) как директорию `<skills-root>/<skill-name>/SKILL.md` с frontmatter (метаданными). На старте загружается лёгкая metadata (name + description), тело доступно on-demand (по требованию) через `read skill://<name>`; в interactive mode (интерактивном режиме) доступны `/skill:<name>` команды.

| Механизм | Поддержка | Комментарий |
|----------|-----------|-------------|
| `SKILL.md` layout | ✅ | Нерекурсивное сканирование `skills/*/SKILL.md`. |
| `.agents/skills/` | ✅ | Провайдер `agents` — каноничная OMP-native локация. |
| `.omp/skills/` | ✅ | Native provider (родной поставщик) с highest priority (высшим приоритетом). |
| `.claude/skills/`, `.codex/skills/`, `.github/skills/` и др. | ✅ | Импорт сторонних форматов. |
| `skill://<name>` | ✅ | Чтение `SKILL.md` или вложенных файлов с защитой от path traversal (выхода из директории). |
| `--no-skills` | ✅ | Отключает discovery/load (обнаружение/загрузку) скиллов. |
| `--skills <glob[,glob]>` | ✅ | Фильтр по glob patterns (шаблонам), например `git-*,docker`. |
| `--skill <path>` | ⚠️ Не подтверждён в v17.1.1 | В пакете v17.1.1 есть `--skills`, но не singular `--skill`; явные директории подключаются через `skills.customDirectories` в config или symlink. |

### Подключение наших скиллов

```bash
# Вариант 1: symlink (символическая ссылка)
ln -s docs/agents/skills .agents/skills

# Вариант 2: config overlay (конфигурационный оверлей)
omp --config 'skills.customDirectories=["docs/agents/skills"]' "Выполни задачу"

# Вариант 3: фильтр уже обнаруженных скиллов
omp --skills "agent-report,run-*" "Выполни анализ"
```

### Оценка: ✅ Полная поддержка с оговоркой по CLI-флагу

Функционально omp покрывает Agent Skills standard шире Pi за счёт `skill://` и сторонних providers (поставщиков), но точный Pi-флаг `--skill <path>` в v17.1.1 не подтверждён. Для task-orchestrator это не блокер: используем `.agents/skills` symlink или `skills.customDirectories`.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Discovery (обнаружение)

omp автоматически загружает context files (контекстные файлы) разных экосистем. По документации `context-files.md`, standalone (отдельный) `AGENTS.md` обрабатывается provider (поставщиком) `agents-md` и обнаруживается walk-up (подъёмом по директориям) от `cwd` до git root (корня репозитория) или home (домашней директории), если git root неизвестен.

| Provider | Файлы | Scope (область) |
|----------|-------|-----------------|
| `native` | `.omp/AGENTS.md`, `~/.omp/agent/AGENTS.md` | user + project, высший приоритет |
| `agents-md` | `AGENTS.md` | project walk-up |
| `agents` | `.agent/AGENTS.md`, `.agents/AGENTS.md` | user + project |
| `claude` | `.claude/CLAUDE.md` | user + project |
| `gemini` | `.gemini/GEMINI.md` | user + project |
| `github` | `.github/copilot-instructions.md`, `.github/instructions/**/*.instructions.md` | context + rules |
| `codex` | `~/.codex/AGENTS.md` | user |
| `opencode` | `~/.config/opencode/AGENTS.md` | user |

### Отключение

`--no-context-files` в v17.1.1 не найден в CLI args (аргументах командной строки). Отключение делается через `disabledProviders` в `~/.omp/agent/config.yml`, `<project>/.omp/config.yml` или `--config` overlay:

```yaml
disabledProviders:
  - agents-md
  - claude
  - github
```

### Оценка: ✅ Полная поддержка

AGENTS.md для нашего проекта будет подхвачен автоматически. Отсутствие короткого `--no-context-files` — operational caveat (эксплуатационная оговорка), но не снижает поддержку самого критерия: discovery богаче, чем у Pi.

---

## Критерий 5. `.agents/skills/` автосканирование

| Локация | Поддержка | Примечание |
|---------|-----------|------------|
| `.agents/skills/<name>/SKILL.md` | ✅ | Project-level provider `agents`. |
| `~/.agents/skills/<name>/SKILL.md` | ✅ | User-level provider `agents`. |
| `.omp/skills/<name>/SKILL.md` | ✅ | Native project-level, priority 100. |
| `~/.omp/agent/skills/<name>/SKILL.md` | ✅ | Native user-level. |
| `.claude/skills/`, `.codex/skills/`, `.github/skills/` | ✅ | Совместимость с внешними агентами. |
| `skills.customDirectories` | ✅ | Добавляет кастомные директории; сканирование нерекурсивное. |

### Наша структура

Наши скиллы лежат в `docs/agents/skills/`, поэтому прямого автосканирования без настройки нет. Практически оптимальный вариант — symlink `.agents/skills -> docs/agents/skills` или config `skills.customDirectories`.

### Оценка: ✅ Полная поддержка

`.agents/skills/` поддерживается из коробки; подключение нашей нестандартной директории требует минимальной настройки, как и для большинства агентов.

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### Режимы запуска

| Режим | Команда | Назначение |
|-------|---------|------------|
| Interactive TUI | `omp` | Интерактивный терминальный интерфейс. |
| One-shot | `omp -p` / `omp --print` | Один промпт и выход. |
| JSONL | `omp --mode json` | Поток structured events (структурированных событий). |
| RPC | `omp --mode rpc` | JSONL commands (команды) in, response/event frames out. |
| RPC-UI | `omp --mode rpc-ui` | RPC + UI frames (`extension_ui_request`). |
| ACP | `omp acp` или `--mode acp` | Agent Client Protocol для редакторов. |
| SDK | `@oh-my-pi/pi-coding-agent` | Embedding (встраивание) в Node/TypeScript-код. |

### Пример JSONL для нашего wrapper

```bash
omp --mode json --no-session \
  --append-system-prompt "Возьми на себя роль из файла: docs/agents/roles/team/system_analyst_sherlock.ru.md" \
  "Составь техническое задание" \
  2>/dev/null | jq -c 'select(.type == "message_end" or .type == "agent_end")'
```

### Нативные subagents

Инструмент `task` умеет fan-out (распараллеливание) в isolated worktrees (изолированные рабочие деревья), выдаёт typed results (типизированные результаты) и позволяет читать output через `agent://<id>/...`. RPC-mode также имеет subagent frames (`subagent_lifecycle`, `subagent_progress`, `subagent_event`).

### Оценка: ✅ Полная поддержка

omp покрывает Pi-сценарий `--mode json --no-session` и добавляет RPC/RPC-UI/ACP/SDK. Это лучший уровень интеграционных surface (поверхностей интеграции) среди исследованных агентов.

---

## Критерий 7. Токены и стоимость

### Метрики

В session entries (записях сессии) и UI используются usage statistics (статистика использования):

```typescript
interface UsageStatistics {
  input: number
  output: number
  cacheRead: number
  cacheWrite: number
  totalTokens: number
  orchestrationInput: number
  orchestrationOutput: number
  orchestrationCacheRead: number
  premiumRequests: number
  cost: number
}
```

Дополнительно подтверждены:

| Механизм | Назначение |
|----------|------------|
| `omp stats` | Usage dashboard (панель статистики) через пакет `@oh-my-pi/omp-stats`. |
| `omp usage` | Provider usage reports (отчёты лимитов провайдера) для authenticated accounts (аутентифицированных аккаунтов). |
| Status line | Показывает input/output/cache/cost/context. |
| OTLP-зависимости | В пакете есть OpenTelemetry exporters (экспортёры трассировок/метрик/логов). |
| Native token counting | README описывает in-process BPE counting (локальный подсчёт токенов). |

### Оценка: ✅ Полная поддержка

Уровень телеметрии не хуже Pi: есть токены, cache read/write и cost (стоимость), плюс отдельные `stats`/`usage` surface.

---

## Критерий 8. Free tier / стоимость

omp как инструмент бесплатен: open source (открытый код), MIT. Стоимость определяется выбранным LLM provider (поставщиком модели), а не самим CLI.

| Категория | Примеры | Бесплатность |
|-----------|---------|--------------|
| Local | Ollama, LM Studio, llama.cpp, vLLM, LiteLLM | ✅ Бесплатно при наличии локального железа. |
| BYOK APIs | Anthropic, OpenAI, Google Gemini, xAI, Mistral, Groq, Cerebras, Fireworks, Together, Hugging Face, NVIDIA, OpenRouter и др. | ⚠️ По тарифу провайдера; часть имеет free tier. |
| OAuth / coding plans | Anthropic, OpenAI Codex, GitHub Copilot, Cursor, Qwen Portal, Z.AI/GLM, Kimi Code и др. | ⚠️ Зависит от подписки/квот аккаунта. |
| Custom providers | Любой совместимый backend через `models.yml` | ✅/⚠️ Зависит от backend. |

### Оценка: ✅ Полная поддержка

Бесплатный инструмент + локальные модели + BYOK + coding plans дают максимальную гибкость по стоимости.

---

## Критерий 9. Провайдеры и модели

README заявляет 40+ providers (провайдеров), docs/providers.md описывает bundled catalog (встроенный каталог), custom providers (кастомные провайдеры), OAuth/API-key auth (аутентификацию) и local engines (локальные движки).

### Поддерживаемые категории

| Категория | Примеры |
|-----------|---------|
| Direct APIs / gateways | Anthropic, OpenAI, OpenAI Codex, Google Gemini, Google Antigravity, xAI, Mistral, Groq, Cerebras, Fireworks, Together, Hugging Face, NVIDIA, OpenRouter, Synthetic, Vercel AI Gateway, Cloudflare AI Gateway, Wafer Serverless, Perplexity |
| Coding plans | Cursor, GitHub Copilot, GitLab Duo, Kimi Code, Moonshot, MiniMax, Alibaba, Qwen Portal, Z.AI/GLM, Xiaomi MiMo, Qianfan, NanoGPT, Novita, Venice, Kilo, ZenMux, OpenCode Go/Zen |
| Local | Ollama, Ollama Cloud, LM Studio, llama.cpp, vLLM, LiteLLM |
| Custom | 7 API protocols: `openai-completions`, `openai-responses`, `openai-codex-responses`, `azure-openai-responses`, `anthropic-messages`, `google-generative-ai`, `google-vertex` |

### Пример custom provider

```yaml
# ~/.omp/agent/models.yml
providers:
  local-gateway:
    baseUrl: http://127.0.0.1:8000/v1
    api: openai-completions
    auth: none
    models:
      - id: qwen-coder
        name: Qwen Coder Local
        contextWindow: 128000
        maxTokens: 32000
```

### Оценка: ✅ Полная поддержка

По breadth (широте) omp превосходит Pi: 40+ providers, local engines, custom protocols, provider disabling, path-scoped controls и round-robin credentials (ротация ключей с привязкой к сессии).

---

## Критерий 10. Лицензия

| Параметр | Значение |
|----------|----------|
| License (лицензия) | MIT |
| Source (исходный код) | ✅ Open source: TypeScript + Rust |
| Fork (форк) | ✅ Форк Pi (`badlogic/pi-mono`) |
| Commercial use (коммерческое использование) | ✅ Разрешено MIT |
| Modification/distribution (модификация/распространение) | ✅ Разрешено MIT |
| Vendor lock-in (зависимость от вендора) | ❌ Нет обязательного cloud backend; зависит только от выбранного provider. |

### Оценка: ✅ Полная поддержка

MIT — максимально permissive (разрешительная) лицензия; для интеграции в task-orchestrator юридических блокеров не видно.

---

## Итоговая таблица

| # | Критерий | Оценка | Обоснование |
|---|----------|--------|-------------|
| К1 | Системный промпт | ✅ (3) | `--system-prompt`, `--append-system-prompt`, файлы, TTSR. |
| К2 | Роль | ✅ (3) | Инъекция через prompt + model roles + advisor + subagents. |
| К3 | Скиллы | ✅ (3) | Agent Skills, `.agents/skills`, `skill://`, фильтр `--skills`; оговорка: нет подтверждённого `--skill <path>`. |
| К4 | AGENTS.md | ✅ (3) | Автодискавери standalone `AGENTS.md` + сторонние форматы; отключение через config. |
| К5 | `.agents/skills/` | ✅ (3) | Поддерживается из коробки; наша `docs/agents/skills` через symlink/config. |
| К6 | JSON/subagent | ✅ (3) | `--mode json`, RPC, RPC-UI, ACP, SDK, `task` subagents. |
| К7 | Токены/стоимость | ✅ (3) | UsageStatistics, `cost`, `omp stats`, `omp usage`, status line. |
| К8 | Free tier | ✅ (3) | MIT, local models, BYOK, coding plans. |
| К9 | Провайдеры/модели | ✅ (3) | 40+ providers, custom protocols, local, OpenRouter. |
| К10 | Лицензия | ✅ (3) | MIT, open source, нет обязательного cloud lock-in. |
| | **Сумма** | **30/30** | |

---

## Вердикт

### ✅ Подходит (10/10)

**Сводный ранг:** #1 среди 18 исследований.

omp — лучший кандидат на upgrade (обновление) текущего Pi для task-orchestrator: он покрывает критичные требования сабагентной интеграции (`--mode json`, `--no-session`, системный prompt injection (инъекция промпта), AGENTS.md, Agent Skills) и даёт существенные дополнительные возможности.

### omp vs Pi — что добавлено can1357

| Категория | Pi | omp |
|-----------|----|-----|
| Движок | TypeScript/Node.js | Rust-core + TypeScript/Bun, in-process grep/shell/AST |
| Code intelligence | Ограничено | LSP 14 ops, DAP 28 ops |
| Subagents | Внешняя оркестрация | First-class `task`, worktree-изоляция, schema output |
| Memory | Нет/ограниченно | `pi-mnemopi`, local SQLite memory, `memory://` |
| Edits | `str_replace`/diff | `hashline`, AST-aware tools |
| Rules | Static context | Time-traveling stream rules, rulebook, `rule://` |
| Review | Внешний ревью | Advisor model + `/review` subagents |
| Интеграция | JSON/RPC | JSON, RPC, RPC-UI, ACP, SDK |
| Providers | 20+ | 40+ + coding plans + local + custom protocols |
| Observability | JSON usage | `omp stats`, `omp usage`, OpenTelemetry dependencies |
| Ecosystem import | AGENTS/CLAUDE | `.omp`, `.agents`, `.claude`, `.codex`, `.gemini`, `.github`, opencode и др. |

### Риски и проверки перед заменой

| Риск | Почему важен | Митигировать |
|------|--------------|--------------|
| `--skill <path>` не подтверждён | Pi-wrapper может передавать singular flag | Smoke test wrapper; перейти на symlink/config/`--skills` filter. |
| `--no-context-files` не подтверждён | Для изолированных запусков иногда нужно отключать context files | Использовать `disabledProviders` config overlay. |
| Быстрое развитие v17.x | Возможен drift (расхождение) JSONL event schema | Зафиксировать версию и добавить contract test (контрактный тест) JSONL. |
| Memory/auto-learn | Может сохранять нежелательный контекст | Для CI/headless запусков явно держать `memory.backend=off`, если память не нужна. |

---

## Рекомендации

1. **P0 — Smoke test drop-in:** запустить `omp --mode json --no-session --append-system-prompt ...` на типовой роли и сравнить event types (типы событий) с Pi-парсером.
2. **P0 — Проверить skills-path:** если наш wrapper использует Pi `--skill <path>`, заменить на `.agents/skills` symlink или `skills.customDirectories` overlay.
3. **P1 — Зафиксировать версию:** использовать v17.1.1 или совместимую pin (фиксацию версии) до появления contract tests.
4. **P1 — Бенчмарк на репозитории:** измерить grep/search/read/edit на task-orchestrator против Pi.
5. **P2 — Изучить `task` subagents:** оценить как альтернативу внешнему `watch-subagent.sh` для параллельных research/review задач.
6. **P3 — Изучить TTSR/advisor:** использовать для enforcement (соблюдения) AGENTS.md/Конвенций и quality gates.

---

## Источники

1. [omp.sh — официальный сайт](https://omp.sh/) — features, providers, entry points, Rust-core.
2. [can1357/oh-my-pi — GitHub](https://github.com/can1357/oh-my-pi) — README, AGENTS.md, структура monorepo, Rust-crates.
3. [@oh-my-pi/pi-coding-agent — npm](https://www.npmjs.com/package/@oh-my-pi/pi-coding-agent) — v17.1.1, license, bin, dependencies.
4. [docs/system-prompt-customization.md](https://github.com/can1357/oh-my-pi/blob/main/docs/system-prompt-customization.md) — `--system-prompt`, `--append-system-prompt`.
5. [docs/skills.md](https://github.com/can1357/oh-my-pi/blob/main/docs/skills.md), [docs/context-files.md](https://github.com/can1357/oh-my-pi/blob/main/docs/context-files.md), [docs/providers.md](https://github.com/can1357/oh-my-pi/blob/main/docs/providers.md), [docs/rpc.md](https://github.com/can1357/oh-my-pi/blob/main/docs/rpc.md) — детали runtime (исполнения), skills/context/providers/RPC.
