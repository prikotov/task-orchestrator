# Исследование OpenCode CLI для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-09
**Объект:** OpenCode v1.3.17 (SST/opencode-ai)
**Задача:** [TASK-research-opencode-cli](../../../todo/TASK-research-opencode-cli.todo.md)

---

## Результат

### Вердикт: ⚠️ Частично подходит (7/10)

OpenCode CLI частично подходит для использования как сабагент с нашей системой ролей и скиллов.

### Сводка по 10 критериям

| # | Критерий | Оценка | Комментарий |
|---|----------|--------|-------------|
| 1 | Системный промпт | ✅ | Агенты `.opencode/agent/*.md` + `instructions[]` |
| 2 | Промпт агента / роль | ✅ | `--agent <name>`, изоляция tools/permissions |
| 3 | Скиллы | ⚠️ | Автосканирование, но нет назначения скиллов агентам |
| 4 | AGENTS.md | ✅ | Автообнаружение AGENTS.md + CLAUDE.md |
| 5 | `.agents/skills/` | ✅ | Автосканирование из коробки |
| 6 | Запуск как сабагент | ⚠️ | JSON-режим есть, но бедные события, нет ephemeral |
| 7 | Токены и стоимость | ✅ | Полная телеметрия + `opencode stats` CLI |
| 8 | Free tier | ✅ | MIT, 5 бесплатных моделей OpenCode Zen |
| 9 | Провайдеры и модели | ✅ | 75+ провайдеров через models.dev |
| 10 | Лицензия | ✅ | MIT |

### Ключевые находки

**Сильные стороны:**
- Полноценная система кастомных агентов с изоляцией tools и permissions
- 75+ провайдеров через models.dev + 5 бесплатных моделей
- Автообнаружение AGENTS.md и CLAUDE.md
- Автосканирование `.agents/skills/` и `.claude/skills/`

**Ограничения (vs pi):**
- JSON-режим беднее: нет событий tool-вызовов
- Нет ephemeral-режима (сессия всегда в SQLite)
- Нет CLI-аргументов `--system-prompt` / `--append-system-prompt`
- Нет назначения разных скиллов разным агентам

### Файлы

- Отчёт: [docs/research/coding-agents/opencode-cli-comparison.md](../../../docs/research/coding-agents/opencode-cli-comparison.md)
- Сводная таблица: [docs/research/coding-agents-summary.md](../../../docs/research/coding-agents-summary.md)
