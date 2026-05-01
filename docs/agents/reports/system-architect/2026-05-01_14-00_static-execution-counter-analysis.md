# Контр-анализ: Integration-слой модуля StaticExecution

**Роль:** Архитектор Локи
**Дата:** 2026-05-01
**Объект:** PR #117, модуль `StaticExecution` — контр-анализ отчёта Архитектора Гэндальфа
**Задача:** Найти слепые зоны и альтернативы в анализе Гэндальфа

---

## 1. Классификация запроса

🧩 сложность запроса: **7 из 10** — нужно не просто сопоставить код с конвенциями, а оспорить архитектурные выводы и найти системные проблемы, которые Гэндальф обошёл.

🗂️ уровень контекста: **9 из 10** — полный код, конвенции, Deptrac, эталон, отчёт Гэндальфа прочитаны.

🛡️️ риск ошибки: **4 из 10** — анализ на основе документальных свидетельств, но интерпретация Deptrac-правил и архитектурных рисков допускает разные мнения.

---

## 2. Резюме позиции Гэндальфа

Гэндальф утверждает:
1. Port/Adapter — «чужеродный паттерн», нарушает конвенции именования.
2. Deptrac уже разрешает `StaticExecutionDomain → OrchestratorDomain`, поэтому Port избыточен.
3. Pass-through адаптеры не добавляют значения.
4. Нужно переименовать в Integration Service (`RunAgentServiceInterface` + `RunAgentService`).
5. Mapper не нужен — VO общие.

Я соглашусь с пунктами 1 и 3 формально, но оспорю глубину анализа по пунктам 2, 4 и 5.

---

## 3. Слепое пятно №1: Deptrac-правило слишком широкое

### Что Гэндальф упустил

Гэндальф использует широкое Deptrac-правило как **оправдание** для удаления Port:

> «Deptrac уже разрешает StaticExecution Domain → Orchestrator Domain, Port избыточен.»

Но правильный вывод — ровно обратный: **Deptrac-правило `StaticExecutionDomain → OrchestratorDomain` слишком широкое и само нуждается в исправлении.**

Текущее правило из `depfile.yaml`:

```yaml
StaticExecutionDomain:
  - StaticExecutionDomain
  - StaticExecutionDomainVo
  - OrchestratorDomainVo
  - OrchestratorDomainEnum
  - OrchestratorDomainDto
  - DomainEnum
  - DomainDto
  - OrchestratorDomain      # ← ВСЁ Orchestrator Domain разрешено
```

`OrchestratorDomain` включает **все** Orchestrator Domain-сервисы, интерфейсы, репозитории и т.д. StaticExecution Domain формально может зависеть от `AuditLoggerInterface`, `PromptFormatterInterface`, `ChainDefinitionValidator`, `BuildDynamicContextService` и любых других сервисов Orchestrator — Deptrac это разрешит.

Но фактически StaticExecution Domain нуждается только в:
- `RunAgentServiceInterface` (запуск агента)
- `AuditLoggerInterface` (аудит)

А StaticExecution Infrastructure — в:
- `PromptFormatterInterface` (форматирование промптов)

### Аргумент

Port/Adapter был **кривой, но правильной реакцией** на слишком широкое Deptrac-правило. Разработчик PR пытался сузить зависимость на уровне кода (ACL-интерфейсы), поскольку Deptrac не делал этого на уровне слоёв.

### Рекомендация

**Сузить Deptrac-правило** вместо того, чтобы удалять Port. Варианты:

**Вариант A — Фиксация текущего состояния (minimum viable fix):**
```yaml
StaticExecutionDomain:
  - StaticExecutionDomain
  - StaticExecutionDomainVo
  - OrchestratorDomainVo
  - OrchestratorDomainEnum
  - OrchestratorDomainDto
  - DomainEnum
  - DomainDto
  # OrchestratorDomain убран — вместо этого конкретные подслои:
  - OrchestratorDomainIntegrationServices  # RunAgentServiceInterface
  - OrchestratorDomainAuditServices         # AuditLoggerInterface
```

Для этого нужно создать fine-grained Deptrac-слои:
```yaml
- name: OrchestratorDomainIntegrationServices
  collectors:
    - type: classLike
      value: ^TaskOrchestrator\\Common\\Module\\Orchestrator\\Domain\\Service\\Integration\\.*

- name: OrchestratorDomainAuditServices
  collectors:
    - type: classLike
      value: ^TaskOrchestrator\\Common\\Module\\Orchestrator\\Domain\\Service\\Chain\\Audit\\.*
```

**Вариант B — Integration Service + сужение Deptrac (рекомендуемый):**

