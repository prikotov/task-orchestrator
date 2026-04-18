---
type: refactor
created: 2026-04-17
value: V3
complexity: C3
priority: P2
depends_on: []
epic: EPIC-arch-orchestrator-module-decomposition
author: Архитектор (Гэндальф)
assignee: Бэкендер
branch: task/arch-integration-naming-convention
status: in_progress
---

# TASK-arch-integration-naming-convention: Привести интеграционный слой Orchestrator к конвенции service.md

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда я разрабатываю интеграционный слой модуля Orchestrator, я хочу чтобы интерфейсы и реализации следовали конвенции `service.md` (Integration Service), — чтобы именование и расположение файлов были единообразными с остальными сервисами проекта.

### Goal (Цель по SMART)
Устранить 4 нарушения конвенции `docs/conventions/core_patterns/service.md` (раздел «Интеграционный сервис»):

| # | Что | Фактически | По конвенции |
|---|-----|-----------|--------------|
| 1 | Подкаталог интерфейса | `Domain/Service/Port/` | `Domain/Service/Integration/` |
| 2 | Суффикс интерфейса | `*PortInterface` | `*ServiceInterface` |
| 3 | Подкаталог реализации | `Integration/Adapter/` | `Integration/Service/` |
| 4 | Суффикс реализации | `*Adapter` | `*Service` |
| 5 | Именование | `AgentRunnerPortInterface` (нет Action) | `{Action}{Target}ServiceInterface` |

После задачи все интеграционные сервисы следуют `{Action}{Target}Service` / `{Action}{Target}ServiceInterface`.

## 2. Context and Scope (Контекст и Границы)

* **Где делаем:** `src/Common/Module/Orchestrator/Domain/Service/Port/`, `src/Common/Module/Orchestrator/Integration/Adapter/`
* **Текущее поведение:**
  - `Domain/Service/Port/AgentRunnerPortInterface` — интерфейс без глагола-действия, нестандартный суффикс
  - `Domain/Service/Port/AgentRunnerRegistryPortInterface` — аналогично
  - `Integration/Adapter/AgentRunnerAdapter` — реализация в `Adapter/` вместо `Service/`
  - `Integration/Adapter/AgentRunnerRegistryAdapter` — аналогично
  - `Integration/Adapter/AgentDtoMapper` — вспомогательный маппер
* **Конвенция:** `docs/conventions/core_patterns/service.md` → раздел «Интеграционный сервис (Integration Service)»:
  - Интерфейс: `{Module}\Domain\Service\{Context?}\{Action}{Target}ServiceInterface`
  - Реализация: `{Module}\Integration\Service\{Context?}\{Action}{Target}Service`
  - Именование: `{Action}` = глагол, `{Target}` = предмет

### Scope (делаем)
- Переименование интерфейсов: `*PortInterface` → `*ServiceInterface` с правильным Action
- Перемещение интерфейсов: `Domain/Service/Port/` → `Domain/Service/Integration/`
- Переименование реализаций: `*Adapter` → `*Service`
- Перемещение реализаций: `Integration/Adapter/` → `Integration/Service/AgentRunner/`
- Обновление всех `use`-директив в Orchestrator (Domain, Application, Infrastructure, Integration)
- Обновление DI-конфигурации (`config/services.yaml`)
- Обновление unit-тестов

### Out of Scope (не делаем)
- Не меняем сигнатуры методов и бизнес-логику
- Не меняем AgentRunner-модуль
- Не создаём новые интерфейсы/сервисы — только переименование существующих

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (обязательно)

- [ ] Переименовать интерфейсы и реализации по конвенции:

  | Было | Стало |
  |------|-------|
  | `Domain/Service/Port/AgentRunnerPortInterface` | `Domain/Service/Integration/RunAgentServiceInterface` |
  | `Domain/Service/Port/AgentRunnerRegistryPortInterface` | `Domain/Service/Integration/ResolveAgentRunnerServiceInterface` |
  | `Integration/Adapter/AgentRunnerAdapter` | `Integration/Service/AgentRunner/RunAgentService` |
  | `Integration/Adapter/AgentRunnerRegistryAdapter` | `Integration/Service/AgentRunner/ResolveAgentRunnerService` |
  | `Integration/Adapter/AgentDtoMapper` | `Integration/Service/AgentRunner/AgentDtoMapper` |

- [ ] Обновить все `use`-импорты в `src/` (grep по старым именам → 0 совпадений)
- [ ] Обновить `config/services.yaml` — alias'ы на новые имена
- [ ] Обновить unit-тесты в `tests/Unit/` (переименование + `use`-импорты)
- [ ] `vendor/bin/phpunit` — зелёные
- [ ] `vendor/bin/psalm` — 0 ошибок
- [ ] Удалить пустые каталоги `Domain/Service/Port/` и `Integration/Adapter/`

### 🟡 Should Have (желательно)

