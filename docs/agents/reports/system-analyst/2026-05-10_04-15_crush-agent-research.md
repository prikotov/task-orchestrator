# Исследование Crush (Charmbracelet) как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-10
**Объект:** Crush v0.66.1 (`github.com/charmbracelet/crush`, Go)
**Задача:** [TASK-research-crush-agent](../../../../todo/done/TASK-research-crush-agent.todo.md)

---

## Краткое резюме

Исследован Crush (Charmbracelet) — терминальный AI-кодинг-ассистент на Go. Оценён по 10 критериям для интеграции как сабагент с ролями и скиллами.

**Вердикт:** ⚠️ Частично подходит (6/10)

**Ключевые находки:**
- ✅ Agent Skills standard с полноценным discovery + lazy loading
- ✅ Широкая поддержка контекстных файлов (AGENTS.md + CRUSH.md + CLAUDE.md + GEMINI.md + .cursorrules)
- ✅ 20+ LLM-провайдеров, BYOK, Ollama, LM Studio
- ❌ Нет JSON/JSONL streaming — `crush run` выводит только plain text
- ❌ Нет ephemeral-режима — сессии сохраняются в SQLite
- ❌ Нет CLI-флагов для управления системным промптом (`--system-prompt`, `--append-system-prompt`)
- ⚠️ FSL-1.1-MIT лицензия — допустима для внутреннего использования

**Файлы:**
- Отчёт: `docs/research/coding-agents/crush-agent-comparison.md`
- Сводная таблица: `docs/research/coding-agents-summary.md` (обновлена)

Полный отчёт: [crush-agent-comparison.md](../../../research/coding-agents/crush-agent-comparison.md)
