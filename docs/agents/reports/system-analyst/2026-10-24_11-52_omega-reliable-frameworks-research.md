# Ресёрч книги Ω «Инженерия надёжных фреймворков на AI-агентах» для эпика orchestration-articles

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-10-24
**Объект:** книга Ω «Инженерия надёжных фреймворков на AI-агентах» (синтез корпуса переписки, 12 глав, рецензия автора 05.08.2026; внешний URL отсутствует — локальная копия `docs/research/orchestration-articles/sources/omega-reliable-ai-agent-frameworks.html`); маппинг на модули task-orchestrator
**Задача:** [TASK-research-omega-reliable-frameworks](../../../../todo/done/TASK-research-omega-reliable-frameworks.todo.md) (эпик [EPIC-research-orchestration-articles](../../../../todo/EPIC-research-orchestration-articles.todo.md), evergreen)

---

## Резюме

Книга — инженерный синтез полугодовой переписки практика Ω (конвейер AI-агентов, проекты 250–500K строк), построчно сверенный с автором (исправления помечены «Правка автора»). Исследована по методологии эпика из 6 критериев; оценка — **18/18**, итоговый вердикт: **`apply`** для принципов и валидации текущей архитектуры + **`study`** для механизмов следующего уровня (enforcement-хуки и write boundary, словарь классов отказов, dispatch-бюджеты, parking, baseline provenance, adversarial-верификация, production eval) + **`skip`** для блоков вне нашего масштаба (ночная автономность, waves, founder-интерфейс, числа конвейера Ω).

## Ключевые находки

1. **Валидация архитектуры task-orchestrator.** Компоненты «минимального ядра» книги (гл. 11) один-в-один ложатся на наши модули: `ChainDefinition` = Product/Spec layer, `ChainExecution`/`DynamicLoop` = Controller/Scheduler, `AgentRunner` = role adapters, JSONL audit = evidence store. По шкале зрелости книги (L0–L4) мы — **L2 «Verified pipeline»** с элементами L3.
2. **Точная валидация существующих практик.** Fix-итерации (`max_iterations: 3`) — буквально bounded rework книги; бюджеты total/per-step/per-role — budgets как routing; разные модели на ролях в `chains.yaml` — портфель моделей; merge только по подтверждению человека — «что оставлять человеку».
3. **Главный системный gap: enforcement как код** (гл. 5). «Промпт — это policy, enforcement — это код, sandbox и хуки»: у нас почти весь контроль процессный (AGENTS.md + человек + quality gates); write boundary и хуки на рёбрах со стабильными кодами отсутствуют.
4. **Словарь классов отказов** (гл. 10): `spec_gap` / `context_insufficient` / `implementation_failure` / `test_failure` / `budget_exceeded` с разными маршрутами реакций. У нас классификация только на уровне раннера (`ErrorClassificationEnum`: fatal/transient/unknown).
5. **Независимое подтверждение replay-классификации шагов** (гл. 4.7, 7.8): «сначала доказательство безопасности повтора — потом повтор» — та же рекомендация №6 из pi-durable, теперь от второго источника.
6. **Dispatch-бюджеты** (гл. 4): лимит вызовов агента на попытку — дёшево считаемое дополнение к нашим стоимостным бюджетам; числа Ω (21/34) — параметры его домена, не копировать.
7. **Parking ≠ failure** (гл. 4.6): состояние «отложено с handoff-записью» вместо «сбой = стоп цепочки»; ADR-010 (resume) книга валидирует и расширяет.
8. **Не переносим:** ночную автономность (наш merge by design требует человека), waves/worktree (нет параллелизма — вернёмся с parallel/DAG-задачами), founder-интерфейс и installer (мы — composer-библиотека).

## Артефакты

- Отчёт: `docs/research/orchestration-articles/omega-reliable-frameworks-research.md` (10 разделов: 6 критериев, 26 механизмов с вердиктами, маппинг всех 12 глав на модули, failure-handling таблица, самооценка L0–L4, рекомендации 1–11)
- Сводная таблица: `docs/research/orchestration-articles-summary.md` — строка #3 (18/18), backlog-рекомендации 13–22, тренды 6–8
- Задача: `todo/TASK-research-omega-reliable-frameworks.todo.md` (in_progress, до перевода в done требуется PR)

## Связь с предыдущими исследованиями трека

- Подтверждает выводы [The Orchestrator's Tax](../../../../docs/research/orchestration-articles/orchestrator-tax-research.md): тонкий оркестратор, минимальный контекст, «агентов запускать с чистого листа» (= cognitive locality).
- Подтверждает и расширяет [Pi Durable](../../../../docs/research/orchestration-articles/pi-durable-research.md): durable-исполнение (L3) — следующий уровень; добавляет предусловие — replay-классификация до resume, parking — как состояние между «сбой» и «успех».
- Новое измерение трека: экономика верификации («строгость пропорциональна цене ошибки», risk tier) и уровни зрелости L0–L4 как система координат дорожной карты.

## Проверки

PHPUnit/Psalm пропущены: изменения строго документационные (`docs/research/*`, `docs/agents/reports/*`, `todo/*`) — код, конфигурации и скрипты не затронуты. Выполнены: `todo-md validate` (задача, эпик — 0 ошибок), `make validate-language` (новые файлы не в предупреждениях; 12 предупреждений в `docs/releases/*` существовали до правок).

## Внешние источники

Не использовались: первоисточник — локальная копия в репозитории (`docs/research/orchestration-articles/sources/omega-reliable-ai-agent-frameworks.html`), внешний URL у книги отсутствует.
