---
type: build
created: 2026-06-13
value: V2
complexity: C0
priority: P1
depends_on: []
epic:
author: codex-cli
assignee: codex-cli
branch: task/update-prikotov-dependencies
pr:
status: done
---

# TASK-build-normalize-prikotov-package-sources: Normalize Prikotov package source URLs

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда Composer фиксирует lockfile для внутренних пакетов Prikotov, я хочу хранить source URLs в HTTPS-формате и сохранить metadata support, чтобы установка зависимостей была воспроизводимой без SSH-доступа к GitHub.

### Goal (Цель по SMART)
Обновить `composer.lock` для пакетов `prikotov/coding-standard`, `prikotov/git-workflow` и `prikotov/todo-md`: заменить SSH source URLs на HTTPS source URLs и сохранить блоки `support` без изменения версий пакетов.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `composer.lock`.
*   **Текущее поведение:** В lockfile для части внутренних пакетов source URL указывает на SSH-remote `git@github.com:*`, что требует настроенный SSH-доступ при source-install сценариях.
*   **Границы (Out of Scope):** Не обновляем версии зависимостей, не меняем `composer.json`, не трогаем код приложения.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Source URLs внутренних пакетов Prikotov в `composer.lock` переведены на HTTPS.
- [x] Версии и references пакетов не изменены.
- [x] Локальные проверки `vendor/bin/phpunit` и `vendor/bin/psalm` успешны.
### 🟡 Should Have (Желательно)
- [x] Блоки `support.source` и `support.issues` сохранены для затронутых пакетов.
### 🟢 Could Have (Опционально)
- [ ] Дополнительно проверить clean install в отдельном окружении.
### ⚫ Won't Have (Не будем делать)
- [ ] Обновление зависимостей до новых версий.
- [ ] Изменение конфигурации Composer.

## 4. Implementation Plan (План реализации)
1. [x] Проверить diff `composer.lock`.
2. [x] Зафиксировать lockfile-изменение отдельным commit.
3. [x] Запустить PHPUnit.
4. [x] Запустить Psalm.
5. [x] Открыть PR в `main`.

## 5. Definition of Done (Критерии приёмки)
- [x] `composer.lock` содержит HTTPS source URLs для `prikotov/coding-standard`, `prikotov/git-workflow`, `prikotov/todo-md`.
- [x] Изменение опубликовано в PR.
- [x] Задача находится в `todo/done/`.

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Риск низкий: версии зависимостей и commit references не меняются.
- Если CI использует source-install через SSH-only доступ, HTTPS URL должен снизить зависимость от локальных SSH credentials.

## 8. Sources (Источники)
- `composer.lock`
- `docs/git-workflow/pull-request.md`
- `docs/git-workflow/commits.md`

## 9. Comments (Комментарии)
Задача оформлена постфактум для уже подготовленного dependency lockfile изменения, чтобы PR соответствовал правилам ведения задач.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-13 | codex-cli | Создание и закрытие задачи |
