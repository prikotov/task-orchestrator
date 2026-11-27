---
type: chore
created: 2026-11-27 15:12:56 (1795792376)
due: 
started: 2026-11-27 16:07:38 (1795795658)
completed: 
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
pr: 
status: in_progress
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
- [ ] Снять подтверждённые «мёртвые» подавления `nullsafe.neverNull` / `return.type` (не сматчены при `reportUnmatchedIgnoredErrors: true`, проверка 2026-11-27):
  - `src/Module/ChainExecution/Application/Mapper/ReportJsonMapper.php:55` — `return.type` (над `json_encode` на :56);
  - `src/Module/DynamicLoop/Domain/Entity/DynamicLoopExecution.php:297` и `:298` — `nullsafe.neverNull`;
  - `src/Module/DynamicLoop/Domain/Service/Dynamic/FinalizeDynamicLoopService.php:95` — `nullsafe.neverNull`.
- [ ] Перепроверить живые подавления (необходимость, идентификатор, оформление):
  - `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php:41` — `property.unusedType`;
  - `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php:176` — `nullCoalesce.offset`;
  - `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php:102` — `booleanNot.alwaysFalse` (введено при обновлении PHPStan 2.3.7, см. TASK-chore-deps-update-all);
  - `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopService.php:113` — `argument.type`.
- [ ] Принять решение по `reportUnmatchedIgnoredErrors` (предпочтительно `true`) и зафиксировать его в задаче.

### ⚫ Won't Have (Не будем делать)
- Массовое переписывание кода под правила анализатора, где это меняет поведение.
- Переход на глобальное ослабление конфигурации (`treatPhpDocTypesAsCertain: false` и аналоги).

## 4. План реализации (Implementation Plan)
1. [ ] Временно включить `reportUnmatchedIgnoredErrors: true`, собрать полный список несматченных подавлений.
2. [ ] Снять/актуализировать «мёртвые» подавления из раздела 3.
3. [ ] Перепроверить живые подавления из раздела 3; обновить комментарии (причина, точный идентификатор).
4. [ ] Зафиксировать решение по флагу; прогнать `make check`.

## 5. Критерии приёмки (Definition of Done)
- [ ] `vendor/bin/phpstan analyse` — 0 ошибок; несматченных подавлений нет (при выбранном значении флага).
- [ ] Полный `make check` зелёный.
- [ ] В `src/` нет подавлений без причины и без ссылки на задачу/прецедент по конвенции.

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
- Полный инвентарь подавлений зафиксирован на 2026-11-27: 8 мест в `src/`; при выполнении задачи перечень обновить (новые подавления, появившиеся с момента создания).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-27 15:12:56 (1795792376) | Ревьювер Бэка Пуаро (pi) | Создание задачи |
