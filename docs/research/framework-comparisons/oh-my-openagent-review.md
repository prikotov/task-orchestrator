# Архитектурное ревью: Oh My OpenAgent (OmO) — паттерны оркестрации

**Роль:** Архитектор Гэндальф
**Дата:** 2026-05-11
**Объект:** Oh My OpenAgent v4.0.0 — research-отчёт [`oh-my-openagent-comparison.md`](./oh-my-openagent-comparison.md)
**Фокус:** Паттерны оркестрации, применимость к архитектуре task-orchestrator

---

## Сводка

Проведён анализ шести ключевых архитектурных паттернов Oh My OpenAgent (OmO) с точки зрения их применимости к task-orchestrator. Исследование опирается на нашу трёхмодульную DDD-архитектуру (AgentRunner / ChainDefinition / ChainExecution / DynamicLoop), слоистую модель Domain → Application → Infrastructure/Integration и существующую систему ролей и цепочек.

**Итог:** 2 паттерна — **заимствовать** (IntentGate, Skill-Embedded Tools), 3 — **наблюдать** (Discipline Agents, Team Mode, Category System), 1 — **антипаттерн** (Dual-Prompt). Лицензия SUL-1.0 подтверждена как ограничивающая, но допустимая для внутреннего использования.

---

## 1. Discipline Agents (Sisyphus/Hephaestus/Prometheus)

### Описание паттерна

OmO реализует 11 фиксированных Discipline Agents — специализированных агентов, каждый с собственным системным промптом, моделью, permissions и fallback-цепочкой. Агенты не являются просто ролями — это **runtime-сущности**, жёстко привязанные к конкретным моделям (Sisyphus → Claude Opus, Hephaestus → GPT-5.5, Oracle → GPT-5.5 high).

### Параллель с нашей архитектурой

Наши 10 ролей (`team_lead_alex`, `system_analyst_sherlock` и др.) — это **декларативные markdown-файлы** без runtime-привязки к моделям. Роль подключается к шагу цепочки через `role:` в YAML-конфигурации. Разница фундаментальна:

| Аспект | OmO Discipline Agents | Наши роли |
|--------|----------------------|-----------|
| Привязка к модели | Жёсткая (agent → model) | Отсутствует (роль → runner через конфиг) |
| Permissions | Per-agent | Не реализовано |
| Fallback-цепочка | Per-agent | Через `RetryingAgentRunner` (per-runner) |
| Количество | 11 фиксированных | 10 + произвольные |

### Анализ

**Положительное:** Идея per-agent permissions заслуживает внимания. Сейчас наш `AgentRunnerInterface::run()` не получает информацию о допустимых операциях для конкретной роли — это потенциальная дыра в безопасности.

**Отрицательное:** OmO делает **жёсткую привязку agent → model**. Это нарушает наш принцип: модель — это Infrastructure-деталь, оркестратор о ней не знает. У нас `AgentRunnerRegistryService` выбирает runner по имени, а модель — деталь реализации runner'а. Это правильный подход.

**Риск:** Если мы начнём привязывать роли к моделям на уровне Domain — это загрязнит Domain-слой знанием о Infrastructure.

### Рекомендация: 🔍 Наблюдать

Заимствовать **идею per-role permissions** как отдельную VO в Domain (например, `RolePermissionsVo` в `ChainExecution.Domain`). Не заимствовать жёсткую привязку agent → model — это антипаттерн для нашей слоистой архитектуры.

---

## 2. Dual-Prompt (Claude/GPT auto-switch)

### Описание паттерна

Агенты Prometheus и Atlas автоматически переключают системный промпт в зависимости от модели: ~1,100 строк Claude-optimized для Claude/GLM/Kimi и ~300 строк XML-tagged для GPT. Детекция через `isGptModel()` — runtime-проверка имени модели.

### Анализ

