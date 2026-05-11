# Coding Agents — Сводная таблица сравнения (финальная версия)

**Дата создания:** 2026-05-09
**Дата обновления:** 2026-05-11 (15 исследований, добавлен Oh My OpenAgent)
**Эпик:** [EPIC-research-coding-agents-comparison](../../todo/EPIC-research-coding-agents-comparison.todo.md)
**Автор:** Аналитик (Шерлок)

---

## Легенда оценок

| Символ | Значение | Балл |
|--------|----------|------|
| ✅ | Полная поддержка, без оговорок | 3 |
| ⚠️ | Частичная поддержка, есть ограничения | 2 |
| ❌ | Не поддерживается или критически ограничено | 1 |

---

## Часть 1. Ранжирование агентов по пригодности

Место определяется суммой баллов по 10 критериям (максимум 30).

| # | Агент | Лиц. | К1 | К2 | К3 | К4 | К5 | К6 | К7 | К8 | К9 | К10 | ∑ | Вердикт |
|---|-------|------|----|----|----|----|----|----|----|----|-----|-----|---|---------|
| 1 | **Pi Coding Agent** | MIT | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **30** | ✅ Подходит (10/10) |
| 2 | **Qwen Code** | A-2.0 | ✅ | ✅ | ⚠️ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ | **27** | ✅ Подходит (8/10) |
| 3 | **Claude Code** | Пропр. | ✅ | ✅ | ⚠️ | ⚠️ | ❌ | ✅ | ✅ | ❌ | ⚠️ | ❌ | **21** | ⚠️ Частично (7/10) |
| 4 | **Goose** | A-2.0 | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ | ✅ | **24** | ⚠️ Частично (7/10) |
| 5 | **OpenCode CLI** | MIT | ✅ | ✅ | ⚠️ | ✅ | ✅ | ⚠️ | ✅ | ✅ | ✅ | ✅ | **26** | ⚠️ Частично (7/10) |
| 6 | **Oh My OpenAgent** | SUL-1.0 | ✅ | ✅ | ⚠️ | ✅ | ✅ | ⚠️ | ✅ | ⚠️ | ✅ | ⚠️ | **25** | ⚠️ Частично (7/10) |
| 7 | **Hermes Agent** | MIT | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | **25** | ⚠️ Частично (7/10) |
| 8 | **Warp AI (Oz)** | AGPL | ⚠️ | ⚠️ | ✅ | ⚠️ | ✅ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | **20** | ⚠️ Частично (7/10) |
| 9 | **Codex CLI** | A-2.0 | ⚠️ | ⚠️ | ⚠️ | ✅ | ❌ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | **19** | ⚠️ Частично (6/10) |
| 10 | **Gemini CLI** | A-2.0 | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **22** | ⚠️ Частично (6/10) |
| 11 | **Kilo Code CLI** | MIT | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ⚠️ | ✅ | ✅ | ⚠️ | ✅ | **22** | ⚠️ Частично (6/10) |
| 12 | **Crush** | FSL | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ⚠️ | ⚠️ | ✅ | ✅ | ⚠️ | **20** | ⚠️ Частично (6/10) |
| 13 | **Factory Droid** | Пропр. | ⚠️ | ⚠️ | ⚠️ | ✅ | ⚠️ | ✅ | ⚠️ | ❌ | ⚠️ | ❌ | **17** | ⚠️ Частично (6/10) |
| 14 | **OpenClaw** | MIT | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | ❌ | ⚠️ | ✅ | ✅ | ✅ | **21** | ❌ Не подходит (4/10) |
| 15 | **GitHub Copilot CLI** | Пропр. | ⚠️ | ⚠️ | ❌ | ⚠️ | ❌ | ⚠️ | ❌ | ⚠️ | ❌ | ❌ | **12** | ❌ Не подходит (3/10) |

> **Примечание:** Вердикт включает качественную оценку (числовой score не всегда совпадает с суммой баллов — учитывается вес критериев для конкретного сценария сабагентной интеграции).

---

## Часть 2. Детальная сводная таблица по 10 критериям

### 2.1. Open Source агенты (11)

#### Критерий 1. Системный промпт (замена / дополнение)

