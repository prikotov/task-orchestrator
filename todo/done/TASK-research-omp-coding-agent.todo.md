---
# Metadata (Метаданные)
type: research
created: 2026-07-24
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-omp-coding-agent
pr:
status: done
---

# TASK-research-omp-coding-agent: omp (Oh My Pi) — CLI-агент кодинга

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно подключить CLI-агент кодинга как сабагента к роли команды, я хочу знать возможности omp (Oh My Pi) по кастомизации (системный промпт, скиллы, AGENTS.md, запуск в JSON-режиме), чтобы определить, подходит ли он для работы с нашей системой ролей и скиллов — и стоит ли рассматривать его как upgrade текущего Pi.

### Goal (Цель по SMART)
Исследовать omp — форк Pi от can1357 (`@oh-my-pi/pi-coding-agent`, v17.1.1, MIT, TypeScript+Bun+Rust) по 10 критериям. Создать отчёт в `docs/research/coding-agents/omp-comparison.md` со сводкой по каждому критерию. Вердикт: подходит / частично подходит / не подходит. Добавить строку в сводную таблицу `docs/research/coding-agents-summary.md`.

## 2. Context and Scope (Контекст и Границы)
*   **Объект:** omp (Oh My Pi) v17.1.1 (`github.com/can1357/oh-my-pi`, npm `@oh-my-pi/pi-coding-agent`). Форк Pi (badlogic/pi-mono, Mario Zechner), доработанный can1357 (Can Bölük): нативный Rust-core (~55k строк), LSP, DAP, subagents, hindsight memory, hashline edits, 40+ провайдеров.
*   **Где делаем:** `docs/research/coding-agents/omp-comparison.md`
*   **Текущее поведение:** Pi (`@earendil-works/pi-coding-agent`) — текущий основной сабагент. omp — кандидат на замену/upgrade (обратно-совместим с Pi по CLI).
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерий 1–10** — по единой методологии (системный промпт, роль, скиллы, AGENTS.md, `.agents/skills/`, JSON-режим, токены, free tier, провайдеры, лицензия)
- [x] Вердикт: ✅ Подходит (10/10) — omp превосходит Pi, обратно-совместим
- [x] Оценка отличий omp от исходного Pi (что унаследовано, что добавлено can1357)

### 🟡 Should Have (Желательно)
- [x] Практические примеры запуска omp как сабагента (`omp --mode json`)
- [x] Оценка уникальных возможностей (native Rust engine, LSP, DAP, time-traveling rules, hindsight, hashline edits, subagents, ACP)

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции, бенчмарки

## 4. Implementation Plan (План реализации)
1. [x] Изучить сайт (omp.sh), репозиторий (github.com/can1357/oh-my-pi), npm (@oh-my-pi/pi-coding-agent)
2. [x] Оценить каждый из 10 критериев
3. [x] Создать отчёт в `docs/research/coding-agents/omp-comparison.md`
4. [x] Добавить строку в `docs/research/coding-agents-summary.md` + обновить ранжирование и рекомендации

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт создан, все 10 критериев оценены
- [x] Вердикт с обоснованием
- [x] Строка добавлена в сводную таблицу
- [x] Ранжирование и Top-кандидаты обновлены с учётом omp

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/omp-comparison.md
grep "omp" docs/research/coding-agents-summary.md
```

## 7. Sources (Источники)
- [omp.sh — официальный сайт](https://omp.sh/)
- [omp — GitHub (can1357/oh-my-pi)](https://github.com/can1357/oh-my-pi)
- [@oh-my-pi/pi-coding-agent — npm](https://www.npmjs.com/package/@oh-my-pi/pi-coding-agent)
- [omp Discord](https://discord.gg/4NMW9cdXZa)

## 8. Comments (Комментарии)
omp — форк Pi (Mario Zechner / badlogic/pi-mono) от can1357, переписанный как coding-first surface с batteries-included подходом. Ключевое отличие от исходного Pi: нативный Rust-движок (~55k строк, in-process grep/shell/AST/PTTY), встроенные LSP/DAP, first-class subagents (task fan-out с worktree-изоляцией), hindsight memory (mnemopi, локальная SQLite), hashline-редактирование (content-hash anchored patches), time-traveling stream rules, advisor-модель, ACP (Agent Client Protocol для Zed), 40+ провайдеров, импорт 8 форматов правил (Cursor/Cline/Codex/Copilot/Gemini/Windsurf). Лицензия MIT, ★19.5k, 65k загрузок/нед.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-24 | Тимлид (Алекс) | Создание задачи |
| 2026-07-24 | Аналитик (Шерлок) | Исследование выполнено: отчёт `docs/research/coding-agents/omp-comparison.md` (10 критериев), сводная таблица обновлена (omp #1, Pi #2 как подмножество), ранжирование/Top-3/рекомендации обновлены. Вердикт ✅ Подходит (10/10). |
