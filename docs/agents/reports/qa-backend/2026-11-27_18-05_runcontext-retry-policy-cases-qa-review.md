# QA-ревью тестового покрытия ветки task/runcontext-retry-policy-cases: симметричные retry-policy кейсы RunDynamicLoopAgentServiceTest

**Роль:** Тестировщик Бэка Хаус (qa_backend_house)
**Дата:** 2026-11-27
**Объект:** ветка `task/runcontext-retry-policy-cases` (HEAD `19ad0848`) + незакоммиченные правки рабочего дерева: `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php` (+135 строк), `todo/TASK-techdebt-runcontext-retry-policy-cases.todo.md`
**Задача:** [todo/TASK-techdebt-runcontext-retry-policy-cases.todo.md](../../../../todo/TASK-techdebt-runcontext-retry-policy-cases.todo.md)
**Тип прохода:** полное ревью тестового покрытия (полнота/чувствительность кейсов, симметрия трёх методов, независимое воспроизведение мутаций Must-3, конвенции тестирования, edge cases в скоупе)
**Вердикт:** 🟢 APPROVE — замечаний к тестовому коду нет; запросов на изменения не требуется. Организационные шаги перед PR (коммит, полный барьер, перевод задачи в `done`) остаются за исполнителем — см. раздел 6.

---

## 1. Полнота и чувствительность новых кейсов

### 1.1. Participant-кейс с ненулевой retryPolicy — `runParticipantMapsEnabledRetryPolicyToExecutionRetryPolicy` (строка 347)

| Аспект | Оценка | Факты |
| :--- | :--- | :--- |
| Постановка Must-2 | ✅ выполнен | Ненулевая политика `3/250/8000/2.5` доезжает до `runParticipant` и до раннера |
| Чувствительность к перестановкам | ✅ | 4 значения политики попарно различны внутри кейса и различны между кейсами (2/500/9000/3.0 vs 3/250/8000/2.5 vs 4/100/5000/1.5); подтверждено мутацией A (раздел 3) |
| Проброс экземпляра VO | ✅ | `assertSame($retryPolicy, $invocationArgs['request']->getRetryPolicy())` — тот же экземпляр в `DynamicLoopRunRequestVo` |
| Маппинг в foreign VO | ✅ | Все 4 поля `ExecutionRetryPolicyVo` + `isEnabled()` проверены |
| Минимальный VO (`workingDir: null`, `responseFilesList: ''`) | ✅ допустимо | Must-2 полноты VO не требует; маппинг остальных полей participant-пути закреплён tail-кейсом (`runParticipantMapsVoTailToFormatterPromptAndRunRequest`) |
| Доступ к захвату стаба | ✅ | `assertNotNull($invocationArgs)` до обращения к массиву — хрупкого доступа нет |

### 1.2. Finalize-кейс с полным VO — `runFacilitatorFinalizeMapsEnabledRetryPolicyToExecutionRetryPolicy` (строка 494)

`FacilitatorFinalizeRunContextVo` имеет ровно 8 полей (`src/Module/DynamicLoop/Domain/ValueObject/FacilitatorFinalizeRunContextVo.php`); сверка «поле кейса → ассерт в том же кейсе»:

| Поле VO | Значение | Ассерт в кейсе | Статус |
| :--- | :--- | :--- | :--- |
| `brainstormSystemPrompt` | `'finalize-full-brainstorm-system-prompt'` | `$writtenPrompts[0]['content']` + суффикс `_1_system.md` + step/round 4/2 | ✅ |
| `facilitatorAppendPrompt` | `'finalize-full-append-prompt'` | `$writtenPrompts[1]['content']` + суффикс `_2_append.md` | ✅ |
| `facilitatorFinalizePrompt` | `'finalize-full-finalize-prompt'` | `$finalizeContextArgs[0]` (тройной `assertSame` массива) | ✅ |
| `workingDir` | `'/tmp/finalize-workdir'` | `runRequest->getWorkingDir()` + `chainRequest->getWorkingDir()` | ✅ |
| `responseFilesList` | `'finalize-full-response-files'` | `$finalizeContextArgs[2]` | ✅ |
| `timeout` | `90` | `chainRequest->getTimeout()` | ✅ |
| `command` | `['pi', '--model', 'finalize-full']` | `chainRequest->getCommand()` | ✅ |
| `retryPolicy` | `4/100/5000/1.5` | `assertSame` экземпляра в `DynamicLoopRunRequestVo` + 4 поля `ExecutionRetryPolicyVo` + `isEnabled()` | ✅ |