| Агент | Замена через CLI | Дополнение через CLI | Замена через файл | Файловое дополнение |
|-------|------------------|---------------------|-------------------|---------------------|
| **Pi** | `--system-prompt <text>` ✅ | `--append-system-prompt <text>` ✅ | `.pi/SYSTEM.md` ✅ | `.pi/APPEND_SYSTEM.md` ✅ |
| **Qwen Code** | `--system-prompt <text>` ✅ | `--append-system-prompt <text>` ✅ | — | — |
| **OpenCode** | Агенты `.opencode/agent/*.md` ✅ | `instructions[]` в конфиге ✅ | — | — |
| **OmO** | `agents.<name>.prompt` с `file://` ✅ | `agents.<name>.prompt_append` с `file://` ✅ | — | — |
| **Hermes** | SOUL.md (файл) ⚠️ | `HERMES_EPHEMERAL_SYSTEM_PROMPT` env ⚠️ | `~/.hermes/SOUL.md` ✅ | — |
| **Goose** | Только через config.yaml ⚠️ | `--system <text>` ✅ | `GOOSE_SYSTEM_PROMPT_FILE_PATH` ⚠️ | — |
| **Codex CLI** | `model_instructions_file` в config ⚠️ | ❌ Нет | config.toml ✅ | — |
| **Gemini CLI** | `GEMINI_SYSTEM_MD` env ⚠️ | ❌ Нет | `.gemini/system.md` ⚠️ | — |
| **Kilo Code** | Агенты `.kilo/agent/*.md` ⚠️ | `instructions[]` в конфиге ⚠️ | — | — |
| **Crush** | ❌ Нет CLI | ❌ Нет CLI | `context_paths` в crush.json ⚠️ | — |
| **OpenClaw** | ❌ Нет CLI | ❌ Нет CLI | Workspace-файлы (SOUL.md) ⚠️ | — |
| **Warp Oz** | ❌ Нет CLI | ❌ Нет CLI | Config file / profile ⚠️ | — |

#### Критерий 2. Промпт агента / Роль

| Агент | Механизм инъекции роли | Изоляция ролей |
|-------|------------------------|----------------|
| **Pi** | `--append-system-prompt` + инструкция прочитать файл ✅ | Нет (роль в промпте) |
| **Qwen Code** | `--append-system-prompt` + инструкция прочитать файл ✅ | Нет (роль в промпте) |
| **OpenCode** | `--agent <name>`, полная изоляция tools/permissions ✅ | ✅ Да (отдельный агент) |
| **OmO** | `--agent <name>` + 11 Discipline Agents ✅ | ✅ Да (isolated agents + categories) |
| **Hermes** | `HERMES_EPHEMERAL_SYSTEM_PROMPT` env / профили ⚠️ | ✅ Да (профили) |
| **Goose** | `--system <text>` / рецепты (YAML) ⚠️ | ⚠️ Через рецепты |
| **Codex CLI** | User prompt / profiles ⚠️ | ⚠️ Через profiles |
| **Gemini CLI** | User prompt `-p` ⚠️ | Нет |
| **Kilo Code** | Агенты `.kilo/agent/*.md` ⚠️ | ✅ Да (отдельный агент) |
| **Crush** | `context_paths` в crush.json ⚠️ | Нет |
| **OpenClaw** | Multi-agent routing (config) ⚠️ | ✅ Да (workspace-изоляция) |
| **Warp Oz** | Config file / profile ⚠️ | ⚠️ Через profile |

#### Критерий 3. Скиллы

| Агент | Agent Skills standard | CLI-управление (`--skill`) | Разным ролям — разные скиллы |
|-------|-----------------------|---------------------------|------------------------------|
| **Pi** | ✅ Полная поддержка | ✅ `--skill`, `--no-skills` | ✅ Разные наборы через CLI |
| **Hermes** | ✅ Skills Hub, curator | ✅ `--skills <name>` | ✅ Через профили |
| **Warp Oz** | ✅ 20+ встроенных | ✅ `--skill <spec>` | ✅ Разные через CLI |
| **Qwen Code** | ✅ Из коробки | ❌ Нет CLI | ❌ Все глобальны |
| **OpenCode** | ✅ Из коробки | ❌ Нет CLI | ❌ Все глобальны |
| **OmO** | ✅ Из коробки + Skill-Embedded MCPs | ❌ Нет CLI | ⚠️ Через `task(load_skills=[])` |
| **Goose** | ✅ Плагины | ❌ Нет CLI | ❌ Все глобальны |
| **Codex CLI** | ⚠️ Глобальные скиллы | ❌ Нет CLI | ❌ Все глобальны |
| **Gemini CLI** | ⚠️ Установка/ссылки | ❌ Нет CLI | ❌ Все глобальны |
| **Kilo Code** | ⚠️ Из коробки | ❌ Нет CLI | ❌ Все глобальны |
| **Crush** | ✅ Lazy loading | ❌ Нет CLI | ❌ Все глобальны |
| **OpenClaw** | ⚠️ Per-agent allowlist | ❌ Нет CLI | ⚠️ Через config |

#### Критерий 4. AGENTS.md (контекстные файлы)

