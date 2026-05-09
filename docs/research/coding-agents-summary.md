# Coding Agents — Сводная таблица сравнения

**Дата создания:** 2026-05-09
**Дата обновления:** 2026-05-10 (добавлены OpenClaw, Copilot CLI)
**Эпик:** [EPIC-research-coding-agents-comparison](../../todo/EPIC-research-coding-agents-comparison.todo.md)

---

## Легенда оценок

| Символ | Значение |
|--------|----------|
| ✅ | Полная поддержка |
| ⚠️ | Частичная поддержка |
| ❌ | Не поддерживается |
| — | Не исследовано |

---

## Сводная таблица

| # | Критерий | Pi Coding Agent | Codex CLI | Gemini CLI | Claude Code | OpenCode CLI | Kilo Code CLI | Qwen Code | Goose | Crush (Charmbracelet) | Copilot CLI | OpenClaw |
|---|----------|-----------------|-----------|-------------|-------------|--------------|---------------|-----------|-------|
| 1 | Системный промпт (замена/дополнение) | ✅ Полная замена + дополнение | ⚠️ Полная замена через файл (нет append) | ⚠️ Полная замена через env (нет append) | ✅ Полная замена + дополнение | ✅ Агенты `.opencode/agent/*.md` + `instructions[]` | ⚠️ Агенты `.kilo/agent/*.md` (нет CLI-флагов) | ✅ `--system-prompt` + `--append-system-prompt` | ⚠️ Дополнение `--system`, замена только через config | ⚠️ Только `context_paths` в конфиге, нет CLI-флагов | ⚠️ Только `copilot-instructions.md`, нет CLI-флагов | ⚠️ Только workspace-файлы (SOUL.md, AGENTS.md), нет CLI-флагов |
| 2 | Промпт агента / роль | ✅ Через --append-system-prompt | ⚠️ Через user prompt или profiles | ⚠️ Через user prompt (нет append) | ✅ Через --append-system-prompt | ✅ `--agent <name>`, изоляция tools/permissions | ⚠️ `--agent <name>`, файл агента обязателен, нет CLI-инъекции | ✅ Через `--append-system-prompt` | ⚠️ Через `--system` или рецепты (YAML) | ⚠️ Через `context_paths` или инструкцию модели прочитать файл | ⚠️ Через `-p` user prompt, нет профилей/append | ⚠️ Multi-agent routing (agents.list[]), нет CLI-инъекции |
| 3 | Скиллы | ✅ Agent Skills standard, автосканирование | ⚠️ Глобальные скиллы, нет CLI-фильтрации | ⚠️ Agent Skills standard, нет CLI-фильтрации | ⚠️ Agent Skills standard, нет CLI-управления | ⚠️ Автосканирование, нет назначения скиллов агентам | ⚠️ Agent Skills standard, автосканирование, нет CLI-управления | ⚠️ Автосканирование `.agents/skills/` + `.qwen/skills/`, нет CLI-управления | ⚠️ Agent Skills standard, автосканирование, нет CLI-управления, плагины | ⚠️ Agent Skills standard, lazy loading, только конфиг (нет CLI `--skill`) | ❌ Нет системы скиллов, только `--allow-tool` | ⚠️ Agent Skills standard, автосканирование, per-agent allowlist, нет CLI-управления |
| 4 | AGENTS.md | ✅ Автообнаружение AGENTS.md + CLAUDE.md | ✅ Автообнаружение AGENTS.md | ⚠️ GEMINI.md из коробки, AGENTS.md через settings | ❌ Только CLAUDE.md, AGENTS.md не поддерживается | ✅ Автообнаружение AGENTS.md + CLAUDE.md | ✅ Автообнаружение AGENTS.md + CLAUDE.md + instructions[] | ✅ QWEN.md + AGENTS.md из коробки | ✅ AGENTS.md + `.goosehints` + subdirectory tracking | ✅ AGENTS.md + CRUSH.md + CLAUDE.md + GEMINI.md + .cursorrules | ⚠️ Только `copilot-instructions.md`, AGENTS.md не поддерживается | ⚠️ AGENTS.md из workspace (не из CWD), 6 bootstrap-файлов |
| 5 | `.agents/skills/` автосканирование | ✅ Из коробки | ❌ Не поддерживается | ✅ Из коробки | ❌ Своя структура `.claude/skills/` | ✅ Из коробки (`.claude/` + `.agents/`) | ✅ Из коробки (`.claude/` + `.agents/` + `.kilo/skill/`) | ✅ Из коробки (`.qwen/` + `.agents/`) | ✅ Из коробки (`.agents/` + `.goose/` + `.claude/`) | ✅ Из коробки (`.agents/` + `.crush/` + `.claude/` + `.cursor/`) | ❌ Не поддерживается | ✅ Из коробки (workspace + `~/.agents/skills` + `extraDirs`) |
| 6 | Запуск как сабагент (JSON) | ✅ --mode json, --no-session | ⚠️ --json, --ephemeral (бедная телеметрия) | ✅ -o stream-json, --yolo, богатая телеметрия | ✅ --print --output-format json, guard rails | ⚠️ `--format json`, нет ephemeral, бедные события | ⚠️ `kilo run --format json --auto`, ACP-протокол, слабая документация | ✅ `--output-format stream-json`, `--yolo`, JSONL-телеметрия | ✅ `--output-format stream-json/json`, `--no-session`, ACP-протокол | ⚠️ `crush run` plain text, Server API (HTTP/socket), нет JSONL, нет ephemeral | ❌ Только `-p` plain text, нет JSON/JSONL, нет ephemeral | ❌ Gateway daemon, нет JSON/JSONL, нет ephemeral, нет автономного CLI |
| 7 | Токены и стоимость | ✅ Полная телеметрия в JSONL | ⚠️ TUI status line, JSONL — не подтверждено | ✅ Полная телеметрия (токены, latency, файлы) | ✅ Полная телеметрия + по-модельная разбивка | ✅ Полная телеметрия + `opencode stats` CLI | ✅ Полная телеметрия + `kilo stats` CLI | ✅ Токены в JSON/JSONL, `/stats`, нет стоимости в $ | ⚠️ Токены отслеживаются, нет расчёта стоимости в $ | ⚠️ Per-session cost tracking, `crush stats` (HTML), нет вывода в stdout при `run` | ❌ Нет телеметрии в CLI, только GitHub UI | ⚠️ Телеметрия внутри gateway (Pi runtime), `/usage` в чате, нет CLI-вывода |
| 8 | Free tier | ✅ MIT, стоимость зависит от LLM-провайдера | ⚠️ Apache-2.0, OpenAI требует подписку | ✅ 60 RPM / 1000 запросов/день, Google OAuth | ❌ $20+/мес (Pro) или API pay-as-you-go | ✅ MIT, 5 бесплатных моделей OpenCode Zen | ✅ MIT, Kilo Credits бесплатно, BYOK, Ollama | ⚠️ Apache-2.0, нативный free tier прекращён, BYOK, Ollama/vLLM | ✅ Apache-2.0, BYOK, Ollama, LM Studio, Gemini free tier | ✅ FSL-1.1-MIT, стоимость зависит от LLM-провайдера, BYOK, Ollama | ⚠️ Проприетарный, Copilot Free (2000 completions + 50 chat/мес), нет BYOK | ✅ MIT, стоимость зависит от LLM-провайдера |
| 9 | Провайдеры и модели | ✅ 20+ провайдеров, BYOK, Ollama, LM Studio | ⚠️ OpenAI + OSS (Ollama, LM Studio) + BYOK | ❌ Только Google Gemini | ⚠️ Только Anthropic (Claude), Bedrock/Vertex/Azure | ✅ 75+ провайдеров, BYOK, OpenCode Zen | ⚠️ 4 AI SDK + Kilo Cloud (500+), BYOK, Ollama | ✅ 3 протокола (OpenAI, Anthropic, Gemini), BYOK, Ollama/vLLM/LM Studio | ✅ 30+ провайдеров, BYOK, Ollama, LM Studio, declarative providers | ✅ 20+ провайдеров, BYOK, Ollama, LM Studio, dual model (large+small) | ❌ Только GitHub (OpenAI + Claude), нет BYOK, нет локальных моделей | ✅ 40+ провайдеров (plugins), BYOK, Ollama, LM Studio, Codex app-server |
| 10 | Лицензия | ✅ MIT | ✅ Apache-2.0 | ✅ Apache-2.0 | ❌ Проприетарная | ✅ MIT | ✅ MIT | ✅ Apache-2.0 | ✅ Apache-2.0 | ⚠️ FSL-1.1-MIT (→ MIT через 2 года) | ❌ Проприетарная | ✅ MIT |
| | **Вердикт** | **✅ Подходит (10/10)** | **⚠️ Частично подходит (6/10)** | **⚠️ Частично подходит (6/10)** | **⚠️ Частично подходит (7/10)** | **⚠️ Частично подходит (7/10)** | **⚠️ Частично подходит (6/10)** | **✅ Подходит (8/10)** | **⚠️ Частично подходит (7/10)** | **⚠️ Частично подходит (6/10)** | **❌ Не подходит (3/10)** | **❌ Не подходит (4/10)** |

