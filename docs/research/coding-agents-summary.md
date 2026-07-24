# Coding Agents — Сводная таблица сравнения (финальная версия)

**Дата создания:** 2026-05-09
**Дата обновления:** 2026-07-24 (18 исследований)
**Эпик:** [EPIC-research-coding-agents-comparison](../../todo/done/EPIC-research-coding-agents-comparison.md)
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

Место определяется суммой баллов по 10 критериям (максимум 30) и качественной пригодностью для запуска как subagent (сабагент) в `task-orchestrator`.

| # | Агент | Лиц. | К1 | К2 | К3 | К4 | К5 | К6 | К7 | К8 | К9 | К10 | ∑ | Вердикт |
|---|-------|------|----|----|----|----|----|----|----|----|-----|-----|---|---------|
| 1 | **omp (Oh My Pi)** | MIT | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **30** | ✅ Подходит (10/10) |
| 2 | **Pi Coding Agent** | MIT | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | **30** | ✅ Подходит (10/10) |
| 3 | **Qwen Code** | A-2.0 | ✅ | ✅ | ⚠️ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ | **27** | ✅ Подходит (8/10) |
| 4 | **OpenCode CLI** | MIT | ✅ | ✅ | ⚠️ | ✅ | ✅ | ⚠️ | ✅ | ✅ | ✅ | ✅ | **26** | ⚠️ Частично (7/10) |
| 5 | **Hermes Agent** | MIT | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | **25** | ⚠️ Частично (7/10) |
| 6 | **Goose** | A-2.0 | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ | ✅ | **24** | ⚠️ Частично (7/10) |
| 7 | **Zeroclaw** | MIT/A-2.0 | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | ✅ | ✅ | **23** | ⚠️ Частично (6/10) |
| 8 | **Codebuff** | A-2.0 | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | **23** | ⚠️ Частично (6/10) |
| 9 | **Gemini CLI** | A-2.0 | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | **22** | ⚠️ Частично (6/10) |
| 10 | **Kilo Code CLI** | MIT | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ⚠️ | ✅ | ✅ | ⚠️ | ✅ | **22** | ⚠️ Частично (6/10) |
| 11 | **Claude Code** | Пропр. | ✅ | ✅ | ⚠️ | ⚠️ | ❌ | ✅ | ✅ | ❌ | ⚠️ | ❌ | **21** | ⚠️ Частично (7/10) |
| 12 | **OpenClaw** | MIT | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | ❌ | ⚠️ | ✅ | ✅ | ✅ | **21** | ❌ Не подходит (4/10) |
| 13 | **Warp AI (Oz)** | AGPL | ⚠️ | ⚠️ | ✅ | ⚠️ | ✅ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | **20** | ⚠️ Частично (7/10) |
| 14 | **Crush** | FSL | ⚠️ | ⚠️ | ⚠️ | ✅ | ✅ | ⚠️ | ⚠️ | ✅ | ✅ | ⚠️ | **20** | ⚠️ Частично (6/10) |
| 15 | **Codex CLI** | A-2.0 | ⚠️ | ⚠️ | ⚠️ | ✅ | ❌ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ✅ | **19** | ⚠️ Частично (6/10) |
| 16 | **Factory Droid** | Пропр. | ⚠️ | ⚠️ | ⚠️ | ✅ | ⚠️ | ✅ | ⚠️ | ❌ | ⚠️ | ❌ | **17** | ⚠️ Частично (6/10) |
| 17 | **ZCode (Z.AI)** | Пропр. | ❌ | ❌ | ⚠️ | ✅ | ❌ | ❌ | ⚠️ | ⚠️ | ✅ | ❌ | **17** | ❌ Не подходит (4/10) |
| 18 | **GitHub Copilot CLI** | Пропр. | ⚠️ | ⚠️ | ❌ | ⚠️ | ❌ | ⚠️ | ❌ | ⚠️ | ❌ | ❌ | **12** | ❌ Не подходит (3/10) |

> **Примечание:** omp и Pi имеют одинаковую формальную сумму 30/30, но omp занимает первое место как надмножество Pi: сохраняет критичный JSON/headless surface (поверхность запуска без интерфейса) и добавляет Rust-core, LSP/DAP, native subagents, advisor, memory, ACP и 40+ провайдеров. Pi остаётся стабильным baseline (базовой точкой) и fallback (резервом).

---

## Часть 2. Детальная сводная таблица по 10 критериям

### 2.1. Open Source агенты (14 из 18)

#### Критерий 1. Системный промпт (замена / дополнение)