| Агент | AGENTS.md | CLAUDE.md | Собственный файл | Отключение через CLI |
|-------|-----------|-----------|------------------|---------------------|
| **Pi** | ✅ Авто | ✅ Авто | `.pi/SYSTEM.md` | `--no-context-files` |
| **Qwen Code** | ✅ Авто | — | `QWEN.md` | Нет |
| **OpenCode** | ✅ Авто | ✅ Авто | — | Нет |
| **OmO** | ✅ Авто + `/init-deep` (иерархический) | ✅ Авто + Claude Code compat | — | Нет |
| **Hermes** | ✅ Progressive discovery | ✅ | `.hermes.md` | `--ignore-rules` |
| **Goose** | ✅ Авто | — | `.goosehints` | Нет |
| **Codex CLI** | ✅ Авто | — | — | Нет |
| **Gemini CLI** | ⚠️ Через settings | — | `GEMINI.md` | Нет |
| **Kilo Code** | ✅ Авто | ✅ | `.kilo/instructions.md` | Нет |
| **Crush** | ✅ Авто | ✅ | `CRUSH.md` | Нет |
| **OpenClaw** | ⚠️ Только workspace | — | `SOUL.md` | `skipBootstrap` в config |
| **Warp Oz** | ⚠️ Частично | — | `WARP.md` | Нет |

#### Критерий 5. `.agents/skills/` автосканирование

| Агент | `.agents/skills/` | Дополнительные локации | Подключение `docs/agents/skills/` |
|-------|-------------------|----------------------|----------------------------------|
| **Pi** | ✅ | `.pi/skills/`, `settings.json` | `--skill` или `settings.json` |
| **Qwen Code** | ✅ | `.qwen/skills/` | Симлинк |
| **OpenCode** | ✅ | `.opencode/skill/`, `skills.paths` | `skills.paths` в конфиге |
| **OmO** | ✅ + Skill-Embedded MCPs | `.opencode/skills/`, `.claude/skills/`, `.agents/skills/` | `skills.paths` в конфиге / symlink |
| **Hermes** | ✅ (через `external_dirs`) | `~/.hermes/skills/` | `skills.external_dirs` |
| **Goose** | ✅ | `.goose/skills/`, `.claude/skills/` | Симлинк |
| **Codex CLI** | ❌ | `~/.codex/skills/` | Копия в `~/.codex/skills/` |
| **Gemini CLI** | ✅ | `.gemini/skills/` | `gemini skills link` |
| **Kilo Code** | ✅ | `.kilo/skill/`, `skills.paths` | `skills.paths` в конфиге |
| **Crush** | ✅ | `.crush/skills/`, `skills_paths` | `skills_paths` в конфиге |
| **OpenClaw** | ✅ (workspace-scoped) | `extraDirs` в config | `skills.load.extraDirs` |
| **Warp Oz** | ✅ | `.warp/skills/`, `.codex/skills/` | `--skill <path>` |

#### Критерий 6. Запуск как сабагент (JSON-режим)

| Агент | JSON/JSONL режим | Ephemeral | Таймауты | События tool calls |
|-------|-----------------|-----------|----------|-------------------|
| **Pi** | `--mode json` (JSONL) ✅ | `--no-session` ✅ | Встроены в watch-subagent.sh | ✅ Детальные |
| **Qwen Code** | `--output-format stream-json` ✅ | Нет | Внешний timeout | ✅ tool_use / tool_result |
| **Claude Code** | `--print --output-format json` ✅ | По умолчанию (без --resume) | `--max-turns`, `--max-budget-usd` | ✅ stream-json |
| **Goose** | `--output-format stream-json` ✅ | `--no-session` ✅ | Внешний timeout | ⚠️ message, complete |
| **OpenCode** | `--format json` ⚠️ | Нет (сессия в SQLite) | Внешний timeout | ❌ Только step_start/text/finish |
| **OmO** | `--json` runner ⚠️ | Нет (сессия в SQLite) | Внешний timeout | ❌ Structured result only |
| **OmO Team** | 12 `team_*` tools ⚠️ | Нет | `max_wall_clock_minutes` в config | ❌ Mailbox-based |
| **Hermes** | ❌ Plain text (`hermes -z`) | Нет | Внешний timeout | ❌ Нет |
| **Codex CLI** | `--json` ⚠️ | `--ephemeral` ✅ | Внешний timeout | ❌ Бедные события |
| **Gemini CLI** | `-o stream-json` ✅ | Нет | Внешний timeout | ✅ tool_use / tool_result |
| **Kilo Code** | `--format json` ⚠️ | По умолчанию (kilo run) | Внешний timeout | ⚠️ Недокументировано |
| **Crush** | ❌ Plain text (`crush run`) | Нет | Внешний timeout | ❌ Нет |
| **OpenClaw** | ❌ Нет (gateway RPC) | Нет | Внутренний runTimeoutSeconds | ❌ Нет |
| **Warp Oz** | `--output-format ndjson` ⚠️ | Нет | Внешний timeout | ⚠️ Не подтверждено |

#### Критерий 7. Токены и стоимость

