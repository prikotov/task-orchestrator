---
type: feat
created: 2026-07-01
value: V1
complexity: C3
priority: P2
depends_on:
epic:
author: Бэкендер (Левша)
assignee: Бэкендер (Левша)
branch: task/skills-per-role-launcher
pr: '#289'
status: review
---

# TASK-feat-adopt-role-skills: Универсальный загрузчик skills роли (adopt-role) для pi и codex

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Skills ролей лежат в `docs/agents/skills/` — не в стандартных локациях автозагрузки pi/codex. Поэтому они не попадают в контекст агента автоматически. Раньше роль ссылалась на skills markdown-ссылками и агент читал их «руками» — ненадёжно и без progressive disclosure. Нужен единый механизм: при работе от роли — прописать её skills в контекст так, чтобы агент их использовал (читал SKILL.md по описанию).

### Варианты или путь решения (Solution Sketch)
Единый общий skill `adopt-role` (виден всем через `.agents/skills/`): агент вызывает его → скрипт резолвит skills роли из frontmatter (с развёрткой `depends_on`) и объявляет XML-каталог `<available_skills>` (формат pi/agentskills). Модель видит каталог и по описанию читает нужный SKILL.md (progressive disclosure). Ядро резолвинга — PHP-модуль `AgentRole` + CLI `agent:role-skills`. Установка в host-проектах — `agent:init` (симлинк). Промптинг сабагента — через `watch-subagent.sh`.

### Ожидаемый результат (Expected Result)
`adopt-role` виден нативно и в pi, и в codex (через `.agents/skills/`); вызов объявляет skills роли в контексте; модель находит нужный skill по описанию и читает его SKILL.md. `agent:init` ставит симлинк в host-проекте. `phpunit`/`psalm`/`deptrac` зелёные.

## 1. Concept and Goal (Концепция и Цель)

### Goal (Цель по SMART)
Создать универсальный механизм «adopt-role», который по имени роли прописывает её skills (с `depends_on`) в контекст агента в формате Agent Skills, работающий одинаково в pi и codex — без зависимости от нативной автозагрузки конкретного инструмента.

## 2. Context and Scope (Контекст и границы)

### In Scope (Что делаем)
- Модуль `AgentRole`: VO, резолвер skills (depends_on, топо-порядок, дедуп, детект циклов), XML-форматтер каталога (формат pi `formatSkillsForPrompt`).
- CLI `agent:role-skills <role> [--format=block|list|json]`.
- Skill `adopt-role` (SKILL.md + adopt-role.sh) + симлинк `.agents/skills/adopt-role`.
- CLI `agent:init` — установка симлинка в host-проекте (идемпотентно, `--force`).
- Промптинг сабагента: `watch-subagent.sh` рекомендует войти в роль через adopt-role.
- Авто-прокси codex в `watch-subagent.sh` (source `.env.local`, `CODEX_HTTP_PROXY` → `HTTPS_PROXY`).

### Out of Scope (Чего НЕ делаем)
- Детерминированный инжект каталога в system-prompt сабагента (работаем через промптинг).
- Размещение role-специфичных skills в автозагружаемых локациях (они остаются в `docs/agents/skills/`).
- RACI-маппинг «вид задачи → роль» в коде (роль определяет модель).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have
- [x] Модуль `AgentRole` с резолвером `depends_on` и XML-форматтером (формат pi).
- [x] CLI `agent:role-skills` (block/list/json).
- [x] Skill `adopt-role` + симлинк `.agents/skills/adopt-role`.
- [x] `agent:init` для host-проектов.
- [x] Промптинг `watch-subagent.sh` + авто-прокси codex.
- [x] adopt-role виден нативно и в pi, и в codex (проверено end-to-end).

### 🟡 Should Have
- [x] Unit-тесты: VO, резолвер, форматтер, handler.
- [x] Integration-тесты: стек readers, adopt-role.sh, InitCommand.

### ⟫ Won't Have (Не будем делать)
- Детерминированный инжект каталога skills в system-prompt сабагента (работаем через промптинг).
- Размещение role-специфичных skills в автозагружаемых локациях (они остаются в `docs/agents/skills/`).
- RACI-маппинг «вид задачи → роль» в коде (роль определяет модель).

## 4. Implementation Plan (План реализации)
1. Модуль `AgentRole`: VO, доменные сервисы (резолвер `depends_on`, XML-форматтер), интерфейсы читателей/локатора.
2. Application UseCase `ResolveRoleSkills` + CLI `agent:role-skills`.
3. Infrastructure: readers/locator (Symfony YAML frontmatter), `services.yaml`, `AgentRoleModule`, регистрация в `config/modules.php` и `Kernel::registerConsoleServices()`.
4. Skill `adopt-role` (`SKILL.md` + `adopt-role.sh`) + симлинк `.agents/skills/adopt-role`.
5. `agent:init` для host-проектов (симлинк, идемпотентно, `--force`).
6. Промптинг `watch-subagent.sh` (вход в роль через adopt-role) + авто-прокси codex (source `.env.local`, `CODEX_HTTP_PROXY` → `HTTPS_PROXY`).
7. Тесты (unit + integration + end-to-end).
8. Документация (`AGENTS.md`, `docs/guide/cli.md`).

## 5. Definition of Done (Критерии приёмки)
- `adopt-role` виден нативно и в pi, и в codex через `.agents/skills/`.
- Вызов `adopt-role` объявляет skills роли (с `depends_on`) в контексте.
- Модель находит нужный skill по описанию и читает его `SKILL.md` (progressive disclosure).
- `agent:init` ставит симлинк в host-проекте (идемпотентно).
- `make check` зелёный.

## 6. Verification (Самопроверка)
- [x] `phpunit`: 1309 OK.
- [x] `psalm`: 0 ошибок.
- [x] `deptrac`: 0 нарушений.
- [x] `make check`: зелёный (phpstan/phpmd/phpcs/md-links/validate-todo/validate-roles/tests).
- [x] End-to-end pi (zai): модель вызвала adopt-role, перечислила skills, read SKILL.md.
- [x] End-to-end codex (через прокси): adopt-role виден нативно, progressive disclosure работает.

## 7. Risks and Dependencies (Риски и зависимости)
- `adopt-role` кладёт каталог skills в conversation context (вывод скрипта как toolResult), а не в system prompt. При компактизации длинного контекста каталог может уйти из активного окна. Для сабагентов (короткие сессии) некритично.

## 8. Sources (Источники)
- docs pi: `skills.md` (`formatSkillsForPrompt`), `rpc.md` (`commands.get`).
- Стандарт Agent Skills: agentskills.io (specification, adding-skills-support).
- Код проекта: `AgentRunner`, `watch-subagent.sh`, `config/chains.yaml`.