Все 8 полей проверены в одном кейсе — Must-1 выполнен буквально (доработка CR-1 Пуаро подтверждена). Замыкание цепочки «записанный prompt-файл → путь в `ChainRunRequestVo`» (`/session/4_2_facilitator_1_system.md`, `/session/4_2_facilitator_2_append.md`) проверено. Шаг/раунд 4/2 и `command` отличны от соседних кейсов — трассируемость обеспечена. Независимая убиваемость новых ассертов промптов/контекста подтверждена мутациями B и C (раздел 3): finalize-кейс падает сам, без опоры на tail-кейс.

## 2. Симметричность покрытия трёх методов интерфейса

`RunDynamicLoopAgentServiceInterface`: `runFacilitator` / `runParticipant` / `runFacilitatorFinalize` — итоговая сетка файла (8 тестов):

| Метод | Tail-маппинг VO | Retry-policy (ненулевая) | Дополнительные кейсы |
| :--- | :--- | :--- | :--- |
| `runFacilitator` | ✅ `…MapsVoTailToFormatterContextAndRunRequest` | ✅ `…MapsEnabledRetryPolicyToExecutionRetryPolicy` (строка 177, до ветки) | error-маппинг |
| `runParticipant` | ✅ | ✅ **новый** (строка 347) | promptFile-приоритет |
| `runFacilitatorFinalize` | ✅ | ✅ **новый** (строка 494) | — |

Контракт всех трёх методов покрыт retry-маппинг-тестами симметрично; docblock класса дополнен соответствующей строкой — документация теста актуальна. Требование «симметричные кейсы по образцу существующих, без новых абстракций» выполнено: оба кейса используют существующие фабрики стабов (`createService`/`createAgentRunner`/`createSessionWriter`/`createResponseParser`/`createFormatter`/`createQueryBus`) с уже имевшимися by-reference параметрами захвата, сигнатуры хелперов не менялись.

**Наблюдения (не замечания, в код не просятся):**