| Агент | Токены в JSON/JSONL | Стоимость в $ | Per-model разбивка |
|-------|---------------------|---------------|--------------------|
| **Pi** | ✅ Полная | ✅ `cost: {input, output, cacheRead, cacheWrite}` | ✅ |
| **Claude Code** | ✅ Полная | ✅ `total_cost_usd` | ✅ `modelUsage` |
| **Gemini CLI** | ✅ Полная (input, output, cached, thoughts) | ❌ Только токены | ✅ Per-model |
| **OpenCode** | ✅ В step_finish | ✅ `cost` в USD | ✅ `opencode stats` |
| **OmO** | ✅ В step_finish + context-window-monitor | ✅ `cost` в USD | ✅ `opencode stats` + dynamic pruning |
| **Qwen Code** | ✅ В JSON output | ❌ Только токены | ❌ |
| **Kilo Code** | ✅ `kilo stats` CLI | ❌ Только токены | ✅ `--models` флаг |
| **Hermes** | ❌ Нет при `hermes -z` | ❌ Только через API Server | ❌ |
| **Goose** | ⚠️ `total_tokens` в JSON | ❌ Нет расчёта стоимости | ❌ |
| **Codex CLI** | ⚠️ Только TUI | ❌ Только лимиты | ❌ |
| **Crush** | ❌ Нет в stdout (crush run) | ❌ Нет в stdout | ✅ SQLite / crush stats HTML |
| **OpenClaw** | ⚠️ Через gateway | ❌ Нет CLI-вывода | ❌ |
| **Warp Oz** | ❌ Серверно (Warp cloud) | ❌ Нет CLI-доступа | ❌ |

#### Критерий 8. Free tier / стоимость

| Агент | Лицензия | Бесплатный тариф | BYOK | Ollama / LM Studio |
|-------|----------|-------------------|------|---------------------|
| **Pi** | MIT | ✅ Полностью бесплатный | ✅ | ✅ Ollama, LM Studio, vLLM |
| **Qwen Code** | Apache-2.0 | ⚠️ OAuth free tier прекращён | ✅ | ✅ Ollama, vLLM, LM Studio |
| **OpenCode** | MIT | ✅ + 5 бесплатных моделей Zen | ✅ | ✅ Через кастомный провайдер |
| **OmO** | SUL-1.0 | ✅ + 4+ бесплатных модели Zen | ✅ | ✅ Через кастомный провайдер |
| **Hermes** | MIT | ✅ + Google Gemini OAuth free | ✅ | ✅ LM Studio, Ollama |
| **Goose** | Apache-2.0 | ✅ | ✅ | ✅ Ollama, LM Studio, Docker |
| **Gemini CLI** | Apache-2.0 | ✅ 60 RPM / 1000 запросов/день | ⚠️ Только Gemini | ❌ Нет |
| **Codex CLI** | Apache-2.0 | ⚠️ Требует подписку OpenAI $20+/мес | ✅ | ✅ `--oss` |
| **Kilo Code** | MIT | ✅ Kilo Credits бесплатно | ✅ | ✅ openai-compatible |
| **Crush** | FSL-1.1-MIT | ✅ | ✅ | ✅ Кастомные провайдеры |
| **OpenClaw** | MIT | ✅ | ✅ | ✅ Через config |
| **Warp Oz** | AGPL-3.0/MIT | ⚠️ Ограниченный free tier | ✅ 4 провайдера | ❌ Нет напрямую |

#### Критерий 9. Провайдеры и модели

| Агент | Кол-во провайдеров | Ключевые провайдеры | OpenRouter | Локальные модели |
|-------|-------------------|--------------------|-----------|-----------------|
| **OpenCode** | 75+ | Все через models.dev | ✅ | ✅ |
| **OmO** | 75+ (plugin для OpenCode) | Все через models.dev + Category System | ✅ | ✅ |
| **OpenClaw** | 40+ | OpenAI, Anthropic, Google, ZAI, DeepSeek... | ✅ | ✅ Ollama, LM Studio |
| **Hermes** | 30+ | OpenRouter, OpenAI, Anthropic, Google, DeepSeek... | ✅ | ✅ LM Studio, Ollama |
| **Goose** | 30+ | OpenAI, Anthropic, Google, Ollama, Groq, Cerebras... | ✅ | ✅ Ollama, LM Studio, Docker |
| **Pi** | 20+ | OpenAI, Anthropic, Google, DeepSeek, xAI, ZAI... | ✅ | ✅ Ollama, LM Studio, vLLM |
| **Crush** | 20+ | Anthropic, OpenAI, Google, OpenRouter... | ✅ | ✅ Кастомные провайдеры |
| **Qwen Code** | 3 протокола | OpenAI, Anthropic, Google GenAI | ❌ | ✅ Ollama, vLLM, LM Studio |
| **Kilo Code** | 4 AI SDK + Cloud | Anthropic, OpenAI, OpenRouter + 500+ через Cloud | ✅ | ✅ openai-compatible |
| **Warp Oz** | 4 BYOK + 4 harness | Google, Anthropic, OpenAI, OpenRouter + делегирование | ✅ | ❌ Нет напрямую |
| **Gemini CLI** | 1 | Только Google Gemini | ❌ | ❌ Нет |
| **Codex CLI** | 1 + OSS + BYOK | OpenAI, Ollama, LM Studio | ❌ (только через BYOK) | ✅ Ollama, LM Studio |

