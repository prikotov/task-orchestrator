# Отчёт по research-задаче omp (Oh My Pi)

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-07-24
**Объект:** `docs/research/coding-agents/omp-comparison.md`, `docs/research/coding-agents-summary.md`, `todo/done/EPIC-research-coding-agents-comparison.md`, `todo/done/TASK-research-omp-coding-agent.todo.md`
**Задача:** `todo/done/TASK-research-omp-coding-agent.todo.md`

---

## Выполнено

- Создан детальный отчёт `docs/research/coding-agents/omp-comparison.md` по 10 критериям К1–К10.
- Обновлена сводка `docs/research/coding-agents-summary.md` до 18 исследований:
  - omp добавлен во все секции;
  - omp поставлен на #1 как надмножество Pi;
  - Pi сохранён как #2 baseline/fallback;
  - Top-3 обновлён: omp → Pi → Qwen Code.
- Задача перенесена в `todo/done/TASK-research-omp-coding-agent.todo.md` для соответствия ссылке в отчёте.
- Эпик `todo/done/EPIC-research-coding-agents-comparison.md` обновлён Stage 1i и счётчиками 18 исследований.

## Проверенные источники

- `https://omp.sh/`
- `https://github.com/can1357/oh-my-pi` (`README.md`, `AGENTS.md`, структура `packages/`, `crates/`, `docs/`)
- `https://www.npmjs.com/package/@oh-my-pi/pi-coding-agent`
- npm registry metadata и downloads API для v17.1.1

## Существенная аналитическая оговорка

В первоисточниках v17.1.1 подтверждены `--system-prompt`, `--append-system-prompt`, `--mode json`, `--mode rpc`, `--no-session`, `--no-skills`, но не подтверждены exact Pi flags `--skill <path>` и `--no-context-files`. Поэтому в отчёте зафиксирована рекомендация перед production-заменой прогнать smoke/contract tests и подключать скиллы через `.agents/skills` symlink или `skills.customDirectories`.

## Проверка согласованности

- Локальные Markdown-ссылки в изменённых файлах проверены скриптом: все существуют.
- Счётчики в summary согласованы: 18 исследований, 14 open source, 4 proprietary.
- Вердикт omp согласован: ✅ Подходит (10/10), #1.

## Проверки

- PHPUnit/Psalm не запускались: изменения docs-only (документация и todo-файлы), код/конфигурация/скрипты не затронуты.
