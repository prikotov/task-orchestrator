---
type: test
created: 2026-11-24 03:50:21 (1795492221)
due: 
started: 2026-11-27 16:58:48 (1795773528)
completed: 2026-11-27 17:35:47 (1795775747)
cancelled: 
value: V1
complexity: C1
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
branch: task/runcontext-retry-policy-cases
pr: '#518'
status: done
---

# TASK-techdebt-runcontext-retry-policy-cases: Симметричные retry-policy кейсы в контракт-тесте RunDynamicLoopAgentService

<!--
  Секция создана по шаблону docs/todo-md/templates/task.md по решению владельца 2026-11-24
  (неблокирующая рекомендация ревью PR #493). Плейсхолдеры заменены, AI-инструкции удалены.
-->

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

В задаче [TASK-techdebt-runfacilitator-vo](done/TASK-techdebt-runfacilitator-vo.todo.md) (PR #493) появился контракт-тест `RunDynamicLoopAgentServiceTest`: он проверяет, что value-объекты контекста (VO — небольшие неизменяемые объекты с полями) передаются в вызовы раннеров агентов «поле в поле», без перестановок и потерь. Но такая страховка покрывает только часть сценариев: для завершающего прохода цикла (finalize — вызов `runFacilitatorFinalize`) и для прохода участника с ненулевой `retryPolicy` (policy повторов — правила, сколько раз перезапускать шаг при сбое) симметричных проверок в тесте нет. Код сейчас работает верно — это не баг; проблема в том, что при будущих правках сигнатур или полей VO регрессия именно на этих путях может проскочить незамеченной: тест будет зелёным не потому, что всё хорошо, а потому, что эти пути он не смотрит. Мешает команде: сужает защитную сетку, введённую в PR #493, ровно там, где маппинг тоже нетривиальный.

### Варианты или путь решения (Solution Sketch)

Один вариант: дополнить существующий `RunDynamicLoopAgentServiceTest` двумя-тремя кейсами по образцу уже имеющихся (заготовки стабов и хелперы в файле переиспользовать):

1. finalize-кейс: `FacilitatorFinalizeRunContextVo` со всеми заполненными полями → проверить 1:1 маппинг в вызов `runFacilitatorFinalize`;
2. participant-кейс с ненулевой `retryPolicy` → проверить, что политика повторов доезжает до раннера без перепутывания полей;
3. (опционально) facilitator-кейс с ненулевой `retryPolicy` — если окажется, что существующий start/continue-кейс уже покрывает ненулевую политику, не дублировать.

Решение не задано жёстко; исполнитель может предложить минимальный вариант в плане реализации, сохранив принцип: симметрия покрытия с существующими кейсами, без новых абстракций и без рефакторинга теста.

### Ожидаемый результат (Expected Result)

Контракт всех трёх методов раннера (`runFacilitator`, `runParticipant`, `runFacilitatorFinalize`) покрыт маппинг-тестами симметрично: любая перестановка или потеря поля VO на любом из путей роняет тест. Пользователь этого увидит так: при ревье будущих правок `DynamicLoop` исчезает класс рисков «тест не смотрит этот путь», а отчёт ревьювера перестаёт повторять эту рекомендацию.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)

> **Job Story:** Когда я правлю сигнатуры или поля VO в `DynamicLoop` и прогоняю контракт-тест, я хочу, чтобы finalize- и participant-пути с ненулевой `retryPolicy` проверялись так же строго, как facilitator start/continue, чтобы регрессия маппинга на этих путях ловилась автоматически.

### Цель по SMART (Goal)

Дополнить `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php` кейсами: finalize с полным `FacilitatorFinalizeRunContextVo` и participant с ненулевой `retryPolicy` — каждый проверяет пофайловый 1:1 маппинг в вызов раннера, по образцу существующих кейсов файла. Критерий: временная перестановка любого смещённого поля в соответствующем VO роняет тест (мутационная проверка при реализации); полный `make check` зелёный.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php` (только тестовый файл; продакшн-код `src/Module/DynamicLoop/` не трогается).
*   **Текущее поведение:** тест покрывает facilitator-сценарии (start/continue) — по 6 тестам / 128 assertions на 5 стабах; finalize- и retryPolicy-пути не проверяются (зафиксировано в отчёте ревью PR #493 как неблокирующая рекомендация).
*   **Прецеденты для переиспользования:** существующие кейсы и стабы того же файла — копировать их структуру (arrange-блоки, проверка порядка аргументов); сами VO — `src/Module/DynamicLoop/Domain/ValueObject/` (`FacilitatorFinalizeRunContextVo`, `ParticipantRunContextVo`).
*   **Критичные конвенции:** [Конвенции тестирования](../docs/conventions/testing/index.md); стиль кейсов — по соседним тестам файла.
*   **Границы (Out of Scope):** любые правки `src/`; рефакторинг/переименование существующих кейсов и стабов теста; повышение `complexity`/пересмотр контрактов `RunDynamicLoopAgentServiceInterface`; покрытие прочих модулей.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)

- [x] Кейс finalize: полный `FacilitatorFinalizeRunContextVo` → `runFacilitatorFinalize`; проверен 1:1 маппинг всех полей (по образцу существующих кейсов файла). — `runFacilitatorFinalizeMapsEnabledRetryPolicyToExecutionRetryPolicy`, все 8 полей VO проверены (после доработки CR-1 +17 строк: захват `$writtenPrompts`/`$finalizeContextArgs`).
- [x] Кейс participant с ненулевой `retryPolicy`: политика повторов доезжает до `runParticipant` без перепутывания полей. — `runParticipantMapsEnabledRetryPolicyToExecutionRetryPolicy` (3/250/8000/2.5).
- [x] Эффективность новых кейсов подтверждена мутационной проверкой при реализации: временная перестановка смещённого поля роняет тест, после чего код возвращён как был. — см. запись в Change History от 2026-11-27.
- [x] `vendor/bin/phpunit` и `vendor/bin/psalm` зелёные. — полный phpunit 1782 тестов / 5372 утверждения (2 skipped — сторонние), psalm: No errors found.

### 🟡 Желательно (Should Have)

- [x] Если существующий facilitator-кейс уже использует ненулевую `retryPolicy` — в отчёте задачи указать это явно (дубликат не заводить). — существующий кейс `runFacilitatorMapsEnabledRetryPolicyToExecutionRetryPolicy` (строка ~175) уже покрывает ненулевую политику 2/500/9000/3.0; третий кейс не заведён (подтверждено ревью Пуаро, CR-3).

### 🟢 Опционально (Could Have)

- [ ] Третий кейс facilitator с ненулевой `retryPolicy`, если Should-1 покажет непокрытость.

### ⚫ Не будем делать (Won't Have)

- [ ] Правки продакшн-кода `src/`.
- [ ] Рефакторинг существующих кейсов/стабов теста.
- [ ] Заведение новых задач из этого процесса.

## 4. План реализации (Implementation Plan)

1. [x] Перечитать существующие кейсы `RunDynamicLoopAgentServiceTest.php`, отчёт ревью PR #493 (раздел с рекомендацией) и VO `FacilitatorFinalizeRunContextVo`/`ParticipantRunContextVo`.
2. [x] Добавить кейс finalize (Must-1) по образцу существующих.
3. [x] Добавить кейс participant с ненулевой `retryPolicy` (Must-2).
4. [x] Мутационная проверка: переставить поле → убедиться в падении → вернуть (Must-3).
5. [x] `vendor/bin/phpunit`, `vendor/bin/psalm`; при необходимости точечный `composer install`.
6. [x] Обновить Change History задачи, перевести в `done`, PR (#518).

## 5. Критерии приёмки (Definition of Done)

- [x] Сценарий протестирован (Unit/Integration)
- [x] Нет регрессий в смежных модулях
- [x] Обновлена техническая документация (если требуется) — PHPDoc класса теста дополнен; CLI/контракты не менялись
- [x] Кейсы Must-1 и Must-2 добавлены, файл теста симметрично покрывает все три метода интерфейса.
- [x] Мутационная проверка выполнена и зафиксирована в Change History задачи.
- [x] `vendor/bin/phpunit` (полный) и `vendor/bin/psalm` — зелёные. Полный барьер `make check` — зелёный.
- [x] Задача в `done`, файл в `todo/done/`, поле `pr` заполнено (PR #518).

## 6. Самопроверка (Verification)

```bash
vendor/bin/phpunit                                            # все тесты
vendor/bin/phpunit tests/Unit/Common/Module/DynamicLoop/      # целевой модуль
vendor/bin/psalm                                              # статический анализ
make check                                                    # полный барьер перед PR
php vendor/bin/todo-md validate TASK-techdebt-runcontext-retry-policy-cases
```

## 7. Риски и зависимости (Risks and Dependencies)

- Зависимость: структура стабов существующего теста; если при реализации окажется, что finalize-путь недостижим через публичный контракт сервиса стабами, — остановиться и уточнить у тимлида (не выдумывать обходы).
- Риск: мутационная проверка (Must-3) требует аккуратности — мутация только во временной рабочей копии и обязательно откатывается; потеря отката = испорченное рабочее дерево (контроль `git status` до и после).

## 8. Источники (Sources)

- [ ] [Отчёт ревью PR #493 (Пуаро) — раздел с неблокирующей рекомендацией](../docs/agents/reports/code-reviewer-backend/2026-11-23_22-08_runfacilitator-vo-review.md)
- [ ] [Задача TASK-techdebt-runfacilitator-vo (done) — источник контракта](done/TASK-techdebt-runfacilitator-vo.todo.md)
- [ ] Тест `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php`
- [ ] VO: `src/Module/DynamicLoop/Domain/ValueObject/` (`FacilitatorFinalizeRunContextVo`, `ParticipantRunContextVo`)

## 9. Комментарии (Comments)

- Задача заведена по решению владельца 2026-11-24 («если нужно — оформи задачей») на базе неблокирующей рекомендации ревьюера в PR #493; приоритет P3 — отложенная страховка, не блокирует ничего.
- Тип фикса — расширение тестовой сетки; в реестре ретроспектив отдельной записи не имеет (рекомендация ревью, не ретро-находка).
- Решение тимлида 2026-11-27 (CR-4 отчёта ревью 2026-11-27_17-15): двухстрочный док-фикс битых ссылок (`19ad0848`) оставлен в этой ветке отдельным коммитом `docs(reports)`. Обоснование: ссылки на `todo/TASK-build-doc-checks-ci.todo.md` битые с мержа PR #510 (коммит `56555b44` — перенос файла задачи без обновления ссылок в отчётах ревью), падение воспроизведено на чистом HEAD; без фикса невозможен зелёный `make check` — обязательный барьер перед PR. Это завершение упущенной при мерже #510 очистки ссылок, а не новая задача.
- Наблюдение исполнителя вне скоупа (потенциальный техдолг, задача не заводится по Won't Have): ветка `!$policy->isEnabled()` в `toExecutionRetryPolicy` (не-`null` политика с `maxRetries=0`) не покрыта — тестами задачи не требовалась.

## История изменений (Change History)

| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-24 03:50:21 (1795492221) | Тимлид Алекс (pi) | Создание задачи |
| 2026-11-27 17:10:00 | Тимлид Алекс (pi) | Старт работы: `assignee: Бэкендер Левша (pi)`, `status: in_progress`, ветка `task/runcontext-retry-policy-cases`, публикация ветки токеном бота |
| 2026-11-27 17:10:00 | Бэкендер Левша (pi) | Реализация Must-1/Must-2: кейсы `runFacilitatorFinalizeMapsEnabledRetryPolicyToExecutionRetryPolicy` (полный VO, 8 полей) и `runParticipantMapsEnabledRetryPolicyToExecutionRetryPolicy` (ненулевая политика 3/250/8000/2.5); +118 строк в `RunDynamicLoopAgentServiceTest.php`; phpunit полный — 1782/5372 OK, psalm OK. Should-1: дубликат не заведён — facilitator-путь с ненулевой политикой уже покрыт кейсом `runFacilitatorMapsEnabledRetryPolicyToExecutionRetryPolicy` (2/500/9000/3.0) |
| 2026-11-27 17:10:00 | Бэкендер Левша (pi) | Мутационная проверка (Must-3), методика: временная рабочая копия `git worktree` от HEAD с полной копией `vendor` + `composer dump-autoload` (симлинк `vendor` давал невалидный первый прогон — автозагрузчик грузил классы основного репозитория; пересоздано, путь проверен `ReflectionClass`), контроль `git status` до/после, worktree удалён. Мутация 1: перестановка `initialDelayMs` ↔ `maxDelayMs` в `toExecutionRetryPolicy()` — упали ровно 3 retry-кейса (facilitator + 2 новых). Мутация 2: перестановка `command` ↔ `runnerArgs` в `runViaAgentRunner()` — упали facilitator-кейс и новый finalize-кейс |
| 2026-11-27 17:19:00 | Ревьювер Бэка Пуаро (pi) | Полное ревью: REQUEST CHANGES (CR-1 Must-1 не буквально — 4 поля VO без ассертов; CR-2 Must-3 без записи; CR-3 Should-1 без записи; CR-4 скоуп док-фикса). Отчёт: `docs/agents/reports/code-reviewer-backend/2026-11-27_17-15_runcontext-retry-policy-cases-review.md` |
| 2026-11-27 17:19:00 | Бэкендер Левша (pi) | Доработка CR-1: захват `$writtenPrompts`/`$finalizeContextArgs` через существующие параметры хелперов, ассерты на все 8 полей VO (+17 строк); phpunit модуль 33/220 OK, psalm OK; мутационная самопроверка новых ассертов — перестановки `brainstormSystemPrompt` ↔ `facilitatorAppendPrompt` и `facilitatorFinalizePrompt` ↔ `responseFilesList` роняют тест, откат из бэкапа |
| 2026-11-27 17:19:00 | Тимлид Алекс (pi) | Закрытие CR-2/CR-3: чекбоксы Must/Should/плана отмечены, записи о мутационной проверке и Should-1 внесены; CR-4 — решение зафиксировано в разделе «Комментарии» |
| 2026-11-27 17:35:47 (1795775747) | Тимлид Алекс (pi) | Верификационное ревью Пуаро — APPROVE (CR-1…CR-4 устранены); QA-ревью Хауса — APPROVE (3 контрольные мутации воспроизведены); `make check` — зелёный; задача переведена в `done`, перенесена в `todo/done/`, PR #518 |
