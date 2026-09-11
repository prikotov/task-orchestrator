---
type: docs
created: 2026-09-11 03:50:15 (1789098615)
due: 
started: 2026-09-11 03:52:32 (1789098752)
completed: 
cancelled: 
value: V3
complexity: C3
priority: P2
cost_plan: 
cost_fact: 85000
depends_on: 
epic: EPIC-research-orchestration-articles
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-t3-code
pr: 
status: in_progress
---

# TASK-research-t3-code: T3 Code (pingdotgg): кейс оркестрации агент-харнесов (BYO harness, branch-per-thread, one-button PR)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Наш движок оркестрации AI-агентов развивается без систематического изучения того, как задачу оркестрации агентов решают сторонние продукты.
- T3 Code (pingdotgg / Theo Browne) — открытый (MIT, ~22.4k звёзд) «agent harness control surface» (панель управления агент-харнесами): оркестрирует ЧУЖИЕ агент-харнесы (Claude Code, Codex, Cursor, Grok Build, OpenCode, Google Antigravity) через web-, desktop- и мобильные приложения.
- Ключевые паттерны T3 Code не исследованы: BYO harness (подключение харнесов по подпискам пользователя), branch-per-thread (поток агента → своя ветка), one-button PR (одна кнопка commit/push/PR с генерацией описания), switch harness/model mid-thread (смена исполнителя посреди потока), remote control (управление с телефона). Неизвестно, что из этого применимо к нашему AgentRunner, ChainExecution и GitIdentity.

### Варианты или путь решения (Solution Sketch)
- Research-задача по единой методологии эпика из 6 критериев: отчёт `docs/research/orchestration-articles/t3-code-research.md` с маппингом каждого паттерна на компоненты task-orchestrator и вердиктом apply / study / skip.
- Объект исследования — открытый репозиторий и документация (продукт как первоисточник паттернов оркестрации; прецедент — TASK-research-agents-crew в родственном эпике approaches).

### Ожидаемый результат (Expected Result)
- Research-документ `docs/research/orchestration-articles/t3-code-research.md` по 6 критериям.
- Обновлённая сводная таблица `docs/research/orchestration-articles-summary.md` (строка #2 трека).
- Аналитический отчёт в `docs/agents/reports/system-analyst/`.
- Чёткий вердикт: какие паттерны T3 Code применять в task-orchestrator, какие изучить детально (gaps), какие неприменимы.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story / Job Story)
> **Job Story:** Когда мы проектируем и улучшаем нашу систему оркестрации AI-агентов (AgentRunner, ChainExecution, DynamicLoop, GitIdentity), я хочу исследовать T3 Code — открытый «agent harness control surface», оркестрирующий Claude Code, Codex, Cursor, Grok Build, OpenCode и Antigravity, — чтобы выявить паттерны оркестрации мульти-харнесов, применимые к нашему движку, и не изобретать колёса.

### Цель по SMART (Goal)
Исследовать T3 Code (репозиторий `pingdotgg/t3code`: README, `docs/internals/`, `docs/user/`; сайт t3.codes) по единой методологии эпика из 6 критериев. Создать отчёт в `docs/research/orchestration-articles/t3-code-research.md` с маппингом каждого паттерна на нашу архитектуру (AgentRunner, ChainExecution, DynamicLoop, ChainDefinition, GitIdentity) и вердиктом apply / study / skip. Добавить строку в сводную таблицу `docs/research/orchestration-articles-summary.md`.

## 2. Контекст и Границы (Context and Scope)