1. Существующий facilitator-retry-кейс (строка 177) не имеет ассерта проброса того же экземпляра в `DynamicLoopRunRequestVo` (проверяет только смаппленный результат), тогда как оба новых кейса имеют. Правка коснулась бы существующего кейса — запрещена Won't Have задачи («рефакторинг существующих кейсов»). Риск минимален: проброс во всех трёх методах — структурно идентичный код `retryPolicy: $runContext->getRetryPolicy()`, а смаппленный результат facilitator-кейс проверяет полностью.
2. Ветка `!$policy->isEnabled()` в `toExecutionRetryPolicy()` (не-null политика с `maxRetries=0`) не покрыта — зафиксировано в задаче как вне скоупа (Won't Have: новые задачи не заводятся). Null-ветка покрыта `assertNull(...retryPolicy)` в существующих tail-кейсах.

## 3. Мутационная проверка Must-3 — независимое воспроизведение

Методика: мутация в рабочем дереве через точечную замену → прогон целевого файла → гарантированный откат `git restore` (файл сервиса чист относительно HEAD) → контроль `git status --porcelain` (md5 до/после каждой мутации) → финальная сверка `git diff --stat -- src/` (пусто). Прогоны — на PHP 8.5.12 (окружение станции; см. примечание в разделе 5).

| # | Мутация | Место | Результат | Откат |
| :--- | :--- | :--- | :--- | :--- |
| A | `initialDelayMs` ↔ `maxDelayMs` | `toExecutionRetryPolicy()` | ✅ убита: `Tests: 8, Failures: 3` — упали ровно 3 retry-кейса (facilitator + participant-новый + finalize-новый); tail-кейсы с `assertNull` не отреагировали — селективность точная | ✅ git status идентичен |
| B | `getBrainstormSystemPrompt` ↔ `getFacilitatorAppendPrompt` (обе `writePromptFile`) | `runFacilitatorFinalize()` | ✅ убита: `Failures: 2` — tail-finalize + **новый finalize-кейс падает самостоятельно** (ассерты `$writtenPrompts`) | ✅ git status идентичен |
| C | `getFacilitatorFinalizePrompt` ↔ `getResponseFilesList` | `buildFinalizeContext()` в `runFacilitatorFinalize()` | ✅ убита: `Failures: 2` — tail-finalize + **новый finalize-кейс падает самостоятельно** (ассерт `$finalizeContextArgs`) | ✅ git status идентичен |

Результаты полностью воспроизводят записи Change History задачи (мутации 1–2 Левши и самопроверка доработки CR-1) и независимые контрольные мутации Пуаро. После откатов: `git diff --stat -- src/` пуст, повторный прогон целевого файла — OK (8 тестов / 165 ассертов). Мутационная проверка Must-3 **подтверждена**: каждый из двух новых кейсов роняет свою перестановку, селективность по путям точная.

## 4. Конвенции тестирования и edge cases в скоупе

По [Конвенциям тестирования](../../../conventions/testing/index.md) — нарушений нет:

- **Расположение/уровень:** `tests/Unit/…` зеркалит `src/…`; `TestCase`, стабы, без внешних сервисов и секретов ✅
- **AAA:** секции разделены пустыми строками, act — одна инструкция ✅
- **BDD-именование:** `run{Method}MapsEnabledRetryPolicyToExecutionRetryPolicy` — `{What}{Scenario}{Expected}`, симметрично соседним ✅; атрибут `#[Test]` по стилю файла ✅
- **Ассерты:** статические `self::assertSame`/`assertInstanceOf`/`assertCount`/`assertTrue`; полей-дубликатов без различающих значений нет ✅
- **phpcs** по целевому файлу — чисто; **psalm** (полный) — No errors found ✅
- **Edge cases:** различающие значения политик между кейсами (нет маскировки между кейсами); `assertNotNull` перед обращением к захвату; различные step/round в finalize (4/2 vs 1/3) ✅. Participant-retry использует step/round 2/1 — совпадает с tail-кейсом, но кейсы независимы и захватывают собственные стабы: маскировки и ложных срабатываний нет (микро-наблюдение, в правку не просятся — различающие значения policy-полей достаточны)

## 5. Прогоны (сводка)

| Проверка | Результат |
| :--- | :--- |
| `vendor/bin/phpunit tests/.../RunDynamicLoopAgentServiceTest.php` | ✅ 8 тестов / 165 ассертов |
| `vendor/bin/phpunit tests/Unit/Common/Module/DynamicLoop/` | ✅ 33 теста / 220 ассертов |
| `vendor/bin/phpunit` (полный) | ✅ 1782 теста / 5380 ассертов / 2 skipped (сторонние) — 5380 = 5372 (запись Левши до CR-1) + 8 новых ассертов, сходится |
| `vendor/bin/psalm --no-cache` | ✅ No errors found |
| `vendor/bin/phpcs <тестовый файл>` | ✅ чисто |
| Мутации A/B/C + откат | ✅ все убиты, `git status` до/после идентичен, `git diff --stat -- src/` пуст |
| Границы: `git diff main --name-only -- src/ apps/ config/` | ✅ пусто — прод-код не тронут |

Примечание о среде: прогоны на PHP 8.5.12 (проектный таргет — PHP 8.4), как и у предыдущих проходов; на вердикт не влияет, но `make check` перед PR исполняться в проектном окружении.

## 6. Вердикт

🟢 **APPROVE.** Тестовое покрытие изменений полное, чувствительное и симметричное:

- Must-1 (finalize, полный VO, все 8 полей в одном кейсе) — выполнен буквально, подтверждён сверкой поле-за-полем;
- Must-2 (participant с ненулевой retryPolicy) — выполнен, проброс экземпляра + полный маппинг foreign VO;
- Must-3 (мутационная проверка) — независимо воспроизведён тремя мутациями с контролируемым откатом; записи в Change History соответствуют фактам;
- конвенции тестирования соблюдены, границы задачи (только тестовый файл в части кода) не нарушены.

**Запросов на изменения нет.**

**Напоминание вне объёма QA-ревью (не новое замечание, дублирует хвост CR-2 Пуаро):** тестовые правки и актуализация задачи существуют только в рабочем дереве (не закоммичены), отчёт ревью Пуаро не добавлен в индекс (`?? docs/agents/reports/code-reviewer-backend/2026-11-27_17-15_runcontext-retry-policy-cases-review.md`), `status: in_progress`, чекбоксы DoD и пункт плана 6 не отмечены, поле `pr` пустое. Перед PR по регламенту: коммит тестов + отчётов + задачи (Conventional Commits), полный `make check` в проектном окружении (PHP 8.4), перевод задачи в `done` с переносом в `todo/done/` и заполнением `pr`. Проверки полного барьера (`phpunit`/`psalm`) данным QA-проходом выполнены и зелёные, но коммит и push — только по явному запросу пользователя.