#### Критерий 10. Лицензия

| Агент | Лицензия | Открытый код | Форк | Vendor lock-in |
|-------|----------|-------------|------|---------------|
| **Pi** | MIT | ✅ | ✅ | ❌ Нет |
| **Qwen Code** | Apache-2.0 | ✅ | ✅ | ❌ Нет |
| **OpenCode** | MIT | ✅ | ✅ | ❌ Нет |
| **OmO** | SUL-1.0 | ✅ | ⚠️ Только internal use | ❌ Нет (коммерческое распространение запрещено) |
| **Hermes** | MIT | ✅ | ✅ | ❌ Нет |
| **Goose** | Apache-2.0 (Linux Foundation) | ✅ | ✅ | ❌ Нет |
| **Codex CLI** | Apache-2.0 | ✅ | ✅ | ❌ Нет |
| **Gemini CLI** | Apache-2.0 | ✅ | ✅ | ❌ Нет |
| **Kilo Code** | MIT | ✅ | ✅ | ❌ Нет |
| **Crush** | FSL-1.1-MIT (→ MIT через 2 года) | ✅ | ⚠️ Ограничен конкурентное использование | ❌ Нет |
| **OpenClaw** | MIT | ✅ | ✅ | ❌ Нет |
| **Warp Oz** | AGPL-3.0 + MIT | ✅ (клиент) | ✅ | ⚠️ Сервер проприетарный |

### 2.2. Проприетарные агенты (3)

| Агент | К1 Промпт | К2 Роль | К3 Скиллы | К4 AGENTS.md | К5 .agents/skills/ | К6 JSON-режим | К7 Токены | К8 Free tier | К9 Провайдеры | К10 Лицензия | Вердикт |
|-------|-----------|---------|-----------|-------------|--------------------|--------------|-----------|-------------|--------------|-------------|---------|
| **Claude Code** | ✅ `--system-prompt` + `--append-system-prompt` | ✅ `--append-system-prompt` | ⚠️ Нет CLI | ⚠️ Только CLAUDE.md | ❌ `.claude/skills/` | ✅ `--print --output-format json` | ✅ Полная + $ | ❌ $20+/мес | ⚠️ Только Anthropic | ❌ Проприетарная | 7/10 |
| **Factory Droid** | ⚠️ Только конфиг | ⚠️ Custom Droids | ⚠️ Нет CLI | ✅ Авто | ⚠️ С настройкой | ✅ JSON-RPC | ⚠️ Токены без $ | ❌ $20+/мес | ⚠️ 10 BYOK | ❌ Проприетарная | 6/10 |
| **GitHub Copilot CLI** | ⚠️ copilot-instructions.md | ⚠️ User prompt only | ❌ Нет | ❌ Только copilot-instructions.md | ❌ Нет | ❌ Plain text | ❌ Нет | ⚠️ Copilot Free (ограничен) | ❌ Только GitHub | ❌ Проприетарная | 3/10 |

---

## Часть 3. Top-3 кандидата для приоритетной интеграции

### 🥇 1. Pi Coding Agent — Score: 10/10

**Обоснование:** Pi — наш текущий основной сабагент, уже интегрированный через `watch-subagent.sh`. Подтверждён боевой эксплуатацией.

| Преимущество | Описание |
|-------------|----------|
| CLI API для промпта | `--system-prompt` + `--append-system-prompt` — прямая инъекция роли через CLI |
| CLI управление скиллами | `--skill <path>` + `--no-skills` — разные наборы для разных ролей |
| JSONL-стриминг | `--mode json` — детальные события (tool calls, messages, usage) |
| Ephemeral-режим | `--no-session` — изоляция между вызовами сабагентов |
| Полная телеметрия | Токены + стоимость ($) + cache разбивка в каждом JSONL-событии |
| AGENTS.md | Автообнаружение из коробки |
| 20+ провайдеров | BYOK, Ollama, LM Studio, vLLM |
| MIT-лицензия | Максимальная свобода |

### 🥈 2. Qwen Code — Score: 8/10

**Обоснование:** Qwen Code — **ближайший к Pi по CLI API**. Идентичные флаги `--system-prompt` + `--append-system-prompt`, stream-json, `--yolo`. Open source (Apache-2.0).

| Преимущество | Описание |
|-------------|----------|
| Идентичный Pi CLI API | `--system-prompt` + `--append-system-prompt` — миграция watch-subagent.sh минимальна |
| stream-json | `--output-format stream-json` — JSONL-стриминг событий |
| AGENTS.md + QWEN.md | Оба файла из коробки |
| `.agents/skills/` | Автосканирование из коробки |
| 3 протокола API | OpenAI, Anthropic, Google GenAI — покрывает основных провайдеров |
| Apache-2.0 | Open source |

