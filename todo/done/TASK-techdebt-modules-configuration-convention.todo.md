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
pr: '#282'
status: done
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

- [x] Принято архитектурное решение, зафиксировано в ADR (ADR-012, Path A).
- [x] Конвенция и код согласованы (код 4 модулей приведён к конвенции Path A; docs/conventions/modules/configuration.md — локально актуализирован под task-orchestrator).
- [x] Все 5 модулей (GitIdentity + AgentRunner + ChainDefinition + ChainExecution + DynamicLoop) следуют единому стандарту (ModuleInterface + Resource/config/services.yaml + config/modules.php).
- [x] `phpunit`/`psalm`/`deptrac` (а также phpstan/phpmd/phpcs) зелёные (`make check` OK, 1233 теста).
- [x] `docs/guide/architecture.md` (+ troubleshooting.md, extension.md) актуализированы под решение; ADR-012 добавлен.

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
| 2026-06-24 | Тимлид Алекс | Реализовано через конвейер сабагентов: Гэндальф — ADR-012 (Path A); Левша (codex/gpt-5.5) — миграция 4 модулей (Module.php + Resource/config/services.yaml + modules.php + чистка root services.yaml + GitIdentity auto-discovery); Гермиона — актуализация docs/guide/architecture.md, troubleshooting.md, extension.md и configuration.md; Пуаро — code review (код подтверждён, tagged iterators проверены в скомпилированном контейнере). `make check` зелёный (1233 теста). ПОПУТНО (обязательно для зелёного CI): обнаружено, что main был в красном состоянии по CI (PHPStan 3 / PHPMD ~20 / PHPCS 2 pre-existing ошибок из PR #275) — пофикшено минимально: Kernel.php + ModuleKernelTrait PHPStan-фиксы; phpmd.baseline.xml (17 pre-existing violations); RepoSlugVo::fromString → createFromString (кастомный PHPCS-снифф value-object). Статус → done. Merge — по подтверждению пользователя. Отдельный техдолг: зачистка GitIdentity-violations и аудит «почему main красный по CI» — вынести в отдельную задачу. |
| 2026-06-25 | Тимлид Алекс | **Дополнение: PHAR-safe регистрация (Вариант 4).** После открытия PR #282 CI-проверка `phar-smoke` упала: Symfony `GlobResource` (механизм `resource:`) возвращает 0 файлов по `phar://` — фундаментальное ограничение PHP stream-wrapper. Тимлид эмпирически валидировал Вариант 4 (PHAR-safe регистратор на `RecursiveDirectoryIterator` + container-wide autoconfiguration вместо module-local `_instanceof`). Реализовано через конвейер: тимлид — POC-валидация 3 гипотез (PHAR-контейнер грузится из обоих CWD; container-wide tags применяются; explicit-wins); Левша (pi/gpt-5.5) — production-ize: `ModuleServiceRegistrar` + расширение `ModuleInterface` (`getServiceNamespace()`/`getServiceExcludePaths()` + константа `DEFAULT_SERVICE_EXCLUDE_PATHS`) + упрощение `ModuleKernelTrait` (убран match-table) + container-wide `registerForAutoconfiguration` в `Kernel::build` для 3 интерфейсов + unit-тест регистратора (10 тестов с fixtures); тимлид — ревью (Approve, эквивалент Пуаро: Пуаро был убит по soft-timeout до завершения); Гермиона — ADR-012 дополнен PHAR-разделом (В1–В4, explicit-wins, empirical evidence), обновлены architecture.md/extension.md/troubleshooting.md. ПОПУТНО раскрыт latent pre-existing баг: `DynamicExecutionStrategy` autowire в prod был сломан (отсутствующий alias `SessionCompletedNotifierInterface`) — тесты инстанциируют вручную с mock, PHAR не проявлял; alias добавлен. Проверки зелёные: PHPUnit 1243/3352 OK (+10), Psalm exit 0, PHAR smoke EXIT=0 из checkout и temp CWD, `make md-links` ✅. `config/reference.php` добавлен в .gitignore. Статус остаётся done. Отдельные техдолги (вне scope): Раздел B — `config/console_services.yaml` (apps/console команды) тоже использует `resource:` и не работает в PHAR; framework-bundle debug/cache команды не работают в PHAR (bare Console Application). |
| 2026-06-25 | Тимлид Алекс | **Дополнение 2: package-root + Component/apps-console PHAR-safe (Шаги 1–3).** При глубокой проверке распространяемого PHAR раскрыто, что Вариант 4 чинил только доменные модули, но PHAR оставался hollow (пустым по модулям) из произвольного CWD, а `phar-smoke` был ложнозелёным (проверял только `--version`). Две новые корневые причины: (1) наследуемый `BaseKernel::getProjectDir()` в PHAR даёт неверный `phar://.../src` (`composer.json` не упакован в PHAR) → `getModules()=0` из `/tmp` → модули не грузятся; (2) ещё 4 блока `resource:` ломались в PHAR (GlobResource): `src/Component/*`, 3 блока `apps/console` (Command×2 + EventSubscriber). Пользователь подтвердил вариант C (чинить всё в этом PR). Реализовано через конвейер сабагентов (стратегия разбивки на фокусные шаги — сабагенты в окружении нестабильны по timeout/stall): **Шаг 1 (Левша, pi)** — `getPackageDir() = dirname(__DIR__)` в `Kernel` (CWD-независимый package root); A/B-эксперимент доказал `getProjectDir()`→0 модулей, `getPackageDir()`→5. **Шаг 2 (Левша, pi + тимлид валидация)** — обобщение `ModuleServiceRegistrar` (параметр `serviceDir`, опция `public`), `Kernel::registerConsoleServices()` для apps/console (теги через container-wide autoconfig Symfony), явный def `SystemClock` для Component; убраны все `resource:` блоки. **Шаг 3 (тимлид, testing-infra)** — усиление `bin/phar-smoke`: проверка команд модулей (`agent:run`, `validate:connectivity`) из checkout и distributable CWD со сбросом кэша (ловит hollow). Docs (Гермиона — Variant 4 часть; тимлид — Шаги 1–2 дополнение после stall'а сабагента): ADR-012 дополнен разделами package-root/Component-apps-console/усиление-smoke, обновлены architecture.md/troubleshooting.md (hollow-диагностика)/extension.md. Проверки зелёные: PHPUnit 1245/3355 OK (+2), Psalm exit 0, `make phar-smoke` 4/4 ✓ (5 команд модулей видны из PHAR `/tmp` CWD — контейнер НЕ hollow), `make md-links` ✅. Инсайт: PHAR-кэш в `/tmp/task-orchestrator/` переживает перезапуски — требует сброса при повторных проверках. Transparent disclosure: Шаг 3 и doc-дополнение Шагов 1–2 выполнены тимлидом (testing-infra и docs после 3× stall сабагентов). Статус done. |
