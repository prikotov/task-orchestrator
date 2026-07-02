---
type: feat
created: 2026-07-01
value: V1
complexity: C2
priority: P3
depends_on:
epic:
author: Бэкендер (Левша)
assignee:
branch:
pr:
status: todo
---

# TASK-feat-agent-role-i18n-locale: Локаль-зависимое поведение become-role (i18n)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Язык header'а каталога skills и приоритет поиска role-file захардкожены под `ru` (русский): `FormatSkillCatalogService` отдаёт русский header, `FilesystemLocateRoleFileService` и `become-role.sh` ищут сначала `.ru.md`. Проект уже многоязычный на уровне README (`README.md`/`README.en.md`/`README.zh.md`) и имеет env `APP_LOCALE` (Symfony translation), но роли пока только `.ru.md` — поэтому локаль-механика отложена.

### Варианты или путь решения (Solution Sketch)
Когда появятся локализованные роли (`.en.md`, `.zh.md`): сделать `APP_LOCALE` единым источником локали; `FormatSkillCatalogService` и `FilesystemLocateRoleFileService` принимают локаль (через DI-параметр `task_orchestrator.locale`); header skills и поиск role-file — по локали с fallback на `en`-формат / `<role>.md`.

### Ожидаемый результат (Expected Result)
Один env `APP_LOCALE` управляет языком header'а skills и выбором role-file во всех точках (PHP-сервисы, bash-скрипты). Роли и skills локализуются по локали проекта.

## 1. Concept and Goal (Концепция и Цель)

### Goal (Цель по SMART)
Реализовать локаль-зависимое поведение `become-role` (header каталога skills + приоритет role-file) через существующий env `APP_LOCALE`, когда в проекте появятся локализованные роли помимо `.ru.md`.

## 2. Context and Scope (Контекст и границы)

### In Scope (Что делаем)
- Параметр `task_orchestrator.locale` (Kernel, из env `APP_LOCALE`, default `ru`).
- `FormatSkillCatalogService`: карта `locale → перевод header'а` (`ru`, `en`, `zh`, …), fallback на `en` (формат pi).
- `FilesystemLocateRoleFileService` + `become-role.sh`: приоритет `<role>.<locale>.md` → `<role>.md` → любой `<role>.*.md`.
- `AGENTS.md`: фиксация языка проекта по `APP_LOCALE` (модель отвечает на локали).

### Out of Scope (Чего НЕ делаем)
- Перевод самих ролей (role-files) — отдельная задача локализации контента.
- Локализация `docs/` и SKILL.md — вне механики become-role.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have
- [ ] `task_orchestrator.locale` из `APP_LOCALE`.
- [ ] Header skills и поиск role-file по локали.

### ⟫ Won't Have (Не будем делать)
- Перевод контента ролей/docs — это задача авторов, не кода.

## 4. Implementation Plan (План реализации)
1. Параметр `task_orchestrator.locale` в Kernel (env `APP_LOCALE`).
2. `FormatSkillCatalogService` — локаль в конструктор, карта переводов header.
3. `FilesystemLocateRoleFileService` — локаль в конструктор, приоритет по ней.
4. `become-role.sh` — читает `APP_LOCALE` (или локаль из вывода PHP).
5. Тесты на локаль (ru/en/zh + fallback).

## 5. Definition of Done (Критерии приёмки)
- `APP_LOCALE=en` → английский header skills + приоритет `.en.md`.
- `APP_LOCALE=ru` → русский header + `.ru.md` (текущее поведение).
- Fallback на `en`/`.md` при отсутствии перевода/файла.

## 6. Verification (Самопроверка)
- [ ] make check зелёный.
- [ ] Тесты на ru/en/zh + fallback.

## 7. Risks and Dependencies (Риски и зависимости)
- Триггер — появление локализованных ролей (пока только `.ru.md`). До этого задача преждевременна (YAGNI).

## 8. Sources (Источники)
- `config/packages/translation.yaml` (`APP_LOCALE`).
- `FormatSkillCatalogService`, `FilesystemLocateRoleFileService`.
- pi `formatSkillsForPrompt` (en-формат для fallback).
