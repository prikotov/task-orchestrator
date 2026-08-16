# Исследование DeepSeek Harness (`deepseek-ai/deepseek-harness`) как кандидата в сабагенты

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-08-16
**Объект:** `deepseek-ai/deepseek-harness` (`dsh`) — snapshot commit `47f9438` (master, 2026-08-13), npm `@deepseek-ai/dsh` 0.1.0-rc.6, PyPI `deepseek-harness-sdk` 0.1.0rc6
**Задача:** [TASK-research-deepseek-harness](../../../../todo/TASK-research-deepseek-harness.todo.md), эпик [EPIC-research-coding-agents-comparison](../../../../todo/done/EPIC-research-coding-agents-comparison.md), стадия 1l

---

## Резюме

`dsh` — open-source агентный харнес DeepSeek (TypeScript, MIT, ≈116.8k★ за 3 дня с создания) на фреймворке Cordis с архитектурой «everything is a plugin». Продуктовые поверхности: Web UI (`dsh web`), headless CLI (`dsh --profile headless "task"` → финальный текст в stdout, exit 0/1), JSON-RPC SDK (TS + Python `deepseek-harness-sdk` с bundled runtime), automation-only ACP-сервер.

**Вердикт: ⚠️ Частично подходит (7/10, 26/30); SDK — ✅ 9/10.** Строка #22 сводной таблицы, в рейтинге #6 (после OpenCode при равной сумме — из-за developer preview без релизов).

## Оценки по 10 критериям

| Критерий | Оценка | Ключевой факт |
|---|---|---|
| К1 Системный промпт | ⚠️ 2 | Реестр `PromptSection` с `complete: true` (полная замена), persona с `{{model}}`/`{{cwd}}`, per-child persona; SDK `DSH_SYSTEM_PROMPT`; CLI-флага нет (patch-слои) |
| К2 Роль | ⚠️ 2 | Persona-плагин, `!!js` env-выражения в `cordis.patch.yml`; механизма ролей как продукта нет |
| К3 Скиллы | ✅ 3 | `SKILL.md`-бандлы + flat `.md`, frontmatter `disable-model-invocation`/`user-invocable`, watcher, remote-провайдеры |
| К4 AGENTS.md | ✅ 3 | Эталон: AGENTS.md + CLAUDE.md + `.local`, walk-up до `.git`, user-global `~/.dsh/AGENTS.md`, бюджеты (64 KiB), отключение |
| К5 `.agents/skills/` | ✅ 3 | rank 200 project / rank 500 user + `.dsh/skills` + `customSkillDirs` |
| К6 Сабагент | ⚠️ 2 | CLI plain text (JSONL stdout нет); JSON-RPC SDK TS/Python ✅, ACP-сервер; таймауты (`timeoutMs`, AbortSignal); субагент-провайдеры codex/claude-code/acp/dsh-sdk |
| К7 Токены | ⚠️ 2 | token-meter (usage anchors + эвристика + positional breakdown); $ нет |
| К8 Free tier | ✅ 3 | Бесплатный MIT-инструмент, BYOK |
| К9 Провайдеры | ✅ 3 | Native DeepSeek (v4-flash/pro) + `dsh-llm-pi-ai` на `@earendil-works/pi-ai` ^0.82.1 (каталог как у Pi) + custom OpenAI-compatible (Ollama/vLLM/LM Studio), Bedrock/Vertex/Azure/Codex |
| К10 Лицензия | ✅ 3 | MIT |

## Главные находки

1. **К4/К5 — эталон в выборке из 22**: многослойное discovery скиллов с рангами и shadowing; AGENTS.md/CLAUDE.md(+.local) с byte-бюджетами — наш стек (`SKILL.md`, `.agents/skills`, AGENTS.md) подхватывается нативно.
2. **Программная поверхность первоклассна**: Python SDK (`DeepSeekHarness.run()`, персистентные JSONL-сессии, cancellation), TS JSON-RPC client/server; наш `watch-subagent.sh`-контракт (JSONL stdout) CLI не выполняет — интеграция через SDK-раннер.
3. **Обратная интеграция**: dsh сам запускает Codex (`codex app-server`) и Claude Code (Agent SDK) как субагентов — кандидат на «агрегатора» для мультиагентных цепочек.
4. **Риски**: developer preview (0 релизов, ломающие изменения задекларированы, npm 0.1.0-rc.6), $-стоимости нет, стабильного TUI нет (только Web UI).

## Рекомендации

- Стек сабагентов не менять (omp #1 / Pi fallback); dsh — в watch-list до первого тегированного релиза, затем пересмотр (кандидат в топ-5).
- Интеграция, если понадобится сейчас, — только через Python SDK с пиннованной версией и snapshot-коммитом; стоимость считать на нашей стороне по usage из JSONL-логов.

## Артефакты

- Отчёт: `docs/research/coding-agents/deepseek-harness-comparison.md`
- Сводная таблица (22/22): `docs/research/coding-agents-summary.md`
- Задача: `todo/TASK-research-deepseek-harness.todo.md`; эпик — стадия 1l

## Источники

- https://github.com/deepseek-ai/deepseek-harness (README, apps/cli + reference, docs/subsystems/*, docs/user/guide/*)
- https://www.npmjs.com/package/@deepseek-ai/dsh — 0.1.0-rc.6
- https://pypi.org/project/deepseek-harness-sdk/ — 0.1.0rc6
