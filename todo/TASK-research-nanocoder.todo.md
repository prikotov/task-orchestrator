---
# Metadata (Метаданные)
type: docs
created: 2026-07-28
value: V3
complexity: C2
priority: P3
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-nanoclaw-and-nanocoder
pr: '#331'
status: review
---

# TASK-research-nanocoder: Nanocoder — local-first CLI-агент кодинга

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- «nano» из телеграм-поста не ресерчен в эпике `EPIC-research-coding-agents-comparison`. Помимо NanoClaw (Claw-семейство), есть Nanocoder — независимый local-first агент; нужна оценка его пригодности как лёгкой альтернативы OpenClaw / fallback к Pi/omp.

### Варианты или путь решения (Solution Sketch)
- Исследовать Nanocoder (`Nano-Collective/nanocoder`) по 10 критериям; акцент на К6 (JSON headless) и К9 (local-first / BYOM / Ollama).
- Создать comparison-отчёт и добавить строку в сводную таблицу.

### Ожидаемый результат (Expected Result)
- Comparison-отчёт с вердиктом и evidence по 10 критериям.
- Позиционирование vs Pi/omp (local-first) и vs OpenClaw (лёгкость); вывод о пригодности как сабагента.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужен лёгкий лаконичный CLI-агент кодинга как альтернатива OpenClaw для сабагент-интеграции, я хочу знать возможности Nanocoder по кастомизации (системный промпт, скиллы, AGENTS.md, JSON-режим) и локальному запуску (BYOM), чтобы определить, подходит ли он для работы с нашей системой ролей и скиллов.

### Goal (Цель по SMART)
Исследовать Nanocoder (`github.com/Nano-Collective/nanocoder`, npm `@nanocollective/nanocoder`, local-first, MIT) по 10 критериям единой методологии. Создать отчёт в `docs/research/coding-agents/nanocoder-comparison.md`. Вердикт: подходит / частично подходит / не подходит. Добавить строку в сводную таблицу `docs/research/coding-agents-summary.md`.

## 2. Context and Scope (Контекст и Границы)
*   **Объект:** Nanocoder (`github.com/Nano-Collective/nanocoder`, npm `@nanocollective/nanocoder`). Local-first CLI-агент от community collective: BYOM (Ollama, OpenRouter, любой OpenAI-compatible API), native tool calling с XML fallback, MCP-поддержка, file-based custom commands и tools. Лицензия MIT.
*   **Где делаем:** `docs/research/coding-agents/nanocoder-comparison.md`
*   **Текущее поведение:** не входит в текущие 18 исследованных агентов. Кандидат на роль лёгкой альтернативы OpenClaw и потенциальный fallback к Pi/omp для local-only сценариев.
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников, бенчмарки.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерий 1–10** — по единой методологии (системный промпт, роль, скиллы, AGENTS.md, `.agents/skills/`, JSON-режим, токены, free tier, провайдеры, лицензия)
- [x] Вердикт: подходит / частично подходит / не подходит — с обоснованием
- [x] Особое внимание К6 (JSON/JSONL headless-режим) и К9 (local-first / BYOM / Ollama)
- [x] Оценка пригодности как сабагента (programmatic/pipe-запуск, таймауты)

### 🟡 Should Have (Желательно)
- [x] Практические примеры запуска Nanocoder как сабагента (JSON-режим)
- [x] Сравнение local-first-фокуса с Pi/omp (offline, без облака)

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции, бенчмарки

## 4. Implementation Plan (План реализации)
1. [x] Изучить репозиторий `github.com/Nano-Collective/nanocoder`, npm `@nanocollective/nanocoder`, документацию
2. [x] Оценить каждый из 10 критериев, собрать evidence (CLI-флаги, env, конфиг)
3. [x] Создать отчёт в `docs/research/coding-agents/nanocoder-comparison.md`
4. [x] Передать результаты для финального обновления `docs/research/coding-agents-summary.md` (Stage 1j — единое обновление сводки для Nanocoder + NanoClaw)

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт создан, все 10 критериев оценены с evidence
- [x] Вердикт с обоснованием; явное позиционирование vs Pi/omp (local-first) и vs OpenClaw (лёгкость)
- [x] Результаты переданы для сводной таблицы (Stage 1j)

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/nanocoder-comparison.md
grep -i "nanocoder" docs/research/coding-agents-summary.md
```

## 7. Sources (Источники)
- [Nanocoder — GitHub (Nano-Collective/nanocoder)](https://github.com/Nano-Collective/nanocoder)
- [@nanocollective/nanocoder — npm](https://www.npmjs.com/package/@nanocollective/nanocoder)
- [awesome-cli-coding-agents — Open Source](https://github.com/bradAGI/awesome-cli-coding-agents)

## 8. Comments (Комментарии)
Происхождение: проверка по телеграм-посту «лёгкие альтернативы OpenClaw: zero, nano, pi». Помимо NanoClaw (Claw-семейство, отдельная задача), исследуется **Nanocoder** — независимый local-first «nano»-кандидат (не Claw-семейство, 2.3k★, MIT). Сводная таблица обновляется один раз для обоих (Stage 1j), чтобы избежать конфликта слияния.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-28 | Тимлид (Алекс) | Создание задачи (Stage 1j расширение закрытого эпика). |
| 2026-07-28 | Аналитик (Шерлок) | Исследование выполнено: отчёт `nanocoder-comparison.md` (10 критериев, evidence). **Вердикт ⚠️ Частично (7/10, 22/30)** — К6 (CLI JSON) очищен (`--json` single-object + `--acp` JSON-RPC streaming; но не streaming JSONL как Pi). Сильные стороны: local-first BYOM (8 local runners), ~25+ провайдеров, subagents с изоляцией, MIT. Слабые: config-only промпт (нет `--system-prompt`/`--append-system-prompt` CLI), свой skill-формат (не agentskills.io SKILL.md), нет `.agents/skills/`, нет токенов/cost в JSON, нет `--no-session`. Сводная таблица обновлена единым Stage 1j-коммитом (Nanocoder #11, 22/30). Ожидает коммита/PR. |
