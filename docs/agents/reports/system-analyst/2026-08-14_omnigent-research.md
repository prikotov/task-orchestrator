# Отчёт Аналитика: omnigent-ai/omnigent research

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-08-14
**Объект:** `omnigent-ai/omnigent` — open-source meta-harness (Python, Apache-2.0, status: alpha) над внешними coding-агентами. Snapshot ветки `main` — commit `bee2b751`, дата анализа 2026-08-14; PyPI `omnigent` `0.9.0` (`Development Status :: 3 - Alpha`).
**Задача:** `todo/done/TASK-research-omnigent.todo.md` → `todo/done/EPIC-research-agent-frameworks-comparison.md`, стадия `1l`.

---

## Verdict (вердикт)

🟡 **Заимствовать отдельные паттерны** / 🔴 **Не использовать как dependency (зависимость).**

**Ценные паттерны для адаптации:**

- **meta-harness abstraction** — единый интерфейс над Claude Code/Codex/Cursor/OpenCode/Hermes/Pi + custom YAML-агенты; прямой мост к нашему coding-agents-эпику и runner/subagent-модели.
- **custom agents в YAML** — богатая параллель нашим ролям/`config/chains.yaml`.
- **policies/governance** — approval-gates (пауза перед рискованными действиями), spend caps (лимиты расходов), tool limits (лимиты инструментов) с гранулярным scope server/agent/chat; релевантно quality gates/budget.
- **cloud + OS sandboxing** — 10 cloud providers (Modal/E2B/K8s/…) + bwrap/seatbelt/Job Objects + L7 egress; релевантно `TASK-feat-docker-sandboxing`.
- **cross-vendor multi-agent supervision** — review-between-agents (взаимный ревью агентов), task split (разбиение задачи); релевантно нашему review/сабагентам.

**Почему не dependency:** Python (не PHP/Symfony), **alpha** (незрелый, API/поведение меняются), multi-device collaboration-платформа (phone/desktop/co-drive), resilience (retry/circuit breaker/budget/fix_iterations) — на уровне platform/sandbox/policy, а не нашего chain-уровня.

## Источники

- [omnigent-ai/omnigent — GitHub](https://github.com/omnigent-ai/omnigent)
- [omnigent — README (capabilities, harnesses, sandboxes, policies)](https://github.com/omnigent-ai/omnigent#readme)
- [omnigent — PyPI](https://pypi.org/project/omnigent/)
- [omnigent.ai — сайт + download desktop app](https://omnigent.ai)
- [.github/agents/*.yaml — custom agent definitions](https://github.com/omnigent-ai/omnigent/tree/main/.github/agents)

## Изменённые файлы

- `docs/research/framework-comparisons/omnigent-comparison.md` — comparison-отчёт (создан ранее).
- `docs/research/agent-frameworks-summary.md` — строка `omnigent` (#33), счётчик и затронутые тренды (обновлён ранее).
- `todo/done/EPIC-research-agent-frameworks-comparison.md` — стадия `1l`, change history; пометка «Статус: review».
- `todo/done/TASK-research-omnigent.todo.md` — статус `review`, change history, пункты плана/DoD/источников.
- `docs/agents/reports/system-analyst/2026-08-14_omnigent-research.md` — этот отчёт.

## Итог

Артефакты исследования готовы к review. Задача переведена в `review` (не `done`) — ожидает review (проверки), одобрения и создания PR. Commit/push/PR не выполнялись.
