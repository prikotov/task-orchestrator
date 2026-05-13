---
# Metadata (Метаданные)
type: research
created: 2026-05-13
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-codebuff-duet-multica
pr: "#204"
status: done
---

# TASK-research-codebuff: Codebuff (CLI-агент кодинга)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно подключить CLI-агент кодинга как сабагента к роли команды, я хочу знать его возможности по кастомизации (системный промпт, скиллы, AGENTS.md, запуск в JSON-режиме), чтобы определить, подходит ли он для работы с нашей системой ролей и скиллов.

### Goal (Цель по SMART)
Исследовать Codebuff (TypeScript, Apache-2.0) по 10 критериям. Создать отчёт в `docs/research/coding-agents/codebuff-comparison.md` со сводкой по каждому критерию. Вердикт: подходит / частично подходит / не подходит для использования как сабагент с ролями.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/coding-agents/codebuff-comparison.md`
*   **Текущее поведение:** Агент ещё не подключён к нашей системе
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] **Критерий 1–10** — по единой методологии (системный промпт, роль, скиллы, AGENTS.md, `.agents/skills/`, JSON-режим, токены, free tier, провайдеры, лицензия)
- [ ] Вердикт: подходит / частично подходит / не подходит — с обоснованием

### 🟡 Should Have (Желательно)
- [ ] Практические примеры запуска в JSON-режиме
- [ ] Оценка мультиагентного подхода (File Picker → Planner → Editor → Reviewer) как паттерна

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции, бенчмарки

## 4. Implementation Plan (План реализации)
1. [ ] Изучить репозиторий: https://github.com/CodebuffAI/codebuff
2. [ ] Оценить каждый из 10 критериев
3. [ ] Создать отчёт в `docs/research/coding-agents/codebuff-comparison.md`
4. [ ] Добавить строку в `docs/research/coding-agents-summary.md`

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт создан, все 10 критериев оценены
- [ ] Вердикт с обоснованием
- [ ] Строка добавлена в сводную таблицу

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/codebuff-comparison.md
grep "Codebuff" docs/research/coding-agents-summary.md
```

## 7. Sources (Источники)
- [Codebuff — GitHub](https://github.com/CodebuffAI/codebuff)
- [Codebuff — сайт](https://codebuff.com)

## 8. Comments (Комментарии)
Codebuff — мультиагентный CLI-кодинг-ассистент (TypeScript, Apache-2.0, ~5k stars). Ключевая особенность: координирует специализированные агенты (File Picker → Planner → Editor → Reviewer) вместо одной модели. Кастомные агенты через TypeScript-определения. Freebuff — бесплатная ad-supported версия.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-13 | Тимлид (Алекс) | Создание задачи |
