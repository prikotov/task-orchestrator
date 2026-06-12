---
type: research
created: 2026-06-12
value: V3
complexity: C3
priority: P2
depends_on: []
epic:
author: Аналитик (Шерлок)
assignee:
branch:
pr:
status: backlog
---

# TASK-arch-agent-skills-registry: Реестр skills/role binding/validation для docs/agents/skills

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда агент (role) выполняет цепочку, я хочу использовать процедурные знания (skills) из реестра с автоматическим discovery, validation и role binding, чтобы избежать ошибок применения и обеспечить согласованность.

### Goal (Цель по SMART)
Спроектировать и реализовать реестр skills для `docs/agents/skills/`: SKILL.md format parsing, validation, discovery, role binding. Определить entity/VO, repository, use case, и интегрировать с нашей DDD/Clean Architecture.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/AgentRunner/` (Application + Domain layers). Возможные новые сущности: `Skill`, `SkillId`, `SkillCategory`, `SkillStatus`, `SkillRegistryService`.
*   **Текущее поведение:** `docs/agents/skills/` содержит SKILL.md файлы, но нет автоматического parsing, validation, role binding. Агенты не знают о доступных skills.
*   **Границы (Out of Scope):** Не реализовывать LLM-based skill learning или teacher escalation. Не интегрировать с vector retrieval для skill search.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Определить entity/VO для Skill: `SkillId`, `SkillName`, `SkillCategory`, `SkillStatus`, `SkillContent` (YAML frontmatter + markdown body)
- [ ] Определить repository interface для skills: `SkillRepositoryInterface` (Domain layer)
- [ ] Определить use case для skill discovery: `FindSkillsQuery/Handler` (Application layer)
- [ ] Определить use case для skill validation: `ValidateSkillCommand/Handler` (Application layer)
- [ ] Реализовать SKILL.md parser (Infrastructure layer): parse YAML frontmatter, extract fields (name, description, category, tags, requires_toolsets, fallback_for_toolsets, when_to_use, procedure, pitfalls, verification, status, version, confidence, source, teacher_model, session_id)
- [ ] Реализовать skill validation rules: required fields, format constraints, enum constraints (status: draft/published/archived)
- [ ] Определить role binding: `RoleSkillAssignment` entity (role → skill mapping)
- [ ] Указать ссылки на конвенции: [`Entity`](../../docs/conventions/layers/domain/entity.md), [`VO`](../../docs/conventions/core_patterns/value-object.md), [`Repository`](../../docs/conventions/layers/domain/repository.md), [`Use Case`](../../docs/conventions/layers/application/use_case.md), [`Service`](../../docs/conventions/core_patterns/service.md)

### 🟡 Should Have (Желательно)
- [ ] Реализовать skill discovery по категории, тегам, requires_toolsets
- [ ] Реализовать skill caching в memory (Infrastructure layer)
- [ ] Определить skill versioning (semantic versioning: major.minor.patch)
- [ ] Определить skill confidence level (0.0–1.0) для routing decisions

### 🟢 Could Have (Опционально)
- [ ] Рассмотреть LLM-based skill synthesis (teacher model writes skill)
- [ ] Рассмотреть skill inheritance (extends other skill)
- [ ] Рассмотреть skill composition (multiple skills combine into workflow)

### ⚫ Won't Have (Не будем делать)
- [ ] Не реализовывать LLM-based skill learning (auto-learning from execution history)
- [ ] Не интегрировать с vector retrieval для skill search
- [ ] Не реализовывать skill execution runtime (только discovery и validation)

## 4. Implementation Plan (План реализации)
*План заполняется исполнителем перед стартом.*
1. [ ] Определить entity/VO для Skill и RoleSkillAssignment
2. [ ] Определить repository interface и implementation (file-based: scan `docs/agents/skills/`)
3. [ ] Реализовать SKILL.md parser (Infrastructure layer)
4. [ ] Реализовать skill validation rules
5. [ ] Определить use case handlers (FindSkillsQuery, ValidateSkillCommand)
6. [ ] Реализовать SkillRegistryService для role binding
7. [ ] Обновить документацию (архитектура, SKILL.md format spec)

## 5. Definition of Done (Критерии приёмки)
- [ ] Определены entity/VO для Skill и RoleSkillAssignment (Domain layer)
- [ ] Определены repository interfaces (Domain layer) и implementation (Infrastructure layer)
- [ ] Реализован SKILL.md parser с validation (Infrastructure layer)
- [ ] Определены use case handlers (FindSkillsQuery, ValidateSkillCommand) (Application layer)
- [ ] Реализован SkillRegistryService для role binding (Application layer)
- [ ] Созданы unit-тесты для parser и validation
- [ ] Обновлена документация: SKILL.md format spec, архитектура skills registry

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/AgentRunner/Infrastructure/Parser/SkillParserTest.php
vendor/bin/phpunit tests/Unit/Module/AgentRunner/Domain/Entity/SkillTest.php
vendor/bin/phpunit tests/Integration/Module/AgentRunner/Application/Handler/FindSkillsQueryHandlerTest.php
ls docs/agents/skills/  # Проверка существующих SKILL.md файлов
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** SKILL.md format из Odysseus может быть слишком rich для нашего scope — нужно выделить minimal useful subset.
- **Риск:** Role binding может конфликтовать с нашей existing role system (если есть) — нужно проверить совместимость.
- **Зависимость:** Задача может быть блокирована до определения final role/agent concept (если ещё не определён).

## 8. Sources (Источники)
- [odysseus-comparison.md](../../docs/research/framework-comparisons/odysseus-comparison.md) — секция "Implementation Candidates for task-orchestrator"
- [SKILL.md format](../../docs/agents/skills/) — существующие SKILL.md файлы в project
- [agentskills.io](https://agentskills.io) — de-facto standard для SKILL.md (reference)

## 9. Comments (Комментарии)
Цель этой задачи — design + implementation basics для skills registry. LLM-based learning и vector search — отдельные задачи/эпики.

**AGPL disclaimer:** Концепция SKILL.md format взята из Odysseus (и других 16+ проектов), но мы не копируем код. Implementation будет с нуля в нашей DDD/Clean Architecture с subset подходящих полей.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-12 | Аналитик (Шерлок) | Создание задачи |