| Агент | Замена через CLI | Дополнение через CLI | Замена через файл | Файловое дополнение |
|-------|------------------|---------------------|-------------------|---------------------|
| **omp** | `--system-prompt <text-or-file>` ✅ | `--append-system-prompt <text-or-file>` ✅ | `.omp/SYSTEM.md` + совместимые форматы ✅ | `.omp/APPEND_SYSTEM.md` ✅ |
| **Pi** | `--system-prompt <text>` ✅ | `--append-system-prompt <text>` ✅ | `.pi/SYSTEM.md` ✅ | `.pi/APPEND_SYSTEM.md` ✅ |
| **Qwen Code** | `--system-prompt <text>` ✅ | `--append-system-prompt <text>` ✅ | — | — |
| **OpenCode** | Агенты `.opencode/agent/*.md` ✅ | `instructions[]` в конфиге ✅ | — | — |
| **Hermes** | SOUL.md (файл) ⚠️ | `HERMES_EPHEMERAL_SYSTEM_PROMPT` env ⚠️ | `~/.hermes/SOUL.md` ✅ | — |
| **Goose** | Только через `config.yaml` ⚠️ | `--system <text>` ✅ | `GOOSE_SYSTEM_PROMPT_FILE_PATH` ⚠️ | — |
| **Codex CLI** | `model_instructions_file` в config ⚠️ | ❌ Нет | `config.toml` ✅ | — |
| **Gemini CLI** | `GEMINI_SYSTEM_MD` env ⚠️ | ❌ Нет | `.gemini/system.md` ⚠️ | — |
| **Kilo Code** | Агенты `.kilo/agent/*.md` ⚠️ | `instructions[]` в конфиге ⚠️ | — | — |
| **Crush** | ❌ Нет CLI | ❌ Нет CLI | `context_paths` в `crush.json` ⚠️ | — |
| **Zeroclaw** | ❌ Нет CLI-флагов | ❌ Нет CLI-флагов | Personality files (SOUL.md, AGENTS.md) ⚠️ | ✅ Все personality files |
| **OpenClaw** | ❌ Нет CLI | ❌ Нет CLI | Workspace-файлы (SOUL.md) ⚠️ | — |
| **Warp Oz** | ❌ Нет CLI | ❌ Нет CLI | Config/profile ⚠️ | — |
| **Codebuff** | SDK `AgentDefinition.systemPrompt` ⚠️ | SDK `instructionsPrompt` ⚠️ | `.agents/*.ts` ⚠️ | — |

#### Критерий 2. Промпт агента / Роль

| Агент | Механизм инъекции роли | Изоляция ролей |
|-------|------------------------|----------------|
| **omp** | `--append-system-prompt` + model roles + agent specs ✅ | ✅ Subagents/worktree + advisor/model roles |
| **Pi** | `--append-system-prompt` + инструкция прочитать файл ✅ | Нет (роль в промпте) |
| **Qwen Code** | `--append-system-prompt` + инструкция прочитать файл ✅ | Нет (роль в промпте) |
| **OpenCode** | `--agent <name>`, tools/permissions per-agent ✅ | ✅ Да |
| **Hermes** | `HERMES_EPHEMERAL_SYSTEM_PROMPT` / профили ⚠️ | ✅ Через профили |
| **Goose** | `--system <text>` / recipes (рецепты) ⚠️ | ⚠️ Через recipes |
| **Zeroclaw** | Workspace SOUL.md / AGENTS.md ⚠️ | ✅ Через workspace |
| **Codex CLI** | User prompt / profiles ⚠️ | ⚠️ Через profiles |
| **Gemini CLI** | User prompt `-p` ⚠️ | Нет |
| **Kilo Code** | Агенты `.kilo/agent/*.md` ⚠️ | ✅ Да |
| **Crush** | `context_paths` в `crush.json` ⚠️ | Нет |
| **OpenClaw** | Multi-agent routing (config) ⚠️ | ✅ Workspace-изоляция |
| **Warp Oz** | Config/profile ⚠️ | ⚠️ Через profile |
| **Codebuff** | SDK / `.agents/*.ts` custom agents ⚠️ | ✅ Через `AgentDefinition` |

#### Критерий 3. Скиллы

| Агент | Agent Skills standard | CLI-управление | Разным ролям — разные скиллы |
|-------|-----------------------|----------------|------------------------------|
| **omp** | ✅ `SKILL.md`, `skill://`, импорт providers | ✅ `--no-skills`, `--skills <glob>`; `--skill <path>` не подтверждён | ✅ Через config/symlink/filter; task subagents наследуют список |
| **Pi** | ✅ Полная поддержка | ✅ `--skill`, `--no-skills` | ✅ Разные наборы через CLI |
| **Hermes** | ✅ Skills Hub, curator | ✅ `--skills <name>` | ✅ Через профили |
| **Warp Oz** | ✅ 20+ встроенных | ✅ `--skill <spec>` | ✅ Разные через CLI |
| **Qwen Code** | ✅ Из коробки | ❌ Нет CLI | ❌ Все глобальны |
| **OpenCode** | ✅ Из коробки | ❌ Нет CLI | ❌ Все глобальны |
| **Goose** | ✅ Плагины | ❌ Нет CLI | ❌ Все глобальны |
| **Codex CLI** | ⚠️ Глобальные скиллы | ❌ Нет CLI | ❌ Все глобальны |
| **Gemini CLI** | ⚠️ Установка/ссылки | ❌ Нет CLI | ❌ Все глобальны |
| **Kilo Code** | ⚠️ Из коробки | ❌ Нет CLI | ❌ Все глобальны |
| **Crush** | ✅ Lazy loading | ❌ Нет CLI | ❌ Все глобальны |
| **OpenClaw** | ⚠️ Per-agent allowlist | ❌ Нет CLI | ⚠️ Через config |
| **Zeroclaw** | ✅ SKILL.md + SkillForge | ❌ Нет CLI | ⚠️ Через workspace |
| **Codebuff** | ✅ Agent Skills standard | ❌ Нет CLI | ⚠️ SDK/global session |

