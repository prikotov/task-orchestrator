# Отчёт по research-задаче SwarmForge

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-06-18
**Объект:** `unclebob/swarm-forge`, `docs/research/framework-comparisons/swarm-forge-comparison.md`, `docs/research/agent-frameworks-summary.md`
**Задача:** `todo/TASK-research-swarm-forge.todo.md`

---

## Выполнено

- Изучены источники SwarmForge на snapshot:
  - `main` `01d4ee498c7bc2bf4370b399e8c47ec55906a67b`;
  - `two-pack` `6484065446eb30473dfdde2e77097a985a47078c`;
  - `four-pack` `70c4792d1d648163ce8d7fe39f6d1e512f0e954d`;
  - `six-pack` `01343cebf5416fc37d0de41911ebaee47dfd04b7`.
- Создан comparison report: `docs/research/framework-comparisons/swarm-forge-comparison.md`.
- Обновлена сводная таблица: `docs/research/agent-frameworks-summary.md`, добавлена строка `SwarmForge (#29)`, статус заполнения `29 / 29`.
- Эпик и файл задачи не изменялись по указанию пользователя.

## Ключевые находки

SwarmForge — не coding agent и не LLM SDK, а desktop-first `tmux` swarm orchestrator для внешних CLI-агентов (`codex`, `claude`, `copilot`, `grok`). Сильные паттерны:

1. Layered constitution с явной override-семантикой (`local-*` дополняет, same-name article замещает).
2. Strict handoff draft schema (`awake`/`git_handoff`/`note`) с daemon-owned delivery.
3. Config-driven team topology через `swarmforge.conf`.
4. Pack presets (`two-pack`/`four-pack`/`six-pack`) под сложность задачи.
5. Role ownership boundaries (`Owns` / `Does Not Own` / `Handoff`).
6. Batch receive mode для equal-priority handoffs.
7. Git worktree per role для параллельной изоляции.

## Verdict

🟡 Заимствовать отдельные governance/swarm patterns.
🔴 Не использовать как dependency: стек zsh+tmux+Babashka, desktop-first terminal automation, нет retry/backoff, circuit breaker, quality gates, budget control и CI/server-first контракта.

## Проверки

- `make validate-todo` — успешно, 0 errors / 0 warnings.
- `make md-links` — успешно, all internal links valid.
- PHPUnit/Psalm не запускались: изменения docs-only, код/конфигурация runtime не затрагивались.