- **Объект:** pingdotgg/t3code — «agent harness control surface» (TypeScript, MIT, ~22.4k звёзд, создан 2026-02). Формы: web (`app.t3.codes`), Electron desktop (macOS/Windows/Linux), iOS/Android, backend-сервер `npx t3@latest`. Подключает харнесы по СУЩЕСТВУЮЩИМ подпискам пользователя (без реселлинга токенов): Claude Code (`claude auth login`), Codex (`codex login`), Cursor (`agent login`), Grok Build (`grok login`), OpenCode (`opencode auth login`), Antigravity (Google sign-in). Ключевые фичи: каждый поток агента пишет в свою ветку; одна кнопка → commit/push/PR с автогенерацией title/body/changelog; inline diff review до пуша; draft/stack PR; смена модели/харнеса посреди потока; remote access с телефона; permission modes (режимы автономии); background service; несколько аккаунтов на провайдера.
- **Где делаем:** `docs/research/orchestration-articles/t3-code-research.md`, `docs/research/orchestration-articles-summary.md`, `docs/agents/reports/system-analyst/`
- **Текущее поведение:** наш AgentRunner абстрагирует запуск раннеров (pi, codex) через `config/chains.yaml` (секция `roles.<role>.command`); ChainExecution исполняет цепочки шагов; GitIdentity обеспечивает идентичность бота для push/PR; запуск сабагентов — через `watch-subagent.sh`.
- **Границы (Out of Scope):** написание кода/конфигов, изменение архитектуры, бенчмарки, установка и запуск T3 Code (исследование — по исходникам и документации). Изменения архитектуры — отдельными задачами вне эпика.

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [x] **Критерии 1–6** — по единой методологии эпика (тезис/проблема, паттерн/концепция, domain, failure handling, маппинг на нашу архитектуру, применяемость)
- [x] Маппинг КАЖДОГО паттерна T3 Code (harness abstraction/нормализация контракта харнеса, branch-per-thread, one-button PR, thread/session management, switch harness/model mid-thread, remote control, BYO subscription, permission modes) на компоненты task-orchestrator (AgentRunner, ChainExecution, DynamicLoop, ChainDefinition, GitIdentity) — со ссылками
- [x] Вердикт по каждому паттерну: apply / study / skip — с обоснованием и оценкой усилий (effort)
- [x] Отдельно проверить гипотезы тимлида (см. секцию «Комментарии»)

### 🟡 Желательно (Should Have)
- [x] Итоговая сводка: что мы УЖЕ делаем правильно (валидация), что ИЗУЧИТЬ детально (gaps), что НЕ ПРИМЕНИМО
- [x] Сравнение с нашим механизмом запуска сабагентов (`watch-subagent.sh`, `config/chains.yaml`): нормализация контрактов раннеров, профиль делегирования роли
- [x] Явное разделение: паттерны уровня UI/продукта (информационные) vs паттерны уровня движка оркестрации (применимые к нам)

### ⚫ Не будем делать (Won't Have)
- Код/конфиги, изменение архитектуры, бенчмарки, установка продукта

