# Отчёт по research-задаче Why Software Factories Fail

**Роль:** Аналитик (Шерлок)  
**Дата:** 2026-07-27  
**Объект:** `docs/research/approaches/why-software-factories-fail-comparison.md`, `docs/research/approaches-summary.md`  
**Задача:** `todo/done/TASK-research-why-software-factories-fail.todo.md`

---

## Выполнено

- Создан comparison-документ `docs/research/approaches/why-software-factories-fail-comparison.md` по 8 критериям методологии эпика.
- Создана сводная таблица `docs/research/approaches-summary.md` как первая таблица нового research-трека approaches (подходы/processes SDLC/PDLC).
- Зафиксирован маппинг ключевых тезисов Dex Horthy на артефакты task-orchestrator:
  - `AGENTS.md`;
  - роли `system_analyst_sherlock`, `system_architect_gandalf`, `system_architect_loki`, `code_reviewer_backend_puaro`, `qa_backend_house`;
  - skills (скиллы) `task-via-subagents`, `epic-via-subagents`, `agent-report`;
  - `docs/conventions/*` и `docs/guide/architecture.md`;
  - `docs/todo-md/templates/task.md` и `docs/todo-md/templates/epic.md`.
- Отдельно проверена гипотеза тимлида про `program design`.

## Проверенные источники

- `https://tonybai.com/2026/07/27/why-software-factories-fail-harness-engineering-not-enough/`
- `https://www.humanlayer.dev/blog/advanced-context-engineering`
- `https://www.youtube.com/watch?v=Ib5GBkD555M`
- `https://www.faros.ai/research/ai-acceleration-whiplash`
- Локальные документы проекта: `AGENTS.md`, `todo/AGENTS.md`, `docs/agents/roles/team/`, `docs/agents/skills/`, `docs/conventions/index.md`, `docs/todo-md/templates/*`.

## Существенные выводы

1. Подход Horthy валидирует текущую стратегию task-orchestrator как `lit factory` (освещённая фабрика), а не `dark factory` (безлюдная фабрика).
2. Тезис `harness engineering is not enough` не обесценивает продукт: он уточняет позиционирование как `lit-factory orchestration` — harness + roles + artifacts + quality gates + human approval.
3. Гипотеза тимлида подтверждена: `program design` (типы, сигнатуры методов, графы вызовов) у нас явно не формализован как planning-артефакт.
4. Тезисы про `RL` (обучение с подкреплением) и model training (обучение моделей) отделены от применимых процессных тезисов. Для нас они информационные: объясняют, почему нельзя заменять human review автоматическим loop (циклом).

## Рекомендации

- Создать отдельную процессную задачу на добавление секции `Program Design` в task template для C3+ code tasks.
- В документации и позиционировании продукта избегать обещания «полностью автономной software factory»; использовать формулировку `lit-factory orchestration`.
- Для сложных задач дополнительно ревьюить research/plan как high-leverage artifacts (артефакты с высоким рычагом влияния), а не только итоговый код.

## Проверка согласованности

- Артефакты research-задачи созданы по требуемым путям.
- Сводная таблица содержит 1 / N исследование и строку #1.
- Ссылки на локальные файлы в основных документах проверены вручную при составлении маппинга; дополнительная машинная проверка ссылок выполнена отдельной командой.

## Проверки

- PHPUnit/Psalm не запускались: изменения docs-only (только документация), код/конфигурация/скрипты не затронуты.
