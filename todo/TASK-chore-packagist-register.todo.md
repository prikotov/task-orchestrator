---
# Metadata (Метаданные)
type: chore
created: 2026-04-23
value: V2
complexity: C2
priority: P0
depends_on: TASK-chore-composer-library-type
epic: EPIC-feat-standalone-cli
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-chore-packagist-register: Регистрация пакета на Packagist

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как PHP-разработчик, я хочу найти task-orchestrator на Packagist, чтобы установить его через `composer require prikotov/task-orchestrator`.

### Goal (Цель по SMART)
Зарегистрировать пакет `prikotov/task-orchestrator` на Packagist. Настроить auto-publish через GitHub webhook.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** packagist.org + GitHub repo settings
* **Предусловие:** TASK-chore-composer-library-type выполнен (type: library, bin: объявлен)
* **Границы:**
  - Не настраиваем CI pipeline (отдельная задача)
  - Не настраиваем Phar publishing

## 3. Requirements (Требования)
### 🔴 Must Have
- [ ] Пакет зарегистрирован на packagist.org
- [ ] `composer require prikotov/task-orchestrator` работает в чистом проекте
- [ ] GitHub webhook настроен для auto-update на Packagist
- [ ] Packagist API token добавлен в GitHub Secrets

### 🟡 Should Have
- [ ] Integration test: `composer require` в чистом окружении → `vendor/bin/task-orchestrator --version`

## 4. Implementation Plan
1. Зарегистрироваться/залогиниться на packagist.org
2. Submit package URL: `https://github.com/prikotov/task-orchestrator`
3. Настроить GitHub webhook для auto-publish
4. Проверить: `composer require prikotov/task-orchestrator` в чистом проекте

## 5. Definition of Done
- [ ] `composer require prikotov/task-orchestrator` устанавливает пакет
- [ ] `vendor/bin/task-orchestrator --version` выводит версию
- [ ] Packagist показывает актуальную версию

## 6. Verification
```bash
mkdir /tmp/test-require && cd /tmp/test-require
composer init --name=test/require
composer require prikotov/task-orchestrator
vendor/bin/task-orchestrator --version
```

## 7. Risks
- Нужен доступ к Packagist и GitHub repo settings (владелец проекта)

## 8. Sources
- [Packagist](https://packagist.org/)
- [RFC: cli-distribution-rfc.md](../docs/research/cli-distribution-rfc.md)

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-23 | Тимлид (Алекс) | Создание задачи из RFC brainstorm |