- [ ] Обновить `docs/conventions/modules/index.md` — отразить новый путь `Integration/Service/`
- [ ] Обновить `docs/guide/architecture.md` — актуализировать диаграмму и примеры
- [ ] Обновить `docs/adr/001-module-decomposition.md` — актуализировать имена

### 🟢 Could Have (опционально)

### ⚫ Won't Have (не будем делать)

- [ ] Изменение сигнатур методов или бизнес-логики
- [ ] Рефакторинг AgentRunner-модуля

## 4. Risks and Dependencies (Риски и зависимости)

- **Риск:** массовое обновление `use`-директив (~20+ файлов) — высокая вероятность пропустить. Митигация: `grep -r` по старым именам после завершения.
- **Зависимость:** нет блокирующих зависимостей от других задач.

## 5. Acceptance Criteria (Критерии приёмки)

1. `grep -r "PortInterface\|AgentRunnerAdapter\|AgentRunnerRegistryAdapter\|AgentDtoMapper" src/` — 0 совпадений (старые имена удалены)
2. Все интеграционные файлы в `Integration/Service/AgentRunner/` с суффиксом `*Service`
3. Все интерфейсы в `Domain/Service/Integration/` с суффиксом `*ServiceInterface`
4. Именование `{Action}{Target}Service` (где Action = глагол: `Run`, `Resolve`)
5. `vendor/bin/phpunit` — зелёные
6. `vendor/bin/psalm` — 0 ошибок
7. Каталоги `Domain/Service/Port/` и `Integration/Adapter/` не существуют

## Инструкции для сабагента

**Роль:** docs/agents/roles/team/backend_developer.ru.md
**Ветка:** task/arch-integration-naming-convention (уже создана и активна)
**PR:** уже создан (draft #15) из task/arch-integration-naming-convention в task/arch-orchestrator-module-decomposition

### Порядок действий
1. Переключись в ветку `task/arch-integration-naming-convention`: `git checkout task/arch-integration-naming-convention`
2. Реализуй задачу согласно описанию и критериям выше.
3. Следуй AGENTS.md и Конвенциям проекта.
4. Делай коммиты по Conventional Commits.
5. Делай промежуточные коммиты после каждого логического этапа (обновил `src/` → коммит, обновил `tests/` → коммит). Это сохранит прогресс при таймауте сабагента.
6. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm` — оба должны пройти.
7. Запуш: `git push`.
8. Переведи PR из draft в ready: `gh pr ready 15`. Эта команда снимает флаг draft — PR становится готовым к мержу.

**НЕ создавай новый PR** — он уже существует.
**НЕ меняй base Branch** — он уже указывает на task/arch-orchestrator-module-decomposition.

### Ключевые архитектурные правила

1. **Переименование файлов и классов — по конвенции `docs/conventions/core_patterns/service.md`** (раздел «Интеграционный сервис»):
   - Интерфейс: `Domain/Service/Integration/{Action}{Target}ServiceInterface`
   - Реализация: `Integration/Service/{Context?}/{Action}{Target}Service`
   - Именование: `{Action}` = глагол (Run, Resolve), `{Target}` = предмет
2. **Конкретные переименования:**
   - `Domain/Service/Port/AgentRunnerPortInterface` → `Domain/Service/Integration/RunAgentServiceInterface`
   - `Domain/Service/Port/AgentRunnerRegistryPortInterface` → `Domain/Service/Integration/ResolveAgentRunnerServiceInterface`
   - `Integration/Adapter/AgentRunnerAdapter` → `Integration/Service/AgentRunner/RunAgentService`
   - `Integration/Adapter/AgentRunnerRegistryAdapter` → `Integration/Service/AgentRunner/ResolveAgentRunnerService`
   - `Integration/Adapter/AgentDtoMapper` → `Integration/Service/AgentRunner/AgentDtoMapper`
3. **Обновить ВСЕ `use`-директивы** в `src/` и `tests/`. После завершения `grep -r "PortInterface\|AgentRunnerAdapter\|AgentRunnerRegistryAdapter" src/` → 0 совпадений.
4. **Обновить DI:** `config/services.yaml` — alias на новые имена.
5. **Удалить пустые каталоги:** `Domain/Service/Port/` и `Integration/Adapter/`.

### Рекомендуемый порядок
1. Переименуй интерфейсы в Domain/Service/Integration/ (новый каталог) — обнови use во всех файлах.
2. Переименуй реализации в Integration/Service/AgentRunner/ — обнови use.
3. Обнови DI (config/services.yaml).
4. Обнови тесты (переименование файлов + use).
5. Запусти phpunit + psalm.
6. Удали пустые старые каталоги.

## Change History (История изменений)

| Дата | Автор | Изменение |
|------|-------|-----------|
| 2026-04-17 | Архитектор (Гэндальф) | Создание задачи по результатам архитектурного ревью Integration-слоя |
