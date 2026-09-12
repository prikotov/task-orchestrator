---
type: chore
created: 2026-09-12 15:38:47 (1789227527)
due: 
started: 2026-09-12 15:39:27 (1789227567)
completed: 2026-09-12 15:46:16 (1789227976)
cancelled: 
value: V1
complexity: C0
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/gitignore-pi-local-settings
pr: https://github.com/prikotov/task-orchestrator/pull/389
status: done
---

# TASK-chore-ignore-pi-settings: Игнорировать локальные настройки pi-агента в Git

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В рабочей копии проекта появились личные настройки pi-агента (каталог `.pi/` с моделью и уровнем ризонинга по умолчанию). Git их не игнорирует, поэтому они видны в `git status` как неотслеживаемые и рискуют случайно попасть в коммит: у каждого разработчика свои умолчания, в общем репозитории им не место.

### Варианты или путь решения (Solution Sketch)
- Добавить `/.pi/` в `.gitignore` — по той же схеме, как уже игнорируются другие локальные артефакты агента (`/.agents/`).

### Ожидаемый результат (Expected Result)
- `git status` не показывает каталог `.pi/`; личные настройки модели pi не попадают в репозиторий ни случайно, ни через `git add .`.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как разработчик, я хочу, чтобы мои локальные настройки pi-агента игнорировались Git, чтобы личная конфигурация модели не попадала в общие коммиты и не создавала шум в `git status`.

### Цель по SMART (Goal)
- Немедленно исключить каталог `.pi/` из отслеживания Git одной записью `/.pi/` в `.gitignore`; игнорирование подтверждается `git check-ignore`, файлы `.pi/` не участвуют в индексе и коммитах.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `.gitignore` в корне репозитория.
*   **Текущее поведение:** `.pi/settings.json` отображается в `git status` как неотслеживаемый файл.
*   **Границы (Out of Scope):** не меняем остальные правила `.gitignore`; не коммитим сами настройки; не трогаем глобальные настройки pi (`~/.pi/agent/settings.json`).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] В `.gitignore` добавлена запись `/.pi/` с поясняющим комментарием.
- [ ] `git check-ignore -v .pi/settings.json` подтверждает, что файл игнорируется правилом из `.gitignore`.
- [ ] `git status` не содержит файлов из `.pi/`.
### ⚫ Won't Have (Не будем делать)
- Не переносим `.pi/settings.json` в репозиторий и не документируем его как командный контракт.

## 4. План реализации (Implementation Plan)
1. [x] Добавить секцию `# Local AI-agent settings (pi: model/thinking defaults per project)` с правилом `/.pi/` в `.gitignore`.
2. [x] Проверить `git check-ignore -v .pi/settings.json` и чистоту `git status`.

## 5. Критерии приёмки (Definition of Done)
- [x] Правило `/.pi/` присутствует в `.gitignore`.
- [x] `git check-ignore` подтверждает игнорирование; `git status` не показывает `.pi/`.
- [x] Проверки `make check` пройдены (изменение не затрагивает код; секция обновится после прогона).

## 6. Самопроверка (Verification)
```bash
make check                                            # lint + валидация + тесты
php vendor/bin/todo-md validate todo/TASK-chore-ignore-pi-settings.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- Существенных рисков нет: изменение не влияет на код, тесты и конфигурации приложения.

## 8. Источники (Sources)

- Документация pi: настройки проекта `.pi/settings.json` (пакет `@earendil-works/pi-coding-agent`, `docs/settings.md`).

## 9. Комментарии (Comments)

- Локальный файл `.pi/settings.json` уже создан в рабочей копии (модель `glm-5.3`, уровень ризонинга `max`) — именно он мотивирует задачу.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-12 15:38:47 (1789227527) | Тимлид Алекс (pi) | Создание задачи |
| 2026-09-12 22:39:06 (1789227546) | Тимлид Алекс (pi) | Заполнение постановки; правка `.gitignore` выполнена |
