# Coding Agents — Сводная таблица сравнения

**Дата создания:** 2026-05-09
**Дата обновления:** 2026-05-10
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

| # | Критерий | Pi Coding Agent | Codex CLI | Gemini CLI | Claude Code | OpenCode CLI | Kilo Code CLI | Qwen Code |
|---|----------|-----------------|-----------|-------------|-------------|--------------|---------------|-----------|
| 1 | Системный промпт (замена/дополнение) | ✅ Полная замена + дополнение | ⚠️ Полная замена через файл (нет append) | ⚠️ Полная замена через env (нет append) | ✅ Полная замена + дополнение | ✅ Агенты `.opencode/agent/*.md` + `instructions[]` | ⚠️ Агенты `.kilo/agent/*.md` (нет CLI-флагов) | ✅ `--system-prompt` + `--append-system-prompt` |
| 2 | Промпт агента / роль | ✅ Через --append-system-prompt | ⚠️ Через user prompt или profiles | ⚠️ Через user prompt (нет append) | ✅ Через --append-system-prompt | ✅ `--agent <name>`, изоляция tools/permissions | ⚠️ `--agent <name>`, файл агента обязателен, нет CLI-инъекции | ✅ Через `--append-system-prompt` |
| 3 | Скиллы | ✅ Agent Skills standard, автосканирование | ⚠️ Глобальные скиллы, нет CLI-фильтрации | ⚠️ Agent Skills standard, нет CLI-фильтрации | ⚠️ Agent Skills standard, нет CLI-управления | ⚠️ Автосканирование, нет назначения скиллов агентам | ⚠️ Agent Skills standard, автосканирование, нет CLI-управления | ⚠️ Автосканирование `.agents/skills/` + `.qwen/skills/`, нет CLI-управления |
| 4 | AGENTS.md | ✅ Автообнаружение AGENTS.md + CLAUDE.md | ✅ Автообнаружение AGENTS.md | ⚠️ GEMINI.md из коробки, AGENTS.md через settings | ❌ Только CLAUDE.md, AGENTS.md не поддерживается | ✅ Автообнаружение AGENTS.md + CLAUDE.md | ✅ Автообнаружение AGENTS.md + CLAUDE.md + instructions[] | ✅ QWEN.md + AGENTS.md из коробки |
| 5 | `.agents/skills/` автосканирование | ✅ Из коробки | ❌ Не поддерживается | ✅ Из коробки | ❌ Своя структура `.claude/skills/` | ✅ Из коробки (`.claude/` + `.agents/`) | ✅ Из коробки (`.claude/` + `.agents/` + `.kilo/skill/`) | ✅ Из коробки (`.qwen/` + `.agents/`) |
| 6 | Запуск как сабагент (JSON) | ✅ --mode json, --no-session | ⚠️ --json, --ephemeral (бедная телеметрия) | ✅ -o stream-json, --yolo, богатая телеметрия | ✅ --print --output-format json, guard rails | ⚠️ `--format json`, нет ephemeral, бедные события | ⚠️ `kilo run --format json --auto`, ACP-протокол, слабая документация | ✅ `--output-format stream-json`, `--yolo`, JSONL-телеметрия |
| 7 | Токены и стоимость | ✅ Полная телеметрия в JSONL | ⚠️ TUI status line, JSONL — не подтверждено | ✅ Полная телеметрия (токены, latency, файлы) | ✅ Полная телеметрия + по-модельная разбивка | ✅ Полная телеметрия + `opencode stats` CLI | ✅ Полная телеметрия + `kilo stats` CLI | ✅ Токены в JSON/JSONL, `/stats`, нет стоимости в $ |
| 8 | Free tier | ✅ MIT, стоимость зависит от LLM-провайдера | ⚠️ Apache-2.0, OpenAI требует подписку | ✅ 60 RPM / 1000 запросов/день, Google OAuth | ❌ $20+/мес (Pro) или API pay-as-you-go | ✅ MIT, 5 бесплатных моделей OpenCode Zen | ✅ MIT, Kilo Credits бесплатно, BYOK, Ollama | ⚠️ Apache-2.0, нативный free tier прекращён, BYOK, Ollama/vLLM |
| 9 | Провайдеры и модели | ✅ 20+ провайдеров, BYOK, Ollama, LM Studio | ⚠️ OpenAI + OSS (Ollama, LM Studio) + BYOK | ❌ Только Google Gemini | ⚠️ Только Anthropic (Claude), Bedrock/Vertex/Azure | ✅ 75+ провайдеров, BYOK, OpenCode Zen | ⚠️ 4 AI SDK + Kilo Cloud (500+), BYOK, Ollama | ✅ 3 протокола (OpenAI, Anthropic, Gemini), BYOK, Ollama/vLLM/LM Studio |
| 10 | Лицензия | ✅ MIT | ✅ Apache-2.0 | ✅ Apache-2.0 | ❌ Проприетарная | ✅ MIT | ✅ MIT | ✅ Apache-2.0 |
| | **Вердикт** | **✅ Подходит (10/10)** | **⚠️ Частично подходит (6/10)** | **⚠️ Частично подходит (6/10)** | **⚠️ Частично подходит (7/10)** | **⚠️ Частично подходит (7/10)** | **⚠️ Частично подходит (6/10)** | **✅ Подходит (8/10)** |

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
