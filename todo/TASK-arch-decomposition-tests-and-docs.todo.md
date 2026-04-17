---
type: refactor
created: 2026-04-17
value: V2
complexity: C2
priority: P2
depends_on:
  - TASK-arch-orchestrator-ports-and-adapters
epic: EPIC-arch-orchestrator-module-decomposition
author: Архитектор (Гэндальф)
assignee: Технический писатель
branch: task/arch-decomposition-tests-and-docs
pr:
status: in_progress
---

# TASK-arch-decomposition-tests-and-docs: Обновление тестов и документации после декомпозиции

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда разработчик читает архитектурную документацию или запускает тесты, он хочет видеть актуальную структуру модулей (AgentRunner + Orchestrator) и полную гарантию что декомпозиция ничего не сломала.

### Goal (Цель по SMART)
Обновить все тесты на новые namespace, актуализировать `docs/guide/architecture.md`, проверить отсутствие остаточных ссылок на старые пути.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `tests/`, `docs/`, `config/`
*   **Контекст:** После TASK-arch-orchestrator-ports-and-adapters структура модулей финализирована. Тесты уже обновлялись в предыдущих задачах, но здесь — финальная проверка и полировка.
*   **Границы (Out of Scope):**
    *   Новые тесты на мапперы — в предыдущей задаче
    *   Изменение логики

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `docs/guide/architecture.md` полностью переписана: 2 модуля, Port/Adapter, VO mapping
- [ ] Все тесты в `tests/Unit/Infrastructure/Service/AgentRunner/` обновлены на namespace `AgentRunner\`
- [ ] Все тесты в `tests/Unit/Domain/Service/AgentRunner/` обновлены
- [ ] Все тесты в `tests/Unit/Application/` обновлены (если нужно)
- [ ] Нет orphan-ссылок на старые namespace `Orchestrator\Domain\Service\AgentRunner\`
- [ ] PHPUnit green (все тесты)
- [ ] Psalm green
- [ ] PHPCS sniff green

### 🟡 Should Have (Желательно)
- [ ] ADR (Architecture Decision Record) в `docs/adr/` с обоснованием декомпозиции
- [ ] `docs/conventions/modules/index.md` дополнен примером двухмодульного бандла

### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- Реструктуризация тестов (перенос тестов AgentRunner в отдельный каталог) — это можно сделать позже

## 4. Implementation Plan (План реализации)
1. [ ] Запустить PHPUnit — убедиться что всё green после предыдущих задач
2. [ ] Запустить Psalm — убедиться что 0 errors
3. [ ] Проверить `grep -r "Orchestrator\\\\Domain\\\\Service\\\\AgentRunner" tests/` — если есть, обновить
4. [ ] Переписать `docs/guide/architecture.md`: структура 2 модулей, Port/Adapter, зависимости
5. [ ] Обновить `docs/guide/reliability.md` если упоминает AgentRunner
6. [ ] Создать `docs/adr/001-module-decomposition.md` — обоснование
7. [ ] Обновить `docs/conventions/modules/index.md` — пример двухмодульного бандла
8. [ ] Финальная проверка: PHPUnit + Psalm + PHPCS

## 5. Definition of Done (Критерии приёмки)
- [ ] `grep -r "Orchestrator\\\\Domain\\\\Service\\\\AgentRunner" src/ tests/` → 0 результатов
- [ ] PHPUnit green
- [ ] Psalm green
- [ ] `docs/guide/architecture.md` отражает 2 модуля + Port/Adapter

## 6. Verification (Самопроверка)
```bash
grep -r "Orchestrator\\\\Domain\\\\Service\\\\AgentRunner" src/ tests/ --include="*.php" | wc -l  # → 0
grep -r "Orchestrator\\\\Domain\\\\ValueObject\\\\AgentResult" src/ tests/ --include="*.php" | wc -l  # → 0
vendor/bin/phpunit
vendor/bin/psalm
php vendor/prikotov/coding-standard/bin/run-sniff-tests.php
```

## 7. Risks and Dependencies (Риски и зависимости)
- Зависит от TASK-arch-orchestrator-ports-and-adapters
- Если предыдущие задачи выполнены качественно, эта — минимальный объём правок

## 8. Sources (Источники)
- [docs/guide/architecture.md](docs/guide/architecture.md)
- [docs/conventions/modules/index.md](docs/conventions/modules/index.md)

## 9. Comments (Комментарии)
Заключительная задача эпика. Эпик можно закрывать после выполнения всех 4 задач.

## Инструкции для сабагента

**Роль:** docs/agents/roles/team/technical_writer.ru.md
**Ветка:** `task/arch-decomposition-tests-and-docs` (уже создана и активна)
**PR:** draft #11 из `task/arch-decomposition-tests-and-docs` в `task/arch-orchestrator-module-decomposition`

### Порядок действий
1. Переключись в ветку `task/arch-decomposition-tests-and-docs`: `git checkout task/arch-decomposition-tests-and-docs`
2. Реализуй задачу согласно описанию и критериям выше.
3. Следуй AGENTS.md и Конвенциям проекта.
4. Делай коммиты по Conventional Commits.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm` — оба должны пройти.
6. Запуш: `git push`.
7. Переведи PR из draft в ready: `gh pr ready 11`. Эта команда снимает флаг draft — PR становится готовым к мержу.

**НЕ создавай новый PR** — он уже существует.
**НЕ меняй base branch** — он уже указывает на `task/arch-orchestrator-module-decomposition`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-17 | Архитектор (Гэндальф) | Создание задачи |