#### Критерий 4. AGENTS.md (контекстные файлы)

| Агент | AGENTS.md | CLAUDE.md | Собственный файл | Отключение |
|-------|-----------|-----------|------------------|------------|
| **omp** | ✅ `AGENTS.md` walk-up + `.omp/AGENTS.md` | ✅ `.claude/CLAUDE.md` | `.omp/AGENTS.md`, `RULES.md` | `disabledProviders` config |
| **Pi** | ✅ Авто | ✅ Авто | `.pi/SYSTEM.md` | `--no-context-files` |
| **Qwen Code** | ✅ Авто | — | `QWEN.md` | Нет |
| **OpenCode** | ✅ Авто | ✅ Авто | — | Нет |
| **Hermes** | ✅ Progressive discovery | ✅ | `.hermes.md` | `--ignore-rules` |
| **Goose** | ✅ Авто | — | `.goosehints` | Нет |
| **Codex CLI** | ✅ Авто | — | — | Нет |
| **Gemini CLI** | ⚠️ Через settings | — | `GEMINI.md` | Нет |
| **Kilo Code** | ✅ Авто | ✅ | `.kilo/instructions.md` | Нет |
| **Crush** | ✅ Авто | ✅ | `CRUSH.md` | Нет |
| **OpenClaw** | ⚠️ Только workspace | — | `SOUL.md` | `skipBootstrap` |
| **Warp Oz** | ⚠️ Частично | — | `WARP.md` | Нет |
| **Zeroclaw** | ⚠️ Из daemon workspace | — | Personality files | Нет CLI |
| **Codebuff** | ✅ Авто | ✅ | `knowledge.md` | Нет CLI |

#### Критерий 5. `.agents/skills/` автосканирование

| Агент | `.agents/skills/` | Дополнительные локации | Подключение `docs/agents/skills/` |
|-------|-------------------|------------------------|----------------------------------|
| **omp** | ✅ | `.omp/skills/`, `.claude/skills/`, `.codex/skills/`, `.github/skills/`, `skills.customDirectories` | Symlink или `skills.customDirectories` |
| **Pi** | ✅ | `.pi/skills/`, `settings.json` | `--skill` или `settings.json` |
| **Qwen Code** | ✅ | `.qwen/skills/` | Symlink |
| **OpenCode** | ✅ | `.opencode/skill/`, `skills.paths` | `skills.paths` |
| **Hermes** | ✅ (`external_dirs`) | `~/.hermes/skills/` | `skills.external_dirs` |
| **Goose** | ✅ | `.goose/skills/`, `.claude/skills/` | Symlink |
| **Codex CLI** | ❌ | `~/.codex/skills/` | Копия в `~/.codex/skills/` |
| **Gemini CLI** | ✅ | `.gemini/skills/` | `gemini skills link` |
| **Kilo Code** | ✅ | `.kilo/skill/`, `skills.paths` | `skills.paths` |
| **Crush** | ✅ | `.crush/skills/`, `skills_paths` | `skills_paths` |
| **OpenClaw** | ✅ workspace-scoped | `extraDirs` | `skills.load.extraDirs` |
| **Warp Oz** | ✅ | `.warp/skills/`, `.codex/skills/` | `--skill <path>` |
| **Zeroclaw** | ❌ | `<workspace>/skills/`, open-skills opt-in | install/symlink |
| **Codebuff** | ✅ | `.claude/skills/`, SDK `skillsDir` | Symlink или SDK |

#### Критерий 6. Запуск как сабагент (JSON-режим)

| Агент | JSON/JSONL режим | Ephemeral | Таймауты | События tool calls |
|-------|-----------------|-----------|----------|-------------------|
| **omp** | `--mode json` ✅ + RPC/RPC-UI/ACP | `--no-session` ✅ | `--max-time` + wrapper | ✅ Детальные + subagent frames в RPC |
| **Pi** | `--mode json` ✅ | `--no-session` ✅ | wrapper | ✅ Детальные |
| **Qwen Code** | `--output-format stream-json` ✅ | Нет | Внешний timeout | ✅ |
| **Goose** | `--output-format stream-json` ✅ | `--no-session` ✅ | Внешний timeout | ⚠️ |
| **OpenCode** | `--format json` ⚠️ | Нет | Внешний timeout | ❌ Бедные события |
| **Hermes** | ❌ Plain text | Нет | Внешний timeout | ❌ |
| **Codex CLI** | `--json` ⚠️ | `--ephemeral` ✅ | Внешний timeout | ❌ |
| **Gemini CLI** | `-o stream-json` ✅ | Нет | Внешний timeout | ✅ |
| **Kilo Code** | `--format json` ⚠️ | По умолчанию | Внешний timeout | ⚠️ |
| **Crush** | ❌ Plain text | Нет | Внешний timeout | ❌ |
| **OpenClaw** | ❌ Gateway RPC | Нет | `runTimeoutSeconds` | ❌ |
| **Warp Oz** | `--output-format ndjson` ⚠️ | Нет | Внешний timeout | ⚠️ |
| **Zeroclaw** | ✅ ACP JSON-RPC | ✅ isolated sessions | ✅ cancel/session timeout | ✅ |
| **Codebuff** | ⚠️ SDK events, нет CLI JSON | SDK run | AbortSignal | ✅ SDK |

