# Целевое исследование: система субагентов OpenCode (anomalyco/opencode)

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-08
**Объект:** Репозиторий [anomalyco/opencode](https://github.com/anomalyco/opencode), файлы: `tool/task.ts`, `agent/agent.ts`, `session/prompt.ts`, `session/session.ts`, `permission/index.ts`, `session/run-state.ts`, `effect/runner.ts`
**Задача:** Детальный разбор архитектуры субагентов OpenCode и её применимость к task-orchestrator

---

## Резюме

Проведён детальный анализ системы субагентов OpenCode. Результат записан в секции 3.4 и 3.4.1 файла `docs/research/framework-comparisons/opencode-comparison.md`.

### Ключевые находки

1. **Жизненный цикл субагента** — 5 стадий: none → created → running → completed/error/aborted. Запуск через TaskTool.execute() с полной изоляцией контекста.

2. **Изоляция контекста** — child session с чистой историей. Наследуются только deny rules, external_directory rules и model. История, system prompt, инструменты — изолированы.

3. **Permission inheritance** — deny rules parent + external_directory → child. task и todowrite запрещены по умолчанию. Рекурсивная делегация запрещена, если агент явно не разрешает через permission ruleset.

4. **Параллельность** — LLM инициирует параллельные tool calls → несколько child sessions → results merge на уровне LLM. Нет shared state между параллельными субагентами.

5. **Cost propagation** — изолированная per-session. Cost субагента НЕ прибавляется к cost родителя. Нет budget limit.

6. **Resume** — task_id → загрузка существующей child session из SQLite → продолжение agent loop с полной историей.

7. **Error handling** — catch в handleSubtask, parent НЕ падает. Error представляется как tool observation.

8. **Сравнение с task-orchestrator:**
   - Resume ≈ fix_iterations (parity)
   - BudgetVo + budget limit — у нас лучше
   - Изоляция ошибок sub-step — adopt pattern
   - Permission inheritance для runners — для future
   - Abort propagation — для future
   - Параллельные steps — для future

### Риски и ограничения

- OpenCode — TypeScript/Effect-TS проект, прямое заимствование кода невозможно
- Анализ основан на версии v1.14.41 (2026-05-08), API может измениться
- Некоторые детали (например, точная семантика primary_tools) определены как experimental
