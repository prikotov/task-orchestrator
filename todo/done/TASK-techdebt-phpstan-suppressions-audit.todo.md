---
type: chore
created: 2026-11-27 15:12:56 (1795792376)
due: 
started: 2026-11-27 16:07:38 (1795795658)
completed: 2026-11-27 16:49:44 (1795798184)
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Ревьювер Бэка Пуаро (pi)
assignee: Бэкендер Левша (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/phpstan-suppressions-audit
pr: https://github.com/prikotov/task-orchestrator/pull/521
status: done
---

# TASK-techdebt-phpstan-suppressions-audit: Аудит подавлений PHPStan: 4 мёртвых suppress при reportUnmatchedIgnoredErrors: false

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Конфигурация PHPStan (`phpstan.neon.dist:11`) отключает контроль «мёртвых» подавлений: `reportUnmatchedIgnoredErrors: false`. При таком режиме подавление `@phpstan-ignore` может молча устареть (правило перестало срабатывать) и продолжать скрывать будущие ошибки или вводить в заблуждение читающих.
- По результатам ревью ветки `task/deps-update-all` (2026-11-27, Ревьювер Бэка Пуаро) подтверждены 4 «мёртвых» подавления; полный инвентарь `@phpstan-ignore` в `src/` — 8 подавлений.

### Варианты или путь решения (Solution Sketch)
- Временно включить `reportUnmatchedIgnoredErrors: true`, зафиксировать полный список несматченных подавлений, затем вернуть значение (или обоснованно оставить `true`, если анализ остаётся зелёным).
- Снять/актуализировать все «мёртвые» подавления; для каждого живого — перепроверить необходимость, точность идентификатора и оформление по конвенции (причина + техдолг-задача, см. `docs/guide/review-functional.md:25`, `docs/conventions/ops/phpmd-suppressions-guidelines.md`).
- По итогам решить судьбу режима `reportUnmatchedIgnoredErrors`: постоянное `true` (с закрытием всех замечаний) — предпочтительный исход; `false` — только с осознанным отступлением.

### Ожидаемый результат (Expected Result)
- Ни одного «мёртвого» `@phpstan-ignore` в `src/`; каждое живое подавление точно нацелено на существующее срабатывание и оформлено по конвенции.
- `vendor/bin/phpstan analyse` зелёный; полный `make check` зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> Как сопровождающий проекта, я хочу, чтобы каждое подавление PHPStan было живым, точным и оформленным по конвенции, чтобы анализатор не «молчал» о реально существующих проблемах.

### Цель по SMART (Goal)
- Провести аудит всех `@phpstan-ignore` подавлений в `src/` (8 мест на момент создания), устранить 4 подтверждённых «мёртвых» и перепроверить остальные, не позднее ближайшей сессии чистки техдолга.

## 2. Контекст и Границы (Context and Scope)
- **Где делаем:** подавления в `src/Module/**` (адреса в разделе 3), `phpstan.neon.dist` (флаг `reportUnmatchedIgnoredErrors`).
- **Вне границ:** изменение проверяемого кода ради снятия подавлений — только если снятие не меняет поведение (иначе отдельная задача); новые подавления, появившиеся после создания задачи, аудитором фиксируются в этой задаче при выполнении.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Снять подтверждённые «мёртвые» подавления `nullsafe.neverNull` / `return.type` (не сматчены при `reportUnmatchedIgnoredErrors: true`, проверка 2026-11-27):
  - `src/Module/ChainExecution/Application/Mapper/ReportJsonMapper.php:55` — `return.type` (над `json_encode` на :56); ✅ снято 2026-11-27: PHPStan корректно выводит `non-falsy-string` для `json_encode` с `JSON_THROW_ON_ERROR`;
  - `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php:297` и `:298` — `nullsafe.neverNull`; ✅ снято 2026-11-27;
  - `src/Module/DynamicLoop/Domain/Service/Dynamic/FinalizeDynamicLoopService.php:95` — `nullsafe.neverNull`. ✅ снято 2026-11-27.
- [x] Перепроверить живые подавления (необходимость, идентификатор, оформление) — перепроверены 2026-11-27 экспериментальным снятием: все 4 сматчиваются, идентификаторы точны:
  - `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php:41` — `property.unusedType`; ✅ оставлено: сужение PHPDoc до `resource|null` ломает Psalm (`RedundantConditionGivenDocblockType` на guard'ах `is_resource()`) — расхождение анализаторов, добавлена причина и `@techdebt`;
  - `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php:176` — `nullCoalesce.offset`; ✅ оставлено: защитный guard, снятие `??` меняет поведение защитной ветки; добавлена причина и `@techdebt`;
  - `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php:102` — `booleanNot.alwaysFalse` (введено при обновлении PHPStan 2.3.7, см. TASK-chore-deps-update-all); ✅ оставлено, причина уже была; актуализирована ссылка на прецеденты: живые `property.unusedType` и `nullCoalesce.offset` (оба оставлены в этом аудите), устаревшие номера строк убраны — актуальные адреса в §9;
  - `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php:113` — `argument.type`. ✅ оставлено: слабый контракт `DynamicLoopSessionLoggerInterface::getRoundFiles(): array` против `list<array{...}>`; сужение контракта — отдельная задача (изменение Domain+Infrastructure); добавлена причина и `@techdebt`.
- [x] Принять решение по `reportUnmatchedIgnoredErrors` (предпочтительно `true`) и зафиксировать его в задаче. ✅ Решение (2026-11-27): оставлено `true` постоянно — анализ зелёный (0 ошибок, несматченных подавлений нет), контроль «мёртвых» подавлений впредь автоматический.

### ⚫ Won't Have (Не будем делать)
- Массовое переписывание кода под правила анализатора, где это меняет поведение.
- Переход на глобальное ослабление конфигурации (`treatPhpDocTypesAsCertain: false` и аналоги).

## 4. План реализации (Implementation Plan)
1. [x] Временно включить `reportUnmatchedIgnoredErrors: true`, собрать полный список несматченных подавлений. ✅ 2026-11-27: ровно 4 несматченных, совпадают с подтверждёнными в задаче; других нет.
2. [x] Снять/актуализировать «мёртвые» подавления из раздела 3. ✅ 2026-11-27: сняты все 4.
3. [x] Перепроверить живые подавления из раздела 3; обновить комментарии (причина, точный идентификатор). ✅ 2026-11-27: 4 живых подтверждены; добавлены причины и `@techdebt` там, где их не было.
4. [x] Зафиксировать решение по флагу; прогнать `make check`. ✅ 2026-11-27: `reportUnmatchedIgnoredErrors: true` оставлен постоянно; `make check` зелёный.

## 5. Критерии приёмки (Definition of Done)
- [x] `vendor/bin/phpstan analyse` — 0 ошибок; несматченных подавлений нет (при выбранном значении флага). ✅ 2026-11-27, при `reportUnmatchedIgnoredErrors: true`.
- [x] Полный `make check` зелёный. ✅ 2026-11-27: PHPStan/Psalm/Deptrac/PHPMD/PHPCS/PHPUnit (1782 теста, 5380 ассертов, 2 skipped) — OK.
- [x] В `src/` нет `@phpstan-ignore`-подавлений без причины и без ссылки на задачу/прецедент по конвенции (подавления Psalm — вне скоупа этой задачи). ✅ 2026-11-27: все 4 оставшихся `@phpstan-ignore` имеют причину и `@techdebt`-ссылку на эту задачу.

## 6. Самопроверка (Verification)
```bash
vendor/bin/phpstan analyse
make check
php vendor/bin/todo-md validate
```

## 7. Риски и зависимости (Risks and Dependencies)
- Снятие «мёртвого» подавления может вскрыть реальную ошибку, замаскированную устаревшим идентификатором — тогда правка уходит в отдельную задачу-фикс.
- Обновления PHPStan могут менять семантику идентификаторов (`nullsafe.neverNull` и др.) — аудит повторять при мажорных обновлениях анализатора.
- Зависимостей от других задач нет; создание задачи — следствие ревью TASK-chore-deps-update-all (CR-2).

## 8. Источники (Sources)
- Отчёт ревью: `docs/agents/reports/code-reviewer-backend/2026-11-27_22-07_deps-update-all-review.md` (CR-2, §3 — эксперименты с `reportUnmatchedIgnoredErrors: true`).
- Конвенции: `docs/guide/review-functional.md:25`, `docs/guide/review-architectural.md:19`, `docs/conventions/ops/phpmd-suppressions-guidelines.md`.
- Задача-источник нового подавления: `todo/done/TASK-chore-deps-update-all.todo.md`.

## 9. Комментарии (Comments)
- Адресация «мёртвых» подавлений дана по строке с `@phpstan-ignore` (в отчёте ревью часть адресов указана по строке срабатывания — отличается на одну строку).
- Итоговый инвентарь подавлений `@phpstan-ignore` в `src/` (аудит 2026-11-27): 4 живых, все с причиной и `@techdebt`-ссылкой на эту задачу:
  1. `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php` — `property.unusedType` (расхождение PHPStan/Psalm по `@var resource|false|null`);
  2. `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php` — `nullCoalesce.offset` (защитный guard `?? false`);
  3. `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php` — `booleanNot.alwaysFalse` (повторный опрос OS-состояния);
  4. `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php` — `argument.type` (слабый контракт сессионного логгера).
- Снято 4 «мёртвых» подавления из исходного инвентаря (8 → 4 живых); `property.unusedType` из исходного списка «живых» оставлен с документированной причиной после эксперимента с сужением PHPDoc (см. Must Have).
- Адресация оставленных подавлений после аудита (строки сместились из-за добавленных комментариев; в Must Have — снимок на момент создания задачи): `HttpsProxyBridge.php:53` (`property.unusedType`), `HttpsProxyBridge.php:195` (`nullCoalesce.offset`), `ProcessLivenessWatcher.php:102` (`booleanNot.alwaysFalse`), `RunDynamicLoopService.php:120` (`argument.type`).
- Потенциальный техдолг вне рамок этой задачи (отдельная задача при желании): сужение контракта `DynamicLoopSessionLoggerInterface::getRoundFiles()` до `list<array{...}>` снимет подавление `argument.type` (изменение контракта Domain+Infrastructure — требует своего PR и ревью).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-27 15:12:56 (1795792376) | Ревьювер Бэка Пуаро (pi) | Создание задачи |
| 2026-11-27 16:07:38 (1795795658) | Бэкендер Левша (pi) | Взятие задачи в работу (in_progress) |
| 2026-11-27 | Бэкендер Левша (pi) | Аудит выполнен: сняты 4 «мёртвых» подавления; 4 живых перепроверены, добавлены причины и `@techdebt`; `reportUnmatchedIgnoredErrors: true` оставлен постоянно; phpstan/make check зелёные. Отмечены MoSCoW/план/DoD |
| 2026-11-27 | Бэкендер Левша (pi) | Доработка по ревью (2026-11-27_23-31_phpstan-suppressions-audit-review): CR-1 — возвращён живой прецедент `property.unusedType` в комментарий `ProcessLivenessWatcher.php` (без номеров строк), строка 64 переформулирована без «снятый»; CR-3 — в `HttpsProxyBridge.php` уточнены идентификаторы Psalm (`DocblockTypeContradiction`, `RedundantConditionGivenDocblockType`); CR-5 — DoD сужен до `@phpstan-ignore`-подавлений (Psalm — вне скоупа) |
