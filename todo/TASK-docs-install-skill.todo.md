---
# Metadata (Метаданные)
type: docs
created: 2026-04-23
value: V3
complexity: C2
priority: P1
depends_on: TASK-chore-composer-library-type
epic: EPIC-feat-standalone-cli
author: Тимлид (Алекс)
assignee: Тех. писатель (Гермиона)
branch: task/docs-install-skill
pr: '#67'
status: in_progress
---

# TASK-docs-install-skill: SKILL.md для установки AI-агентами + секция в README

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как непрограммист, я хочу сказать своему AI-ассистенту: «настрой мне task-orchestrator», чтобы агент сам установил PHP, Composer, пакет и создал конфигурацию цепочки.

### Goal (Цель по SMART)
Создать SKILL.md для AI-агентов по формату `SKILL-CREATION.md`. Добавить секцию Installation в README для людей. AI-агент читает SKILL.md и выполняет пошаговую установку без assumptions.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `docs/agents/skills/install-task-orchestrator/SKILL.md` + `README.md`
* **Формат:** строго по `docs/agents/skills/SKILL-CREATION.md`
* **Границы:**
  - Не создаём примеры цепочек (есть `config/chains.yaml`)
  - Не описываем программный API (отложено)

## 3. Requirements (Требования)
### 🔴 Must Have
- [ ] `docs/agents/skills/install-task-orchestrator/SKILL.md` создан по формату SKILL-CREATION.md:
  - YAML frontmatter (name, description)
  - «Когда использовать» — AI-агенту нужно установить tool
  - «Как использовать» — пошагово: проверить PHP → поставить Composer → require → проверить → создать минимальный конфиг → запустить
  - «Результат» — установленный и работающий tool
- [ ] README.md: секция Installation (`composer require` — primary, Phar — альтернатива)

### 🟡 Should Have
- [ ] В SKILL.md: troubleshooting типичных проблем
- [ ] В SKILL.md: минимальный пример конфига (одна цепочка, одна роль)

## 4. Implementation Plan
1. Создать каталог `docs/agents/skills/install-task-orchestrator/`
2. Написать SKILL.md — пошаговая инструкция для AI-агента, без assumptions
3. Обновить README.md — секция Installation для людей

## 5. Definition of Done
- [ ] SKILL.md валиден по чеклисту SKILL-CREATION.md
- [ ] README содержит секцию Installation
- [ ] Пошаговая инструкция покрывает: проверка PHP → установка → создание конфига → запуск

## 6. Verification
```bash
# Проверить структуру SKILL
cat docs/agents/skills/install-task-orchestrator/SKILL.md
```

## 7. Risks
- Инструкция может устареть при изменении CLI-интерфейса — привязать к `--help`

## 8. Sources
- [SKILL-CREATION.md](../docs/agents/skills/SKILL-CREATION.md) — формат
- [Пример SKILL](../docs/agents/skills/) — существующие skill'ы
- [RFC: cli-distribution-rfc.md](../docs/research/cli-distribution-rfc.md) — Решение владельца #3

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-23 | Тимлид (Алекс) | Создание задачи из RFC brainstorm |
