# Повторное архитектурное ревью (RV-A recheck) ветки task/refactor-overlength-services

**Роль:** Архитектор Локи (system_architect_loki)
**Дата:** 2026-11-23
**Объект:** рабочая копия ветки `task/refactor-overlength-services` (git diff HEAD + untracked) — только устранение замечаний CR-1…CR-3 из RV-A (базовый отчёт: `docs/agents/reports/system-architect-loki/2026-11-23_18-45_refactor-overlength-services-arch-review.md`)
**Задача:** `todo/TASK-refactor-overlength-services.todo.md` (C2)
**Скоуп:** строго проверка трёх замечаний RV-A; новые территории не вскрывались, поведение и метрики не перепроверялись (RV-F approve ранее).

## Вердикт

**APPROVE** — все три замечания RV-A устранены по существу и в требуемом объёме. Новых замечаний нет.

## CR-1 — единый механизм подавления PHPMD StaticAccess — УСТРАНЕНО

- `src/Component/PharRuntime/PharRunningPathResolver.php:21-26` — на `runningPath()` инлайн `@SuppressWarnings("PHPMD.StaticAccess")` с комментарием-причиной в терминах контракта: «единственный способ получить путь PHAR (static API вендора, без инстансной альтернативы); постоянное санкционированное подавление, не техдолг» + отсылка к `docs/conventions/ops/phpmd-suppressions-guidelines.md`. Локально (метод), обосновано → по guidelines.
- `phpmd.baseline.xml` — запись `StaticAccess` для `PharRunningPathResolver` отсутствует (удалена); оставшиеся записи baseline (`GitIdentity/*`) не затронуты этим замечанием.
- `src/Component/Release/ReleaseVersionResolver.php:64-65` и `:84-85` — оба `@SuppressWarnings("PHPMD.StaticAccess")` снабжены причиной («Composer InstalledVersions — официальный статический API Composer runtime»).
- Итог: механизм один (инлайн-подавление), обоснование рядом с каждым подавлением — требование выполнено полностью.

## CR-2 — переименование YamlDynamicPromptsResolverService(Interface) → ResolveYamlDynamicPromptsService(Interface) — УСТРАНЕНО

- Файлы: `src/Module/ChainDefinition/Domain/Service/Chain/ResolveYamlDynamicPromptsServiceInterface.php`, `src/Module/ChainDefinition/Infrastructure/Service/Chain/ResolveYamlDynamicPromptsService.php`, `tests/Unit/Infrastructure/Service/Chain/ResolveYamlDynamicPromptsServiceTest.php` — имена файлов и классов соответствуют `service.md` (`{Action}{Target}Service`, Action — глагол `Resolve`).
- Alias: `src/Module/ChainDefinition/Resource/config/services.yaml:32-33` — `ResolveYamlDynamicPromptsServiceInterface` → `…Infrastructure\Service\Chain\ResolveYamlDynamicPromptsService`.
- Вызовы: `YamlChainLoaderService` (use, свойство, PHPDoc `{@see …}`) и 9 тестовых файлов обновлены.
- Поиск старого имени `YamlDynamicPromptsResolver*` по src/, apps/, tests/, config/, docs/ — **0 совпадений** в коде; упоминания остались только в исторических записях отчётов/комментариев задачи (допустимо, это летопись, не ссылки).

## CR-3 — @techdebt на RunDynamicLoopAgentServiceInterface::runFacilitator() — УСТРАНЕНО (с процессной оговоркой)

- `src/Module/DynamicLoop/Domain/Service/Dynamic/RunDynamicLoopAgentServiceInterface.php:17-18` — `@techdebt 2026-11-23` с причиной («15 позиционных скалярных параметров — инкапсуляция хвоста сигнатуры в VO отложена в техдолг») на PHPDoc `runFacilitator()`. Требование AGENTS.md «Помечай временный код» выполнено.
- Альтернатива «задача в todo/» зафиксирована явным решением тимлида в комментариях задачи (`todo/TASK-refactor-overlength-services.todo.md:112`: «решение тимлида — только метка, задачу заведёт тимлид») — допустимый вариант по CR-3.
- **Оговорка (не блокирует ветку):** `TASK-*` на инкапсуляцию сигнатуры в VO физически ещё не заведена; она числится за тимлидом («задачу заведёт тимлид»). Напоминание тимлиду: завести задачу в `todo/` до закрытия эпика, чтобы цикл «@techdebt → задача → устранение» не завис.

## Итог

| Замечание | Статус | Подтверждение |
| :--- | :--- | :--- |
| CR-1 | Устранено | Инлайн `@SuppressWarnings` + причина в `PharRunningPathResolver::runningPath()`; запись из baseline удалена; оба подавления `ReleaseVersionResolver` с причинами |
| CR-2 | Устранено | Файлы/классы/alias/use/вызовы/тесты/PHPDoc переименованы; старое имя в коде отсутствует |
| CR-3 | Устранено | `@techdebt 2026-11-23` с причиной на `runFacilitator()`; решение по задаче зафиксировано в комментариях (создание TASK-* — за тимлидом) |

**Финальный вердикт RV-A: APPROVE.** Правок кода при recheck не вносилось (по заданию).
