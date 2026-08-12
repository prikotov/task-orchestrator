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
status: done
---

# TASK-chore-agent-identity-bot-push: Зафиксировать «push PR-веток только токеном бота» и правила из ретро branch-protection

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- При merge PR #333 по задаче `TASK-research-orchestrator-tax` всплыл инцидент. PR-ветка была запушена через SSH владельца, из-за чего branch protection (`require_last_push_approval: true` + `enforce_admins: true`) заблокировал merge — approve владельца не засчитывался, а `--admin` блокировался. Потребовалось пересоздание PR (#332 → #333) с чистой push-историей от бота. Дополнительно выявились самовольное закрытие PR и попадание installation token в CLI output.
- Корень. В гайде `docs/guide/agent-identity.md` описана концепция GitHub App, но не зафиксировано явно, что PR-ветки должен пушить бот, и нет рабочего рецепта push от бота. В ролях и скиллах нет правила про деструктивные операции только по согласию пользователя и про недопустимость вывода токена в output.
- Подробности и 4 предложения — в ретроспективе `docs/agents/team-retro/2026-08-03_20-20-orchestrator-tax-branch-protection-incident.md`.

### Варианты или путь решения (Solution Sketch)
- Дополнить гайд `agent-identity.md` разделом про push от бота (warning + рецепт через `http.extraHeader` или изолированный git-config).
- Дополнить локальный `AGENTS.md` правилами про деструктивные операции только по согласию пользователя и запрет вывода `agent:token`.
- Не менять поставляемые внешним потребителям роли и навыки: локальная политика репозитория не является контрактом библиотеки.
- Зафиксировать порядок обновления `pr: '#NNN'` до merge только в локальном `AGENTS.md`.

### Ожидаемый результат (Expected Result)
- Гайд `agent-identity.md` явно предупреждает, что push PR-веток только токеном бота, никогда SSH владельца; дан рабочий рецепт.
- Локальный `AGENTS.md` фиксирует правила про деструктивные операции и токен; поставляемые роли и навыки остаются без изменений.
- Будущие merge-циклы не повторяют инцидент.

## 1. Concept and Goal (Концепция и Цель)
### Goal (Цель по SMART)
Зафиксировать в локальной политике сопровождения репозитория правила, выявленные ретроспективой `2026-08-03_20-20-orchestrator-tax-branch-protection-incident.md`, чтобы исключить push PR-веток через SSH владельца и попадание installation token в output, не навязывая этот процесс внешним потребителям библиотеки.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** локальный `AGENTS.md`, опциональный гайд механизма `docs/guide/agent-identity.md`, точечную диагностику и файл задачи.
* **Границы (Out of Scope):** изменение branch protection; код, тесты и конфиги продукта; consumer-facing роли/навыки; внешние и генерируемые документы пакетов `prikotov/*`.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] `docs/guide/agent-identity.md` — warning и единый безопасный HTTPS-рецепт push installation token'ом GitHub App без вывода токена, SSH и credentials владельца; пункт в DoD.
- [x] `AGENTS.md` — локальная политика bot push, деструктивных операций только по отдельному согласию и запрета token output.
- [x] Гайд явно указывает, что рецепт относится к локальному режиму этого репозитория, а имя GitHub App настраивается владельцем.

### 🟡 Should Have (Желательно)
- [x] Точечная диагностика ссылается на единый локальный рецепт без дублирования команд.

### ⚫ Won't Have (Не будем делать)
- Изменение настроек branch protection репозитория (вне кода проекта).
- Правки в коде или конфигах продукта.
- Helper, автоматические тесты и изменение внешних/генерируемых документов `prikotov/*`.
- Consumer-facing роли и навыки, поставляемые внешним потребителям.

## 4. Implementation Plan (План реализации)
1. [x] Добавить в `agent-identity.md` единый безопасный ручной рецепт bot push и пункт DoD.
2. [x] Точечно обновить только локальную политику репозитория и опциональные guide/troubleshooting.
3. [x] Не менять consumer-facing роли/навыки и внешние/генерируемые документы.
4. [x] Удалить локальный эксперимент helper'а и его тесты из PR.
5. [x] Проверить документацию и задачу.

## 5. Definition of Done (Критерии приёмки)
- [x] Гайд `agent-identity.md` содержит warning и единственный безопасный рецепт push от бота.
- [x] Локальный `AGENTS.md` фиксирует bot push, token output и согласие на деструктивные операции.
- [x] Consumer-facing роли/навыки восстановлены без изменений относительно `main`.
- [x] PR содержит только проектные docs-only изменения; helper, тесты и внешние/генерируемые документы отсутствуют.
- [x] `make md-links`, `make validate-todo` и `make validate-language` — зелёные (предсуществующее предупреждение языка не блокирует проверку).

## 6. Verification (Самопроверка)
```bash
grep -n "push.*токеном бота\|http.extraheader" docs/guide/agent-identity.md
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
Задача-фоллоуап к инциденту merge PR #333. Приоритет P1 — предотвратить повторение в локальном процессе сопровождения репозитория. Изменения docs-only; PHPUnit и Psalm пропущены по исключению. Локальный эксперимент с helper'ом и автоматическими тестами удалён из PR; consumer-facing роли/навыки и внешние/генерируемые документы не изменяются.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-12 | Бэкендер (Левша) | После пользовательского approval задача переведена в `done` и подготовлена к merge. PR #341 сужен до локального `AGENTS.md`, опциональных `agent-identity`/`troubleshooting` и трассировки задачи. Рецепт bot push отключает shell/Git-трассировку до получения секрета и явно не является контрактом для внешних потребителей; имя App настраиваемое. Helper-эксперимент, тесты и изменения consumer-facing ролей/навыков исключены. |
