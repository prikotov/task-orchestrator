---
type: fix
created: 2026-06-15
value: V1
complexity: C2
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер Левша
branch: task/fixiterations-config-error-dx
pr: '#271'
status: done
---

# TASK-fix-fixiterations-config-error-dx: Подробная диагностика fix-итераций недостижима в production-пути загрузки конфига

## 0. Простое описание (Human Brief)
В пути загрузки конфигурации цепочки (`validate:connectivity`, `run` и др.) детальная диагностика `fix_iterations` структурно недостижима: generic-исключение фабрики падает раньше валидатора. Пользователь получает «глухое» сообщение без имени группы/шага.

### Проблема простыми словами (Problem)
Внешний разработчик ошибается в `chains.yaml` (опечатка в имени шага группы `fix_iteration` либо шаг в двух группах). Загрузка идёт через `YamlChainLoaderService::load()` → `ChainDefinitionFactory::assertStepBasedInvariant()`, который бросает generic-исключение:
```
Chain "my-chain": fix_iterations must reference existing named steps and each step name must belong to at most one fix_iteration group.
```
Сообщение называет цепочку, но НЕ называет группу, шаг и тип ошибки (unknown vs duplicate). А detailed-валидатор `ChainDefinitionValidatorService` (наученный в PR #262 писать `fix_iteration step "Y" belongs to multiple groups ("X" and "Z").`) — структурно недостижим: `load()` падает раньше валидатора.

Это **слепая зона №2** из ревью PR #261 — сознательный trade-off на момент редизайна.

### Варианты или путь решения (Solution Sketch)
Архитектурная дилемма: фабрика обязана fail-fast при `load` (иначе невалидный VO попадёт в рантайм), а detailed-диагностика нужна только в пути `validate`, не в боевом `run`. Рассмотренные подходы (на оценку архитектору):
1. Валидатор как **pre-check** перед `load` (запускать detailed-валидатор по «сырому» описанию до фабрики).
2. Перехват `InvalidArgumentException` фабрики + повторный парсинг detailed-валидатором по «сырым» данным.
3. Обогащение generic-сообщения фабрики (half-measure): перечислить имена доступных шагов — дёшево, но не идеально.

**Выбранный подход (D′)** — предложен Архитектором Локи в дизайне, одобрен Тимлидом: единый источник detailed-логики в новом Domain Service-коллекторе; фабрика бросает carrier-исключение с raw-входами (вместо голого `InvalidArgumentException`); validate-путь ловит его и собирает detailed-нарушения. Подробнее — в [Implementation Plan](#4-implementation-plan-план-реализации) и дизайне Локи.

### Ожидаемый результат (Expected Result)
При ошибке в `fix_iterations` в `chains.yaml` пользователь видит имя группы и/или шага и тип нарушения (unknown step / step in multiple groups) — на уровне качества detailed-валидатора. Поведение `run` (боевой путь) остаётся fail-fast. Инвариант не ослабляется.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда внешний разработчик допускает ошибку в `fix_iterations` цепочки в `chains.yaml`, я хочу увидеть в выводе `--validate-config` имя группы, шаг и тип нарушения, чтобы быстро локализовать проблему без вычитывания всего конфигурационного файла глазами.

### Goal (Цель по SMART)
Сделать detailed-диагностику `fix_iterations` (группа + шаг + тип: unknown / multiple groups) достижимой в пути `--validate-config` (`ValidateChainConfigQueryHandler`), сохранив fail-fast боевого пути `run` без ослабления инварианта и не плодя второй источник detailed-сообщений.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/ChainDefinition/Domain/Factory/ChainDefinitionFactory.php` (`assertStepBasedInvariant`) — бросает carrier-исключение вместо generic.
    *   `src/Module/ChainDefinition/Domain/Service/Chain/ChainDefinitionValidatorService.php` — делегирует detailed-логику коллектору.
    *   Новые Domain-компоненты: `Domain/Exception/ChainConfigExceptionInterface`, `Domain/Exception/InvalidFixIterationsException`, `Domain/Service/Chain/CollectFixIterationsViolationsService(Interface)`.
    *   `src/Module/ChainDefinition/Application/UseCase/Query/Chain/ValidateChainConfig/ValidateChainConfigQueryHandler.php` — перехват carrier и сборка violations.
    *   `config/services.yaml` — alias для нового коллектора.
*   **Текущее поведение:** `load()` → factory бросает generic `\InvalidArgumentException` ДО того, как VO доходит до detailed-валидатора; валидатор структурно недостижим в validate-пути. Боевой путь `run` ловит `\Throwable` и печатает `getMessage()` (fail-fast соблюдён).
*   **Границы (Out of Scope):**
    *   `validate:connectivity` — не трогается (не грузит цепочки, не связан с `fix_iterations`; подтвердил Архитектор Локи по коду).
    *   Полный охват validate-**all** (когда одна невалидная цепочка не обрывает проход по всем) — требует parser extraction (подход A), отдельная задача; в рамках текущей — минимальное улучшение (показ violations упавшей цепочки вместо generic-краша).
    *   Обобщение carrier-паттерна на все конфиг-ошибки (YAGNI до второго кейса).
    *   Правка прочих голых `InvalidArgumentException` / `catch (\Throwable)` в модуле — предсуществующий долг.

## 3. Requirements (MoSCoW)
### 🔴 Must Have
- [x] В пути `--validate-config` (`validateSpecificChain`) при ошибке `fix_iterations` выводятся имя группы и/или шага и тип нарушения (unknown step / multiple groups) — на уровне качества detailed-валидатора.
- [x] Единый источник detailed-логики `fix_iterations` — новый Domain Service-коллектор `CollectFixIterationsViolationsService`; валидатор делегирует ему inline-цикл (поведение побайтово то же).
- [x] Фабрика бросает domain-исключение-носитель `InvalidFixIterationsException` (наследник `\InvalidArgumentException` + маркерный `ChainConfigExceptionInterface`) вместо голого `InvalidArgumentException`; `getMessage()` сохраняет прежний generic-текст.
- [x] Run-путь (`agent:orchestrate`, `load()`) — fail-fast сохранён, поведение не меняется (класс исключения-наследник, текст тот же).
- [x] Unit-тесты: новый коллектор (все edge-cases), обновлённые FactoryTest/ValidatorTest/HandlerTest/OrchestrateCommandTest.
- [x] Integration-тест (главный приёмочный): `--validate-config --chain <broken>` через реальные loader+factory+collector+handler с assertion на имя группы/шага.
### 🟡 Should Have
- [x] Антидивергентность: `specification false ⟺ collector non-empty` (усиленный oracle-check).
- [x] `ChainNotFoundException` НЕ маскируется catch по маркеру (чужие исключения всплывают).
### ⚫ Won't Have (Не будем делать)
- [ ] Полный охват validate-all (parser extraction) — отдельная задача.
- [ ] Правка прочих голых исключений в модуле — предсуществующий долг.
- [ ] Обобщение carrier-паттерна до `ChainConfigExceptionInterface` + единый носитель на все конфиг-ошибки (YAGNI).

## 4. Implementation Plan (План реализации)
Архитектурный дизайн (контракт реализации): `docs/agents/reports/system-architect/2026-06-17_fixiterations-config-error-dx-design.md`, секция 7 «Implementation Spec для Бэкендера Левши». Реализация завершена Бэкендером Левшей, ревью APPROVAL от Ревьювера Бэка Пуаро.

1. [x] `Domain/Exception/ChainConfigExceptionInterface.php` — маркерный интерфейс (`extends \Throwable`), прецедент `NotFoundExceptionInterface` в этом же модуле.
2. [x] `Domain/Exception/InvalidFixIterationsException.php` — carrier (`extends \InvalidArgumentException implements ChainConfigExceptionInterface`), readonly props + геттеры (`getChainName/getSteps/getFixIterations`), generic-текст сохранён.
3. [x] `Domain/Service/Chain/CollectFixIterationsViolationsService(Interface).php` — перенос inline-цикла `fix_iterations` из валидатора дословно.
4. [x] `config/services.yaml` — alias `Interface → Impl`.
5. [x] `ChainDefinitionValidatorService` — внедрить коллектор, заменить блок `fix_iterations` на `$this->collector->collect(...)`.
6. [x] `ChainDefinitionFactory::assertStepBasedInvariant` — `throw new InvalidFixIterationsException($name, $steps, $fixIterations)`.
7. [x] `ValidateChainConfigQueryHandler` — 4-я зависимость; `try/catch (ChainConfigExceptionInterface)` вокруг `load()` в `validateSpecificChain`; `list()` в `validateAllChains` обёрнут.

## 5. Definition of Done (Критерии приёмки)
- [x] Сценарий протестирован (Unit + Integration): `make check` зелёный, 1081 тест OK.
- [x] Нет регрессий в смежных модулях (Deptrac 0 violations, Psalm/PHPStan/PHPMD/PHPCS зелёные).
- [x] Detailed-сообщения и порядок нарушений побайтово совпадают с предыдущим поведением валидатора (контракт «дословного переноса»).
- [x] Run-путь fail-fast сохранён, инвариант не ослаблен.
- [ ] Тех. документация обновлена (если формулировки вывода `--validate-config` менялись — проверить поиском по «fix_iteration»/«validate-config»).

## 6. Verification (Самопроверка)
```bash
make check
# phpunit 1081 tests OK; phpstan/psalm/deptrac/phpmd/phppcs/md-links/validate-todo/validate-roles — зелёные
bin/console debug:container CollectFixIterationsViolationsService   # alias резолвится
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Изменение класса исключения фабрики** — низкий риск: `InvalidFixIterationsException extends \InvalidArgumentException`, существующие `expectException(\InvalidArgumentException::class)` остаются зелёными (контракт).
- **Расхождение коллектор ↔ Specification** — средний риск, парируется антидивергентным oracle-тестом.
- **Validate-all остаётся degraded** — осознанный scope-cut (P2); полный охват — отдельная задача (parser extraction).
- **Ручные сборки Handler в тестах** — низкий риск: добавлен 4-й аргумент-коллектор.
- **Документация** — проверить `docs/guide/cli.md` / troubleshooting при изменении формулировок вывода `--validate-config`.

## 8. Sources (Источники)
- Дизайн (контракт реализации): `docs/agents/reports/system-architect/2026-06-17_fixiterations-config-error-dx-design.md`
- Ревью APPROVAL: цикл code review Ревьювера Бэка Пуаро по Implementation Spec §7.
- Ревью (слепая зона №2): `docs/agents/reports/code-reviewer-backend/2026-06-15_08-50_pr-261-fixiterations-redesign-architecture-review.md`
- Предыдущий дизайн: `docs/agents/reports/system-architect/2026-06-15_00-10_fixiterations-validation-redesign.md`
- Конвенции: `docs/conventions/core_patterns/{exception,factory,service}.md`, `docs/conventions/layers/domain/specification.md`
- Связанные закрытые задачи: `todo/done/TASK-sync-validator-specification-fix-iterations.todo.md` (PR #262), `todo/done/TASK-guard-chaindefinitionvo-fixiterations.todo.md` (PR #263)

## Почему задача поднята из backlog (осознанный trade-off → активация)
- **Не баг, не регрессия:** инвариант защищён (fail-fast срабатывает), корректность не страдает. Страдает только DX.
- **Условие активации выполнено:** набрался кейс «глухого» сообщения валидации конфига + спринт на полировку DX.
- **Цена оправдана:** единый источник detailed-логики (коллектор) + carrier-исключение решают сценарий системно и попутно исправляют convention-запах (голое `InvalidArgumentException` → domain-исключение с маркером).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-15 | Тимлид (Алекс) | Создание задачи в backlog (осознанный trade-off после PR #261/#262/#263). |
| 2026-06-17 | Тимлид (Алекс) | Поднята из backlog в работу. Выбран путь C (spike Локи → Левша → Пуаро). Архитектор Локи выполнил дизайн (подход D′). |
| 2026-06-17 | Бэкендер Левша | Реализация по Implementation Spec §7 (7 шагов + тесты). |
| 2026-06-17 | Ревьювер Бэка Пуаро | Code review — APPROVAL (2 NIT, косметика PHPDoc). |
| 2026-06-17 | Тимлид (Алекс) | Приведение файла к каноническому шаблону для `validate-todo`; проверки зелёные. |