| Ограничение | Описание |
|-------------|----------|
| Нет `--skill` CLI | Невозможно явно загрузить/фильтровать скиллы |
| Нет `--no-session` | Сессии сохраняются (незначительно для headless) |
| Нет стоимости в $ | Только подсчёт токенов |

### 🥉 3. Claude Code — Score: 7/10

**Обоснование:** Claude Code — единственный **проприетарный** агент с идентичным Pi API для системного промпта. Богатейшая телеметрия и guard rails.

| Преимущество | Описание |
|-------------|----------|
| Идентичный Pi API | `--system-prompt` + `--append-system-prompt` |
| Guard rails | `--max-budget-usd`, `--max-turns`, `--tools`, `--permission-mode` |
| Богатая телеметрия | По-модельная разбивка `modelUsage`, `total_cost_usd`, `duration_ms` |
| `--json-schema` | Валидация выхода по JSON Schema |
| `--fallback-model` | Graceful degradation при перегрузке |

| Ограничение | Описание |
|-------------|----------|
| Только Anthropic | Архитектурный lock-in на одного провайдера |
| Нет AGENTS.md | Только CLAUDE.md — требуется workaround |
| Нет `.agents/skills/` | Собственная структура `.claude/skills/` |
| Проприетарная | Закрытый код, $20+/мес |

---

## Часть 4. Рекомендации по мультиагентной интеграции

### 4.1. Архитектура интеграции

```
┌─────────────────────────────────────────────────────────────┐
│                    task-orchestrator                         │
│                   (Оркестратор / Тимлид)                     │
├─────────────────────────────────────────────────────────────┤
│  Система ролей (docs/agents/roles/team/*.ru.md)             │
│  Система скиллов (docs/agents/skills/*/SKILL.md)            │
│  watch-subagent.sh (универсальный wrapper)                   │
├──────────┬──────────┬──────────┬──────────┬─────────────────┤
│  Tier 1  │  Tier 2  │  Tier 2  │  Tier 3  │    Tier 4       │
│  Pi (1)  │ Qwen (2) │ Claude(3)│ OpenCode │ Warp Oz         │
│  MIT     │ Apache   │ Пропр.   │ MIT      │ (оркестратор)   │
│  Основной│ Резерв   │ Claude-  │ Доп.     │ Мультиагентн.   │
│  сабагент│ сабагент │ модели   │ модели   │ оркестрация     │
└──────────┴──────────┴──────────┴──────────┴─────────────────┘
```

### 4.2. Уровни интеграции (Tiers)

| Tier | Агент | Роль в системе | Когда использовать |
|------|-------|---------------|--------------------|
| **Tier 1** | Pi Coding Agent | Основной сабагент для всех ролей | По умолчанию для всех задач |
| **Tier 2** | Qwen Code | Резервный сабагент + Gemini модели | Когда Pi недоступен или нужна Google Gemini / Qwen модель |
| **Tier 2** | Claude Code | Доступ к Claude-моделям | Когда нужна именно Claude-модель (opus, sonnet, haiku) |
| **Tier 3** | OpenCode CLI | Доступ к 75+ провайдерам | Когда нужен специфический провайдер, недоступный в Pi |
| **Tier 4** | Warp Oz | Мультиагентная оркестрация | Когда нужен параллельный запуск нескольких агентов с обменом сообщениями |

### 4.3. Унифицированный wrapper

Для Tier 1–3 агентов возможна адаптация `watch-subagent.sh` по единому шаблону:

```bash
# Pi (текущий подход)
watch-subagent.sh -r $ROLE_FILE <<< "$PROMPT"
# → pi --mode json --no-session --system-prompt ... --append-system-prompt ...

# Qwen Code (адаптация)
watch-qwen.sh -r $ROLE_FILE <<< "$PROMPT"
# → qwen -p "$PROMPT" --output-format stream-json --yolo \
#        --system-prompt ... --append-system-prompt ...

# Claude Code (адаптация)
watch-claude.sh -r $ROLE_FILE <<< "$PROMPT"
# → claude -p --output-format json \
#          --system-prompt ... --append-system-prompt ...
```

Общий интерфейс wrapper-ов:
- `-r <role_file>` — путь к файлу роли
- `-s <seconds>` — soft timeout
- `-m <seconds>` — hard timeout
- `-t <seconds>` — stall timeout
- `-o <format>` — формат вывода (raw / text / tools / files)
- `-k <skill>` — загрузить конкретный скилл (Tier 1 only)

### 4.4. Адаптация системы ролей

Текущая система ролей (`docs/agents/roles/team/*.ru.md`) совместима с Pi, Qwen Code и Claude Code без изменений — все три агента поддерживают инъекцию роли через `--append-system-prompt "Возьми на себя роль из файла: ..."`.

