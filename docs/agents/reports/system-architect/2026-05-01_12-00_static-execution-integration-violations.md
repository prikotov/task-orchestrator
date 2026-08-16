# Архитектурный анализ: Integration-слой модуля StaticExecution

**Роль:** Архитектор Гэндальф
**Дата:** 2026-05-01
**Объект:** PR #117, ветка `task/refactor-static-execution-split` — модуль `StaticExecution`
**Задача:** Анализ нарушений конвенций в Integration-слое нового модуля StaticExecution

---

## 1. Классификация запроса

🧩 сложность запроса: **6 из 10** — нужно сопоставить конвенции с кодом, учесть Deptrac-правила и эталонный паттерн.

🗂️ уровень контекста: **9 из 10** — предоставлен полный список файлов, чёткое описание проблем и эталонный пример.

🛡️️ риск ошибки: **3 из 10** — анализ на основе документальных свидетельств, все файлы прочитаны напрямую.

---

## 2. Нарушенные конвенции (с цитатами)

### 2.1. Нарушение: каталог `Port/` и суффикс `PortInterface`

**Конвенция:** [`docs/conventions/core-patterns/service.md`](../../../../docs/conventions/core-patterns/service.md) → Integration Service:

> **Расположение**
> - Интерфейс: `Common\Module\{ModuleName}\Domain\Service\{Context?}\{ServiceName}ServiceInterface`
> - Реализация: `Common\Module\{ModuleName}\Integration\Service\{Context?}\{ServiceName}Service`

> **Правила именования**: `{ServiceName}` = `{Action}` + `{Target}`
> | Класс     | `{Action}{Target}Service`          |
> | Интерфейс | `{Action}{Target}ServiceInterface`  |

**Фактически:**
- `Domain/Service/Port/AgentRunnerPortInterface.php` — каталог `Port/` не описан в конвенциях, суффикс `PortInterface` не соответствует `{Action}{Target}ServiceInterface`.
- `Domain/Service/Port/PromptFormatterPortInterface.php` — то же нарушение.

**Эталон в проекте:** `Orchestrator\Domain\Service\Integration\RunAgentServiceInterface` — интерфейс лежит в `Domain\Service\Integration\`, называется `{Run}{Agent}ServiceInterface`.

### 2.2. Нарушение: реализация называется `*Adapter`, а не `*Service`

**Конвенция:** [`docs/conventions/core-patterns/service.md`](../../../../docs/conventions/core-patterns/service.md):

> | Класс     | `{Action}{Target}Service`          | ChangeStatusService               |

**Фактически:**
- `Integration/Service/AgentRunnerAdapter.php` — суффикс `Adapter` не соответствует конвенции.
- `Integration/Service/PromptFormatterAdapter.php` — то же нарушение.

**Эталон в проекте:** `Orchestrator\Integration\Service\AgentRunner\RunAgentService` — реализация называется `RunAgentService`, размещена в подкаталоге `AgentRunner/`.

### 2.3. Утечка зависимости через сигнатуры Port-интерфейса

**Файл:** `src/Module/StaticExecution/Domain/Service/Port/AgentRunnerPortInterface.php`

```php
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRetryPolicyVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRunRequestVo;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\ValueObject\ChainRunResultVo;

interface AgentRunnerPortInterface
{
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo;
}
```

Port-интерфейс заявлен как «ACL-интерфейс — изолирует StaticExecution Domain от Orchestrator», но **сигнатура полностью повторяет сигнатуру `Orchestrator\RunAgentServiceInterface`**, включая все Orchestrator VO. Изоляция мнимая — интерфейс жёстко привязан к типам Orchestrator.

Сравнение с эталоном: `Orchestrator\RunAgentServiceInterface` тоже использует Orchestrator VO, но это **свой** модуль — это корректно. Для StaticExecution тот же подход корректен при условии использования Integration Service конвенции (а не Port).

### 2.4. Отсутствие Mapper между модулями (при необходимости)

**Конвенция (эталон):** Orchestrator → AgentRunner Integration использует `AgentDtoMapper`:

```php
// Orchestrator\Integration\Service\AgentRunner\AgentDtoMapper.php
final readonly class AgentDtoMapper
{
    public function mapToRunAgentCommand(ChainRunRequestVo $vo, ...): RunAgentCommand { ... }
    public function mapFromRunAgentResultDto(RunAgentResultDto $dto): ChainRunResultVo { ... }
}
```

Mapper преобразует VO между разными контекстами (Orchestrator VO ↔ AgentRunner Application DTO).

**Фактически:** `AgentRunnerAdapter` просто проксирует вызов без маппинга:

```php
public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
{
    return $this->inner->run($request, $retryPolicy);
}
```

Это **проходной адаптер** (pass-through adapter) — не добавляет значения. Если оба модуля используют одни и те же Orchestrator VO (что разрешено Deptrac), то отдельный Mapper не нужен. Но тогда и отдельный интерфейс+адаптер не нужны.

---

## 3. Deptrac-анализ: разрешённые зависимости

Из [`depfile.yaml`](../../../depfile.yaml):

```yaml
StaticExecutionDomain:
  - StaticExecutionDomain
  - StaticExecutionDomainVo
  - OrchestratorDomainVo    # ✅ Разрешено
  - OrchestratorDomainEnum  # ✅ Разрешено
  - OrchestratorDomainDto   # ✅ Разрешено
  - DomainEnum              # ✅ Разрешено
  - DomainDto               # ✅ Разрешено
  - OrchestratorDomain      # ✅ Разрешено (включает Orchestrator Domain Services!)
