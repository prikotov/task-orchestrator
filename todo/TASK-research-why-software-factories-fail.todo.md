---
# Metadata (Метаданные)
type: docs
created: 2026-07-27
value: V3
complexity: C3
priority: P2
depends_on:
epic: EPIC-research-approaches-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-why-software-factories-fail
pr:
status: review
---

# TASK-research-why-software-factories-fail: Подход Dex Horthy «Why Software Factories Fail» (front-loaded alignment)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В проекте есть два завершённых ресерч-трека (CLI-агенты кодинга, фреймворки оркестрации), но отсутствует ресерч подходов и процессов разработки с AI (SDLC/PDLC) — третьего профиля нет.
- Первый кандидат — подход Dex Horthy «Why Software Factories Fail» (тезис «Harness Engineering is not Enough»): dark factory (безлюдная фабрика) проваливается, лекарство — front-loaded alignment (предварительное выравнивание) в 4 шага + построчное human review (человеческое ревью).
- Неизвестно: валидирует ли этот подход наш role-based workflow (процесс по ролям) и какие конкретные паттерны принять, адаптировать или отвергнуть.

### Варианты или путь решения (Solution Sketch)
- Создать третий ресерч-эпик `EPIC-research-approaches-comparison` с методологией из 8 критериев (тезис, модель процесса, автономия, качество, context engineering, артефакты, маппинг, применяемость).
- Делегировать Аналитику (Шерлок) исследование подхода Horthy: comparison-док с маппингом каждого тезиса на конкретные артефакты нашего процесса (роли, скиллы, конвенции, AGENTS.md) и вердиктом adopt/adapt/reject.
- Отдельно проверить гипотезу тимлида: шаг «program design» (типы, сигнатуры методов, графы вызовов) у нас явно не формализован в шаблоне задачи.

