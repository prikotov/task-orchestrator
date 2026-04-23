---
# Metadata (Метаданные)
type: chore
created: 2026-04-23
value: V2
complexity: C3
priority: P1
depends_on: TASK-chore-composer-library-type
epic: EPIC-feat-standalone-cli
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: todo
---

# TASK-chore-phar-build: Настройка сборки Phar через box-project/box

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как DevOps-инженер, я хочу скачать один файл .phar с GitHub Releases, чтобы использовать task-orchestrator в CI-пайплайне без установки Composer.

### Goal (Цель по SMART)
Создать `box.json.dist` для сборки Phar. Настроить CI step: при tag → собрать Phar → опубликовать на GitHub Releases. Best-effort канал: ошибка сборки Phar = warning, не блокирует релиз.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `box.json.dist`, `.github/workflows/`
* **Текущее поведение:** Phar не собирается
* **Границы (осознанный техдолг до v1.0):**
  - Без GPG-подписи (`@techdebt`)
  - Без Windows CI (`@techdebt`)
  - Без self-update команды (`@techdebt`)
  - Ошибка сборки = warning, не блокирует релиз

## 3. Requirements (Требования)
### 🔴 Must Have
- [ ] `box.json.dist` создан и настроен
- [ ] `box compile` → `task-orchestrator.phar` собирается локально
- [ ] `php task-orchestrator.phar --version` работает на Linux/macOS
- [ ] CI step: при tag → compile → publish на GitHub Releases

### 🟡 Should Have
- [ ] Smoke test в CI: `php task-orchestrator.phar --version`

### 🟢 Could Have
- [ ] `.phar` добавлен в `.gitignore`

## 4. Implementation Plan
1. Создать `box.json.dist` (composer bin + stub + exclude dev)
2. Протестировать локально: `box compile` → `php task-orchestrator.phar --version`
3. Добавить GitHub Actions step для сборки при tag
4. Smoke test в CI

## 5. Definition of Done
- [ ] `box compile` собирает Phar
- [ ] Phar запускается и выводит версию
- [ ] CI публикует Phar на GitHub Releases при tag

## 6. Verification
```bash
composer require --dev humbug/box
vendor/bin/box compile
php task-orchestrator.phar --version
```

## 7. Risks
- PHP 8.4 + Phar могут иметь edge cases — проверить
- Phar stub может потребовать настройки для Symfony DI

## 8. Sources
- [Box — Phar builder](https://github.com/box-project/box)
- [RFC: cli-distribution-rfc.md](../docs/research/cli-distribution-rfc.md) — Решение 3, investment tier

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-23 | Тимлид (Алекс) | Создание задачи из RFC brainstorm |