#### Критерий 7. Токены и стоимость

| Агент | Токены в JSON/JSONL | Стоимость в $ | Per-model разбивка |
|-------|---------------------|---------------|--------------------|
| **omp** | ✅ `input/output/cacheRead/cacheWrite/totalTokens` | ✅ `cost`, `omp stats`, `omp usage` | ✅ Через session/model usage |
| **Pi** | ✅ Полная | ✅ `cost` | ✅ |
| **Gemini CLI** | ✅ input/output/cached/thoughts | ❌ Только токены | ✅ |
| **OpenCode** | ✅ step_finish | ✅ USD | ✅ `opencode stats` |
| **Qwen Code** | ✅ JSON output | ❌ Только токены | ❌ |
| **Kilo Code** | ✅ `kilo stats` | ❌ Только токены | ✅ |
| **Hermes** | ❌ Нет при headless | ❌ Только API server | ❌ |
| **Goose** | ⚠️ `total_tokens` | ❌ Нет $ | ❌ |
| **Codex CLI** | ⚠️ TUI/лимиты | ❌ Только лимиты | ❌ |
| **Crush** | ❌ Нет stdout | ❌ Нет stdout | ✅ SQLite/stats HTML |
| **OpenClaw** | ⚠️ Gateway | ❌ Нет CLI | ❌ |
| **Warp Oz** | ❌ Серверно | ❌ Нет CLI | ❌ |
| **Zeroclaw** | ✅ Prometheus + done frame | ✅ `cost_usd` | ✅ |
| **Codebuff** | ⚠️ `totalCost`, `contextTokenCount` | ⚠️ Без детальной разбивки | ❌ |

#### Критерий 8. Free tier / стоимость

| Агент | Лицензия | Бесплатный тариф | BYOK | Ollama / LM Studio |
|-------|----------|------------------|------|--------------------|
| **omp** | MIT | ✅ Бесплатный CLI, local/free tiers у провайдеров | ✅ | ✅ Ollama, LM Studio, llama.cpp, vLLM, LiteLLM |
| **Pi** | MIT | ✅ Бесплатный CLI | ✅ | ✅ Ollama, LM Studio, vLLM |
| **Qwen Code** | Apache-2.0 | ⚠️ OAuth free tier прекращён | ✅ | ✅ Ollama/vLLM/LM Studio |
| **OpenCode** | MIT | ✅ + бесплатные модели Zen | ✅ | ✅ Custom provider |
| **Hermes** | MIT | ✅ + Gemini OAuth free | ✅ | ✅ LM Studio/Ollama |
| **Goose** | Apache-2.0 | ✅ | ✅ | ✅ Ollama/LM Studio/Docker |
| **Gemini CLI** | Apache-2.0 | ✅ 60 RPM / 1000 req/day | ⚠️ Gemini only | ❌ |
| **Codex CLI** | Apache-2.0 | ⚠️ OpenAI subscription | ✅ | ✅ `--oss` |
| **Kilo Code** | MIT | ✅ Kilo Credits | ✅ | ✅ openai-compatible |
| **Crush** | FSL-1.1-MIT | ✅ | ✅ | ✅ Custom providers |
| **OpenClaw** | MIT | ✅ | ✅ | ✅ Config |
| **Warp Oz** | AGPL-3.0/MIT | ⚠️ Limited free tier | ✅ 4 providers | ❌ |
| **Zeroclaw** | MIT/Apache-2.0 | ✅ Бесплатный | ✅ | ✅ Ollama/LM Studio |
| **Codebuff** | Apache-2.0 | ⚠️ Credits + Freebuff | ✅ OpenRouter/OAuth | ❌ Нет напрямую |

#### Критерий 9. Провайдеры и модели

| Агент | Кол-во провайдеров | Ключевые провайдеры | OpenRouter | Локальные модели |
|-------|-------------------|--------------------|------------|-----------------|
| **OpenCode** | 75+ | Все через models.dev | ✅ | ✅ |
| **omp** | 40+ | Anthropic, OpenAI, Google, xAI, Mistral, Groq, Cerebras, coding plans, local | ✅ | ✅ Ollama, LM Studio, llama.cpp, vLLM, LiteLLM |
| **OpenClaw** | 40+ | OpenAI, Anthropic, Google, ZAI, DeepSeek... | ✅ | ✅ |
| **Hermes** | 30+ | OpenRouter, OpenAI, Anthropic, Google, DeepSeek... | ✅ | ✅ |
| **Goose** | 30+ | OpenAI, Anthropic, Google, Ollama, Groq... | ✅ | ✅ |
| **Zeroclaw** | 25+ | Anthropic, OpenAI, Ollama, Gemini, Bedrock... | ✅ | ✅ |
| **Pi** | 20+ | OpenAI, Anthropic, Google, DeepSeek, xAI, ZAI... | ✅ | ✅ |
| **Crush** | 20+ | Anthropic, OpenAI, Google, OpenRouter... | ✅ | ✅ |
| **Qwen Code** | 3 протокола | OpenAI, Anthropic, Google GenAI | ❌ | ✅ |
| **Kilo Code** | 4 AI SDK + Cloud | Anthropic, OpenAI, OpenRouter + Cloud | ✅ | ✅ |
| **Warp Oz** | 4 BYOK + 4 harness | Google, Anthropic, OpenAI, OpenRouter + delegation | ✅ | ❌ |
| **Gemini CLI** | 1 | Google Gemini | ❌ | ❌ |
| **Codex CLI** | 1 + OSS/BYOK | OpenAI, Ollama, LM Studio | ❌ | ✅ |
| **Codebuff** | 5+ + OpenRouter | OpenRouter, Anthropic OAuth, OpenAI OAuth | ✅ | ❌ |

