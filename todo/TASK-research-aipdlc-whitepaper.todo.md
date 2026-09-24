---
type: docs
created: 2026-09-24 02:30:26 (1790217026)
due: 
started: 
completed: 
cancelled: 
value: V3
complexity: C4
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: EPIC-research-orchestration-articles
author: Тимлид Алекс (pi)
assignee: Аналитик Шерлок (pi)
branch: task/research-aipdlc-whitepaper
pr: 
status: todo
---

# TASK-research-aipdlc-whitepaper: Белая книга «AI-Disrupt PDLC» (aipdlc.ru)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- В evergreen-треке статей по оркестрации появилась объёмная белая книга «AI-Disrupt PDLC» (175 страниц, 8 частей): ИИ-нативная трансформация разработки ПО — двухпетлевая модель, мультиагентные паттерны, validation spine, governance mesh, Guardian Agents, токеномика, метрики.
- Неизвестно: какие архитектурные паттерны книги применимы к task-orchestrator, что относится к корпоративной методологии (вне нашего домена), и какие gaps она подсвечивает.

### Варианты или путь решения (Solution Sketch)
- Исследовать книгу по единой методологии эпика из 6 критериев, с фокусом на Части 2 (архитектурное ядро: агентные паттерны, мультиагентные системы, eval-driven development, governance mesh); методологические/корпоративные части — компактно, с разграничением доменов.
- Первоисточник публичный (PDF по URL), локальная копия не требуется; зафиксировать версию (v2.0, июнь 2026) в отчёте.

### Ожидаемый результат (Expected Result)
- Research-отчёт `docs/research/orchestration-articles/aipdlc-whitepaper-research.md` по 6 критериям с маппингом на task-orchestrator и вердиктами apply/study/skip; строка в сводной таблице `docs/research/orchestration-articles-summary.md`.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story или Job Story)
> **Job Story:** Когда мы развиваем систему оркестрации AI-агентов (AgentRunner, ChainExecution, DynamicLoop, GitIdentity), я хочу исследовать белую книгу «AI-Disrupt PDLC» — прежде всего её архитектурное ядро (двухпетлевая модель, паттерны агентных систем, мультиагентное проектирование, сквозная ИИ-валидация, governance mesh), чтобы определить применимые паттерны для task-orchestrator и выявить gaps.

### Цель по SMART (Goal)
Исследовать белую книгу «AI-Disrupt PDLC» v2.0 (https://aipdlc.ru/documents/ru/whitepaper_full_ru.pdf, 175 стр., 8 частей) по единой методологии из 6 критериев. Создать отчёт в `docs/research/orchestration-articles/aipdlc-whitepaper-research.md` с маппингом архитектурных паттернов (акцент — Часть 2) на нашу архитектуру (AgentRunner, ChainExecution, DynamicLoop, GitIdentity, ChainDefinition) и вердиктом apply / study / skip. Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`.

## 2. Контекст и Границы (Context and Scope)
* **Объект:** «AI-Disrupt PDLC. ИИ-нативная трансформация разработки ПО для зрелого корпоративного контура. Практическое руководство», v2.0, июнь 2026 (Альвианский Алексей; титул упоминает Сбер). Первоисточник: https://aipdlc.ru/documents/ru/whitepaper_full_ru.pdf
* **Где делаем:** `docs/research/orchestration-articles/aipdlc-whitepaper-research.md`, `docs/research/orchestration-articles-summary.md`
* **Границы (Out of Scope):** написание кода/конфигов, изменение архитектуры, бенчмарки. Корпоративные/регуляторные части (Часть 8) — только контекстно, без детального разбора. Только исследование и рекомендации. Изменения — отдельными задачами вне эпика.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] **Критерии 1–6** — по единой методологии эпика (тезис/проблема, паттерн/концепция, domain, failure handling, маппинг на нашу архитектуру, применяемость)
- [ ] Детальный разбор Части 2 «Архитектурное ядро PDLC»: двухпетлевая модель с ИИ-валидацией, петля намерения, уровни автономии/task horizon, архитектурные паттерны агентных систем (2.7), контекст-инжиниринг и сжатие (2.8), мультиагентные системы (2.9), долгосрочные агенты и передача сессии (2.10), eval-driven development и evidence bundle (2.11), validation spine (2.12), governance mesh (2.13) — маппинг на компоненты task-orchestrator со ссылками
- [ ] Вердикт apply / study / skip по каждому ключевому паттерну — с обоснованием и оценкой усилий
- [ ] Явное разграничение доменов: паттерны оркестрации (наш домен) vs корпоративная методология/люди/регуляторика (вне домена — кратко)
- [ ] Строка в сводной таблице `docs/research/orchestration-articles-summary.md`

### ⚫ Won't Have (Не будем делать)
- Код/конфиги, изменение архитектуры, бенчмарки; детальный разбор корпоративно-регуляторной Части 8