## 4. План реализации (Implementation Plan)
1. [x] Изучить первоисточники: `github.com/pingdotgg/t3code` (README, `docs/internals/overview.md`, `docs/user/source-control.md`, `docs/user/remote-access.md`, `docs/user/permission-modes.md`), landing `t3.codes`
2. [x] Актуализировать знание нашей архитектуры: `src/Module/AgentRunner/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/`, `src/Module/ChainDefinition/`, `src/Module/GitIdentity/`, `docs/guide/architecture.md`
3. [x] Оценить каждый из 6 критериев; по каждому паттерну — маппинг + вердикт apply/study/skip
4. [x] Проверить гипотезы тимлида (секция «Комментарии»)
5. [x] Создать отчёт `docs/research/orchestration-articles/t3-code-research.md`
6. [x] Обновить сводную таблицу `docs/research/orchestration-articles-summary.md` (строка #2, актуализировать «Часть 5. Тренды»)
7. [x] Создать аналитический отчёт в `docs/agents/reports/system-analyst/` по формату проекта

## 5. Критерии приёмки (Definition of Done)
- [x] Отчёт создан, все 6 критериев оценены
- [x] Маппинг каждого паттерна на нашу архитектуру — со ссылками на конкретные файлы/компоненты
- [x] Вердикт apply/study/skip по каждому паттерну с обоснованием и усилиями
- [x] Гипотезы тимлида проверены (подтверждены/опровергнуты — явно)
- [x] Строка добавлена в сводную таблицу `docs/research/orchestration-articles-summary.md`
- [x] Аналитический отчёт в `docs/agents/reports/system-analyst/` создан
- [x] `php vendor/bin/todo-md validate` — без ошибок

## 6. Самопроверка (Verification)
```bash
ls docs/research/orchestration-articles/t3-code-research.md
ls docs/research/orchestration-articles-summary.md
ls docs/agents/reports/system-analyst/*t3-code*.md
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Продукт молодой (создан 2026-02, «very early» по заявлению авторов), активно меняется — зафиксировать в отчёте дату исследования и изученную версию/состояние репозитория.
- Категория источника: в эпике заявлены «статьи», T3 Code — продукт/репозиторий; рассматриваем его как первоисточник паттернов оркестрации (прецедент — TASK-research-agents-crew в родственном эпике `EPIC-research-approaches-comparison`).
- Мобильные приложения и серверная часть могут быть слабо документированы — исследовать по открытым исходникам и `docs/`; не домысливать.
- Внешние источники могут быть недоступны — опираться на README и docs репозитория.

## 8. Источники (Sources)
📚 **Внешние источники** (до 5, кратким списком):

- [t3.codes](https://t3.codes/) — лендинг продукта
- [github.com/pingdotgg/t3code](https://github.com/pingdotgg/t3code) — репозиторий (README, docs/)
- [docs/internals/overview.md](https://github.com/pingdotgg/t3code/blob/main/docs/internals/overview.md) — внутренняя архитектура
- [docs/user/source-control.md](https://github.com/pingdotgg/t3code/blob/main/docs/user/source-control.md) — Git-интеграция (branch-per-thread, PR)
- [docs/user/remote-access.md](https://github.com/pingdotgg/t3code/blob/main/docs/user/remote-access.md) — удалённый доступ

🔗 **Внутренние источники:**

- `docs/guide/architecture.md` — архитектура проекта
- `src/Module/AgentRunner/` — модуль запуска AI-агентов
- `src/Module/ChainExecution/` — модуль исполнения цепочек
- `src/Module/DynamicLoop/` — модуль динамических циклов
- `src/Module/ChainDefinition/` — модуль определения цепочек
- `src/Module/GitIdentity/` — модуль Git-идентичности бота
- `docs/research/orchestration-articles/orchestrator-tax-research.md` — предыдущее исследование трека
- `docs/research/orchestration-articles-summary.md` — сводная таблица трека

## 9. Комментарии (Комментарии)

**Резюме объекта исследования (для контекста исполнителя, НЕ заменяет самостоятельное изучение источников):**

T3 Code позиционируется как «agent harness control surface»: НЕ собственный агент, а оркестрация чужих харнесов. Авторы вдохновлялись Codex desktop app, Conductor, Claude Desktop, Cursor Glass, но сделали ставку на производительность, remote-ready и открытость (MIT, форк приветствуется). Модель монетизации — ничего не продаём: «Bring your own subscription», токены не реселлятся, квот не вводится.

**Гипотезы тимлида к проверке:**

1. **T3 Code — «мета-оркестратор» (orchestrator of orchestrators):** его юниты оркестрации (Claude Code, Codex) — сами полноценные агент-харнесы. Наш AgentRunner решает ту же задачу абстракции раннеров (pi, codex). Вопрос: что взять — нормализованный контракт харнеса, permission modes, поддержку нескольких аккаунтов на провайдера?
2. **Branch-per-thread:** каждый поток агента пишет в свою ветку, одна кнопка → commit/push/PR с генерацией title/body. У нас GitIdentity + ручной процесс (`docs/git-workflow/`). Маппинг: изоляция «поток оркестрации → ветка» применима к нашим параллельным сабагентам?
3. **Switch harness/model mid-thread:** смена исполнителя посреди потока с сохранением контекста. Как это соотносится с нашим fallback/retry между раннерами в ChainExecution? Совместим ли контекст между разными раннерами у нас?
4. **Remote control:** управление агентами с телефона/web вне рабочей машины. У нас CLI-only — потенциальный трек на будущее (изучение, не реализация).
5. **BYO subscription как экономическая модель:** оркестратор не дублирует провайдера, а переиспользует авторизацию раннера. Для нас — аналогия: task-orchestrator не вводит своего биллинга, а использует авторизацию настроенных раннеров.
6. **Permission modes (режимы автономии):** градации самостоятельности агента. Сравнить с нашей моделью подтверждений (апрув пользователя на merge, запрет автоматических коммитов).

**Связь с предыдущим исследованием трека:** в `orchestrator-tax-research.md` вердикт — мы централизованный orchestrator, и это осознанно. T3 Code — вторая точка зрения на ту же проблему: централизованная контрольная поверхность НАД независимыми оркестраторами. Проверить, не «платит» ли T3 Code тот самый orchestrator tax, и как он его минимизирует.

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-11 03:50:15 (1789098615) | Тимлид (Алекс) | Создание задачи |
| 2026-09-11 | Аналитик (Шерлок) | Исследован T3 Code на commit `0a37240a87bb2fed8f48cf20dc8312ee3b94eba6`: подготовлен отчёт по 6 критериям и 8 паттернам, проверены 6 гипотез, обновлены сводка и аналитический отчёт; `cost_fact` зафиксирован по доступной оценке контекста сессии. |
