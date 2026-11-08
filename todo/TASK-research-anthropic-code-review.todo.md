---
type: docs
created: 2026-11-08 12:55:53 (1794142553)
due: 
started: 
completed: 
cancelled: 
value: V3
complexity: C3
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: EPIC-research-orchestration-articles
author: Тимлид Алекс (pi)
assignee: 
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: todo
---

# TASK-research-anthropic-code-review: Статья Anthropic «Solving Code Review» (агентный ревью кода)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Наш процесс ревью кода построен на сабагентах-ревьюверах (Ревьювер Бэка Пуаро — код и архитектура, Тестировщик Бэка Хаус — тест-покрытие) с «здоровым конфликтом» подходов и человеческой приёмкой владельцем; при этом границы автономности агентного ревью, его качество и место в SDLC не систематизированы — мы собираем их по крупицам из ретроспектив.
- Anthropic опубликовал статью «Solving Code Review» (https://claude.dev/blog/solving-code-review-at-anthropic/, официальный блог Claude) о том, как они решают code review с помощью агентов: ревью-агенты, распределение ролей человек/агент, контроль качества и границы автономности.
- Неизвестно: какие решения Anthropic применимы к нашему review-процессу (роли, RACI, SDLC) и какие — к самому движку task-orchestrator (ревью как шаги цепочки, контроль качества, динамические циклы).

### Варианты или путь решения (Solution Sketch)
- Добыть первоисточник через HTTPS-прокси: `CODEX_HTTP_PROXY` из `.env.local` (значение секрета в документах не указывается). Проверено Тимлидом 2026-11-08: статья отвечает HTTP 200 без редиректа. Локальная копия в `docs/research/orchestration-articles/sources/` — прецедент эпика (книга Ω, учебник, курс).
- Исследовать статью по единой методологии эпика из 6 критериев (тезис, паттерн, domain, failure handling, маппинг, применяемость).
- Маппинг на две плоскости: (а) наш review-процесс — роли Пуаро/Хаус, RACI-этапы Review/Acceptance, SDLC, ретро-реестр; (б) движок — что из агентного ревью выражается цепочками (шаги review, контроль качества, когда/postStep).
- Вердикт apply / study / skip по каждому решению статьи.

### Ожидаемый результат (Expected Result)
- Research-документ `docs/research/orchestration-articles/anthropic-code-review-research.md` по 6 критериям с маппингом на наш процесс и движок.
- Строка в сводной таблице `docs/research/orchestration-articles-summary.md` + рекомендации в backlog.
- Чёткий вердикт: что переносить в наш процесс ревью (apply), что проектировать отдельными задачами (study), что неприменимо (skip).

## 1. Концепция и Цель (Concept and Goal)

### Story (Job Story)
> **Job Story:** Когда мы улучшаем процесс ревью кода (сабагенты-ревьюверы, здоровый конфликт, человеческая приёмка) и встраиваем ревью в цепочки движка, я хочу исследовать опыт Anthropic «Solving Code Review» — как устроено агентное ревью: какие агенты, какие роли человека, как меряется качество ревью и где границы автономности, — чтобы перенести проверенные решения в наш SDLC и в дизайн цепочек task-orchestrator.

### Цель по SMART (Goal)
Исследовать статью Anthropic «Solving Code Review» (https://claude.dev/blog/solving-code-review-at-anthropic/) по методологии из 6 критериев. Создать отчёт в `docs/research/orchestration-articles/anthropic-code-review-research.md` с маппингом на review-процесс проекта (роли, RACI, SDLC) и на модули движка, вердиктами apply/study/skip. Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`.

## 2. Контекст и Границы (Context and Scope)

- **Объект:** статья Anthropic «Solving Code Review at Anthropic» (блог claude.dev / claude.com — официальный блог Claude). Тема: агентный code review — ревью-агенты, сотрудничество человек/агент, качество и автономность ревью.
- **Где делаем:** `docs/research/orchestration-articles/anthropic-code-review-research.md`, `docs/research/orchestration-articles-summary.md`, `docs/research/orchestration-articles/sources/` (локальная копия первоисточника).
- **Текущее поведение:** ревью у нас — двух сабагентов (Пуаро — код/архитектура, Хаус — тесты) + self-review исполнителя + приёмка владельцем; распределение по этапам закреплено в RACI; накопленные проблемы — в реестре ретро (`docs/agents/team-retro/RETRO-ROADMAP.md`).
- **Границы (Out of Scope):** написание кода, изменение процесса/архитектуры без решения владельца, бенчмарки. Изменения — отдельными задачами.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] **Критерии 1–6** — по единой методологии эпика (тезис/проблема, паттерн/концепция, domain, failure handling, маппинг на нашу архитектуру, применяемость)
- [ ] Маппинг решений статьи на review-процесс проекта: роли ревьюверов (Пуаро/Хаус), RACI (этапы Review/Acceptance), SDLC, ретро-реестр — со ссылками
- [ ] Маппинг решений статьи на движок task-orchestrator: ревью как шаги цепочки, контроль качества, динамические циклы — со ссылками на модули
- [ ] Вердикт apply / study / skip по каждому решению — с обоснованием и оценкой усилий
- [ ] Первоисточник добыт и зафиксирован локальной копией в `docs/research/orchestration-articles/sources/`

### 🟡 Желательно (Should Have)
- [ ] Сопоставление с накопленными находками ретроспектив (что ревью-процесс уже «лечил» и чем статья это подтверждает/опровергает)
- [ ] Рекомендации для review-скиллов ролей (Пуаро/Хаус) и для будущих задач движка в backlog сводной таблицы

### ⚫ Won't Have (Не будем делать)
- [ ] Код/конфиги, изменение процесса или архитектуры, бенчмарки, автоматизация ревью без отдельного решения владельца

## 4. План реализации (Implementation Plan)
1. [ ] Скачать первоисточник через HTTPS-прокси (`CODEX_HTTP_PROXY` из `.env.local`; проверено 2026-11-08: HTTP 200), сохранить локальную копию в `docs/research/orchestration-articles/sources/` (прецедент эпика: книга Ω, учебник, курс)
2. [ ] Изучить статью по протоколу полного покрытия скилла knowledge-extraction (RAG-допрос по локальной копии)
3. [ ] Актуализировать знание нашего процесса: `docs/workflow/sdlc.md`, `docs/raci-matrix.md`, роли Пуаро/Хаус, `docs/agents/team-retro/RETRO-ROADMAP.md`
4. [ ] Оценить каждый из 6 критериев; маппинг на процесс и на движок (AgentRunner, ChainExecution, ChainDefinition, DynamicLoop)
5. [ ] Создать отчёт `docs/research/orchestration-articles/anthropic-code-review-research.md`
6. [ ] Обновить сводную таблицу `docs/research/orchestration-articles-summary.md` (строка, backlog-рекомендации, тренды)
7. [ ] Создать аналитический отчёт в `docs/agents/reports/system-analyst/` по формату проекта

## 5. Критерии приёмки (Definition of Done)
- [ ] Первоисточник скачан через прокси; локальная копия в `sources/` зафиксирована
- [ ] Отчёт создан, все 6 критериев оценены
- [ ] Маппинг на процесс (роли, RACI, SDLC) и на модули движка — со ссылками на конкретные файлы
- [ ] Вердикт apply/study/skip по каждому решению с обоснованием
- [ ] Строка добавлена в сводную таблицу `docs/research/orchestration-articles-summary.md`
- [ ] Аналитический отчёт в `docs/agents/reports/system-analyst/` создан
- [ ] `make md-links validate-todo validate-language` пройдены (PHPUnit/Psalm не требуются — изменения только в docs/todo)

## 6. Самопроверка (Verification)
```bash
ls docs/research/orchestration-articles/anthropic-code-review-research.md
ls docs/research/orchestration-articles/sources/
php vendor/bin/todo-md validate TASK-research-anthropic-code-review
```

## 7. Риски и зависимости (Risks and Dependencies)
- Доступ к первоисточнику — только через HTTPS-прокси (`CODEX_HTTP_PROXY` из `.env.local`; проверено 2026-11-08: HTTP 200 без редиректа). Прямой доступ из окружения заблокирован по региону. Значение прокси — секрет: в файлах, отчётах и логах не указывается. Содержание не выдумывать; при недоступности — `blocked` с причиной.
- Домен статьи: claude.dev (алиас официального блога Claude); возможный редирект на claude.com — в отчёте зафиксировать финальный URL.
- Зависимостей от других задач нет; эпик ведётся в evergreen-режиме.

## 8. Источники (Sources)

- [Anthropic, «Solving Code Review at Anthropic»](https://claude.dev/blog/solving-code-review-at-anthropic/) — первоисточник

**Внутренние источники:**

- `docs/workflow/sdlc.md`, `docs/raci-matrix.md` — наш процесс и распределение ответственности
- `docs/agents/roles/team/code_reviewer_backend_puaro.ru.md`, `docs/agents/roles/team/qa_backend_house.ru.md` — роли ревьюверов
- `docs/research/orchestration-articles-summary.md` — сводная таблица трека
- `docs/agents/team-retro/RETRO-ROADMAP.md` — накопленные находки ревью-процесса

## 9. Комментарии (Comments)

**Контекст постановки (для исполнителя):** задача добавлена по предложению владельца (2026-11-08) в evergreen-трек статей по оркестрации. Статья — первый в треке источник про агентный code review как отдельную дисциплину; ожидаем пересечение с ретро-находками (здоровый конфликт ревьюверов, человеческая приёмка) и с методологией верификации из книги Ω / белой книги PDLC — пересечения помечать кросс-ссылками на строки сводной таблицы.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-08 12:55:53 (1794142553) | Тимлид Алекс (pi) | Создание задачи |
| 2026-11-08 | Тимлид Алекс (pi) | Уточнение: первоисточник доступен через HTTPS-прокси (`CODEX_HTTP_PROXY` из `.env.local`), проверено HTTP 200 без редиректа; метод добычи в постановке обновлён (без VPN/копии владельца). |