## 4. План реализации (Implementation Plan)
1. [ ] Изучить первоисточник: https://aipdlc.ru/documents/ru/whitepaper_full_ru.pdf (v2.0, июнь 2026; 175 стр.) — оглавление, резюме для руководства, Части 1–2 детально, Части 3–7 обзорно, заключение и глоссарий
2. [ ] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`, `src/Module/GitIdentity/`, `docs/guide/architecture.md`
3. [ ] Оценить 6 критериев; по паттернам Части 2 — маппинг + вердикт apply/study/skip; разграничить домены
4. [ ] Проверить гипотезы: (а) validation spine ↔ наши quality gates и fix_iterations; (б) governance mesh ↔ AGENTS.md/конвенции/hooks — чем отличаемся; (в) событийно-управляемые агенты (7.3) ↔ event-driven расширение оркестратора
5. [ ] Создать отчёт в `docs/research/orchestration-articles/aipdlc-whitepaper-research.md`
6. [ ] Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`

## 5. Критерии приёмки (Definition of Done)
- [ ] Отчёт создан, все 6 критериев оценены, Часть 2 покрыта паттерн-за-паттерном
- [ ] Вердикт apply/study/skip по каждому ключевому паттерну с обоснованием и усилиями
- [ ] Домены разграничены (оркестрация vs корпоративная методология), гипотезы проверены
- [ ] Строка добавлена в сводную таблицу `docs/research/orchestration-articles-summary.md`

## 6. Самопроверка (Verification)
```bash
ls docs/research/orchestration-articles/aipdlc-whitepaper-research.md
php vendor/bin/todo-md validate todo/TASK-research-aipdlc-whitepaper.todo.md
```

## 7. Риски и зависимости (Risks и Dependencies)
- Объём первоисточника (175 стр.) — риск поверхностного разбора; обязателен детальный разбор Части 2, остальные части — обзорно с явной пометкой.
- Источник — живой документ (v2.0): фиксировать версию и дату в отчёте; при смене версии — сверка оглавления.
- Пересечение с треком `EPIC-research-approaches-comparison` (SDLC/PDLC-подходы): при обнаружении методологических находок, релевантных тому треку, — кросс-ссылка на его сводную таблицу, без дублирования разбора.

## 8. Источники (Sources)
📚 **Внешние источники** (до 5, кратким списком):

- [AI-Disrupt PDLC — белая книга, v2.0, полный текст (PDF)](https://aipdlc.ru/documents/ru/whitepaper_full_ru.pdf) — первоисточник
- [aipdlc.ru — сайт методологии](https://aipdlc.ru/) — контекст и обновления версий

🔗 **Внутренние источники:**

- `docs/research/orchestration-articles/orchestrator-tax-research.md` — прецедент отчёта трека
- `docs/guide/architecture.md` — архитектура проекта
- `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`, `src/Module/GitIdentity/` — модули для маппинга

## 9. Комментарии (Comments)
**Резюме содержания книги (для контекста исполнителя, НЕ заменяет самостоятельное изучение первоисточника):**

Центральный тезис: «Модель задаёт минимально доступное качество. Среда работы определяет возможности». Три сдвига: ИИ-агент — субъект петли реализации, человек — субъект петли намерения; качество определяет среда вокруг модели; контур управления встраивается в каждый шаг (метафора «Формула-1»: четыре контура — намерение, реализация, валидация, управление).

- **Часть 1** — основания трансформации: экономический императив, «среда важнее модели», рынок агентного ИИ, рамка Discovery.
- **Часть 2** — архитектурное ядро (главная для нас): двухпетлевая модель с ИИ-валидацией; петля намерения; уровни автономии и task horizon; децентрализация IDE; архитектурные паттерны агентных систем; контекст-инжиниринг и сжатие; мультиагентные системы; долгосрочные агенты и передача сессии; eval-driven development и evidence bundle; validation spine; governance mesh.
- **Часть 3** — роли и команды: от исполнителя к оркестратору, tiny teams, уровни зрелости L0–L5.
- **Часть 4** — IDP (Integrated Development Platform): Guardian Agents и уровни их зрелости.
- **Часть 5** — безопасность и governance: токеномика, cost-to-outcome, динамическое распределение вычислений.
- **Часть 6** — метрики: категории AWS, сводная система метрик PDLC, искажения метрик.
- **Часть 7** — стратегия внедрения: горизонты, событийно-управляемые агенты, антипаттерны трансформации.
- **Часть 8** — российский корпоративный контекст: регуляторика, Policy-as-Code «RU Compliance Core», локализация governance.

**Перекличка с нашей системой (предварительно, требует верификации):** двухпетлевая модель ↔ цепочки `ChainExecution` + фикс-итерации; validation spine ↔ quality gates и `make check`; evidence bundle ↔ критерии приёмки todo-md + PR-проверки; governance mesh ↔ конвенции/AGENTS.md/hooks; мультиагентные паттерны ↔ роли `config/chains.yaml`; уровни автономии ↔ режимы раннеров; событийно-управляемые агенты ↔ потенциальное event-driven расширение.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-24 02:30:26 (1790217026) | Тимлид Алекс (pi) | Создание задачи (предложена владельцем). Первоисточник — публичная белая книга v2.0 по URL; сложность C4 из-за объёма (175 стр.) с обязательным детальным разбором Части 2 |
