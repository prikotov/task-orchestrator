# Отчёт: Oh My OpenAgent — исследование как фреймворк оркестрации (#23)

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-13
**Объект:** Oh My OpenAgent v4.0.0 — plugin для OpenCode CLI
**Задача:** Дополнить исследование OmO отчётом в формате agent-frameworks

---

## Выполненные действия

1. Прочитан существующий отчёт coding-agents: `docs/research/coding-agents/oh-my-openagent-comparison.md` (10 критериев сабагента)
2. Прочитано архитектурное ревью Гэндальфа: `docs/research/coding-agents/oh-my-openagent-review.md`
3. Прочитан формат-образец: `docs/research/framework-comparisons/crush-comparison.md`
4. Создан новый отчёт: `docs/research/framework-comparisons/oh-my-openagent-comparison.md`
5. Обновлена сводная таблица: `docs/research/agent-frameworks-summary.md` (22/22 → 23/23)

## Ключевые результаты

### Новый файл
- `docs/research/framework-comparisons/oh-my-openagent-comparison.md` (31 KB, ~700 строк)
- Фокус: архитектура оркестрации, не CLI-критерии
- Категория: `CLI-agent + multi-agent`
- 4 оси анализа: Orchestration Model, State Management, Error Handling, Extensibility
- Сравнение с task-orchestrator по 15 аспектам
- Паттерны для заимствования (на основе ревью Гэндальфа)

### Обновлённый файл
- `docs/research/agent-frameworks-summary.md`
- Строка #23: Oh My OpenAgent (OmO)
- Тренды пересчитаны (22→23): agent loop 16/23, SKILL.md 15/23, MCP 16/23, sub-agents 15/23, compression 11/23
- Добавлены рекомендации: IntentGate (P2), Skill-Embedded Requires (P2), Category-based Runner Resolution (P2), Per-role Permissions (P2), Proactive Context Management (P3), Team Mode для DynamicLoop (P3)

### Вердикт: 🟡 Заимствовать отдельные паттерны

**Заимствовать (P2):** IntentGate, Skill-Embedded Requires
**Наблюдать (P2-P3):** Category-based Runner Resolution, Per-role Permissions, Proactive Context Management
**R&D (пост-MVP):** Team Mode для DynamicLoop
**Антипаттерн:** Dual-Prompt (нарушение слоистой архитектуры)

## Git

- Ветка: `task/research-oh-my-openagent`
- Коммит: `e125469` — `feat(research): add Oh My OpenAgent framework comparison (#23)`
- Push: выполнен в `origin/task/research-oh-my-openagent`
