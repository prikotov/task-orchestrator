# Ревью ветки task/runcontext-retry-policy-cases: симметричные retry-policy кейсы RunDynamicLoopAgentServiceTest

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-27
**Объект:** ветка `task/runcontext-retry-policy-cases` (HEAD `19ad0848`) + незакоммиченные правки рабочего дерева: `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php` (+118 строк); коммиты `a95145be` (старт задачи), `19ad0848` (док-фикс ссылок после мержа PR #510)
**Задача:** [todo/done/TASK-techdebt-runcontext-retry-policy-cases.todo.md](../../../../todo/done/TASK-techdebt-runcontext-retry-policy-cases.todo.md)
**Тип прохода:** полный RV (функциональное ревью: соответствие постановке, границы, конвенции, статическая оценка тестов + точечные запуски проверок)
**Вердикт:** 🔴 REQUEST CHANGES — 1 блокирующее замечание (CR-1), 3 важных (CR-2…CR-4), наблюдений без замечаний — 2. → Замечания устранены; верификационный проход 2026-11-27 17:20 — 🟢 APPROVE (раздел 7).

---

## 1. Состав изменений (фактический diff)

| Носитель | Файл | Содержание |
| :--- | :--- | :--- |
| Рабочее дерево (не закоммичено) | `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php` | +118 строк: кейс `runParticipantMapsEnabledRetryPolicyToExecutionRetryPolicy` (строки 346–392), кейс `runFacilitatorFinalizeMapsEnabledRetryPolicyToExecutionRetryPolicy` (строки 493–561), дополнение docblock класса (строки 38–39) |
| Коммит `a95145be` | `todo/TASK-techdebt-runcontext-retry-policy-cases.todo.md` | старт задачи: `assignee`, `branch`, `status: in_progress` — корректно |
| Коммит `19ad0848` | `docs/agents/reports/code-reviewer-backend/2026-11-25_13-54-build-doc-checks-ci-review.md`, `docs/agents/reports/code-reviewer-backend/2026-11-26_18-43_pr-510-marker-scope-review.md` | по 1 строке: ссылка `todo/TASK-build-doc-checks-ci.todo.md` → `todo/done/TASK-build-doc-checks-ci.todo.md` |

**Границы задачи соблюдены:** `git diff main --name-only -- src/ apps/ config/` — пусто; продакшн-код не тронут (`Won't Have` выполнен). Правки теста — только добавление кейсов и docblock; существующие кейсы и стабы не рефакторились (`Won't Have` выполнен). Новых задач не заведено (`Won't Have` выполнен).

⚠️ **Существенное состояние:** реализация Must-1/Must-2 существует **только в рабочем дереве, коммита с тестами нет**. Ревью проведено по фактическому diff рабочего дерева; одобрение коммита невозможно, пока коммита не существует (см. CR-2).

## 2. Трассировка требований постановки

| Требование | Статус | Обоснование |
| :--- | :--- | :--- |
| Must-1: finalize-кейс, полный `FacilitatorFinalizeRunContextVo`, 1:1 маппинг **всех** полей | 🔴 частично | Кейс добавлен (строка 493), VO заполнен всеми 8 полями, но 4 поля из 8 заполнены и **не проверены** — см. CR-1 |
| Must-2: participant-кейс с ненулевой `retryPolicy`, политика доезжает без перепутывания полей | ✅ | Кейс добавлен (строка 346): `assertSame($retryPolicy, …getRetryPolicy())` — тот же экземпляр в `DynamicLoopRunRequestVo`; все 4 поля `ExecutionRetryPolicyVo` проверены попарно различными значениями (3 / 250 / 8000 / 2.5), `isEnabled()` проверен. Перестановка любого поля в `toExecutionRetryPolicy()` статически гарантированно роняет ассерты |
| Must-3: мутационная проверка выполнена и зафиксирована | 🟡 не подтверждено | В Change History задачи записей нет, чекбоксы Must не отмечены — см. CR-2 |
| Must-4: `vendor/bin/phpunit` + `vendor/bin/psalm` зелёные | 🟡 частично подтверждено ревьювером | Psalm (полный) — `No errors found!`; целевой файл — 8 тестов / 157 ассертов, зелёный; модуль DynamicLoop — 33 теста / 212 ассертов, зелёный. Полный phpunit и `make check` — обязанность исполнителя перед PR, свидетельства отсутствуют |
| Should-1: дубликат facilitator-кейса не заводить, явно указать в отчёте | 🟡 частично | Дубликат **не заведён** ✅: существующий `runFacilitatorMapsEnabledRetryPolicyToExecutionRetryPolicy` (строка 175, был в базе до ветки) уже использует ненулевую политику (2 / 500 / 9000 / 3.0). Явная запись в задаче отсутствует — см. CR-3 |
| Could-1: третий facilitator-кейс | ✅ не требуется | Should-1 показал покрытость — третий кейс не нужен, верно не добавлен |
| Won't: правки `src/`, рефакторинг существующих кейсов/стабов, новые задачи | ✅ | Diff подтверждает: только добавления в тестовый файл |

Симметрия трёх методов интерфейса на уровне файла достигнута: `runFacilitator` (tail + retry + error), `runParticipant` (tail + retry + promptFile), `runFacilitatorFinalize` (tail + retry). База 6 тестов / 128 ассертов → 8 / 157 (+2 / +29) — соответствует заявленному объёму.

## 3. Замечания (Change Requests)

### 🔴 CR-1 (блокирующее). Must-1 не выполнен буквально: в finalize-кейсе 4 поля «полного VO» заполнены, но их маппинг не проверен

**Файл:** `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php`, кейс `runFacilitatorFinalizeMapsEnabledRetryPolicyToExecutionRetryPolicy` (строки 493–561).

Постановка Must-1: «Кейс finalize: **полный** `FacilitatorFinalizeRunContextVo` → `runFacilitatorFinalize`; проверен 1:1 маппинг **всех** полей (по образцу существующих кейсов файла)». SMART-цель усиливает: «каждый проверяет пофайловый 1:1 маппинг».

Фактически в кейсе VO заполняется всеми полями (строки 515–524), но не проверяются:

| Поле VO | Значение в кейсе | Куда уходит по коду сервиса | Ассерта нет |
| :--- | :--- | :--- | :--- |
| `brainstormSystemPrompt` | `'finalize-full-brainstorm-system-prompt'` | `writePromptFile(..., '_1_system.md')` | `$writtenPrompts` не захвачен — `createSessionWriter()` вызван без аргумента (строка 499) |
| `facilitatorAppendPrompt` | `'finalize-full-append-prompt'` | `writePromptFile(..., '_2_append.md')` | то же |
| `facilitatorFinalizePrompt` | `'finalize-full-finalize-prompt'` | `buildFinalizeContext()` первым аргументом | `$finalizeContextArgs` не захвачен — `createFormatter()` без него (строки 501–504) |
| `responseFilesList` | `'finalize-full-response-files'` | `buildFinalizeContext()` третьим аргументом | то же |

Комментарий на строке 527 «Полный VO: политика и workingDir доезжают…» и комментарий на строке 536 «Маппинг… поле в поле» заявляют больше, чем тест реально проверяет — это ложное покрытие в миниатюре: значения полей выглядят как данные кейса, но перестановка `brainstormSystemPrompt` ↔ `facilitatorAppendPrompt` или `facilitatorFinalizePrompt` ↔ `responseFilesList` **в этом кейсе** не ловится. Существующий tail-кейс (`runFacilitatorFinalizeMapsVoTailToFormatterContextAndRunRequest`, строка 375) эти поля проверяет, но с другим VO (`workingDir: null`, без retryPolicy) — критерий «полный VO → все поля в одном кейсе» не закрыт ни одним кейсом.

Смягчающее обстоятельство: защитная сетка **файла** перечисленные перестановки ловит (tail-кейсом), поэтому риск регрессии невелик. Но отклонение от Must-критерия не оформлено ни техдолгом, ни осознанным отступлением с утверждением тимлида ([правила отклонений](../../../guide/review-functional.md)) — по правилам функционального ревью это отклонение, блокирующее.

**Запрос на изменение (вариант А, предпочтительный, ~6 строк):** в кейсе строки 493–561 задействовать уже существующие параметры хелперов:
1. строка 499: `$this->createSessionWriter($writtenPrompts)` (объявить `$writtenPrompts = []` в arrange);
2. строки 501–504: добавить `finalizeContextArgs: $finalizeContextArgs` в `createFormatter(...)`;
3. после строки 534 добавить ассерты: содержимое `$writtenPrompts[0]['content'] === 'finalize-full-brainstorm-system-prompt'`, `$writtenPrompts[1]['content'] === 'finalize-full-append-prompt'`, суффиксы `_1_system.md` / `_2_append.md`, и `assertSame(['finalize-full-finalize-prompt', self::TOPIC, 'finalize-full-response-files'], $finalizeContextArgs)` — по образцу tail-кейса (строки 459–476).

**Вариант Б:** оформить осознанное отступление записью в файле задачи с причиной («поля промптов покрыты tail-кейсом, дублирование ассертов избыточно») и утверждением тимлида; комментарий «Полный VO … поле в поле» (строки 527, 536) привести в соответствие с фактически проверяемым. Без А или Б замечание остаётся блокирующим.

### 🟡 CR-2 (важное). Must-3 не зафиксирован; реализация не закоммичена; задача не актуализирована

**Файл:** `todo/TASK-techdebt-runcontext-retry-policy-cases.todo.md` (Change History — единственная запись о создании; чекбоксы разделов 3–5 не отмечены; `status: in_progress`).

1. Мутационная проверка (Must-3, критерий DoD «Мутационная проверка выполнена и зафиксирована в Change History») свидетельства не имеет. Статически эффективность кейсов правдоподобна (все 4 значения политики попарно различны в каждом кейсе; шаг/раунд 4/2 и 2/1 асимметричны; command отличен от соседних кейсов), но это не заменяет требуемую процедуру: мутация → падение → откат → запись.
2. Тестовые правки (+118 строк) не закоммичены — коммитовать должен исполнитель, Conventional Commits, тип `test` (например `test(dynamic-loop): symmetric retry-policy mapping cases for participant/finalize`).
3. Перед PR: полный `vendor/bin/phpunit`, `vendor/bin/psalm`, `make check`; результаты — в Change History; затем перевод в `done` per регламенту.

**Запрос:** выполнить мутационную проверку по Must-3 (достаточно по одной смещённой паре на кейс: participant — `initialDelayMs`↔`maxDelayMs`, finalize — `maxRetries`↔`initialDelayMs`, плюс пара промптов после CR-1), зафиксировать в Change History дату/поле/результат/откат (`git status` чистый после отката), закоммитить тест, актуализировать чекбоксы.

### 🟡 CR-3 (важное). Should-1: явная запись о непокрытости/покрытости facilitator-кейса отсутствует

**Файл:** `todo/TASK-techdebt-runcontext-retry-policy-cases.todo.md`, разделы «Комментарии» / Change History.

Дубликат не заведён — верно. Но Should-1 требует «в отчёте задачи указать это явно». Факт для записи: существующий `runFacilitatorMapsEnabledRetryPolicyToExecutionRetryPolicy` (тестовый файл, строка 175) использует ненулевую политику `maxRetries: 2, initialDelayMs: 500, maxDelayMs: 9000, multiplier: 3.0` и проверяет все 4 поля маппинга — facilitator-путь покрыт, третий кейс (Could-1) не требуется.

**Запрос:** добавить эту запись в Comments или Change History задачи и отметить Should-1 выполненным.

### 🟡 CR-4 (важное). Коммит `19ad0848`: содержание корректно, но чужой скоуп в task-ветке не оформлен

**Файлы:** `docs/agents/reports/code-reviewer-backend/2026-11-25_13-54-build-doc-checks-ci-review.md:6`, `docs/agents/reports/code-reviewer-backend/2026-11-26_18-43_pr-510-marker-scope-review.md:6`.

Проверка содержания — ✅:
- обе ссылки `../../../../todo/TASK-build-doc-checks-ci.todo.md` → `../../../../todo/done/TASK-build-doc-checks-ci.todo.md`; относительный путь из `docs/agents/reports/code-reviewer-backend/` разрешается корректно (4 уровня вверх = корень);
- цель `todo/done/TASK-build-doc-checks-ci.todo.md` существует;
- `make md-links` — 541 файл, все внутренние ссылки валидны; иных markdown-ссылок на старый путь в репозитории не осталось (прочие упоминания — исторический текст в backtick, не ссылки, править не нужно);
- формат коммита `docs(reports): …` соответствует Conventional Commits.

Замечание — по границам: док-фикс последствий мержа PR #510 логически не относится к задаче retry-policy-кейсов; правило «Один PR содержит одну логически завершённую задачу» (AGENTS.md) формально нарушено. Правка тривиальна (2 строки) и не влияет на код, поэтому не блокирую, но требуется оформление.

**Запрос:** либо получить явное согласие тимлида/владельца на включение док-фикса в этот PR и зафиксировать его записью в Comments задачи (со ссылкой на коммит `19ad0848` и причину — битые ссылки после мержа PR #510), либо вынести коммит в отдельный docs-PR. Молча оставлять смешанный скоуп нельзя.

## 4. Статическая оценка тестов (конвенции тестирования)

Проверено по [Конвенциям тестирования](../../../conventions/testing/index.md) — нарушений нет:

- **Расположение/уровень:** `tests/Unit/…` зеркалит `src/…`, наследование `PHPUnit\Framework\TestCase`, стабы без внешних сервисов и секретов ✅.
- **AAA:** arrange/act/assert разделены пустыми строками, act — одна строка (`$service->runParticipant(...)`, `$service->runFacilitatorFinalize(...)`) ✅.
- **Именование:** `runParticipantMapsEnabledRetryPolicyToExecutionRetryPolicy`, `runFacilitatorFinalizeMapsEnabledRetryPolicyToExecutionRetryPolicy` — BDD-семантика `{What}{Scenario}{Expected}`, симметричны существующему facilitator-кейсу; атрибут `#[Test]` — по стилю файла, как прямо предписано постановкой («стиль кейсов — по соседним тестам файла») ✅.
- **Ассерты:** `self::assertSame`/`assertInstanceOf`/`assertCount`/`assertTrue`, статические вызовы ✅; значения политик попарно различны внутри кейса и различны между кейсами (2/500/9000/3.0 vs 3/250/8000/2.5 vs 4/100/5000/1.5) — перестановки ловятся, трассируемость кейса по значениям обеспечена ✅.
- **Переиспользование:** новые кейсы используют существующие фабрики стабов без изменений сигнатур; новых абстракций нет ✅ (требование «без новых абстракций и без рефакторинга теста»).
- **Корректность против кода:** сверено с `src/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentService.php` — `toExecutionRetryPolicy()` (строки 289–301) маппит 4 поля 1:1, `null`/disabled → `null`; ассерты `isEnabled() === true` согласованы с `DynamicLoopRetryPolicyVo::isEnabled()` (`maxRetries > 0`) ✅. Проверка `assertSame($retryPolicy, $runRequest->getRetryPolicy())` корректно закрепляет передачу того же экземпляра в `DynamicLoopRunRequestVo` ✅.
- **Docblock класса** дополнен симметричным описанием трёх путей — документация теста актуальна ✅.
- Подавлений анализаторов, магических чисел без пояснения, мёртвого кода в новых кейсах нет ✅.

Косметическое наблюдение (без замечания): в participant-кейсе `workingDir: null`, `responseFilesList: ''` — минимальный VO; это допустимо, фокус кейса — retryPolicy, а маппинг остальных полей participant-пути закреплён tail-кейсом (строка 249). В отличие от finalize (CR-1), Must-2 полноты VO не требует — критерий выполнен.

## 5. Выполненные проверки (сводка)

| Проверка | Результат |
| :--- | :--- |
| `vendor/bin/phpunit tests/Unit/.../RunDynamicLoopAgentServiceTest.php` | ✅ OK, 8 тестов / 157 ассертов |
| `vendor/bin/phpunit tests/Unit/Common/Module/DynamicLoop/` | ✅ OK, 33 теста / 212 ассертов |
| `vendor/bin/psalm --no-cache` (полный) | ✅ No errors found (163 info — вне скоупа, базовые) |
| `vendor/bin/phpcs <тестовый файл>` | ✅ чисто |
| `make md-links` | ✅ 541 файл, все ссылки валидны |
| Границы: `git diff main --name-only -- src/ apps/ config/` | ✅ пусто |
| Полный `vendor/bin/phpunit`, `make check` | ⚠️ не запускались ревьювером (роль допускает точечные запуски); обязательны для исполнителя перед PR (CR-2) |

Наблюдение о среде: тесты исполнены на PHP 8.5.12 (проектный таргет — PHP 8.4); на результат ревью не влияет, но исполнителю стоит убедиться, что `make check` гоняется в проектном окружении.

## 6. Вердикт

🔴 **REQUEST CHANGES.**

- **CR-1 (блокирующее):** finalize-кейс не проверяет 4 из 8 полей «полного VO» — Must-1 не выполнен буквально и отклонение не оформлено. Доработать (~6 ассертов через существующие хелперы) либо оформить осознанное отступление с утверждением тимлида.
- **CR-2:** мутационная проверка Must-3 не зафиксирована; тестовые правки не закоммичены; чекбоксы/Change History задачи не актуализированы.
- **CR-3:** Should-1 — явная запись о покрытости facilitator-пути существующим кейсом отсутствует в задаче.
- **CR-4:** док-фикс `19ad0848` корректен по содержанию, но смешанный скоуп требует явного согласования тимлида (запись в задаче) либо выноса в отдельный PR.

Качество самих тестов высокое: симметрия, различность значений, переиспользование стабов, корректность против контракта сервиса — претензий нет. После устранения CR-1…CR-4 ожидаю верификационный проход и апрув.

---

## 7. Верификационное ревью

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-27 17:20
**Тип прохода:** верификационный — проверка только устранения замечаний CR-1…CR-4 в объёме, заданном тимлидом: CR-1 — ассерты всех 8 полей `FacilitatorFinalizeRunContextVo` в finalize-кейсе; CR-2 — запись о мутационной проверке в Change History задачи; CR-3 — явная запись Should-1; CR-4 — решение тимлида по скоупу док-фикса `19ad0848` в разделе «Комментарии» задачи.
**Объект:** ветка `task/runcontext-retry-policy-cases` (HEAD `19ad0848`) + рабочее дерево: `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php` (+135 строк суммарно, из них +17 — доработка CR-1), `todo/TASK-techdebt-runcontext-retry-policy-cases.todo.md`.

### 7.1. Проверка замечаний

| Замечание | Результат | Свидетельство |
| :--- | :--- | :--- |
| 🔴 CR-1: 4 из 8 полей полного VO без ассертов в finalize-кейсе | ✅ устранено (вариант А) | Кейс `runFacilitatorFinalizeMapsEnabledRetryPolicyToExecutionRetryPolicy`: `createSessionWriter($writtenPrompts)` и `createFormatter(finalizeContextArgs: $finalizeContextArgs, …)` задействуют **существующие** by-reference параметры хелперов (сигнатуры не менялись). Добавлены ассерты: `finalizeContextArgs === ['finalize-full-finalize-prompt', TOPIC, 'finalize-full-response-files']`; `writtenPrompts[0]['content'] === 'finalize-full-brainstorm-system-prompt'` + суффикс `_1_system.md` + step/round 4/2; `writtenPrompts[1]['content'] === 'finalize-full-append-prompt'` + суффикс `_2_append.md`. Сверено с VO (`src/Module/DynamicLoop/Domain/ValueObject/FacilitatorFinalizeRunContextVo.php`, ровно 8 полей): `brainstormSystemPrompt` ✅, `facilitatorAppendPrompt` ✅, `facilitatorFinalizePrompt` ✅, `workingDir` ✅ (`runRequest` + `chainRequest`), `responseFilesList` ✅, `timeout` ✅ (90), `command` ✅ (`['pi','--model','finalize-full']`), `retryPolicy` ✅ (`assertSame` экземпляра + 4 поля `ExecutionRetryPolicyVo` 4/100/5000/1.5 + `isEnabled`). Все 8 полей проверены в одном кейсе — Must-1 выполнен буквально. Комментарии «Полный VO…» теперь соответствуют фактически проверяемому |
| 🟡 CR-2: мутационная проверка Must-3 не зафиксирована (объём верификации — запись в Change History) | ✅ устранено | Запись Change History от 2026-11-27 (Бэкендер Левша): методика (`git worktree` от HEAD, полная копия `vendor` + `composer dump-autoload`, контроль пути через `ReflectionClass` — первый прогон с симлинком честно признан невалидным и пересоздан), мутация 1 `initialDelayMs`↔`maxDelayMs` в `toExecutionRetryPolicy()` → упали ровно 3 retry-кейса (покрывает политику participant и finalize), мутация 2 `command`↔`runnerArgs` в `runViaAgentRunner()` → упали facilitator + finalize; контроль `git status` до/после, worktree удалён. Отдельная запись доработки CR-1 фиксирует мутационную самопроверку новых ассертов (обе пары промптов) с откатом. Чекбоксы Must-1…Must-4, Should-1 и пунктов плана 1–5 отмечены с отсылками к свидетельствам |
| 🟡 CR-3: явная запись Should-1 отсутствует | ✅ устранено | Запись в разделе Should Have (отмечен `[x]`): существующий кейс `runFacilitatorMapsEnabledRetryPolicyToExecutionRetryPolicy` (строка ~175) уже покрывает ненулевую политику 2/500/9000/3.0, третий кейс не заведён; дублирующая запись в Change History от 2026-11-27 17:10. Факт соответствует коду теста (проверено в полном ревью, раздел 2) |
| 🟡 CR-4: скоуп док-фикса `19ad0848` не оформлен | ✅ устранено (вариант согласования) | Раздел «Комментарии» задачи: решение тимлида 2026-11-27 со ссылкой на CR-4 данного отчёта, коммит `19ad0848` оставлен отдельным коммитом `docs(reports)`; причина — битые ссылки после мержа PR #510 (коммит `56555b44`), падение воспроизведено на чистом HEAD, без фикса недостижим зелёный `make check` — обязательный барьер перед PR. Обоснование связное: завершение упущенной при мерже очистки, а не новая задача. Смешанный скоуп оформлен явно, как и требовал CR-4 |

### 7.2. Независимые проверки ревьювера

| Проверка | Результат |
| :--- | :--- |
| `vendor/bin/phpunit tests/Unit/.../RunDynamicLoopAgentServiceTest.php` | ✅ OK, 8 тестов / 165 ассертов (было 157 до доработки CR-1: +8 ассертов) |
| `vendor/bin/phpunit tests/Unit/Common/Module/DynamicLoop/` | ✅ OK, 33 теста / 220 ассертов — совпадает с записью исполнителя в Change History |
| `vendor/bin/phpcs <тестовый файл>` | ✅ чисто |
| Контрольная мутация 1 (независимая): `getBrainstormSystemPrompt`↔`getFacilitatorAppendPrompt` в finalize-пути сервиса (строки 193/200) | ✅ finalize-кейс **упал** (Failures: 1) — новые ассерты промптов ловят перестановку |
| Контрольная мутация 2 (независимая): `getFacilitatorFinalizePrompt`↔`getResponseFilesList` в `buildFinalizeContext` (строки 184/186) | ✅ finalize-кейс **упал** (Failures: 1) |
| Откат мутаций | ✅ сервис восстановлен из бэкапа, `git status -- src/` чистый, повторный прогон кейса — OK (28 ассертов) |
| Границы: `git diff main --name-only -- src/ apps/ config/` | ✅ пусто — продакшн-код не тронут, доработка осталась в границах задачи |

Мутации выполнялись ревьювером только как временная контрольная проверка с гарантированным откатом (бэкап + trap); постоянных правок кода ревьювер не вносил.

### 7.3. Вердикт верификационного прохода

🟢 **APPROVE.** Все четыре замечания (CR-1 — блокирующее, CR-2…CR-4 — важные) в заданном объёме верификации устранены и подтверждены свидетельствами: код — независимыми контрольными мутациями, записи задачи — сверкой с фактическим diff и прогонами. Остатка для доработки по CR-1…CR-4 нет.

**Напоминание вне объёма верификации (не новое замечание):** организационные шаги перед PR из исходного CR-2 остаются за исполнителем и на момент прохода не выполнены — тестовые правки и правки задачи не закоммичены (существуют только в рабочем дереве), `status: in_progress`, чекбоксы DoD (раздел 5) и пункт плана 6 не отмечены, поле `pr` пустое. Перед PR обязательны: коммит тестов (`test(dynamic-loop): …`, Conventional Commits), полный `vendor/bin/phpunit` + `vendor/bin/psalm` + `make check` в проектном окружении (PHP 8.4), перевод задачи в `done` с переносом в `todo/done/` и заполнением `pr` — по регламенту AGENTS.md и todo/AGENTS.md. Апрув верификационного прохода не отменяет эти барьеры и не является подтверждением merge.