#### Критерий 10. Лицензия

| Агент | Лицензия | Открытый код | Форк | Vendor lock-in |
|-------|----------|-------------|------|----------------|
| **omp** | MIT | ✅ TypeScript + Rust | ✅ Pi fork | ❌ Нет обязательного cloud backend |
| **Pi** | MIT | ✅ | ✅ | ❌ Нет |
| **Qwen Code** | Apache-2.0 | ✅ | ✅ | ❌ Нет |
| **OpenCode** | MIT | ✅ | ✅ | ❌ Нет |
| **Hermes** | MIT | ✅ | ✅ | ❌ Нет |
| **Goose** | Apache-2.0 | ✅ | ✅ | ❌ Нет |
| **Codex CLI** | Apache-2.0 | ✅ | ✅ | ❌ Нет |
| **Gemini CLI** | Apache-2.0 | ✅ | ✅ | ❌ Нет |
| **Kilo Code** | MIT | ✅ | ✅ | ❌ Нет |
| **Crush** | FSL-1.1-MIT | ✅ | ⚠️ Ограничен конкурентный use | ❌ Нет |
| **OpenClaw** | MIT | ✅ | ✅ | ❌ Нет |
| **Warp Oz** | AGPL-3.0 + MIT | ✅ client | ✅ | ⚠️ Server proprietary |
| **Zeroclaw** | MIT OR Apache-2.0 | ✅ | ✅ | ❌ Нет |
| **Codebuff** | Apache-2.0 | ✅ | ✅ | ⚠️ Codebuff backend по умолчанию |

### 2.2. Проприетарные агенты (4 из 18)

| Агент | К1 Промпт | К2 Роль | К3 Скиллы | К4 AGENTS.md | К5 .agents/skills/ | К6 JSON-режим | К7 Токены | К8 Free tier | К9 Провайдеры | К10 Лицензия | Вердикт |
|-------|-----------|---------|-----------|--------------|--------------------|---------------|-----------|--------------|---------------|-------------|---------|
| **Claude Code** | ✅ `--system-prompt` + `--append-system-prompt` | ✅ `--append-system-prompt` | ⚠️ Нет CLI | ⚠️ Только CLAUDE.md | ❌ `.claude/skills/` | ✅ `--print --output-format json` | ✅ Полная + $ | ❌ $20+/мес | ⚠️ Anthropic only | ❌ Проприетарная | 7/10 |
| **Factory Droid** | ⚠️ Только конфиг | ⚠️ Custom Droids | ⚠️ Нет CLI | ✅ Авто | ⚠️ С настройкой | ✅ JSON-RPC | ⚠️ Токены без $ | ❌ $20+/мес | ⚠️ 10 BYOK | ❌ Проприетарная | 6/10 |
| **ZCode (Z.AI)** | ❌ Нет | ❌ Нет ролей | ⚠️ SKILL.md + импорт GUI | ✅ AGENTS.md + CLAUDE.md | ❌ Только `~/.zcode/skills/` | ❌ Нет headless/JSON | ⚠️ GUI only | ⚠️ Free trial quota | ✅ Z.ai + BYOK | ❌ Проприетарная | 4/10 |
| **GitHub Copilot CLI** | ⚠️ copilot-instructions.md | ⚠️ User prompt only | ❌ Нет | ⚠️ copilot-instructions.md | ❌ Нет | ⚠️ Plain text/ограничен | ❌ Нет | ⚠️ Copilot Free | ❌ GitHub only | ❌ Проприетарная | 3/10 |

---

## Часть 3. Top-3 кандидата для приоритетной интеграции

### 🥇 1. omp (Oh My Pi) — ✅ Подходит (10/10)

**Обоснование:** omp — лучший upgrade текущего Pi: сохраняет критичный headless JSONL (`--mode json --no-session`) и prompt API (`--system-prompt`, `--append-system-prompt`), но добавляет Rust-core, LSP/DAP, first-class subagents, advisor, memory, ACP и 40+ providers.

