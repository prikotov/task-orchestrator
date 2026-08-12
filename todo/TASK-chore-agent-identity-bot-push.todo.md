---
type: chore
created: 2026-08-03
value: V3
complexity: C1
priority: P1
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/chore-agent-identity-bot-push
pr: https://github.com/prikotov/task-orchestrator/pull/341
status: review
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
* **Где делаем:** проектные `AGENTS.md`, `docs/guide/agent-identity.md`, роль Тимлида, навыки `task-via-subagents`/`epic-via-subagents` и точечную диагностику.
* **Границы (Out of Scope):** изменение branch protection; код, тесты и конфиги продукта; внешние и генерируемые документы пакетов `prikotov/*`.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] `docs/guide/agent-identity.md` — warning и единый безопасный HTTPS-рецепт push installation token'ом GitHub App без вывода токена, SSH и credentials владельца; пункт в DoD.
- [x] Роль Тимлида и навыки — деструктивные операции только по отдельному явному согласию пользователя.
- [x] `AGENTS.md` и роль Тимлида — `agent:token` не выводить в output/stdout, использовать только command substitution или `eval`.

### 🟡 Should Have (Желательно)
- [x] Навыки фиксируют порядок commit → bot push → create PR → metadata commit → bot push → approval/merge.
- [x] Чек-лист перед commit — `git status` проверен, метаданные задачи обновлены.

### ⚫ Won't Have (Не будем делать)
- Изменение настроек branch protection репозитория (вне кода проекта).
- Правки в коде или конфигах продукта.
- Helper, автоматические тесты и изменение внешних/генерируемых документов `prikotov/*`.

## 4. Implementation Plan (План реализации)
1. [x] Добавить в `agent-identity.md` единый безопасный ручной рецепт bot push и пункт DoD.
2. [x] Точечно обновить проектные правила, роль Тимлида и навыки без изменения внешних/генерируемых документов.
3. [x] Зафиксировать согласие на деструктивные операции, защиту token output и metadata-before-approval.
4. [x] Удалить локальный эксперимент helper'а и его тесты из PR.
5. [x] Проверить документацию и задачу.

## 5. Definition of Done (Критерии приёмки)
- [x] Гайд `agent-identity.md` содержит warning и единственный безопасный рецепт push от бота.
- [x] Роль и навыки фиксируют bot push, metadata-before-approval и согласие на деструктивные операции.
- [x] Правило «токен не в output» зафиксировано в `AGENTS.md` и роли Тимлида.
- [x] PR содержит только проектные docs-only изменения; helper, тесты и внешние/генерируемые документы отсутствуют.
- [x] `make md-links`, `make validate-todo` и `make validate-language` — зелёные (предсуществующее предупреждение языка не блокирует проверку).

## 6. Verification (Самопроверка)
```bash
grep -n "push.*токеном бота\|http.extraheader" docs/guide/agent-identity.md
grep -n "деструктив" docs/agents/roles/team/team_lead_alex.ru.md docs/agents/skills/task-via-subagents/SKILL.md
grep -n "не выводить в output\|agent:token" AGENTS.md
make md-links
make validate-todo
make validate-language
```

## 7. Sources (Источники)
📚 **Внешние источники:**
- [Authenticating with an installation access token](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app-installation) — installation access tokens.

🔗 **Внутренние источники:**
- Ретроспектива: `docs/agents/team-retro/2026-08-03_20-20-orchestrator-tax-branch-protection-incident.md`.
- Предыдущее ретро по теме: `docs/agents/team-retro/2026-06-20_09-25-bot-account-agent-identity.md`.
- Гайд: `docs/guide/agent-identity.md`.

## 8. Comments (Комментарии)
Задача-фоллоуап к инциденту merge PR #333. Приоритет P1 — предотвратить повторение в следующих merge-циклах. Изменения docs-only; PHPUnit и Psalm пропущены по исключению. Локальный эксперимент с helper'ом и автоматическими тестами удалён из PR; внешние/генерируемые документы не изменяются.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-12 | Бэкендер (Левша) | PR #341 сужен до точечных проектных docs-only правил: единый безопасный ручной рецепт bot push с отключением shell/Git-трассировки до получения секрета, защита token output, согласие на деструктивные операции и metadata-before-approval. Локальный helper-эксперимент и тесты исключены. `md-links`, `validate-todo`, `validate-language` успешны; предсуществующее предупреждение языка не блокирует проверку. |