```

**Ключевой вывод:** Deptrac **явно разрешает** `StaticExecutionDomain → OrchestratorDomain`. Это означает, что StaticExecution Domain **имеет право** напрямую зависеть от Orchestrator Domain Service-интерфейсов.

Комментарий в адаптере:
> «Адаптер необходим для Deptrac: StaticExecution Domain не зависит от Orchestrator Domain Service, только от собственного Port.»

**Это утверждение ошибочно.** Deptrac-правила уже разрешают эту зависимость. Более того, она уже используется:

```php
// RunStaticChainService.php (StaticExecution Domain)
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\Audit\AuditLoggerInterface;
```

StaticExecution Domain **уже** напрямую зависит от `AuditLoggerInterface` из Orchestrator Domain. Port-интерфейсы не обеспечивают изоляции, которую они заявляют.

---

## 4. Сравнение с эталонным паттерном Orchestrator → AgentRunner

| Аспект | Orchestrator → AgentRunner (эталон) | StaticExecution → Orchestrator (фактически) |
|--------|--------------------------------------|-----------------------------------------------|
| Интерфейс | `RunAgentServiceInterface` в `Domain\Service\Integration\` | `AgentRunnerPortInterface` в `Domain\Service\Port\` |
| Реализация | `RunAgentService` в `Integration\Service\AgentRunner\` | `AgentRunnerAdapter` в `Integration\Service\` |
| Mapper | `AgentDtoMapper` — маппит VO ↔ DTO | Нет маппера, pass-through |
| VO изоляция | Orchestrator VO используются в интерфейсе, Mapper конвертирует в AgentRunner DTO | Port использует Orchestrator VO напрямую — никакой изоляции |
| Deptrac | `OrchestratorIntegration → AgentRunnerApplication, AgentRunnerDomain` | Deptrac уже разрешает `StaticExecutionDomain → OrchestratorDomain` |

---

## 5. Нужно ли создавать свои VO в StaticExecution Domain?

**Нет, не нужно.** Обоснование:

1. **Deptrac уже разрешает** `StaticExecutionDomain → OrchestratorDomainVo`. Это осознанное архитектурное решение, закреплённое в конфигурации.
2. **Существующие интерфейсы StaticExecution** (`ResolveChainRunnerServiceInterface`, `QualityGateRunnerInterface`) уже используют Orchestrator VO напрямую — это установленный в проекте паттерн.
3. **Дублирование VO** создало бы ненужную сложность: одинаковые структуры в двух модулях, которые нужно синхронизировать.
4. **Orchestrator VO** (`ChainRunRequestVo`, `ChainRunResultVo`, `ChainStepVo` и т.д.) — это не «утечка», а **разделяемый доменный контракт**, аналогично тому, как Orchestrator использует свои VO для общения с AgentRunner через Mapper.

В проекте уже есть прецедент: Orchestrator VO не дублируются между Orchestrator и AgentRunner. Маппинг происходит на уровне Integration-сервиса через Mapper, но VO остаются общими. Для StaticExecution → Orchestrator ситуация ещё проще: оба модуля **уже** работают с одними и теми же Orchestrator VO.

---

## 6. План исправления

### 6.1. AgentRunner Integration Service

**Удалить:**
- `src/Module/StaticExecution/Domain/Service/Port/AgentRunnerPortInterface.php`
- `src/Module/StaticExecution/Integration/Service/AgentRunnerAdapter.php`

**Создать:**
- `src/Module/StaticExecution/Domain/Service/Integration/RunAgentServiceInterface.php`
  - Namespace: `TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Integration`
  - Сигнатура идентична текущему Port: `run(ChainRunRequestVo, ?ChainRetryPolicyVo): ChainRunResultVo`
  - Импортирует Orchestrator VO (разрешено Deptrac)

- `src/Module/StaticExecution/Integration/Service/AgentRunner/RunAgentService.php`
  - Namespace: `TaskOrchestrator\Common\Module\StaticExecution\Integration\Service\AgentRunner`
  - Делегирует в `Orchestrator\Domain\Service\Integration\RunAgentServiceInterface`
  - Mapper **не нужен**: оба модуля используют одни и те же Orchestrator VO
  - Реализация идентична текущему `AgentRunnerAdapter` (pass-through делегация)

### 6.2. PromptFormatter Integration Service

**Удалить:**
- `src/Module/StaticExecution/Domain/Service/Port/PromptFormatterPortInterface.php`
- `src/Module/StaticExecution/Integration/Service/PromptFormatterAdapter.php`

**Создать:**
- `src/Module/StaticExecution/Domain/Service/Integration/FormatPromptServiceInterface.php`
  - Namespace: `TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Integration`
  - Методы: `buildStaticContext()`, `resolveSlot()` — идентичны текущему Port
  - Не импортирует Orchestrator VO (методы работают только с примитивами и `array`)

- `src/Module/StaticExecution/Integration/Service\Prompt\FormatPromptService.php`
  - Namespace: `TaskOrchestrator\Common\Module\StaticExecution\Integration\Service\Prompt`
  - Делегирует в `Orchestrator\Domain\Service\Chain\Shared\PromptFormatterInterface`
  - Реализация идентична текущему `PromptFormatterAdapter`

### 6.3. Обновить потребителей

**Обновить imports в:**
- `src/Module/StaticExecution/Domain/Service/ExecuteStaticStepService.php`
  - `AgentRunnerPortInterface` → `RunAgentServiceInterface` (из `Domain\Service\Integration`)
  - `PromptFormatterPortInterface` → `FormatPromptServiceInterface` (из `Domain\Service\Integration`)
- `src/Module/StaticExecution/Infrastructure/Service/ResolveChainRunnerService.php`
  - `AgentRunnerPortInterface` → `RunAgentServiceInterface`
  - `PromptFormatterPortInterface` → `FormatPromptServiceInterface`

### 6.4. Обновить `services.yaml`

Заменить алиасы:
```yaml
# Было:
TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Port\AgentRunnerPortInterface: '@TaskOrchestrator\Common\Module\StaticExecution\Integration\Service\AgentRunnerAdapter'
TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Port\PromptFormatterPortInterface: '@TaskOrchestrator\Common\Module\StaticExecution\Integration\Service\PromptFormatterAdapter'