Принять предложение Гэндальфа по переименованию Port → Integration Service, **но одновременно** сузить Deptrac-правило, чтобы `StaticExecutionDomain → OrchestratorDomain` было запрещено, а `StaticExecutionDomain → OrchestratorDomainIntegrationServices, OrchestratorDomainAuditServices` — разрешено. Тогда Integration Service будет не просто косметическим переименованием, а архитектурно обоснованным выбором при наличии жёсткого Deptrac-контроля.

---

## 4. Слепое пятно №2: AuditLogger — иллюзия изоляции

### Что Гэндальф упомянул вскользь

Гэндальф отмечает:

> «StaticExecution Domain уже напрямую зависит от AuditLoggerInterface из Orchestrator Domain. Port-интерфейсы не обеспечивают изоляции.»

Это верно, но Гэндальф не доводит мысль до конца. Проблема **системная**, а не локальная.

### Картина утечки

`AuditLoggerInterface` (Orchestrator Domain) пронизывает **все три слоя** StaticExecution:

| Слой | Файл | Тип зависимости |
|------|------|-----------------|
| Application | `ExecuteStaticChainServiceInterface` | Параметр метода `execute()` |
| Application | `ExecuteStaticChainService` | Параметр метода `execute()` |
| Domain | `RunStaticChainService` | Параметр метода `execute()`, передаётся в приватные методы |
| Domain | `RunStaticChainService` | Вызовы `logChainStart()`, `logStepStart()`, `logStepResult()`, `logChainResult()` |

Это не просто «зависимость» — это **жёсткая привязка к контракту аудита Orchestrator**. StaticExecution Domain знает о `ChainResultAuditDto`, `StepAuditStatusDto` (Orchestrator DTO) и конструирует их:

```php
// RunStaticChainService.php, метод buildResult():
$auditLogger?->logChainResult(new ChainResultAuditDto(
    chainName: $chainName,
    totalDurationMs: ...,
    totalInputTokens: ...,
    // ... 6 параметров, специфичных для Orchestrator
));
```

### Следствие

Даже если мы заменим Port → Integration Service, «изоляция» Integration-слоя останется иллюзией. StaticExecution Domain **конструирует Orchestrator DTO** (`ChainResultAuditDto`, `StepAuditStatusDto`) — это гораздо более глубокая связь, чем сигнатура Port-интерфейса.

### Рекомендация

Если мы серьёзно относимся к изоляции модулей, нужно:
1. Создать `StaticExecution\Domain\Service\Audit\StaticAuditLoggerInterface` с собственными типами
2. Mapper в Integration-слое: `StaticAuditLoggerInterface → AuditLoggerInterface`
3. Или — признать, что AuditLogger — это **разделяемый контракт** (как VO), и задокументировать это явно

Второй вариант прагматичнее. Но тогда нужно **честно зафиксировать** в конвенциях: «AuditLoggerInterface — разделяемый доменный контракт, допустимый к использованию в зависимых модулях». Сейчас такого правила нет.

---

## 5. Слепое пятно №3: Pass-through Integration Service — то же самое, только сбоку

### Суть проблемы

Гэндальф предлагает:

```php
// Новый RunAgentServiceInterface в Domain\Service\Integration\
interface RunAgentServiceInterface
{
    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo;
}

// Новый RunAgentService в Integration\Service\AgentRunner\
final readonly class RunAgentService implements RunAgentServiceInterface
{
    public function __construct(
        private OrchestratorRunAgentServiceInterface $inner,
    ) {}

    public function run(ChainRunRequestVo $request, ?ChainRetryPolicyVo $retryPolicy = null): ChainRunResultVo
    {
        return $this->inner->run($request, $retryPolicy);
    }
}
```

Это **тот же pass-through**, просто с другим именем. Количество косвенных вызовов не изменилось. Значение, добавляемое этим слоем, — **ноль**. Ни маппинга, ни трансформации, ни защиты от изменений.

### Сравнение

| Аспект | Port/Adapter (текущий) | Integration Service (предложение Гэндальфа) |
|--------|----------------------|----------------------------------------------|
| Косвенность | 2 вызова (Port → Adapter → Orchestrator) | 2 вызова (Interface → Service → Orchestrator) |
| Маппинг | Нет | Нет |
| Изоляция VO | Нет (Orchestrator VO в сигнатуре) | Нет (Orchestrator VO в сигнатуре) |
| Соответствие конвенции | ❌ `Port/`, `*PortInterface` | ✅ `ServiceInterface`, `*Service` |
| Добавленная ценность | 0 | 0 |