Для агентов без `--append-system-prompt` (OpenCode, Goose, Kilo Code) потребуется:
- Предварительное создание файлов агентов (`.opencode/agent/*.md`, `.kilo/agent/*.md`)
- Или передача роли через user prompt

### 4.5. Адаптация системы скиллов

Текущая система скиллов (`docs/agents/skills/*/SKILL.md`) совместима со всеми агентами, поддерживающими Agent Skills standard, при соблюдении условий:

| Условие | Pi | Qwen | Claude | OpenCode | Goose | Hermes | Warp |
|---------|-----|------|--------|----------|-------|--------|------|
| `--skill` CLI | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| `.agents/skills/` symlink | — | ✅ | ❌ (`.claude/`) | ✅ | ✅ | ✅ | ✅ |
| `skills.paths` / `external_dirs` | — | — | — | ✅ | — | ✅ | — |

**Рекомендация:** Создать symlink `.agents/skills/ → docs/agents/skills/` в корне проекта. Это обеспечит автосканирование для 10 из 14 агентов.

---

## Часть 5. Паттерны и пробелы

### 5.1. Общие паттерны CLI-интерфейсов

| Паттерн | Агенты | Частота |
|---------|--------|---------|
| **Agent Skills standard** (agentskills.io) | Pi, Qwen, OpenCode, Goose, Crush, Hermes, Warp, Kilo, Codex, Gemini, OpenClaw | 11/14 (79%) |
| **AGENTS.md автосканирование** | Pi, Qwen, OpenCode, Hermes, Goose, Codex, Gemini, Kilo, Crush, Warp | 10/14 (71%) |
| **`.agents/skills/` автосканирование** | Pi, Qwen, OpenCode, Hermes, Goose, Gemini, Kilo, Crush, OpenClaw, Warp | 10/14 (71%) |
| **JSON/JSONL-режим** | Pi, Qwen, Claude, OpenCode, Goose, Gemini, Kilo, Codex, Warp | 9/14 (64%) |
| **`--yolo` / auto-approve** | Qwen, Gemini, Crush, Kilo, Hermes | 5/14 (36%) |
| **Ephemeral / no-session** | Pi, Goose, Codex | 3/14 (21%) |
| **`--append-system-prompt`** | Pi, Qwen, Claude | 3/14 (21%) |
| **`--skill` CLI-флаг** | Pi, Hermes, Warp | 3/14 (21%) |
| **Стоимость в $ в CLI** | Pi, Claude, OpenCode | 3/14 (21%) |
| **Кастомные агенты (файлы)** | OpenCode, Kilo, Claude, Droid | 4/14 (29%) |

### 5.2. Пробелы — что не покрывается ни одним агентом

| Пробел | Описание |
|--------|----------|
| **Единый стандарт JSONL-событий** | Каждый агент имеет свой формат событий. Нет общего протокола для мониторинга сабагентов. |
| **Ephemeral + JSONL + CLI-инъекция промпта** | Только Pi имеет все три. Ни один другой агент не обеспечивает полную комбинацию. |
| **CLI-фильтрация скиллов + AGENTS.md + JSONL** | Только Pi. Остальные либо не имеют CLI для скиллов, либо не имеют JSONL. |
| **Стандартный wrapper-протокол** | Нет общего протокола для запуска агента как сабагента с таймаутами, мониторингом и структурированным результатом. |
| **Cross-agent коммуникация** | Только Warp Oz имеет inter-agent messaging. Остальные агенты изолированы. |

### 5.3. Экосистемные тренды

1. **Agent Skills standard (agentskills.io)** — стал де-факто стандартом. 79% исследованных агентов поддерживают формат SKILL.md.
2. **AGENTS.md как стандарт контекста проекта** — 71% агентов автоматически обнаруживают AGENTS.md. Становится эквивалентом `README.md` для AI-агентов.
3. **Мультипровайдерность** — тренд к поддержке множества LLM-провайдеров. Исключения: Gemini CLI (Google only), Copilot CLI (GitHub only), Claude Code (Anthropic only).
4. **Harness-делегирование** — Warp Oz может делегировать другим CLI-агентам (Claude, OpenCode, Gemini, Codex). Потенциальный паттерн для мультиагентной оркестрации.
5. **ACP (Agent Client Protocol)** — поддерживается Goose, Kilo Code, Hermes, OpenClaw. Потенциальный стандарт для межагентной коммуникации.

### 5.4. Рекомендации по дальнейшему развитию