---

## Детальные отчёты

| Агент | Отчёт | Вердикт |
|-------|-------|---------|
| Pi Coding Agent | [pi-coding-agent-comparison.md](coding-agents/pi-coding-agent-comparison.md) | ✅ Подходит |
| Codex CLI | [codex-cli-comparison.md](coding-agents/codex-cli-comparison.md) | ⚠️ Частично подходит |
| Gemini CLI | [gemini-cli-comparison.md](coding-agents/gemini-cli-comparison.md) | ⚠️ Частично подходит |
| Claude Code | [claude-code-agent-comparison.md](coding-agents/claude-code-agent-comparison.md) | ⚠️ Частично подходит |
| OpenCode CLI | [opencode-cli-comparison.md](coding-agents/opencode-cli-comparison.md) | ⚠️ Частично подходит |
| Kilo Code CLI | [kilocode-cli-comparison.md](coding-agents/kilocode-cli-comparison.md) | ⚠️ Частично подходит |
| Qwen Code | [qwen-cli-comparison.md](coding-agents/qwen-cli-comparison.md) | ✅ Подходит |
| Goose | [goose-agent-comparison.md](coding-agents/goose-agent-comparison.md) | ⚠️ Частично подходит |
| Crush (Charmbracelet) | [crush-agent-comparison.md](coding-agents/crush-agent-comparison.md) | ⚠️ Частично подходит |
| GitHub Copilot CLI | [copilot-cli-comparison.md](coding-agents/copilot-cli-comparison.md) | ❌ Не подходит |
| OpenClaw | [openclaw-agent-comparison.md](coding-agents/openclaw-agent-comparison.md) | ❌ Не подходит (gateway, не CLI-агент) |
