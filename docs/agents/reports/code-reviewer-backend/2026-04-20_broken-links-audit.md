# Аудит нерабочих ссылок в AGENTS.md и связанных файлах

**Роль:** Ревьювер Бэка
**Дата:** 2026-04-20
**Объект:** `AGENTS.md`, `docs/agents/roles/team/*.md`, `docs/conventions/index.md`, `docs/git-workflow/*.md`, `todo/AGENTS.md`
**Задача:** Пользовательский запрос — проверить все markdown-ссылки на существование целевых файлов

---

## Методика

Из корневого `AGENTS.md` извлечены все markdown-ссылки (`[text](path)`), проверено существование целевых файлов. Затем рекурсивно проверены все ссылки в каждом связанном файле. Внешние URL (`https://...`) не проверялись — только локальные пути.

## Результаты по файлам

### ✅ `AGENTS.md` (корень проекта) — 0 проблем

Все 11 ссылок ведут на существующие файлы.

### ✅ `docs/agents/roles/team/*.md` (6 файлов) — 0 проблем

Все ссылки на `docs/conventions/index.md`, `docs/guide/architecture.md`, `todo/AGENTS.md`, skills — рабочие.

### ✅ `docs/conventions/index.md` — 0 проблем

63 ссылки на файлы внутри `docs/conventions/` — все рабочие (обновлены через `coding-standard-init --force`).

### ✅ `docs/git-workflow/commits.md` — 0 проблем

Ссылки на `branches.md`, `pull-request.md` — рабочие.

### ✅ `docs/git-workflow/pull-request.md` — 0 проблем

Ссылки на `branches.md`, `commits.md`, `release.md`, `code-review.md`, `todo/AGENTS.md` — рабочие.

### ❌ `todo/AGENTS.md` — 11 уникальных нерабочих ссылок

## Детальный разбор проблем в `todo/AGENTS.md`

Все нерабочие ссылки — следствие бага в пакете `prikotov/todo-md` v0.0.1:

- init-скрипт (`todo-md-init`) копирует документацию в `docs/todo-md/` (AGENTS_TASK_WRITING_GUIDE, reference/*, templates/*)
- но `todo/AGENTS.md` (копия `docs/todo-md/AGENTS.md`) содержит ссылки, рассчитывающие на то, что файлы лежат **рядом** — в `todo/`

| Ссылка в `todo/AGENTS.md` | Ожидаемый путь | Реальный путь | Строки |
|---|---|---|---|
| `./AGENTS_TASK_WRITING_GUIDE.md` | `todo/AGENTS_TASK_WRITING_GUIDE.md` | `docs/todo-md/AGENTS_TASK_WRITING_GUIDE.md` | L6, L145 |
| `./reference/TYPES.md` | `todo/reference/TYPES.md` | `docs/todo-md/reference/TYPES.md` | L8, L22 |
| `./reference/STATUSES.md` | `todo/reference/STATUSES.md` | `docs/todo-md/reference/STATUSES.md` | L9, L29, L147 |
| `./reference/VALUES.md` | `todo/reference/VALUES.md` | `docs/todo-md/reference/VALUES.md` | L10, L23 |
| `./reference/COMPLEXITY.md` | `todo/reference/COMPLEXITY.md` | `docs/todo-md/reference/COMPLEXITY.md` | L11, L24 |
| `./reference/PRIORITIES.md` | `todo/reference/PRIORITIES.md` | `docs/todo-md/reference/PRIORITIES.md` | L12, L25 |
| `./reference/AI_AGENTS.md` | `todo/reference/AI_AGENTS.md` | `docs/todo-md/reference/AI_AGENTS.md` | L13, L26 |
| `./reference/GLOSSARY.md` | `todo/reference/GLOSSARY.md` | `docs/todo-md/reference/GLOSSARY.md` | L146 |
| `templates/task.md` | `todo/templates/task.md` | `docs/todo-md/templates/task.md` | L15, L66 |
| `templates/epic.md` | `todo/templates/epic.md` | `docs/todo-md/templates/epic.md` | L16 |
| `done/TASK-ID.todo.md` | `todo/done/TASK-ID.todo.md` | — (пример, файла не существует) | L105 |

> Примечание: `done/TASK-ID.todo.md` — это шаблон-плейсхолдер в документации, реального файла быть не должно. Это не баг.

## Корневая причина

Пакет `prikotov/todo-md` v0.0.1: функция `todo-md-init` устанавливает документацию в `docs/todo-md/`, но шаблон `AGENTS.md` внутри пакета написан с предположением, что файлы лежат в той же директории (относительные ссылки `./`). При копировании `AGENTS.md` в `todo/` ссылки ломаются.

## Варианты устранения

1. **Исправить пакет `prikotov/todo-md`** (рекомендуемый): обновить `AGENTS.md` в пакете так, чтобы ссылки указывали на `docs/todo-md/...`, либо изменить init-скрипт чтобы он копировал reference/templates прямо в `todo/`.
2. **Локальный фикс в `todo/AGENTS.md`**: заменить все `./` ссылки на `../docs/todo-md/...`. Однако при следующем `--force` init изменения затрутся.

## Резюме

| Область | Проверено ссылок | Рабочих | Нерабочих |
|---|---|---|---|
| `AGENTS.md` (корень) | 11 | 11 | 0 |
| `docs/agents/roles/team/*.md` | ~30 | ~30 | 0 |
| `docs/conventions/index.md` | 63 | 63 | 0 |
| `docs/git-workflow/*.md` | 7 | 7 | 0 |
| `todo/AGENTS.md` | ~20 | 0 | 10 уникальных |
| **Итого** | **~131** | **~121** | **10** |