| Приоритет | Рекомендация | Обоснование |
|-----------|-------------|-------------|
| **P0** | Pi остаётся основным сабагентом | Идеальное сочетание CLI API, JSONL, скиллов, телеметрии |
| **P1** | Qwen Code — первый резервный сабагент | Идентичный CLI API, stream-json, Apache-2.0, миграция wrapper за 1 час |
| **P2** | Claude Code — для задач с Claude-моделями | Идентичный API промпта, guard rails, богатая телеметрия |
| **P3** | Создать symlink `.agents/skills/ → docs/agents/skills/` | Обеспечит автосканирование для 10/14 агентов |
| **P4** | Исследовать Warp Oz как платформу оркестрации | Inter-agent messaging + harness delegation для будущей мультиагентной архитектуры |
| **P5** | Инициировать обсуждение стандарта JSONL-событий | Нет общего протокола — каждый wrapper приходится писать отдельно |

---

## Детальные отчёты

| # | Агент | Отчёт | Вердикт |
|---|-------|-------|---------|
| 1 | Pi Coding Agent | [pi-coding-agent-comparison.md](coding-agents/pi-coding-agent-comparison.md) | ✅ Подходит (10/10) |
| 2 | Qwen Code | [qwen-cli-comparison.md](coding-agents/qwen-cli-comparison.md) | ✅ Подходит (8/10) |
| 3 | Claude Code | [claude-code-agent-comparison.md](coding-agents/claude-code-agent-comparison.md) | ⚠️ Частично (7/10) |
| 4 | OpenCode CLI | [opencode-cli-comparison.md](coding-agents/opencode-cli-comparison.md) | ⚠️ Частично (7/10) |
| 5 | Oh My OpenAgent | [oh-my-openagent-comparison.md](coding-agents/oh-my-openagent-comparison.md) | ⚠️ Частично (7/10) |
| 5 | Goose | [goose-agent-comparison.md](coding-agents/goose-agent-comparison.md) | ⚠️ Частично (7/10) |
| 6 | Hermes Agent | [hermes-agent-comparison.md](coding-agents/hermes-agent-comparison.md) | ⚠️ Частично (7/10) |
| 7 | Warp AI (Oz) | [warp-agent-comparison.md](coding-agents/warp-agent-comparison.md) | ⚠️ Частично (7/10) |
| 8 | Codex CLI | [codex-cli-comparison.md](coding-agents/codex-cli-comparison.md) | ⚠️ Частично (6/10) |
| 9 | Gemini CLI | [gemini-cli-comparison.md](coding-agents/gemini-cli-comparison.md) | ⚠️ Частично (6/10) |
| 10 | Kilo Code CLI | [kilocode-cli-comparison.md](coding-agents/kilocode-cli-comparison.md) | ⚠️ Частично (6/10) |
| 11 | Crush | [crush-agent-comparison.md](coding-agents/crush-agent-comparison.md) | ⚠️ Частично (6/10) |
| 12 | Factory Droid | [droid-agent-comparison.md](coding-agents/droid-agent-comparison.md) | ⚠️ Частично (6/10) |
| 13 | OpenClaw | [openclaw-agent-comparison.md](coding-agents/openclaw-agent-comparison.md) | ❌ Не подходит (4/10) |
| 14 | GitHub Copilot CLI | [copilot-cli-comparison.md](coding-agents/copilot-cli-comparison.md) | ❌ Не подходит (3/10) |

---

## Приложение. Сводка по группам

### Open Source (11 агентов)

| Агент | Язык | Лицензия | Провайдеры | Score |
|-------|------|----------|-----------|-------|
| Pi Coding Agent | TypeScript/Node.js | MIT | 20+ | 10/10 |
| Qwen Code | TypeScript/Node.js | Apache-2.0 | 3 протокола | 8/10 |
| OpenCode CLI | TypeScript/Go | MIT | 75+ | 7/10 |
| Oh My OpenAgent | TypeScript/Bun (plugin) | SUL-1.0 | 75+ (OpenCode) | 7/10 |
| Hermes Agent | Python | MIT | 30+ | 7/10 |
| Goose | Rust | Apache-2.0 | 30+ | 7/10 |
| Warp Oz | Rust | AGPL-3.0/MIT | 4 BYOK + 4 harness | 7/10 |
| Codex CLI | Rust/Node.js | Apache-2.0 | OpenAI + OSS | 6/10 |
| Gemini CLI | TypeScript/Node.js | Apache-2.0 | Google only | 6/10 |
| Kilo Code CLI | TypeScript/Bun | MIT | 4 AI SDK + Cloud | 6/10 |
| Crush | Go | FSL-1.1-MIT | 20+ | 6/10 |
| OpenClaw | TypeScript/Node.js | MIT | 40+ | 4/10 |

### Проприетарные (3 агента)

| Агент | Язык | Провайдеры | Цена | Score |
|-------|------|-----------|------|-------|
| Claude Code | TypeScript/Node.js | Anthropic only | $20+/мес | 7/10 |
| Factory Droid | Go (бинарник) | 10 BYOK | $20+/мес | 6/10 |
| GitHub Copilot CLI | Go (бинарник) | GitHub only | Free (ограничен) / $19+/мес | 3/10 |
