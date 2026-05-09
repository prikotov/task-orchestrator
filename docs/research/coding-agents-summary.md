# Coding Agents — Сводная таблица сравнения

**Дата создания:** 2026-05-09
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

| # | Критерий | Pi Coding Agent | Codex CLI | Gemini CLI |
|---|----------|-----------------|-----------|-------------|
| 1 | Системный промпт (замена/дополнение) | ✅ Полная замена + дополнение | ⚠️ Полная замена через файл (нет append) | ⚠️ Полная замена через env (нет append) |
| 2 | Промпт агента / роль | ✅ Через --append-system-prompt | ⚠️ Через user prompt или profiles | ⚠️ Через user prompt (нет append) |
| 3 | Скиллы | ✅ Agent Skills standard, автосканирование | ⚠️ Глобальные скиллы, нет CLI-фильтрации | ⚠️ Agent Skills standard, нет CLI-фильтрации |
| 4 | AGENTS.md | ✅ Автообнаружение AGENTS.md + CLAUDE.md | ✅ Автообнаружение AGENTS.md | ⚠️ GEMINI.md из коробки, AGENTS.md через settings |
| 5 | `.agents/skills/` автосканирование | ✅ Из коробки | ❌ Не поддерживается | ✅ Из коробки |
| 6 | Запуск как сабагент (JSON) | ✅ --mode json, --no-session | ⚠️ --json, --ephemeral (бедная телеметрия) | ✅ -o stream-json, --yolo, богатая телеметрия |
| 7 | Токены и стоимость | ✅ Полная телеметрия в JSONL | ⚠️ TUI status line, JSONL — не подтверждено | ✅ Полная телеметрия (токены, latency, файлы) |
| 8 | Free tier | ✅ MIT, стоимость зависит от LLM-провайдера | ⚠️ Apache-2.0, OpenAI требует подписку | ✅ 60 RPM / 1000 запросов/день, Google OAuth |
| 9 | Провайдеры и модели | ✅ 20+ провайдеров, BYOK, Ollama, LM Studio | ⚠️ OpenAI + OSS (Ollama, LM Studio) + BYOK | ❌ Только Google Gemini |
| 10 | Лицензия | ✅ MIT | ✅ Apache-2.0 | ✅ Apache-2.0 |
| | **Вердикт** | **✅ Подходит (10/10)** | **⚠️ Частично подходит (6/10)** | **⚠️ Частично подходит (6/10)** |

---

## Детальные отчёты

| Агент | Отчёт | Вердикт |
|-------|-------|---------|
| Pi Coding Agent | [pi-coding-agent-comparison.md](coding-agents/pi-coding-agent-comparison.md) | ✅ Подходит |
| Codex CLI | [codex-cli-comparison.md](coding-agents/codex-cli-comparison.md) | ⚠️ Частично подходит |
| Gemini CLI | [gemini-cli-comparison.md](coding-agents/gemini-cli-comparison.md) | ⚠️ Частично подходит |
