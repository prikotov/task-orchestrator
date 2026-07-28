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

# TASK-research-nanoclaw: NanoClaw — CLI-агент кодинга (экосистема OpenClaw)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В телеграм-посте «лёгкие альтернативы OpenClaw: zero, nano, pi» упомянут «nano», но в нашем эпике `EPIC-research-coding-agents-comparison` (18 исследованных агентов) такого ресерча нет. Не ясно, закрывает ли NanoClaw (security-first альтернатива OpenClaw) главный блокер OpenClaw — отсутствие CLI JSON/JSONL-режима (К6).

### Варианты или путь решения (Solution Sketch)
- Исследовать NanoClaw (`nanocoai/nanoclaw`) по единой методологии из 10 критериев; особое внимание К6 (JSON headless) и сравнению с OpenClaw.
- Создать comparison-отчёт и добавить строку в сводную таблицу.

### Ожидаемый результат (Expected Result)
- Comparison-отчёт с вердиктом (подходит / частично / не подходит) и evidence по 10 критериям.
- Явный ответ: закрывает ли NanoClaw К6-блокер OpenClaw — стоит ли рассматривать его для сабагент-интеграции.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно подобрать лёгкий лаконичный CLI-агент кодинга как альтернативу OpenClaw для работы сабагентом в системе ролей, я хочу знать возможности NanoClaw по кастомизации (системный промпт, скиллы, AGENTS.md, запуск в JSON-режиме), чтобы определить, подходит ли он лучше OpenClaw (который у нас оценён ❌ 4/10) — и стоит ли рассматривать его как candidate для интеграции.

### Goal (Цель по SMART)
Исследовать NanoClaw (`github.com/nanocoai/nanoclaw` — канонический репо; `gavrielc/nanoclaw` — раннее зеркало, security-first lightweight alternative to OpenClaw, на Anthropic Agents SDK) по 10 критериям единой методологии. Создать отчёт в `docs/research/coding-agents/nanoclaw-comparison.md`. Вердикт: подходит / частично подходит / не подходит. Добавить строку в сводную таблицу `docs/research/coding-agents-summary.md`.

## 2. Context and Scope (Контекст и Границы)
*   **Объект:** NanoClaw (`github.com/nanocoai/nanoclaw` v2.1.53, автор Gavriel Cohen). Security-first лёгкая альтернатива OpenClaw: изоляция в Docker-контейнерах (v2.x — Docker-only; Apple containers были в v1), построен на Anthropic Claude Agent SDK по умолчанию. Относится к экосистеме OpenClaw (секция «OpenClaw ecosystem» в `awesome-cli-coding-agents`).
*   **Где делаем:** `docs/research/coding-agents/nanoclaw-comparison.md`
*   **Текущее поведение:** OpenClaw (`openclaw-agent-comparison.md`) — ❌ Не подходит (4/10): нет CLI JSON-режима (К6 — блокер). NanoClaw позиционируется как «лёгкая безопасная альтернатива» — нужно проверить, закрывает ли он К6 и пригоден ли как сабагент.
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников, бенчмарки производительности.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерий 1–10** — по единой методологии (системный промпт, роль, скиллы, AGENTS.md, `.agents/skills/`, JSON-режим, токены, free tier, провайдеры, лицензия)
- [x] Вердикт: подходит / частично подходит / не подходит — с обоснованием и сравнением с OpenClaw
- [x] Особое внимание К6 (JSON/JSONL headless-режим) — главный блокер OpenClaw; проверить, есть ли он в NanoClaw
- [x] Оценка security/sandbox-фич (Docker-изоляция) с точки зрения пригодности для сабагент-интеграции

### 🟡 Should Have (Желательно)
- [x] Практические примеры запуска NanoClaw как сабагента (JSON-режим, таймауты)
- [x] Сравнение «лёгкости» vs Pi/omp (объём зависимостей, бинарник, ресурсы)

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции, бенчмарки, аудит безопасности sandbox

## 4. Implementation Plan (План реализации)
1. [x] Изучить репозиторий `github.com/nanocoai/nanoclaw`, npm-пакет (если есть), документацию
2. [x] Оценить каждый из 10 критериев, собрать evidence (CLI-флаги, env, конфиг)
3. [x] Создать отчёт в `docs/research/coding-agents/nanoclaw-comparison.md`
4. [x] Передать результаты для финального обновления `docs/research/coding-agents-summary.md` (Stage 1j — единое обновление сводки для NanoClaw + Nanocoder)

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт создан, все 10 критериев оценены с evidence
- [x] Вердикт с обоснованием; явное сравнение с OpenClaw (улучшение / регресс по каждому блокеру)
- [x] Результаты переданы для сводной таблицы (Stage 1j)

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/nanoclaw-comparison.md
grep -i "nanoclaw" docs/research/coding-agents-summary.md
```

## 7. Sources (Источники)
- [NanoClaw — GitHub (nanocoai/nanoclaw, канонический)](https://github.com/nanocoai/nanoclaw)
- [NanoClaw — GitHub (gavrielc/nanoclaw, раннее зеркало)](https://github.com/gavrielc/nanoclaw)
- [awesome-cli-coding-agents — секция OpenClaw ecosystem](https://github.com/bradAGI/awesome-cli-coding-agents)
- [5 Best OpenClaw Alternatives in 2026 (Safer & Lighter)](https://www.shareuhack.com/en/posts/openclaw-alternatives-guide)
- [OpenClaw comparison (наш ресерч)](../docs/research/coding-agents/openclaw-agent-comparison.md)

## 8. Comments (Комментарии)
Происхождение задачи: проверка эпика `EPIC-research-coding-agents-comparison` выявила, что из телеграм-поста «лёгкие альтернативы OpenClaw: zero, nano, pi» — `zero` (ZeroClaw) и `pi` уже ресерчены, а `nano` отсутствует. Контекст поста (OpenClaw + lightweight alts) однозначно указывает на экосистему OpenClaw; наиболее вероятный кандидат под «nano» — **NanoClaw** (security-first, 30.4k★). Параллельно исследуется **Nanocoder** (`Nano-Collective/nanocoder`) — отдельный local-first агент, не из Claw-семейства, но тоже «nano»-кандидат. Сводная таблица обновляется один раз для обоих (Stage 1j), чтобы избежать конфликта слияния.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-28 | Тимлид (Алекс) | Создание задачи (Stage 1j расширение закрытого эпика). |
| 2026-07-28 | Аналитик (Шерлок) | Исследование выполнено: отчёт `nanoclaw-comparison.md` (10 критериев, evidence). **Вердикт ❌ Не подходит (4/10, 21/30)** — К6 (CLI JSON/JSONL) — критический блокер сохранён: NanoClaw архитектурно ≡ OpenClaw (gateway + Docker-контейнер на сессию, не autonomous coding-agent). Канонический репо уточнён: `nanocoai/nanoclaw` (`gavrielc/nanoclaw` — раннее зеркало); v2.x — Docker-only (Apple containers были в v1). Сводная таблица обновлена единым Stage 1j-коммитом (NanoClaw #14, 21/30). Ожидает коммита/PR. |