| Преимущество | Описание |
|--------------|----------|
| Pi-compatible core | `--mode json`, `--no-session`, `--system-prompt`, `--append-system-prompt` |
| Native Rust-core | In-process grep/search/shell/AST, меньше fork/exec на hot path |
| Subagents | `task` с worktree-изоляцией и schema output |
| Code intelligence | LSP 14 ops, DAP 28 ops |
| Memory | `pi-mnemopi`, local SQLite, `memory://` |
| Enforcement | Time-traveling stream rules + advisor role |
| Интеграция | JSON, RPC, RPC-UI, ACP, SDK |
| Провайдеры | 40+ providers, local, coding plans, custom protocols |

**Оговорка:** v17.1.1 не подтверждает exact Pi flags (точные флаги Pi) `--skill <path>` и `--no-context-files`; заменить на `--skills` filter, `skills.customDirectories`, symlink и `disabledProviders`.

### 🥈 2. Pi Coding Agent — Score: 10/10

**Обоснование:** Pi — текущий baseline (база), уже интегрирован и стабилен. Сохраняется как fallback и reference contract (контракт-эталон) для JSONL событий.

| Преимущество | Описание |
|--------------|----------|
| CLI API для промпта | `--system-prompt` + `--append-system-prompt` |
| CLI управление скиллами | `--skill <path>` + `--no-skills` |
| JSONL-стриминг | `--mode json` |
| Ephemeral | `--no-session` |
| AGENTS.md | Автообнаружение |
| Провайдеры | 20+ providers, BYOK, local |
| License | MIT |

### 🥉 3. Qwen Code — Score: 8/10

**Обоснование:** Qwen Code — ближайший резерв по CLI API: поддерживает `--system-prompt`, `--append-system-prompt` и `stream-json`, но уступает Pi/omp по skills CLI и телеметрии стоимости.

| Преимущество | Описание |
|--------------|----------|
| Похожий prompt API | `--system-prompt` + `--append-system-prompt` |
| stream-json | JSONL-подобный вывод событий |
| AGENTS.md + QWEN.md | Context discovery |
| `.agents/skills/` | Автосканирование |
| Apache-2.0 | Open source |
| Ограничения | Нет `--skill` CLI, нет `--no-session`, нет стоимости в $ |

---

## Часть 4. Рекомендации по мультиагентной интеграции

### 4.1. Архитектура интеграции

```text
┌─────────────────────────────────────────────────────────────┐
│                    task-orchestrator                         │
│                   (Оркестратор / Тимлид)                     │
├─────────────────────────────────────────────────────────────┤
│  Роли: docs/agents/roles/team/*.ru.md                       │
│  Skills: docs/agents/skills/*/SKILL.md                      │
│  Wrapper: watch-subagent.sh-compatible contract             │
├──────────┬──────────┬──────────┬──────────┬─────────────────┤
│ Tier 1   │ Tier 1b  │ Tier 2   │ Tier 3   │ Tier 4          │
│ omp (1)  │ Pi (2)   │ Qwen (3) │ Claude   │ OpenCode/Warp   │
│ Основной │ Fallback │ Резерв   │ Claude   │ SDK/оркестр.    │
└──────────┴──────────┴──────────┴──────────┴─────────────────┘
```

### 4.2. Уровни интеграции (Tiers)

| Tier | Агент | Роль в системе | Когда использовать |
|------|-------|----------------|--------------------|
| **Tier 1** | omp | Основной кандидат на новый default subagent | После smoke/contract tests JSONL и flags |
| **Tier 1b** | Pi Coding Agent | Стабильный fallback и baseline | До полного перевода wrapper на omp |
| **Tier 2** | Qwen Code | Резервный сабагент + Qwen/Gemini сценарии | Когда omp/Pi недоступны или нужна Qwen-модель |
| **Tier 3** | Claude Code | Claude-specific задачи | Когда нужна именно Anthropic Claude и guard rails |
| **Tier 3** | OpenCode CLI | Доступ к 75+ providers | Когда нужен специфический provider |
| **Tier 4** | Warp Oz / Codebuff | Архитектурные референсы мультиагентности | Для будущих SDK/оркестрационных экспериментов |

### 4.3. Унифицированный wrapper

```bash
# omp (кандидат default)
omp --mode json --no-session \
  --system-prompt "$SYSTEM_PROMPT" \
  --append-system-prompt "Возьми на себя роль из файла: $ROLE_FILE" \
  "$PROMPT"

# Pi (fallback)
pi --mode json --no-session \
  --system-prompt "$SYSTEM_PROMPT" \
  --append-system-prompt "Возьми на себя роль из файла: $ROLE_FILE" \
  "$PROMPT"

# Qwen Code (резерв)
qwen -p "$PROMPT" --output-format stream-json --yolo \
  --system-prompt "$SYSTEM_PROMPT" \
  --append-system-prompt "Возьми на себя роль из файла: $ROLE_FILE"
```

### 4.4. Адаптация системы ролей

Текущие роли (`docs/agents/roles/team/*.ru.md`) совместимы с omp, Pi и Qwen через `--append-system-prompt`. Для агентов без такого флага нужны agent files (файлы агентов), profiles (профили) или SDK-wrapper (обёртка SDK).

### 4.5. Адаптация системы скиллов

