---
type: chore
created: 2026-08-03
value: V3
complexity: C1
priority: P1
depends_on:
epic:
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-chore-agent-identity-bot-push: Зафиксировать «push PR-веток только токеном бота» и правила из ретро branch-protection

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- При merge PR #333 по задаче `TASK-research-orchestrator-tax` всплыл инцидент. PR-ветка была запушена через SSH владельца, из-за чего branch protection (`require_last_push_approval: true` + `enforce_admins: true`) заблокировал merge — approve владельца не засчитывался, а `--admin` блокировался. Потребовалось пересоздание PR (#332 → #333) с чистой push-историей от бота. Дополнительно выявились самовольное закрытие PR и попадание installation token в CLI output.
- Корень. В гайде `docs/guide/agent-identity.md` описана концепция GitHub App, но не зафиксировано явно, что PR-ветки должен пушить бот, и нет рабочего рецепта push от бота. В ролях и скиллах нет правила про деструктивные операции только по согласию пользователя и про недопустимость вывода токена в output.
- Подробности и 4 предложения — в ретроспективе `docs/agents/team-retro/2026-08-03_20-20-orchestrator-tax-branch-protection-incident.md`.

### Варианты или путь решения (Solution Sketch)
- Дополнить гайд `agent-identity.md` разделом про push от бота (warning + рецепт через `http.extraHeader` или изолированный git-config).
- Дополнить роль Тимлида и skill `task-via-subagents` правилом про деструктивные операции только по согласию пользователя.
- Дополнить `AGENTS.md` (раздел «Работа с секретами») и роль Тимлида правилом — `agent:token` не выводить в output.
- Уточнить в `task-via-subagents` порядок — `pr: '#NNN'` обновлять до merge.

### Ожидаемый результат (Expected Result)
- Гайд `agent-identity.md` явно предупреждает, что push PR-веток только токеном бота, никогда SSH владельца; дан рабочий рецепт.
- Роли и скиллы фиксируют правила про деструктивные операции и токен.
- Будущие merge-циклы не повторяют инцидент.

## 1. Concept and Goal (Концепция и Цель)
### Goal (Цель по SMART)
Зафиксировать в документации и ролях проекта правила, выявленные ретроспективой `2026-08-03_20-20-orchestrator-tax-branch-protection-incident.md`, чтобы исключить повторение трех классов ошибок: push PR-веток через SSH владельца, самовольные деструктивные операции, попадание installation token в output.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `docs/guide/agent-identity.md`, `docs/agents/roles/team/team_lead_alex.ru.md`, `docs/agents/skills/task-via-subagents/SKILL.md`, `AGENTS.md`.
* **Границы (Out of Scope):** изменение настроек branch protection репозитория; код и конфиги продукта.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `docs/guide/agent-identity.md` — раздел «Push PR-веток только токеном бота» с warning и рабочим рецептом (`GIT_CONFIG_GLOBAL=/dev/null` + `http.extraHeader` с installation token; пояснение, что `gh auth git-credential` отдаёт token human-аккаунта и его надо отключать). Пункт в чек-лист DoD.
- [ ] Роль Тимлида (`team_lead_alex.ru.md`, мини-чеклист) + skill `task-via-subagents` (Шаг 6) — правило про деструктивные операции (close PR, delete branch, force-push, mass move/delete) только по явному согласию пользователя.
- [ ] `AGENTS.md` (раздел «Работа с секретами») + роль Тимлида — `agent:token` не выводить в output/stdout, только `GH_TOKEN=$(...)` или `eval "$(... --format=env)"`.

### 🟡 Should Have (Желательно)
- [ ] `task-via-subagents` (Шаг 6) — порядок commit → push → create PR → amend `pr: '#NNN'` → push → merge.
- [ ] Чек-лист перед commit (из ретро 2026-06-20, повтор) — `git status` чист, метаданные задачи обновлены.

### ⚫ Won't Have (Не будем делать)
- Изменение настроек branch protection репозитория (вне кода проекта).
- Правки в коде или конфигах продукта.

## 4. Implementation Plan (План реализации)
1. [ ] Дополнить `docs/guide/agent-identity.md` разделом про push от бота (warning + рецепт + пункт DoD).
2. [ ] Дополнить роль Тимлида `docs/agents/roles/team/team_lead_alex.ru.md` (мини-чеклист) и skill `docs/agents/skills/task-via-subagents/SKILL.md` (Шаг 6) правилом деструктивных операций по согласию.
3. [ ] Дополнить `AGENTS.md` (раздел «Работа с секретами») и роль Тимлида правилом про токен не в output.
4. [ ] Уточнить порядок `pr: '#NNN'` в `task-via-subagents`.
5. [ ] Проверить `make md-links` и `make validate-language`.

## 5. Definition of Done (Критерии приёмки)
- [ ] Гайд `agent-identity.md` содержит warning и рецепт push от бота.
- [ ] Роль и скиллы фиксируют правило деструктивных операций по согласию.
- [ ] Правило «токен не в output» зафиксировано в `AGENTS.md` и роли Тимлида.
- [ ] `make md-links` — зелёный.

## 6. Verification (Самопроверка)
```bash
grep -n "push.*токеном бота\|http.extraheader" docs/guide/agent-identity.md
grep -n "деструктив" docs/agents/roles/team/team_lead_alex.ru.md docs/agents/skills/task-via-subagents/SKILL.md
grep -n "не выводить в output\|agent:token" AGENTS.md
make md-links
```

## 7. Sources (Источники)
📚 **Внешние источники:**
- [Authenticating with an installation access token](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app-installation) — installation access tokens.

🔗 **Внутренние источники:**
- Ретроспектива: `docs/agents/team-retro/2026-08-03_20-20-orchestrator-tax-branch-protection-incident.md`.
- Предыдущее ретро по теме: `docs/agents/team-retro/2026-06-20_09-25-bot-account-agent-identity.md`.
- Гайд: `docs/guide/agent-identity.md`.

## 8. Comments (Комментарии)
Задача-фоллоуап к инциденту merge PR #333. Приоритет P1 — предотвратить повторение в следующих merge-циклах. Документация только (docs-only), PHPUnit и Psalm пропускаются.