# Станет:
TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Integration\RunAgentServiceInterface: '@TaskOrchestrator\Common\Module\StaticExecution\Integration\Service\AgentRunner\RunAgentService'
TaskOrchestrator\Common\Module\StaticExecution\Domain\Service\Integration\FormatPromptServiceInterface: '@TaskOrchestrator\Common\Module\StaticExecution\Integration\Service\Prompt\FormatPromptService'
```

### 6.5. Deptrac: изменений не требуется

Текущие правила Deptrac уже покрывают корректные пути:
- `StaticExecutionDomain` → `OrchestratorDomain` (разрешено)
- `StaticExecutionIntegration` → `OrchestratorDomain` (разрешено)
- `StaticExecutionDomain` → `StaticExecutionDomain` (покрывает внутренние интерфейсы)

Каталог `Port/` не входит в отдельный Deptrac-слой, поэтому его удаление не влияет на правила.

---

## 7. Итоговая карта изменений

| Действие | Файл |
|----------|------|
| ❌ Удалить | `src/Module/StaticExecution/Domain/Service/Port/AgentRunnerPortInterface.php` |
| ❌ Удалить | `src/Module/StaticExecution/Domain/Service/Port/PromptFormatterPortInterface.php` |
| ❌ Удалить | `src/Module/StaticExecution/Integration/Service/AgentRunnerAdapter.php` |
| ❌ Удалить | `src/Module/StaticExecution/Integration/Service/PromptFormatterAdapter.php` |
| ✅ Создать | `src/Module/StaticExecution/Domain/Service/Integration/RunAgentServiceInterface.php` |
| ✅ Создать | `src/Module/StaticExecution/Domain/Service/Integration/FormatPromptServiceInterface.php` |
| ✅ Создать | `src/Module/StaticExecution/Integration/Service/AgentRunner/RunAgentService.php` |
| ✅ Создать | `src/Module/StaticExecution/Integration/Service/Prompt/FormatPromptService.php` |
| ✏️ Обновить | `src/Module/StaticExecution/Domain/Service/ExecuteStaticStepService.php` (imports) |
| ✏️ Обновить | `src/Module/StaticExecution/Infrastructure/Service/ResolveChainRunnerService.php` (imports) |
| ✏️ Обновить | `config/services.yaml` (aliases) |

---

## 8. Дополнительное замечание: `QualityGateRunnerInterface`

Интерфейс `QualityGateRunnerInterface` в `Domain/Service/` тоже отклоняется от конвенции именования `{Action}{Target}ServiceInterface`. По конвенции он должен называться `RunQualityGateServiceInterface`. Однако это **не блокирующее нарушение** и может быть исправлено отдельной задачей.

---

## 9. Резюме

Port/Adapter — это чужеродный паттерн для данного проекта. Конвенции однозначно определяют Integration Service как способ межмодульного взаимодействия: интерфейс `{Action}{Target}ServiceInterface` в `Domain\Service\{Context}\`, реализация `{Action}{Target}Service` в `Integration\Service\{Context}\`. Deptrac уже разрешает StaticExecution Domain → Orchestrator Domain, и другие сервисы StaticExecution (`ResolveChainRunnerServiceInterface`, `QualityGateRunnerInterface`) уже используют Orchestrator VO напрямую, без Port-обёрток. Port-интерфейсы добавляют слой косвенности без реальной изоляции.
