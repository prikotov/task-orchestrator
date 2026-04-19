---
type: chore
created: 2026-04-19
value: V2
complexity: C2
priority: P2
depends_on: []
epic:
author:
assignee:
branch:
pr:
status: todo
---

# TASK-chore-bot-account-for-agent: Разделение идентичности AI-агента и владельца репо

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда AI-агент создаёт PR от имени `prikotov`, я (prikotov) не могу его аппрувить — GitHub запрещает approve собственных PR. Branch protection требует review, но обойти это нельзя. Нужно разделить идентичности, чтобы агент работал от отдельного аккаунта, а владелец мог контролировать мерж.

### Goal (Цель по SMART)
Настроить отдельную идентичность для AI-агента (бот-аккаунт или GitHub App), чтобы:
- PR создаются от имени бота → владелец видит кнопку Approve
- Branch protection работает: агент не может смержить без аппрува
- Коммиты подписаны ботом — видно что сделано агентом

## 2. Context and Scope (Контекст и Границы)
* **Проблема:** `gh` авторизован как `prikotov`. Все PR от его имени. GitHub не даёт approve свои PR. Branch protection блокирует merge без review.
* **Текущий обход:** мерж через `--admin`, что нарушает branch protection.
* **Границы (Out of Scope):**
  - Изменение branch protection rules
  - Изменение кода проекта

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Выбран подход: бот-аккаунт или GitHub App
- [ ] Настроена авторизация: `gh` работает от имени бота
- [ ] PR от бота → владелец (prikotov) может approve через UI
- [ ] Agent не может merge без approval
- [ ] Документировано: как переключать авторизацию, как обновлять токены

### 🟡 Should Have (Желательно)
- [ ] Короткоживущие токены (GitHub App) вместо long-lived PAT

### ⚫ Won't Have (Не будем делать)
- Изменение branch protection rules

## 4. Implementation Plan (План реализации)

### Вариант A: Бот-аккаунт (рекомендуемый для старта)
1. [ ] Создать GitHub-аккаунт (например, `prikotov-pi`)
2. [ ] Добавить в collaborators репо с правами write
3. [ ] Сгенерировать PAT (classic) с правами `repo`
4. [ ] Авторизовать `gh`: `gh auth login --with-token`
5. [ ] Проверить: создать тестовый PR от бота → аппрув от prikotov → merge
6. [ ] Зафиксировать в документации

### Вариант B: GitHub App (более безопасный)
1. [ ] Зарегистрировать GitHub App с permissions: contents (write), PR (write)
2. [ ] Сгенерировать private key (PEM)
3. [ ] Написать скрипт получения installation token через JWT
4. [ ] Настроить `gh` через прокидывание токена
5. [ ] Проверить: создать тестовый PR от App → аппрув от prikotov → merge
6. [ ] Зафиксировать в документации

## 5. Definition of Done (Критерии приёмки)
- [ ] AI-агент создаёт PR от имени бота/App
- [ ] Владелец видит кнопку Approve в UI
- [ ] Merge без approval невозможен (branch protection работает)
- [ ] Процесс переключения авторизации документирован

## 6. Verification (Самопроверка)
```bash
# Проверить кто авторизован
gh auth status
# Создать тестовый PR
gh pr create --head test-bot-pr --base main --title "test: bot PR" --body "test"
# Проверить что PR от бота, а не от prikotov
gh pr view --json author
```

## 7. Risks and Dependencies (Риски и зависимости)
- PAT long-lived — риск утечки. Mitigation: минимальные права, regular rotation
- GitHub App сложнее в настройке, но безопаснее (короткоживущие токены)
- Для приватных репо в организации бот-аккаунт занимает seat

## 8. Sources (Источники)

## 9. Comments (Комментарии)
Сравнение подходов:

| | Бот-аккаунт | GitHub App |
|---|---|---|
| Настройка | 10 мин | 30–60 мин |
| Безопасность | Средняя (PAT) | Высокая (короткоживущие токены) |
| Стоимость | Seat в платной org | Бесплатно |
| `gh` совместимость | Нативная | Нужен прокси/обёртка |

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-19 | Тимлид | Создание задачи |
