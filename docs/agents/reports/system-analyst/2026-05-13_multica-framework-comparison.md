# Research Report: Multica Framework Comparison

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-13
**Объект:** Multica (github.com/multica-ai/multica, multica.ai)
**Задача:** [TASK-research-multica](../../../todo/TASK-research-multica.todo.md)

---

## Резюме

Исследование Multica завершено. Создан отчёт `docs/research/framework-comparisons/multica-comparison.md` и обновлена сводная таблица `docs/research/agent-frameworks-summary.md`.

### Ключевые находки

- 🟢 **Poisoned session detection** — уникальный паттерн error classification (не найден в 23 других проектах): классификация agent output/error для определения, можно ли возобновить session
- 🟢 **Autopilot (cron/webhook триггеры)** — модель для scheduled chain execution
- 🟢 **Runtime health + admission check** — дополнение к circuit breaker
- 🟡 Multica — **platform layer**, принципиально другой уровень, чем task-orchestrator (execution engine)

### Вердикт

🟡 **Заимствовать отдельные паттерны.** Multica не подходит ни как dependency, ни как reference architecture — проекты решают разные задачи на разных уровнях абстракции. Multica = project management для human+agent teams, task-orchestrator = chain execution engine.

### Файлы

- Отчёт: `docs/research/framework-comparisons/multica-comparison.md`
- Сводная таблица: `docs/research/agent-frameworks-summary.md` (строка #24)