| Условие | omp | Pi | Qwen | Claude | OpenCode | Goose | Hermes | Warp | Codebuff |
|---------|-----|----|------|--------|----------|-------|--------|------|----------|
| Явный CLI skill path | ⚠️ Не подтверждён (`--skills` filter) | ✅ `--skill` | ❌ | ❌ | ❌ | ❌ | ✅ `--skills` | ✅ `--skill` | ❌ |
| `.agents/skills/` symlink | ✅ | ✅ | ✅ | ❌ (`.claude/`) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Config/custom dirs | ✅ `skills.customDirectories` | ✅ settings | — | — | ✅ `skills.paths` | — | ✅ external dirs | — | ✅ SDK `skillsDir` |

**Рекомендация:** создать symlink `.agents/skills/ → docs/agents/skills/` или включить `skills.customDirectories` для omp. Это уменьшает зависимость от точных CLI flags разных агентов.

---

## Часть 5. Паттерны и пробелы

### 5.1. Общие паттерны CLI-интерфейсов

| Паттерн | Агенты | Частота |
|---------|--------|---------|
| **Agent Skills standard** | omp, Pi, Qwen, OpenCode, Goose, Crush, Hermes, Warp, Kilo, Codex, Gemini, OpenClaw, Zeroclaw, Codebuff, ZCode | 15/18 (83%) |
| **AGENTS.md автосканирование** | omp, Pi, Qwen, OpenCode, Hermes, Goose, Codex, Gemini, Kilo, Crush, Codebuff, ZCode | 12/18 (67%) |
| **`.agents/skills/` автосканирование** | omp, Pi, Qwen, OpenCode, Hermes, Goose, Gemini, Kilo, Crush, OpenClaw, Warp, Codebuff | 12/18 (67%) |
| **JSON/JSONL/RPC headless** | omp, Pi, Qwen, Claude, OpenCode, Goose, Gemini, Kilo, Codex, Warp, Zeroclaw, Factory Droid | 12/18 (67%) |
| **Ephemeral / no-session** | omp, Pi, Goose, Codex, Zeroclaw | 5/18 (28%) |
| **`--append-system-prompt`** | omp, Pi, Qwen, Claude | 4/18 (22%) |
| **CLI-фильтрация/выбор скиллов** | omp, Pi, Hermes, Warp | 4/18 (22%) |
| **Стоимость в $ в CLI/JSON** | omp, Pi, Claude, OpenCode, Zeroclaw | 5/18 (28%) |
| **Кастомные агенты / native subagents** | omp, OpenCode, Kilo, Claude, Droid, Codebuff, Warp | 7/18 (39%) |

### 5.2. Пробелы — что не покрывается единым стандартом

| Пробел | Описание |
|--------|----------|
| **Единый JSONL event schema** | Каждый агент имеет свой формат событий; нужен adapter layer (слой адаптера). |
| **Prompt + Skills + Ephemeral + JSONL** | Полная комбинация есть у Pi; omp близок, но требует проверки `--skill <path>`/skills config. |
| **Per-role skill filtering** | Большинство агентов грузят global skills; явный per-role набор есть только у Pi и частично у omp/Hermes/Warp. |
| **Subagent result schema** | omp решает это нативно через `task`; остальные обычно возвращают prose (текст) или custom events. |
| **Context отключение единым флагом** | У omp — config `disabledProviders`, у Pi — `--no-context-files`, у других часто нет. |

### 5.3. Экосистемные тренды

1. **omp меняет baseline:** Pi больше не вершина по возможностям, а стабильное подмножество omp.
2. **Agent Skills standard закрепился:** формат `SKILL.md` поддерживают 83% исследованных агентов.
3. **AGENTS.md стал cross-agent стандартом:** 12 из 18 агентов явно поддерживают или совместимы через импортеры.
4. **Native/Rust hot path:** omp и ряд новых агентов выносят поиск/парсинг/инструменты в нативный слой ради скорости.
5. **Subagents становятся first-class:** omp (`task`), Codebuff (`spawn_agents`), Warp (inter-agent messaging) показывают тренд к внутренней оркестрации.
6. **ACP/RPC растут как интеграционные протоколы:** omp, Goose, Kilo, Hermes, OpenClaw, Zeroclaw двигаются в сторону editor/host protocols.
7. **Memory и hindsight:** omp добавляет project-scoped memory, что сближает CLI-agent с долгоживущим knowledge base (базой знаний).

### 5.4. Рекомендации по дальнейшему развитию

| Приоритет | Рекомендация | Обоснование |
|-----------|--------------|-------------|
| **P0** | Прогнать `omp` smoke test как drop-in replacement | Проверить JSONL schema, exit codes, prompt injection |
| **P0** | Добавить contract test для wrapper events | Зафиксировать совместимость `agent_end`, `message_end`, tool events |
| **P1** | Перевести skills подключение на `.agents/skills` symlink/config | Снизить зависимость от Pi-only `--skill <path>` |
| **P1** | Держать Pi как fallback | Pi уже стабилен в эксплуатации |
| **P2** | Изучить omp `task` для внутренних subagents | Может заменить часть внешней orchestration логики |
| **P3** | Изучить TTSR/advisor как quality gates | Потенциальная альтернатива части динамических циклов контроля |
| **P4** | Продолжить мониторинг ACP | Возможный будущий стандарт host-agent взаимодействия |

