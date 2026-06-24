---
type: chore
created: 2026-06-21
value: V2
complexity: C3
priority: P2
depends_on:
epic:
author: Тимлид Алекс
assignee: Тимлид Алекс
branch: task/modules-configuration-convention
pr:
status: in_progress
---

# TASK-techdebt-modules-configuration-convention: Привести конфигурацию модулей к конвенции

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Конвенция `docs/conventions/modules/configuration.md` требует, чтобы каждый модуль имел свой конфиг `src/Module/<Name>/Resource/config/services.yaml`, класс `<Name>Module implements ModuleInterface` и параметры вида `module.<name>.<context>`. Но **ни один** из существующих модулей (AgentRunner, ChainDefinition, ChainExecution, DynamicLoop) этому не следует — вся конфигурация централизованно лежит в общем `config/services.yaml`, регистрируется через `TaskOrchestratorExtension`. Расхождение обнаружено при разработке модуля GitIdentity (PR #275), где тимлид ошибочно взял за эталон «как у соседей» вместо конвенции.

### Варианты или путь решения (Solution Sketch)
Либо привести все модули к конвенции (вынести `Resource/config/services.yaml` + `*Module.php` + реестр `modules.php`), либо — если текущая централизованная схема осознанно выбрана как архитектура проекта — обновить саму конвенцию `modules/configuration.md`, чтобы она описывала реальность (и не вводила в заблуждение). Решение принимает владелец проекта по итогам архитектурного обсуждения (Архитектор Гэндальф/Локи).

### Ожидаемый результат (Expected Result)
Конвенция и код согласованы: либо все модули следуют `Resource/config/services.yaml` + `ModuleInterface`, либо конвенция переписана под централизованную схему. Пробел «конвенция врёт» устранён, новые модули больше не попадают в ловушку «делать как у соседей».

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда я создаю новый модуль или ревьюлю существующий, я хочу, чтобы конвенция конфигурации была источником истины, которому соответствует реальный код, чтобы не повторять долг «как у соседей» и не ломать архитектуру.

### Goal (Цель по SMART)
Устранить расхождение между `docs/conventions/modules/configuration.md` и реальной конфигурацией 5 модулей. Принять архитектурное решение (привести код к конвенции ИЛИ привести конвенцию к коду), реализовать, покрыть тестами.

## 2. Context and Scope (Контекст и Границы)

* **Где делаем:**
  - `src/Module/AgentRunner/`, `src/Module/ChainDefinition/`, `src/Module/ChainExecution/`, `src/Module/DynamicLoop/` (+ опционально `GitIdentity`, если PR #275 уже слит).
  - `src/DependencyInjection/TaskOrchestratorExtension.php` — централизованная регистрация.
  - `config/services.yaml` — где сейчас живут все модули.
  - `docs/conventions/modules/configuration.md` — конвенция.
* **Текущее состояние (по факту аудита 2026-06-21):**
  | Модуль | `*Module.php` | `Resource/config/services.yaml` | параметры `module.<name>.*` |
  |---|---|---|---|
  | AgentRunner | ❌ | ❌ | 0 |
  | ChainDefinition | ❌ | ❌ | 0 |
  | ChainExecution | ❌ | ❌ | 0 |
  | DynamicLoop | ❌ | ❌ | 0 |
  | GitIdentity | ✅ | ✅ | есть |

  **Все 5 модулей** зарегистрированы централизованно в `config/services.yaml` блоками `# ─── <Module> module`, без `ModuleInterface` и без `Resource/config/`.
* **Границы (Out of Scope):**
  - Не меняем бизнес-логику модулей — только инфраструктуру конфигурации/DI.
  - Не трогаем Domain/Application слои (только как сервисы регистрируются).
  - Развёрнутое приведение `Resource/templates`, `Resource/translations`, Doctrine-маппингов (если применимо) — отдельными задачами; здесь только конфигурация сервисов.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] **Архитектурное решение** (Архитектор Гэндальф или Локи): выбрать направление:
  - **Путь A — привести код к конвенции**: каждый модуль получает `<Name>Module.php` (`ModuleInterface`), `Resource/config/services.yaml` с параметрами `module.<name>.*`, реестр `config/modules.php` (или аналог в `TaskOrchestratorExtension`). Общий `config/services.yaml`Slim-ится.
  - **Путь B — привести конвенцию к коду**: переписать `docs/conventions/modules/configuration.md` под централизованную схему `TaskOrchestratorExtension → config/services.yaml` (с явным описанием блоков по модулям, naming convention параметров). `ModuleInterface`/`Resource/config` убрать из конвенции или пометить как опциональные.
- [ ] Решение зафиксировано в ADR (`docs/adr/`) — почему выбран этот путь.

### 🟡 Should Have (Желательно)

- [ ] Реализация выбранного пути для всех модулей (если Путь A) ИЛИ обновление конвенции (если Путь B).
- [ ] Проверка: `vendor/bin/phpunit`, `vendor/bin/psalm`, `make deptrac` — зелёные, без регрессий.
- [ ] GitIdentity (из PR #275) приведён к тому же стандарту, что и остальные (консистентность).

### ⚫ Won't Have (Не будем делать)

- Полный переход на Symfony Kernel / Flex-структуру (если проект сознательно lightweight без Kernel).
- Doctrine-маппинги и web-специфичные `TwigInterface`/`TranslationInterface` (нет web-приложения).

## 4. Implementation Plan (План реализации)

*Заполняется исполнителем после архитектурного решения:*

1. [ ] Архитектурное обсуждение (brainstorm или Гэндальф+Локи), выбор Путь A/B.
2. [ ] ADR с обоснованием.
3. [ ] Реализация (пилот на одном модуле → остальные).
4. [ ] Проверки + smoke-тест контейнера (`bin/console ... --help` для всех команд).

## 5. Definition of Done (Критерии приёмки)

- [ ] Принято архитектурное решение, зафиксировано в ADR.
- [ ] Конвенция и код согласованы (либо код → конвенция, либо конвенция → код).
- [ ] Все 5 модулей следуют единому стандарту (без исключений).
- [ ] `phpunit`/`psalm`/`deptrac` зелёные.
- [ ] AGENTS.md / `docs/guide/architecture.md` актуализированы под решение.

## 6. Verification (Самопроверка)

```bash
# После реализации — проверить единообразие модулей
for m in $(ls -d src/Module/*/ | xargs -n1 basename); do
  echo "=== $m ==="
  test -f src/Module/$m/${m}Module.php && echo "Module.php: OK" || echo "Module.php: N/A (если Путь B)"
  test -f src/Module/$m/Resource/config/services.yaml && echo "Resource/config: OK" || echo "Resource/config: N/A"
done

vendor/bin/phpunit
vendor/bin/psalm
make deptrac
```

## 7. Risks and Dependencies (Риски и зависимости)

- **Риск регрессии DI:** перемещение конфигурации между `config/services.yaml` и модульными `Resource/config/services.yaml` может сломать автосвязывание. Mitigation: пилот на одном модуле + полный прогон тестов.
- **Нет Symfony Kernel:** проект собирает контейнер вручную в `bin/console`/`bin/task-orchestrator` через `TaskOrchestratorExtension`. Путь A (ModuleInterface + modules.php) требует реализации механизма загрузки модулей — нетривиально. Это может склонить к Пути B.
- **Зависимость:** задача может выполняться независимо от PR #275 (правит существующие модули + конвенцию; GitIdentity подстроится).

## 8. Sources (Источники)

- Конвенция: `docs/conventions/modules/configuration.md`, `docs/conventions/configuration/configuration.md`.
- Реальность: `config/services.yaml`, `src/DependencyInjection/TaskOrchestratorExtension.php`.
- Ретро: `docs/agents/team-retro/2026-06-20_09-25-bot-account-agent-identity.md` (урок «конвенции первичны над примерами из кода»).
- AGENTS.md: правило «Конвенции первичны» (раздел «Терминология»).

## 9. Comments (Комментарии)

Корень проблемы — не в модулях, а в **рассинхроне конвенции и кода**. Любой новый разработчик/агент, читая конвенцию, сделает `Resource/config/services.yaml` (как требует док), а потом обнаружит, что весь проект устроен иначе. Это ловушка. Задача — закрыть её системно, а не точечно.

Связано с уроком в AGENTS.md: «Конвенции первичны над примерами из кода» (введено в этом же PR #275).

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-21 | Тимлид Алекс | Создание задачи. Расхождение обнаружено в PR #275 (GitIdentity): тимлид ошибочно взял за эталон существующие модули вместо конвенции. Задача создана в этом PR по решению владельца (конвенции первичны, расхождения фиксируются задачей там, где обнаружены). |
| 2026-06-24 | Тимлид Алекс | Взята в работу. Сверка с кодом: инфраструктура модульной системы (`src/Component/ModuleSystem/`, `config/modules.php`, `ModuleKernelTrait`) уже создана в PR #275 и доказана модулем GitIdentity — рисковый комментарий «Путь A нетривиален» устарел. Подтверждено направление: **Path A** (привести код 4 модулей к конвенции) + точная правка `configuration.md` под реальный CLI-проект + ADR. Один PR без декомпозиции. План делегирования: Архитектор Гэндальф (ADR) → Бэкендер Левша (миграция модулей) → Тех. писатель Гермиона (правка конвенции) → self-review → Ревьювер Бэка Пуаро (code review). |
