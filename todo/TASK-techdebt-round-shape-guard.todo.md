---
type: test
created: 2026-11-28 09:39:40 (1795858780)
due: 
started: 2026-11-28 11:23:38 (1795865018)
completed: 
cancelled: 
value: V1
complexity: C2
priority: P3
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Бэкендер Левша (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/round-shape-guard
pr: 
status: in_progress
---

# TASK-techdebt-round-shape-guard: Сторож формы записи раунда и защита от потери данных при перезаписи session.json

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Форма записи раунда продублирована инлайн в 9 местах (канон — `DynamicLoopSessionReaderInterface::getRoundFiles()`), и дрейф уже случался один раз: `ChainSessionWriter::parseRoundFiles()` отстал от канона на поле `invocation` (закрыто PR задачи-источника [TASK-techdebt-writer-round-shape-drift](done/TASK-techdebt-writer-round-shape-drift.todo.md)). Сейчас идентичность копий сторожат только ручные сверки при ревью — при следующем расширении формы расхождение снова будет видно лишь человеку.
- Второй класс рисков — перезапись `session.json` «по белому списку»: `writeSessionJson()` сериализует только известные ключи, поэтому неизвестные ключи `rounds[]` и верхнего уровня теряются молча (арх-ревью Локи, слепые зоны BZ-1/BZ-2).
- Слепая зона BZ-1: верхнеуровневое поле `session.json.invocation` не восстанавливается в `resumeSession()` — первая же запись после resume стирает его до `logInvocation()` (окно потери при падении процесса).
- Тесты roundtrip неполны: не закреплены пустая строка `invocation` (QA-1), multi-round-сценарий с `invocation` (QA-1) и error-ветка при resume в `tests/Unit/Infrastructure/Service/Chain/` (QA-2, пробел есть и на `main`).

### Варианты или путь решения (Solution Sketch)
- Unit-тест-сторож: все объявления `array{system: …}` в `src/`, `apps/`, `tests/` байт-в-байт равны канону — дрейф формы становится фатальным для CI, а не заметным для ревьюера (закрывает OBS-2 ревью Пуаро).
- Roundtrip-тест со всеми 18 полями одной записи — сторожит синхронность белых списков `parseRoundFiles()`/`writeSessionJson()` (теловой уровень, который doc-форма не закрывает).
- BZ-1: восстановить верхнеуровневый `invocation` в `resumeSession()` либо явно задокументировать семантику `latest-wins` (решение по итогам анализа).
- BZ-2: задокументировать (или закрыть) потерю неизвестных ключей при перезаписи `session.json` — док-гард в `writeSessionJson()` или явная семантика в контракте.
- Опционально: расширение roundtrip-теста — второй раунд с `invocation: ''`, multi-round (шаги 1/3/5) с `invocation`, error-раунд с `invocation` (QA-1/QA-2).

### Ожидаемый результат (Expected Result)
- Автоматический сторож идентичности 9 инлайн-копий формы: CI краснеет при любом дрейфе без участия ревьюера.
- Теловой roundtrip всех 18 полей зелёный; при расхождении белых списков чтения/записи тест падает.
- По BZ-1 и BZ-2 принято и зафиксировано решение (исправление либо документированная семантика) без изменения формата `session.json`.
- Статические аналитики и полный PHPUnit зелёные без новых подавлений.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как сопровождающий проекта, я хочу, чтобы дрейф формы записи раунда и потеря данных при перезаписи сессии ловились автоматически тестами, чтобы не полагаться на ручные сверки и внимательность ревьюеров.

### Цель по SMART (Goal)
- **S — конкретно:** добавить unit-тест-сторож идентичности всех копий канонической формы, roundtrip-тест всех 18 полей, закрыть или задокументировать BZ-1 (верхнеуровневый `invocation` при resume) и BZ-2 (потеря неизвестных ключей при перезаписи).
- **M — измеримо:** намеренный дрейф любой копии формы роняет сторож-тест; намеренный пропуск одного поля из белого списка `writeSessionJson()` роняет roundtrip-тест; BZ-1/BZ-2 имеют зафиксированное решение в задаче.
- **A — достижимо:** изменения ограничены модулем `DynamicLoop` и его тестами; механизмы `parseRoundFiles()`/`writeSessionJson()` уже покрыты частными тестами, сторож строится поверх существующей канонической формы.
- **R — релевантно:** устраняет класс рисков, подтверждённый находкой Л-4 (дрейф формы) и OBS-1 (потеря `invocation`) задачи-источника; рекомендация AR-5 архитектурного ревью и QA-1/QA-2 функционального QA.
- **T — ограничено во времени:** дедлайн не назначен (`P3`, `backlog`); выполняется одной итерацией и одним отдельным PR.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `tests/Unit/` (новый сторож-тест и расширение roundtrip-тестов в `tests/Unit/Infrastructure/Service/Chain/`); при решении «исправлять» — `src/Module/DynamicLoop/Infrastructure/Service/ChainSessionWriter.php` (`resumeSession()`, `writeSessionJson()`); при решении «документировать» — docblock-гарды и контракт `DynamicLoopSessionReaderInterface` (только текст).
*   **Текущее поведение:** канон формы — `DynamicLoopSessionReaderInterface::getRoundFiles()`, 18 полей; 9 инлайн-копий байт-в-байт идентичны (SHA-256 `962df094…` после синхронизации в задаче-источнике); `writeSessionJson()` сериализует записи по белому списку; `resumeSession()` не восстанавливает верхнеуровневый `session.json.invocation`.
*   **Прецеденты для переиспользования:** roundtrip-тест `ChainSessionReaderRoundFilesContractTest::resumeRestoresInvocationAndPreservesItThroughSessionJsonRewrite` (задача-источник); мутационная методика проверки тестов из QA-отчёта ветки `task/writer-round-shape-sync`; док-гарды AR-1/AR-2 в `DynamicLoopExecution::restoreFromRoundFiles()`.
*   **Критичные конвенции:** [Тестирование](../docs/conventions/testing/index.md); [Infrastructure](../docs/conventions/layers/infrastructure.md), [Domain](../docs/conventions/layers/domain.md) — при решении по BZ-1 не переносить сериализацию в Domain.
*   **Границы (Out of Scope):** не менять каноническую 18-полевую форму записи и контракт `getRoundFiles()`; не вводить псевдонимы типов (`@psalm-type`/`@phpstan-type`) и DTO/VO записи раунда (триггер пересмотра — 19-е поле в форме, см. архитектурное ревью задачи-источника); не менять формат `session.json`, поведение `logRound()`, метрики, `step`-ключирование и `restoreFromRoundFiles()`; не трогать подавления `RunDynamicLoopService`.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Unit-тест-сторож: все объявления формы `array{system: …}` в `src/`, `apps/`, `tests/` байт-в-байт идентичны канону (`DynamicLoopSessionReaderInterface::getRoundFiles()`); намеренный дрейф одной копии роняет тест.
- [ ] Roundtrip-тест всех 18 полей одной записи: каждое поле, записанное `logRound()`, проходит `session.json` → `parseRoundFiles()` → `getRoundFiles()` без потери; намеренный пропуск любого поля из белого списка `writeSessionJson()` роняет тест.
- [ ] BZ-1 проанализирован и решён: верхнеуровневый `invocation` восстанавливается в `resumeSession()` либо семантика `latest-wins` явно задокументирована в контракте/докблоках; решение зафиксировано в задаче.
- [ ] BZ-2 проанализирован и решён: потеря неизвестных ключей при перезаписи `session.json` закрыта либо явно задокументирована (док-гард в `writeSessionJson()`); решение зафиксировано в задаче.
- [ ] `vendor/bin/psalm`, `vendor/bin/phpstan analyse --no-progress`, полный `vendor/bin/phpunit` — зелёные без новых подавлений.

### 🟡 Желательно (Should Have)
- [ ] Расширение roundtrip-теста (QA-1/QA-2): второй раунд с `invocation: ''`, multi-round-сценарий (шаги 1/3/5) с `invocation`, error-раунд с `invocation` при resume.

### 🟢 Опционально (Could Have)
- [ ] Нет.

### ⚫ Не будем делать (Won't Have)
- [ ] Не менять каноническую 18-полевую форму и контракт `getRoundFiles()`.
- [ ] Не вводить псевдонимы типов, DTO/VO записи раунда.
- [ ] Не менять формат `session.json`, поведение `logRound()`, метрики, `step`-ключирование, `restoreFromRoundFiles()`.
- [ ] Не вводить подавления Psalm/PHPStan.

## 4. План реализации (Implementation Plan)
1. [ ] Написать сторож-тест идентичности копий формы (механика: чтение файлов, извлечение `array{…}`, сравнение с каноном). **Проверка:** мутация копии роняет тест.
2. [ ] Написать roundtrip-тест всех 18 полей. **Проверка:** мутация белого списка `writeSessionJson()` роняет тест.
3. [ ] Проанализировать BZ-1/BZ-2, принять и зафиксировать решение (исправление или документирование). **Проверка:** решение в задаче, реализация или док-гард внесены.
4. [ ] Прогнать `vendor/bin/psalm --no-progress`, `vendor/bin/phpstan analyse --no-progress`, `vendor/bin/phpunit`. **Проверка:** зелёные, без новых подавлений.
5. [ ] Оформить отдельный PR по Conventional Commits, обновить задачу (История изменений).

## 5. Критерии приёмки (Definition of Done)
- [ ] Сторож-тест и roundtrip-тест всех полей добавлены и подтверждены мутационной проверкой.
- [ ] Решения по BZ-1/BZ-2 зафиксированы и реализованы (код или документирование).
- [ ] Статический анализ и полный PHPUnit зелёные; Diff ограничен модулем `DynamicLoop` и тестами (либо отклонение оформлено).
- [ ] Изменения оформлены отдельным PR от актуального `main`, задача переведена в `done` по регламенту.

## 6. Самопроверка (Verification)

```bash
vendor/bin/psalm --no-progress                                     # ожидаемый вывод: No errors found!; фактический: не прогонялось (Правило A)
vendor/bin/phpstan analyse --no-progress                           # ожидаемый вывод: [OK] No errors; фактический: не прогонялось (Правило A)
vendor/bin/phpunit tests/Unit/Infrastructure/Service/Chain/        # ожидаемый вывод: OK; фактический: не прогонялось (Правило A)
vendor/bin/phpunit                                                 # ожидаемый вывод: OK, регрессий нет; фактический: не прогонялось (Правило A)
php vendor/bin/todo-md validate TASK-techdebt-round-shape-guard    # ожидаемый вывод: 0 errors; фактический: 0 errors (прогонено при постановке)
```

## 7. Риски и зависимости (Risks and Dependencies)
- Риск: сторож-тест по тексту файлов может стать хрупким при переформатировании docblock — использовать каноническую строку формы как эталон, а не хардкод всей сигнатуры метода.
- Риск: решение «восстанавливать верхнеуровневый invocation» (BZ-1) меняет поведение resume — проверить всех потребителей верхнеуровневого поля (`logInvocation()`, отчёты).
- Риск: закрытие BZ-2 «в лоб» (сохранение неизвестных ключей) может расширить контракт записи — предпочтительно документирование, если неизвестные ключи считаются повреждением данных.
- Зависимости: задача-источник [TASK-techdebt-writer-round-shape-drift](done/TASK-techdebt-writer-round-shape-drift.todo.md) (синхронизация формы, PR ветки `task/writer-round-shape-sync`).

## 8. Источники (Sources)
- [Архитектурное ревью ветки task/writer-round-shape-sync (AR-5, слепые зоны BZ-1/BZ-2)](../docs/agents/reports/system-architect/2026-11-28_16-12_writer-round-shape-sync-arch-review.md)
- [QA-отчёт ветки task/writer-round-shape-sync (QA-1/QA-2)](../docs/agents/reports/qa-backend/2026-11-28_16-38_writer-round-shape-sync-qa-review.md)
- [Полное ревью ветки task/writer-round-shape-sync (OBS-2 — нет сторожа форм)](../docs/agents/reports/code-reviewer-backend/2026-11-28_15-57_writer-round-shape-sync-review.md)
- [Задача-источник TASK-techdebt-writer-round-shape-drift](done/TASK-techdebt-writer-round-shape-drift.todo.md)
- [Конвенции: Тестирование](../docs/conventions/testing/index.md)

## 9. Комментарии (Comments)
- Задача создана Тимлидом по итогам конвейера TASK-techdebt-writer-round-shape-drift: рекомендации AR-5 (Архитектор Локи), QA-1/QA-2 (Тестировщик Хаус), OBS-2 (Пуаро) приняты в один техдолг вместо расширения PR задачи-источника.
- Приоритет `P3`, ценность `V1` — консервативная оценка техдолга, согласована с задачей-источником; сложность `C2` (несколько тестов + два анализа поведения).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-28 09:39:40 (1795858780) | Тимлид Алекс (pi) | Создание задачи |
