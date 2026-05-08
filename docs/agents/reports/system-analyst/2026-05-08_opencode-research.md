# Исследование OpenCode (opencode-ai/opencode) — сравнение с task-orchestrator

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-08
**Объект:** Репозиторий opencode-ai/opencode (Go, архивирован, продолжен как Charmbracelet Crush)
**Задача:** [TASK-research-opencode](../../../todo/TASK-research-opencode.todo.md)

---

## Резюме

OpenCode — терминальный AI-ассистент на Go (архивирован, продолжен как Crush). Исследован полностью: README, исходный код (~42K LOC), структура пакетов.

### Ключевые находки

1. **Архивирован:** OpenCode перенесён в Charmbracelet Crush (строка #1). Это предшественник Crush, не отдельный независимый проект
2. **Sub-agent delegation:** Coder → Task (read-only tools, cost propagation, session hierarchy) — простая и эффективная модель
3. **Permission system:** banned commands + safe read-only whitelist + per-session persistent — простой exec policy без Docker
4. **Auto-compact:** 95% context → LLM summarization → new session — подтверждённый тренд (10/22 проектов)
5. **Context injection:** 11 стандартных путей (OpenCode.md, CLAUDE.md, .cursorrules) — cross-tool compatibility
6. **Custom commands:** Markdown files с $NAME placeholders — reusable prompt templates

### Вердикт

🟡 **Заимствовать отдельные паттерны** (P2: permission system, context injection, custom commands, provider factory; P3: sub-agent delegation, auto-compact, file versioning, PubSub broker)

### Результаты

- Отчёт: `docs/research/framework-comparisons/opencode-comparison.md`
- Строка #22 в сводной таблице: `docs/research/agent-frameworks-summary.md`
- Тренды пересчитаны (21→22)
