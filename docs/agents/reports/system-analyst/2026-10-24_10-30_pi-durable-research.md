# Ресёрч статьи «Pi Durable» (Earendil Works) для эпика orchestration-articles

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-10-24
**Объект:** пост https://earendil.com/posts/pi-durable/ — документация и анонс библиотеки `@earendil-works/pi-durable` (durable execution для AI-агентов); маппинг на модули task-orchestrator
**Задача:** [TASK-research-pi-durable](../../../../todo/done/TASK-research-pi-durable.todo.md) (эпик [EPIC-research-orchestration-articles](../../../../todo/EPIC-research-orchestration-articles.todo.md), evergreen)

---

## Резюме

Пост Earendil Works описывает экспериментальную библиотеку Pi Durable: долговечный harness (каркас) для агентов, где «every step of a run is a task that stores a checkpoint before it moves on». Источник исследован по методологии эпика из 6 критериев; оценка — **18/18**, итоговый вердикт: **`study`** для архитектурных механизмов (durable-журнал с resume, saga-компенсация, документы-состояние, summary-компакция) + **`apply`** для принципов и контрактов (replay-классификация шагов, `failFast`, «store names, never code», «nothing is deleted»).

## Ключевые находки

1. **Replay-семантика — главный переносимый принцип.** Повтор после сбоя допустим только для read-only вызовов (`replay: "safe"`); мутирующий вызов («a deploy interrupted by a crash») — «reported to the model, never repeated». Наш `RetryingAgentRunnerService` повторяет шаг целиком без различения безопасности повтора — риск двойных побочных эффектов.
2. **Saga-компенсация реализована кодом, а не теорией.** `abort` задачи делает `refund`, каскадом bottom-up: «every task cleans up its own effects first». Идемпотентный ключ `payment-${task.id}` гарантирует однократное списание. Это первая статья трека, закрывающая тему компенсации из базовой методологии эпика.
3. **Ожидание задач с политикой `failFast`.** «Run no code until every payment is done. The first failed payment aborts the others» — готовый контракт для backlog-задач parallel-execution / DAG-оркестрации, стоит зафиксировать до реализации.
4. **Главный gap task-orchestrator:** состояние выполнения цепочки живёт в памяти процесса — смерть оркестратора теряет прогресс. У нас закрыт уровень вызова (retry, circuit breaker, budget), но нет durable-журнала шагов и команды resume.
5. **Контекст:** компакция — фоновая задача с порогами (`reserveTokens: 16384`, `backgroundTokens: 32768`); `reset()`/handoff не удаляет историю («nothing is deleted», поиск `search_history`). У нас — грубое усечение `maxContextLength` без сводки.
6. **Не переносим:** malleable (горячая замена кода) — у нас нет долгоживущих процессов; буквальный API — experimental («the API might still change»); числовые пороги — параметры примера.

## Артефакты

- Отчёт: `docs/research/orchestration-articles/pi-durable-research.md` (10 разделов, маппинг 12 механизмов на модули, failure-handling таблица, рекомендации 1–7)
- Сводная таблица: `docs/research/orchestration-articles-summary.md` — строка #2 (18/18), backlog-рекомендации 6–12, тренды 4–5
- Задача: `todo/done/TASK-research-pi-durable.todo.md` (done)

## Связь с предыдущими исследованиями трека

- Подтверждает вывод [The Orchestrator's Tax](../../../../docs/research/orchestration-articles/orchestrator-tax-research.md): Pi Durable — централизованный orchestrator; durability добавляет вертикаль «состояние», не меняя модель координации.
- Рекомендация №1 (replay-классификация) частично закрывает gap «повтор мутирующего шага», смежный с запретом опасных git-операций из Orchestrator's Tax.

## Проверки

PHPUnit/Psalm пропущены: изменения строго документационные (`docs/research/*`, `todo/*`, этот отчёт) — код, конфигурации и скрипты не затронуты.

## Внешние источники

- https://earendil.com/posts/pi-durable/ — первоисточник
- https://www.npmjs.com/package/@earendil-works/pi-durable — пакет (статус experimental)