Этот паттерн **смешивает Domain и Infrastructure** в одной точке принятия решения. Промпт — это Domain-концепт (как агент должен себя вести). Модель — Infrastructure-концепт (через какой провайдер идёт запрос). Решение о том, какой промпт использовать, принимается на основе Infrastructure-детали (имя модели).

В нашей архитектуре:
- Промпт формируется в `RolePromptBuilder` (ChainExecution.Infrastructure)
- Модель определяется в конкретном `AgentRunnerInterface` (AgentRunner.Infrastructure)
- Оркестратор не знает ни о том, ни о другом

Если мы введём dual-prompt, нам придётся либо:
1. Передавать имя модели из Infrastructure обратно в Infrastructure (RolePromptBuilder) — дублирование знания о модели в двух местах
2. Параметризовать промпт через Domain — загрязнение Domain знанием о провайдерах

Оба варианта нарушают слоистую архитектуру.

### Рекомендация: ❌ Антипаттерн

Не заимствовать. Вместо dual-prompt — **универсальный промпт**, работающий с любой моделью. Если конкретная модель требует особого формата — это ответственность runner'а (Infrastructure), а не роли (Domain). Промпт-формат — деталь Infrastructure.

Альтернатива: при необходимости, конфигурация цепочки может указывать runner (который уже знает о модели), а runner при необходимости адаптирует промпт. Это не нарушает слои.

---

## 3. Team Mode (lead + 8 members, shared mailbox)

### Описание паттерна

Team Mode — параллельная мультиагентная координация: Lead Agent + до 8 member-агентов с общим mailbox и shared task list. 12 инструментов `team_*` для создания команд, отправки сообщений, управления задачами. Визуализация через tmux.

### Параллель с нашей архитектурой

| Аспект OmO | Наш аналог |
|------------|-----------|
| Lead Agent | Тимлид Алекс (роль) + `OrchestrateChainCommandHandler` (handler) |
| Members | Шаги цепочки (agent-шаги) |
| Shared Mailbox | `StepContextVo.previousContext` (передача контекста между шагами) |
| Shared Task List | Отсутствует (у нас нет межшаговой координации в runtime) |
| Parallel Execution | Отсутствует (у нас линейное выполнение static-цепочек) |

### Анализ

**Ценное:** Идея shared mailbox + shared task list для координации параллельных агентов — это фактически паттерн **Blackboard Architecture**, хорошо известный в AI (Hearsay-II, 1973). В контексте DynamicLoop (где фасилитатор решает, кому дать слово) это могло бы заменить простой round-robin на более интеллектуальную координацию.

**Проблемное:**
1. **Сложность** — Team Mode требует state-машины для координации (create → active → shutting_down → done). Это существенное усложнение.
2. **Токены** — 8 параллельных агентов = 8× потребление токенов. Наш Budget-механизм (`ExecutionBudgetVo`, `CheckStaticBudgetService`) не готов к параллельным тратам.
3. **Audit** — наш `JsonlAuditLogger` рассчитан на линейные шаги. Параллельное выполнение требует принципиально другого audit-формата.
4. **Потокобезопасность** — PHP/Symfony не рассчитаны на параллельные процессы с shared state в рамках одного запроса. Это потребует внешней координации (Redis, IPC).

### Рекомендация: 🔍 Наблюдать

Не заимствовать сейчас. Архитектурная цена слишком высока для текущей зрелости проекта. Однако:
- **DynamicLoop** — естественное место для экспериментов с координацией (фасилитатор уже решает, кто говорит)
- **Shared Task List** — полезная идея для динамических цепочек, но через JSONL-based state (не через shared memory)
- Добавить в roadmap как **research-задачу** для пост-MVP

---

## 4. Category System (deep/quick/visual-engineering)

### Описание паттерна

Вместо указания конкретной модели — указывается категория задачи (`visual-engineering`, `ultrabrain`, `deep`, `quick`, `writing`), система автоматически маршрутизирует к оптимальной модели. 8 встроенных категорий с кастомизацией через конфигурацию.