Разница — **только в имени**. Гэндальф прав, что имена должны соответствовать конвенциям. Но он создаёт иллюзию, что переименование решает архитектурную проблему. Оно решает **стилистическую** проблему.

### Риск

Если команда увидит, что «Integration Service» — это просто pass-through, возникнет соблазн **вообще убрать** этот слой и использовать `Orchestrator\RunAgentServiceInterface` напрямую. И это было бы **логично**, если Deptrac это разрешает. Но тогда мы теряем даже номинальную границу между модулями.

### Рекомендация

Если создаём Integration Service — он должен **добавлять значение**:
- Либо маппинг (свои VO → Orchestrator VO), если мы считаем модули независимыми контекстами
- Либо честно признать, что это **delegation layer** для Deptrac-совместимости, и задокументировать это как принятый паттерн

---

## 6. Слепое пятно №4: Масштабируемость при Conditional Branching (Sprint 8)

### Проблема

Sprint 8 добавит `ConditionalBrancing`-модуль, которому тоже нужно:
- Запускать агентов через Orchestrator
- Форматировать промпты
- Логировать аудит

Если следовать паттерну Гэндальфа, каждый новый модуль получит:
- `Domain\Service\Integration\RunAgentServiceInterface` (свой!)
- `Integration\Service\AgentRunner\RunAgentService` (свой!)

Итого: 3 модуля × 2 файла = 6 файлов pass-through делегатов для **одной и той же операции** — запуска агента.

### Альтернатива

Вместо дублирования Integration Service в каждом модуле, можно:
1. **Сделать `Orchestrator\RunAgentServiceInterface` разделяемым контрактом** — любой модуль может зависеть от него напрямую (при условии сужения Deptrac до конкретных сервисов, а не всего `OrchestratorDomain`)
2. Это аналогично тому, как VO уже разделяются между модулями
3. Deptrac гарантирует, что модуль зависит только от разрешённых интерфейсов

Это **меньше кода**, **меньше косвенности**, **та же безопасность** (при правильном Deptrac).

### Возражение

«Но тогда модули связаны через конкретный интерфейс Orchestrator!» — Да, и они **уже связаны** через VO, DTO, AuditLoggerInterface. Добавление ещё одной связи через `RunAgentServiceInterface` не ухудшит ситуацию, а уменьшит количество бесполезных прослоек.

---

## 7. Слепое пятно №5: SRP в RunStaticChainService

### Что Гэндальф не увидел

`RunStaticChainService` (StaticExecution Domain) — это God Service в миниатюре:

| Зависимость | Слой | Модуль |
|-------------|------|--------|
| `ExecuteStaticStepService` | Domain (свой) | StaticExecution |
| `CheckStaticBudgetServiceInterface` | Domain (свой) | StaticExecution |
| `LoggerInterface` | PSR-3 | — |
| `AuditLoggerInterface` | Domain (чужой) | **Orchestrator** |
| `ChainResultAuditDto` | Dto (чужой) | **Orchestrator** |
| `StepAuditStatusDto` | Dto (чужой) | **Orchestrator** |
| `ChainDefinitionVo` | VO (чужой) | Orchestrator |
| `ChainStepVo` | VO (чужой) | Orchestrator |
| `FixIterationGroupVo` | VO (чужой) | Orchestrator |
| `BudgetVo` | VO (чужой) | Orchestrator |

**10 зависимостей**, из которых 6 — от чужого модуля. Причём Domain-слой StaticExecution **конструирует** Orchestrator DTO (`new ChainResultAuditDto(...)`, `new StepAuditStatusDto(...)`) — это не просто чтение VO, это **создание объектов чужого модуля**.

Это нарушение SRP, но更重要的是 — это нарушение **границы модуля**. Domain StaticExecution знает слишком много о структуре аудита Orchestrator.

### Рекомендация (долгосрочная)

Вынести Audit-ответственность в отдельный сервис:
- `StaticExecution\Domain\Service\Audit\StaticAuditServiceInterface` — интерфейс с методами, работающими только с StaticExecution VO
- `StaticExecution\Integration\Service\Audit\StaticAuditService` — маппит StaticExecution VO → Orchestrator DTO и делегирует в `AuditLoggerInterface`

Тогда `RunStaticChainService` перестанет знать об Orchestrator DTO совсем.

---

## 8. Слепое пятно №6: QualityGateRunnerInterface — та же проблема, но «непроблема»

### Несогласованность

Гэндальф помечает `QualityGateRunnerInterface` как «non-blocking» нарушение именования:

