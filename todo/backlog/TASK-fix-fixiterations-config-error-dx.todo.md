---
type: fix
created: 2026-06-15
value: V1
complexity: C2
priority: P2
depends_on: []
epic:
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: backlog
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
Требует архитектурного дизайна (Архитектор Локи/Гэндальф), т.к. развязывает два противоречащих требования: фабрика обязана fail-fast при `load` (иначе невалидный VO попадёт в рантайм), а detailed-диагностика нужна только в пути `validate`, не в боевом `run`. Возможные подходы (на оценку архитектору):
1. Валидатор как **pre-check** перед `load` в пути `validate:connectivity`/`validate-config` (запускать detailed-валидатор по «сырому» описанию до фабрики).
2. Перехват `InvalidArgumentException` фабрики + повторный парсинг detailed-валидатором по «сырым» данным.
3. Обогащение generic-сообщения фабрики (half-measure, 🅲 из оценки тимлида): перечислить имена доступных шагов в подсказке — дёшево, но не идеально.

### Ожидаемый результат (Expected Result)
При ошибке в `fix_iterations` в `chains.yaml` пользователь видит имя группы и/или шага и тип нарушения (unknown step / step in multiple groups) — на уровне качества detailed-валидатора. Поведение `run` (боевой путь) остаётся fail-fast. Инвариант не ослабляется.

## Почему в backlog (осознанный trade-off)
- **Не баг, не регрессия:** инвариант защищён (fail-fast срабатывает), корректность не страдает. Страдает только DX.
- **Цена disproportional к вреду:** тянет архитектурный дизайн ради одного сценария. Сделано после закрытия слепых зон №1 (PR #263) и №3 (PR #262), когда deprecated-VO и detailed-валидатор уже держат инвариант согласованно со спецификацией.
- **Условие активации:** вернуться, когда (а) наберётся ещё подобных «глухих» сообщений в валидации конфигов — тогда решать системно (единый путь detailed-диагностики); или (б) будет спринт на полировку DX.
- **Дешёвая альтернатива (half-measure):** если захочется «что-то улучшить сейчас» без архитектуры — обогатить generic-сообщение в `assertStepBasedInvariant` списком доступных шагов. Вынести в отдельную C1-задачу из backlog по запросу.

## Контекст и оценка (тимлид Алекс, 2026-06-15)
- **Критичность для публичного продукта: средняя (P2), не срочная.** Безопасность/корректность не страдают; UX-вред средний (поиск виновной группы/шага глазами); частота сценария низкая-средняя (продвинутая фича); workaround есть (прочитать конфиг глазами).
- Архитектурная дилемма: factory обязан fail-fast при `load`, но detailed-диагностика нужна только в пути `validate`.

## Источники
- Ревью (слепая зона №2): `docs/agents/reports/code-reviewer-backend/2026-06-15_08-50_pr-261-fixiterations-redesign-architecture-review.md`
- Дизайн: `docs/agents/reports/system-architect/2026-06-15_00-10_fixiterations-validation-redesign.md`
- Generic-исключение фабрики: `src/Module/ChainDefinition/Domain/Factory/ChainDefinitionFactory.php` (`assertStepBasedInvariant`)
- Detailed-валидатор (недостижимый в этом пути): `src/Module/ChainDefinition/Domain/Service/Chain/ChainDefinitionValidatorService.php` (синхронизирован в PR #262)
- Загрузчик: `src/Module/ChainDefinition/Infrastructure/Service/Chain/YamlChainLoaderService.php`
- Связанные закрытые задачи: `todo/done/TASK-sync-validator-specification-fix-iterations.todo.md` (PR #262), `todo/done/TASK-guard-chaindefinitionvo-fixiterations.todo.md` (PR #263)