### Параллель с ChainStepTypeEnum

Наш `ChainStepTypeEnum` классифицирует **тип шага** (agent / quality_gate / tool), а OmO Category System классифицирует **тип задачи** (deep / quick / visual). Это ортогональные оси:

```
ChainStepTypeEnum.agent + Category.deep  →  «запустить агента для сложной задачи»
ChainStepTypeEnum.agent + Category.quick →  «запустить агента для быстрой задачи»
ChainStepTypeEnum.quality_gate           →  «выполнить проверку» (категория не нужна)
```

### Анализ

Category System — это фактически **routing layer** между намерением пользователя и конкретным runner'ом. В нашей архитектуре:

```
Сейчас:   YAML role → ResolveChainRunnerService → AgentRunner
С Category: YAML role + category → ResolveChainRunnerService → AgentRunner
```

Если ввести category, она должна жить в `ChainDefinition.Domain` как расширение `ChainStepVo` (не заменять `ChainStepTypeEnum`, а дополнять). Runner-резолюция в `ResolveChainRunnerService` могла бы учитывать категорию при выборе runner'а.

**Проблема:** Сейчас у нас один runner на шаг (через `step.runner`). Category вводит **fallback-цепочку runners** для одного шага. Это существенное изменение в модели выполнения.

### Рекомендация: 🔍 Наблюдать

Category System — перспективная идея, но требует **ADR** и осторожного проектирования. Если реализовывать:
- `ChainStepCategoryEnum` (новый enum в `ChainDefinition.Domain`) — `deep`, `quick`, `default`
- Расширение `ChainStepVo` полем `category`
- `ResolveChainRunnerService` учитывает category при резолюции
- Fallback-цепочка runners — через существующий `RetryingAgentRunner` (не нужно новое понятие)

Пока — наблюдать, зафиксировать как roadmap-элемент.

---

## 5. IntentGate — классификация намерения

### Описание паттерна

IntentGate — классификация истинного намерения пользователя **до начала работы**. Определяет тип запроса (research, implementation, investigation, fix) и маршрутизирует к оптимальной стратегии выполнения.

### Параллель с RunStaticChainService

Наш `RunStaticChainService.execute()` получает задачу как строку `$task` и выполняет все шаги линейно. IntentGate добавил бы **препроцессинг**: анализ задачи → выбор стратегии → выполнение.

Это ближе к нашему **Conditional-цепочкам** (`ConditionalExecutionStrategy`), где шаги могут иметь `when`-условия. Но Conditional работает на уровне **шагов**, а IntentGate — на уровне **цепочки** (до начала выполнения).

### Анализ

IntentGate — это фактически **pre-orchestration routing**. В нашей архитектуре это естественно ложится на уровень Presentation или Application:

```
Presentation (Console Command)
  → IntentClassifierService (Application)
    → выбрать цепочку: research-chain / implementation-chain / fix-chain
    → OrchestrateChainCommandHandler.execute(selectedChain)
```

Это **не требует** изменений в Domain или Infrastructure — только новый Application-сервис + расширение Presentation.

**Вызов:** IntentGate в OmO использует LLM для классификации. Это значит «запуск агента для выбора стратегии». В нашей модели это:
- Лёгкий LLM-вызов через `AgentRunnerInterface` (quick model)
- Или эвристика на основе ключевых слов (без LLM)

### Рекомендация: ✅ Заимствовать

IntentGate — ценный паттерн, который:
1. Не нарушает слоистую архитектуру (чистый Application-слой)
2. Повышает качество оркестрации (правильная цепочка = правильный результат)
3. Легко реализуем: новый `IntentClassifierService` в `ChainExecution.Application`

Модель реализации:
- Этап 1: эвристический классификатор (ключевые слова) — без LLM
- Этап 2: LLM-based классификатор (quick model) — через `AgentRunnerInterface`