> «должен называться `RunQualityGateServiceInterface`. Однако это не блокирующее нарушение и может быть исправлено отдельной задачей.»

Но `QualityGateRunnerInterface` — это **тот же паттерн**, что и Port:
- Лежит в `Domain\Service\` StaticExecution
- Принимает `QualityGateVo` из Orchestrator VO
- Возвращает `QualityGateResultVo` из Orchestrator VO
- Реализация (`QualityGateRunner`) — в Infrastructure

Если Port — это нарушение, то и `QualityGateRunnerInterface` — тоже нарушение по тем же причинам (кросс-модульные VO в сигнатуре интерфейса своего модуля, нарушение конвенции именования). Но Гэндальф даёт ему «проходной билет».

Аналогично `ResolveChainRunnerServiceInterface` — интерфейс в Domain StaticExecution, принимает Orchestrator VO напрямую. Его реализация в Infrastructure, а не в Integration. Но `ResolveChainRunnerService` внутри вызывает `AgentRunnerPortInterface` и `PromptFormatterPortInterface` — т.е. Infrastructure-сервис зависит от Port (→ Integration).

**Либо все эти интерфейсы — норма (и нужно обновить конвенции), либо все — нарушения (и нужен комплексный рефакторинг).** Выборочное применение критериев — признак непоследовательного анализа.

---

## 9. Итоговая карта проблем (приоритизированная)

| # | Проблема | Критичность | Гэндальф увидел? |
|---|----------|-------------|-------------------|
| 1 | Deptrac: `StaticExecutionDomain → OrchestratorDomain` слишком широко | 🔴 Высокая | ❌ Использовал как аргумент ЗА удаление Port |
| 2 | AuditLogger + DTO пронизывают все слои StaticExecution | 🔴 Высокая | 🟡 Упомянул, но не предложил решения |
| 3 | Pass-through Integration Service не добавляет значения | 🟡 Средняя | ❌ Не признал, что его предложение — то же самое |
| 4 | RunStaticChainService конструирует Orchestrator DTO | 🟡 Средняя | ❌ Не заметил |
| 5 | QualityGateRunner / ResolveChainRunner — та же проблема | 🟡 Средняя | 🟡 Отметил как non-blocking |
| 6 | Масштабируемость при Conditional Branching | 🟠 Низкая (пока) | ❌ Не анализировал |
| 7 | Именование Port/Adapter vs Integration Service | 🟠 Низкая | ✅ Основной фокус |

---

## 10. Рекомендуемый план (вместо плана Гэндальфа)

### Фаза 1 — В рамках PR #117 (блокирующее)

**Согласен с Гэндальфом:** переименовать Port → Integration Service для соответствия конвенциям.

**Но добавить:**
1. Сузить Deptrac-правило: `StaticExecutionDomain → OrchestratorDomain` заменить на `StaticExecutionDomain → OrchestratorDomainIntegrationServices, OrchestratorDomainAuditServices` (fine-grained слои)
2. Обновить Deptrac-правило для `StaticExecutionInfrastructure`: убрать `OrchestratorDomain`, оставить только `StaticExecutionDomain` (интерфейсы Port/Service)

### Фаза 2 — Отдельная задача (техдолг)

1. Создать `StaticAuditServiceInterface` в StaticExecution Domain с собственными типами
2. Вынести audit-логику из `RunStaticChainService` в `StaticAuditService` (Integration-слой)
3. `RunStaticChainService` перестанет знать об Orchestrator DTO

### Фаза 3 — Перед Sprint 8 (Conditional Branching)

1. Принять архитектурное решение: Integration Service per-module vs. shared contract
2. Если shared contract — обновить конвенции и Deptrac
3. Если per-module — задокументировать шаблон и создать генерик-подход

---

## 11. Резюме

Гэндальф прав в диагностике симптомов (Port/Adapter не соответствует конвенциям), но ошибается в лечении. Удаление Port и переименование в Integration Service — это **косметическое исправление**, которое не решает системные проблемы:

1. Deptrac-правило слишком широкое — настоящая причина, по которой разработчик создал Port
2. AuditLogger пронизывает все слои — изоляция модулей иллюзорна
3. Pass-through Integration Service — тот же антипаттерн с другим именем
4. RunStaticChainService конструирует чужие DTO — реальное нарушение границы модуля

Предложение Гэндальфа можно принять как **минимальный шаг**, но только при условии одновременного сужения Deptrac-правил. Иначе мы просто перекрасили забор.

---

*«Красивая рецензия. А теперь покажи, как это выглядит, когда три модуля зависят от Orchestrator Domain целиком и Deptrac это разрешает.»*
