---
type: chore
created: 2026-11-25 13:57:54 (1795589874)
due: 
started: 
completed: 
cancelled: 
value: V3
complexity: C1
priority: P3
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: 
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: todo
---

# TASK-techdebt-track-todo-agents-md: Закоммитить todo/AGENTS.md в git и убрать заглушку из CI doc-checks

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

`todo/AGENTS.md` — регламент работы с задачами (исходник правил, копируемых потребителю командой `init`) — намеренно исключён из git (`todo/.gitignore`). При этом на файл ссылаются: корневой `AGENTS.md`, 19 markdown-ссылок (10 с якорями) из активных документов. В чистом checkout файла нет — `make md-links` локально зелёный только потому, что каталог существует на машине сопровождения.

CI-задание `doc-checks` (PR TASK-build-doc-checks-ci) решило это заглушкой: в job создаётся временный `todo/AGENTS.md` с нужными якорями секций — ссылки валидируются структурно, но не по реальному содержимому. Заглушка помечена `@techdebt 2026-11-25` со ссылкой на эту задачу. Пуаро в ревью (2026-11-25, отчёт `docs/agents/reports/code-reviewer-backend/2026-11-25_13-54-build-doc-checks-ci-review.md`) указал: техдолг без задачи — нарушение; задача создана этим PR.

### Варианты или путь решения (Solution Sketch)

Заккоммитить реальный `todo/AGENTS.md` в git: убрать `AGENTS.md` из `todo/.gitignore`, добавить файл (он — источник правил для `init`; его содержание уже версионируется косвенно — идентичная копия лежит в vendor-пакете, но якоря ссылок есть только в локальной версии). В CI убрать шаг материализации заглушки (симлинки vendor-доков `docs/conventions|git-workflow|todo-md` — остаются, это другой механизм). Альтернатива (если владелец хочет сохранить файл вне git): убрать 19 ссылок из активных документов — хуже, теряется регламент из init-копии. Решение по умолчанию — закоммитить.

### Ожидаемый результат (Expected Result)

`todo/AGENTS.md` в git; заглушка из `.github/workflows/ci.yml` удалена; `make md-links` и CI `doc-checks` зелёные на чистом checkout; регламент доступен в свежем клоне (в т.ч. агентам, читающим правила задач).

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)

> Как сопровождение проекта, я хочу, чтобы `todo/AGENTS.md` версионировался в git, чтобы ссылки на регламент задач работали в любом клоне, а CI не нуждался в заглушках.

### Цель по SMART (Goal)

`todo/AGENTS.md` закоммичен, `todo/.gitignore` обновлён, заглушка материализации удалена из `doc-checks`; критерий: полный `make check` и CI `doc-checks` зелёные на чистом checkout, задача в `done`.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `todo/.gitignore`, `todo/AGENTS.md` (добавление в git), `.github/workflows/ci.yml` (удаление шага материализации заглушки).
*   **Текущее поведение:** файл исключён из git, CI материализует заглушку с якорями `#декомпозиция-задач`, `#процесс-работы-с-задачами`.
*   **Прецеденты:** `docs/.gitignore` игнорирует vendor-копии `docs/conventions|git-workflow|todo-md` — они остаются симлинками (другой механизм, не трогается).
*   **Критичные конвенции:** `make check` перед PR.
*   **Границы (Out of Scope):** правки симлинков vendor-доков; правки `init` пакета; правки 86/9 исторических файлов валидации.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)

- [ ] `AGENTS.md` убран из `todo/.gitignore`; `todo/AGENTS.md` закоммичен (актуальное локальное содержимое).
- [ ] Шаг материализации заглушки `todo/AGENTS.md` удалён из `doc-checks` (симлинки vendor-доков остаются).
- [ ] `make md-links`, `make validate-todo`, `make validate-language`, `make check` — зелёные на чистом checkout (CI `doc-checks` зелёный).

### 🟡 Желательно (Should Have)

- [ ] Проверка на чистом клоне (git clone → CI/локальный md-links) до merge.

### ⚫ Не будем делать (Won't Have)

- [ ] Никаких правок 19 ссылающихся документов и якорей.
- [ ] Никаких правок пакета `prikotov/todo-md`.

## 4. План реализации (Implementation Plan)

1. [ ] `todo/.gitignore`: убрать `AGENTS.md`.
2. [ ] `git add todo/AGENTS.md`; убедиться в идентичности локального содержимого актуальному регламенту.
3. [ ] `.github/workflows/ci.yml`: удалить шаг материализации заглушки (heredoc-блок).
4. [ ] Проверки: `make check`, CI `doc-checks` зелёный на PR.

## 5. Критерии приёмки (Definition of Done)
- [ ] Fixture/тесты — не применимо: инфраструктура репозитория
- [ ] Нет регрессий в смежных модулях — CI зелёный
- [ ] Обновлена техническая документация — при наличии упоминаний состава CI
- [ ] Заглушка удалена, файл в git
- [ ] `make check` — зелёный; CI `doc-checks` — зелёный
- [ ] Задача в `done`, файл в `todo/done/`, поле `pr` заполнено

## 6. Самопроверка (Verification)
```bash
git ls-files todo/AGENTS.md          # файл в git
grep -n "AGENTS.md" todo/.gitignore  # пусто (не игнорируется)
grep -n "AGENTS.md" .github/workflows/ci.yml  # пусто (заглушка удалена)
make md-links && make validate-todo  # зелёные
make check                           # полный барьер
```

## 7. Риски и зависимости (Risks and Dependencies)

- Риск 1: локальные правки `todo/AGENTS.md` сверх vendor-исходника создадут рассинхрон копии `init` — при расхождении синхронизировать vendor-исходник отдельным PR пакета.
- Зависимостей нет; выполняется после слияния PR TASK-build-doc-checks-ci.

## 8. Источники (Sources)

- [ ] Отчёт ревью Пуаро `docs/agents/reports/code-reviewer-backend/2026-11-25_13-54-build-doc-checks-ci-review.md` — CR-1
- [ ] `@techdebt 2026-11-25` в `.github/workflows/ci.yml` (шаг Materialize ignored doc paths)

## 9. Комментарии (Comments)

- Задача создана по CR-1 ревью Пуаро (2026-11-25): техдолг без задачи — нарушение AGENTS.md «Временные решения». Ссылка на задачу добавлена в `@techdebt`-комментарий ci.yml тем же PR, что и заглушка.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-25 13:57:54 (1795589874) | Тимлид Алекс (pi) | Создание задачи (по CR-1 ревью Пуаро, TASK-build-doc-checks-ci) |