---

## Детальные отчёты

| # | Агент | Отчёт | Вердикт |
|---|-------|-------|---------|
| 1 | omp (Oh My Pi) | [omp-comparison.md](coding-agents/omp-comparison.md) | ✅ Подходит (10/10) |
| 2 | Pi Coding Agent | [pi-coding-agent-comparison.md](coding-agents/pi-coding-agent-comparison.md) | ✅ Подходит (10/10) |
| 3 | Qwen Code | [qwen-cli-comparison.md](coding-agents/qwen-cli-comparison.md) | ✅ Подходит (8/10) |
| 4 | OpenCode CLI | [opencode-cli-comparison.md](coding-agents/opencode-cli-comparison.md) | ⚠️ Частично (7/10) |
| 5 | Hermes Agent | [hermes-agent-comparison.md](coding-agents/hermes-agent-comparison.md) | ⚠️ Частично (7/10) |
| 6 | Goose | [goose-agent-comparison.md](coding-agents/goose-agent-comparison.md) | ⚠️ Частично (7/10) |
| 7 | Warp AI (Oz) | [warp-agent-comparison.md](coding-agents/warp-agent-comparison.md) | ⚠️ Частично (7/10) |
| 8 | Claude Code | [claude-code-agent-comparison.md](coding-agents/claude-code-agent-comparison.md) | ⚠️ Частично (7/10) |
| 9 | Zeroclaw | [zeroclaw-agent-comparison.md](coding-agents/zeroclaw-agent-comparison.md) | ⚠️ Частично (6/10) |
| 10 | Codebuff | [codebuff-comparison.md](coding-agents/codebuff-comparison.md) | ⚠️ Частично (6/10) |
| 11 | Gemini CLI | [gemini-cli-comparison.md](coding-agents/gemini-cli-comparison.md) | ⚠️ Частично (6/10) |
| 12 | Kilo Code CLI | [kilocode-cli-comparison.md](coding-agents/kilocode-cli-comparison.md) | ⚠️ Частично (6/10) |
| 13 | Crush | [crush-agent-comparison.md](coding-agents/crush-agent-comparison.md) | ⚠️ Частично (6/10) |
| 14 | Codex CLI | [codex-cli-comparison.md](coding-agents/codex-cli-comparison.md) | ⚠️ Частично (6/10) |
| 15 | Factory Droid | [droid-agent-comparison.md](coding-agents/droid-agent-comparison.md) | ⚠️ Частично (6/10) |
| 16 | OpenClaw | [openclaw-agent-comparison.md](coding-agents/openclaw-agent-comparison.md) | ❌ Не подходит (4/10) |
| 17 | ZCode (Z.AI) | [zcode-coding-agent-comparison.md](coding-agents/zcode-coding-agent-comparison.md) | ❌ Не подходит (4/10) |
| 18 | GitHub Copilot CLI | [copilot-cli-comparison.md](coding-agents/copilot-cli-comparison.md) | ❌ Не подходит (3/10) |

---

## Приложение. Сводка по группам

### Open Source (14 из 18)

| Агент | Язык | Лицензия | Провайдеры | Score / вердикт |
|-------|------|----------|------------|-----------------|
| omp (Oh My Pi) | TypeScript/Bun + Rust | MIT | 40+ | #1 — ✅ Подходит (10/10) |
| Pi Coding Agent | TypeScript/Node.js | MIT | 20+ | 10/10 |
| Qwen Code | TypeScript/Node.js | Apache-2.0 | 3 протокола | 8/10 |
| OpenCode CLI | TypeScript/Go | MIT | 75+ | 7/10 |
| Hermes Agent | Python | MIT | 30+ | 7/10 |
| Goose | Rust | Apache-2.0 | 30+ | 7/10 |
| Warp Oz | Rust | AGPL-3.0/MIT | 4 BYOK + 4 harness | 7/10 |
| Zeroclaw | Rust | MIT/Apache-2.0 | 25+ | 6/10 |
| Codex CLI | Rust/Node.js | Apache-2.0 | OpenAI + OSS | 6/10 |
| Gemini CLI | TypeScript/Node.js | Apache-2.0 | Google only | 6/10 |
| Kilo Code CLI | TypeScript/Bun | MIT | 4 AI SDK + Cloud | 6/10 |
| Crush | Go | FSL-1.1-MIT | 20+ | 6/10 |
| Codebuff | TypeScript/Bun | Apache-2.0 | 5+ + OpenRouter | 6/10 |
| OpenClaw | TypeScript/Node.js | MIT | 40+ | 4/10 |

### Проприетарные (4 из 18)

| Агент | Язык | Провайдеры | Цена | Score |
|-------|------|------------|------|-------|
| Claude Code | TypeScript/Node.js | Anthropic only | $20+/мес | 7/10 |
| Factory Droid | Go (бинарник) | 10 BYOK | $20+/мес | 6/10 |
| ZCode (Z.AI) | Desktop (закрытый) | Z.ai + BYOK | Free trial quota / Coding Plans | 4/10 |
| GitHub Copilot CLI | Go (бинарник) | GitHub only | Free limited / $19+/мес | 3/10 |
