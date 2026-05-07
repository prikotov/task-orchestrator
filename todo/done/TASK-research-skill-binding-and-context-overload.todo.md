---
type: research
created: 2026-05-04
assignee: Аналитик Шерлок
branch: task/research-skill-binding
pr:
status: done
---

# Исследование: привязка скиллов к агенту и перегрузка контекста

## Вопрос 1: Как система привязывает скиллы к агенту

Сколько скиллов — предел? Routing agent vs specialist agents? Как решается выбор?

Влияет на: API TasK, тарифы, UX agent builder'а.

Исходная база: `docs/research/agent-frameworks-summary.md` — 9/16 фреймворков используют SKILL.md, Paperclip AI добавляет Company Skills (managed registry, trust levels, compatibility checks).

Нужно вытащить из ресерча:
- [x] Паттерны привязки скиллов к агенту по каждому фреймворку
- [x] Есть ли порог/лимит на количество скиллов у кого-либо
- [x] Routing agent → specialist — кто так делает (Agno: 4 team modes, CrewAI: hierarchical)
- [x] Dynamic skill loading vs всё в контексте

## Вопрос 2: Перегрузка контекста скиллами

10 скиллов — норма. 100? 1000? Что происходит с качеством выбора?

- Порог, после которого accuracy function calling / tool selection падает
- Связь с размером контекстного окна модели
- Стоимость токенов при раздувании system prompt

Связано с вопросом 1: если есть порог, архитектура routing + specialists не опциональна, а обязательна.