### Ожидаемый результат (Expected Result)
- Comparison-документ `docs/research/approaches/why-software-factories-fail-comparison.md` по 8 критериям.
- Сводная таблица `docs/research/approaches-summary.md` (строка #1 нового трека).
- Аналитический отчёт в `docs/agents/reports/system-analyst/`.
- Чёткий вердикт: что мы уже делаем (валидация процесса), что усилить (gap'ы), что отвергнуть; гипотеза про `program design` подтверждена или опровергнута.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мы обосновываем и улучшаем наш role-based workflow (前置-выравнивание через шаблон задачи, обязательные review-gate'ы, harness как quality gates), я хочу исследовать подход Dex Horthy «Why Software Factories Fail» — front-loaded alignment в 4 шага, тезис о провале maintainability-награды и dark vs lit factory — чтобы валидировать собственный процесс и выявить конкретные паттерны к принятию (например, формализацию шага «program design»).

### Goal (Цель по SMART)
Исследовать подход Dex Horthy «Why Software Factories Fail» (тезис «Harness Engineering is not Enough») по единой методологии из 8 критериев. Создать отчёт в `docs/research/approaches/why-software-factories-fail-comparison.md` с маппингом на наш процесс (роли, скиллы, конвенции, AGENTS.md) и вердиктом adopt/adapt/reject по каждому тезису. Добавить строку в сводную таблицу `docs/research/approaches-summary.md` (создать, если отсутствует).

## 2. Context and Scope (Контекст и Границы)
*   **Объект:** Dex Horthy (HumanLayer), доклад и двухчастная X-статья «Why Software Factories Fail» / «Harness Engineering is not Enough», AI Engineer World's Fair (2026). Ядро подхода: модель software factory (classic → agentic → dark/lights-off), тезис о том, что harness не решает проблему поддерживаемости (RL-награда проверяет только «тесты прошли», штраф за плохую архитектуру проявляется месяцами и не проходит в обучение), и лекарство — «включить свет обратно»: front-loaded alignment в 4 шага (product review → system architecture → program design → vertical slicing) + построчное человеческое ревью.
*   **Где делаем:** `docs/research/approaches/why-software-factories-fail-comparison.md`, `docs/research/approaches-summary.md`
*   **Текущее поведение:** Наш процесс (AGENTS.md, роли, скиллы `task-via-subagents`/`epic-via-subagents`, шаблон задачи) уже реализует значительную часть前置-выравнивания и обязательные review-gate'ы. Задача — проверить соответствие и выявить gap'ы.
*   **Границы (Out of Scope):** написание кода/конфигов, изменение ролей/конвенций, бенчмарки. Только исследование и рекомендации. Изменения процесса — отдельными задачами.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерий 1–8** — по единой методологии эпика (тезис/проблема, модель процесса, уровень автономии, механизм качества, context engineering, артефакты, маппинг на наш процесс, применяемость)
- [x] Маппинг КАЖДОГО тезиса подхода на конкретные артефакты нашего процесса (роли, скиллы, конвенции, разделы AGENTS.md) — со ссылками
- [x] Вердикт по каждому тезису: adopt / adapt / reject — с обоснованием и оценкой усилий
- [x] Отдельно проверить гипотезу тимлида: шаг «program design» (типы, сигнатуры, графы вызовов) не формализован у нас явно — подтвердить или опровергнуть

### 🟡 Should Have (Желательно)
- [x] Итоговая сводка: что мы УЖЕ делаем (валидация), что стоит УСИЛИТЬ, что ОТВЕРГНУТЬ
- [x] Оценка, как тезис «harness engineering is not enough» влияет на позиционирование нашего продукта (мы — harness с quality gates и human-gate; это «lit factory», не «dark»)
- [x] Явное разделение: тезисы про МОДЕЛИ/RL (информационные) vs тезисы про ПРОЦЕСС (применимые к нам)

### ⚫ Won't Have (Не будем делать)
- [ ] Код/конфиги, изменение ролей/конвенций, бенчмарки

## 4. Implementation Plan (План реализации)
1. [x] Изучить первоисточники (см. секцию Sources) — обратить внимание: X-статьи блокируют ботов, использовать добросовестный вторичный разбор + канонический блог автора + видео доклада
2. [x] Актуализировать знание нашего процесса: AGENTS.md, `docs/agents/roles/team/`, `docs/agents/skills/` (особенно `task-via-subagents`, `epic-via-subagents`), `docs/conventions/`, шаблон задачи
3. [x] Оценить каждый из 8 критериев; по каждому тезису подхода — маппинг + вердикт
4. [x] Проверить гипотезу про «program design» (есть ли явный шаг в шаблоне задачи / конвенциях)
5. [x] Создать отчёт в `docs/research/approaches/why-software-factories-fail-comparison.md`
6. [x] Создать/обновить сводную таблицу `docs/research/approaches-summary.md` (строка #1, статус `1 / N`)
7. [x] Создать аналитический отчёт в `docs/agents/reports/system-analyst/` по формату проекта

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт создан, все 8 критериев оценены
- [x] Маппинг каждого тезиса на наш процесс — со ссылками на конкретные файлы/разделы
- [x] Вердикт adopt/adapt/reject по каждому тезису с обоснованием и усилиями
- [x] Гипотеза про «program design» проверена (подтверждена/опровергнута)
- [x] Строка добавлена в сводную таблицу `docs/research/approaches-summary.md`
- [x] Аналитический отчёт в `docs/agents/reports/system-analyst/` создан

## 6. Verification (Самопроверка)
```bash
ls docs/research/approaches/why-software-factories-fail-comparison.md
ls docs/research/approaches-summary.md
ls docs/agents/reports/system-analyst/*why-software-factories-fail*.md
```

## 7. Sources (Источники)
🔴 **X/Twitter блокирует автоматический доступ.** Первичный текст статьи получен через добросовестный вторичный разбор; сверять с каноническим блогом автора и видео доклада.

- [Dex Horthy, Why Software Factories Fail Part 1](https://x.com/dexhorthy/status/2080697380379427275) — X-статья (заблокирована для ботов)
- [Dex Horthy, Why Software Factories Fail Part 2](https://x.com/dexhorthy/status/2081058573556306030) — X-статья (заблокирована для ботов)
- [Harness Engineering is not Enough: Why Software Factories Fail — доклад, AI Engineer World's Fair](https://www.youtube.com/watch?v=Ib5GBkD555M) — видео доклада
- [Tony Bai — добросовестный разбор доклада (китайский, 2026-07-27)](https://tonybai.com/2026/07/27/why-software-factories-fail-harness-engineering-not-enough/) — основной доступный источник текста (явно цитирует оба ID твита + видео + данные Faros AI)
- [Dex Horthy — Advanced Context Engineering for Coding Agents (HumanLayer blog, 2025-08)](https://www.humanlayer.dev/blog/advanced-context-engineering) — канонический блог автора; источник workflow research/plan/implement и иерархии leverage (research > plan > code)
- [Faros AI — AI Acceleration Whiplash (отраслевой отчёт)](https://www.faros.ai/research/ai-acceleration-whiplash) — данные о падении качества PR-ревью и росте инцидентов

## 8. Comments (Комментарии)
**Резюме содержания статьи (для контекста исполнителя, НЕ заменяет самостоятельное изучение источников):**

Тезис Декса: «тёмная/безлюдная software factory» (dark/lights-off factory — автономная AI-разработка без человеческого чтения кода) **не работает**, и дело не в недостаточно умном harness. RL-награда coding-моделей проверяет только «тесты прошли», а «архитектура плохая / код стал менее поддерживаемым» проверить в моменте нельзя — последствия проявляются месяцами/годами, штраф нельзя провалить обратно в обучение. Модель не способна поддерживать и улучшать качество кодовой базы без постоянного человеческого участия.

Эволюция factory: classic (2022, ручные bottlenecks в реализации и review) → agentic (реализация → агент, review не ускорился → новый bottleneck) → dark (выкинуть review, залить в тесты/мониторинг/canary). Данные Faros AI: с начала 2026 качество PR-ревью падает, доля merge'ей без ревью растёт, инциденты и bugs-per-capita растут.

Лекарство — «включить свет»: **front-loaded alignment в 4 шага**: (1) product review — проблема и ожидаемое поведение; (2) system architecture — контракты, модели данных, ограничения; (3) **program design** — самый недооценённый шаг: типы, сигнатуры методов, стеки вызовов, графы вызовов; (4) vertical slicing — порядок реализации, межрепо-координация, точки проверки. Идея: 30 минут前置-планирования экономят часы переделок в review; человек по-прежнему читает код построчно и владеет им.

**Гипотеза тимлида к проверке:** шаг «program design» у нас явно не формализован (шаблон задачи содержит «Implementation Plan», но НЕ «Solution Design» и не подэтап типов/сигнатур/графов вызовов). Подтвердить или опровергнуть — и дать рекомендацию.

**Перекличка с нашим процессом (предварительно, требует верификации):** front-loaded alignment ↔ наш шаблон задачи + роль-конвейер Шерлок→Гэндальф/Локи→реализация; vertical slicing ↔ декомпозиция эпика + «один PR — одна задача»; mandatory review ↔ AGENTS.md (merge по явному подтверждению, Ревьювер Пуаро, self-review, QA); «harness is not enough» ↔ наш продукт и есть harness (retry, circuit breaker, quality gates, dynamic loops) → позиционирование как «lit factory».

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-27 | Тимлид (Алекс) | Создание задачи. Первая задача эпика EPIC-research-approaches-comparison. |
| 2026-07-27 | Аналитик (Шерлок) | Исследование выполнено: comparison-док `docs/research/approaches/why-software-factories-fail-comparison.md` (8 критериев, 23/24), сводная таблица `docs/research/approaches-summary.md` (строка #1 нового трека), аналитический отчёт. Гипотеза про `program design` **подтверждена**. Вердикт: **adapt** — подход валидирует наш процесс; главный gap — формализация `program design`. Doc-проверки (`validate-todo`/`validate-language`/`md-links`) — зелёные; PHPUnit/Psalm пропущены (docs-only). |