---

## 6. Skill-Embedded MCPs (скиллы с собственными MCP-серверами)

### Описание паттерна

SKILL.md может объявлять MCP-серверы в YAML frontmatter. Серверы запускаются по требованию при активации скилла, изолированы per-session. Это устраняет «context bloat» от глобальных MCP — инструменты доступны только когда скилл активен.

### Параллель с нашей системой скиллов

Наши скиллы (`docs/agents/skills/*/SKILL.md`) — это чисто информационные ресурсы: промпт-инструкции, которые загружаются в контекст агента. Они не несут инструментов.

В OmO скилл = промпт + инструменты (MCP-серверы). Это важное расширение модели.

### Анализ

В контексте **task-orchestrator** (не сабагента), Skill-Embedded Tools могут означать:

**Аналог в нашей модели:** Скилл объявляет **step-конфигурацию** — какие `tool`-шаги нужно выполнить до/после agent-шага.

Пример (гипотетический YAML):
```yaml
skills:
  run-subagent:
    tools:
      - name: validate-output
        type: quality_gate
        command: "vendor/bin/phpunit {{output_file}}"
```

Это не требует MCP — достаточно расширить модель скиллов в `ChainDefinition.Domain`.

**Однако:** Сейчас наши скиллы — это не Domain-сущности, а файлы, загружаемые агентом через tool `skill`. Мы не управляем ими из оркестратора. Внедрение Skill-Embedded Tools потребует:
1. Парсинг SKILL.md YAML frontmatter в `ChainDefinition.Infrastructure`
2. Новые VO в `ChainDefinition.Domain`: `SkillToolVo`
3. Интеграция в `ExecuteStaticChainService` — injекция tool-шагов из скилла

### Рекомендация: ✅ Заимствовать (ограниченно)

Заимствовать **идею** скиллов с декларативными зависимостями, но без MCP:
1. Расширить SKILL.md YAML frontmatter полем `requires` — список необходимых условий (tools, env vars)
2. Валидация `requires` на этапе загрузки цепочки (`ValidateChainConfigQueryHandler`)
3. Не реализовывать запуск MCP-серверов — это выходит за рамки нашей модели

---

## 7. Лицензия SUL-1.0 — ограничения

### Описание

SUL-1.0 (Sustainable Use License) — не open-source в классическом смысле. Ограничивает коммерческое распространение, но разрешает внутреннее бизнес-использование.

### Анализ

Для **внутренней разработки** task-orchestrator — допустимо. Мы можем:
- ✅ Использовать OmO как plugin для OpenCode при разработке
- ✅ Изучать исходный код для заимствования паттернов
- ✅ Создавать derivative works для внутреннего использования

Для **коммерческого продукта** — недопустимо:
- ❌ Включать OmO код в task-orchestrator
- ❌ Распространять OmO как зависимость
- ❌ Продавать услуги на основе OmO

### Рекомендация: ⚠️ Допустимо для внутреннего использования

Заимствовать **архитектурные паттерны** (идеи), а не код. Паттерны не лицензируются. Task-orchestrator остаётся полностью независимым от OmO codebase.

---

## Итоговая таблица рекомендаций

| # | Паттерн | Рекомендация | Приоритет | Сложность реализации |
|---|---------|-------------|-----------|---------------------|
| 1 | Discipline Agents | 🔍 Наблюдать | — | — |
| 2 | Dual-Prompt (Claude/GPT) | ❌ Антипаттерн | — | — |
| 3 | Team Mode | 🔍 Наблюдать | — | — |
| 4 | Category System | 🔍 Наблюдать | — | — |
| 5 | IntentGate | ✅ **Заимствовать** | Высокий | Средняя |
| 6 | Skill-Embedded Tools | ✅ **Заимствовать** (ограниченно) | Средний | Низкая |
| 7 | Лицензия SUL-1.0 | ⚠️ Внутреннее использование | — | — |

---

## Рекомендация для тимлида

### Что делать сейчас

1. **IntentGate** — реализовать в ближайшем спринте. Это «quick win» с высоким ROI: правильная маршрутизация задачи → правильная цепочка = меньше токенов, выше качество. Реализация на уровне Application-сервиса, не требует изменений в Domain.

2. **Skill-Embedded Tools (requires)** — добавить в следующий спринт. Расширение SKILL.md YAML frontmatter полем `requires` + валидация при загрузке цепочки. Низкий риск, высокая ценность для безопасности выполнения.

### Что отложить

3. **Category System** — требует ADR и расширения модели `ChainStepVo`. Задокументировать как roadmap-элемент, реализовать после стабилизации DynamicLoop.

4. **Team Mode** — слишком рано. Требует принципиально другой модели выполнения (параллелизм), нового audit-формата, budget-координации. Добавить в roadmap как research-задачу на пост-MVP.

5. **Per-role Permissions** — вынести из Discipline Agents как отдельную идею. `RolePermissionsVo` в `ChainExecution.Domain` может ограничивать допустимые операции для конкретной роли (read-only для Oracle, full-access для Sisyphus).

### Чего не делать

6. **Dual-Prompt** — нарушение слоистой архитектуры. Промпт-формат — ответственность Infrastructure, не Domain.

---

## Конкретные задачи для todo/

### Задача 1: IntentGate — Pre-orchestration routing

**Формулировка:** Реализовать `IntentClassifierService` в `ChainExecution.Application` — классификация намерения задачи (research / implementation / fix / investigation) перед выбором цепочки. Этап 1: эвристический классификатор на ключевых словах. Этап 2: LLM-based через `AgentRunnerInterface` с quick model.

**Архитектурные границы:**
- `IntentClassifierService` — Application-слой, зависит от `ChainDefinition.Application` (загрузка списка цепочек)
- Результат классификации — `IntentVo` (новый VO в `ChainExecution.Domain`)
- Интеграция: `OrchestrateChainCommandHandler` или Presentation-слой вызывает классификатор до dispatch

### Задача 2: Skill-Embedded Requires

**Формулировка:** Расширить модель скиллов — добавить поддержку `requires` в YAML frontmatter SKILL.md (список необходимых инструментов, env-переменных). Реализовать валидацию `requires` в `ValidateChainConfigQueryHandler` — предупреждать при нарушении требований.

**Архитектурные границы:**
- Расширение парсинга SKILL.md в `ChainDefinition.Infrastructure`
- `SkillRequiresVo` — новый VO в `ChainDefinition.Domain`
- Валидация в существующем `ValidateChainConfigQueryHandler`

### Задача 3 (research): Category-based Runner Resolution

**Формулировка:** Исследовать расширение `ChainStepVo` полем `category` (deep / quick / default) и адаптацию `ResolveChainRunnerService` для учёта категории при выборе runner'а. Подготовить ADR с анализом влияния на existing chains.

### Задача 4 (research): Team Mode Architecture для DynamicLoop

**Формулировка:** Исследовать применимость паттерна Team Mode (Lead + Members, shared task list) к DynamicLoop-модулю. Проанализировать: модель координации, audit-формат для параллельных шагов, budget-координацию, state-менеджмент. Результат — RFC с архитектурным предложением.

### Задача 5 (research): Per-role Permissions

**Формулировка:** Исследовать добавление `RolePermissionsVo` в `ChainExecution.Domain` — ограничение допустимых операций для конкретной роли (read-only, file-write, shell-exec). Определить, как permissions влияют на `AgentRunRequestVo` и `ResolveStepRunnerService`.

---

*Ревью подготовлено Архитектором Гэндальфом. Паттерны заимствуются на уровне идей, а не кода — task-orchestrator остаётся независимым от OmO codebase.*
