# ADR-009: Dynamic остаётся в Orchestrator

| Поле        | Значение                                                        |
|-------------|-----------------------------------------------------------------|
| Статус      | Принято                                                         |
| Дата        | 2026-05-02                                                      |
| Автор       | Архитектор (Гэндальф)                                          |
| Участники   | Гэндальф, Локи, Левша, Шерлок                                  |
| Источник    | OQ-6 (Roadmap 2026 Q2–Q3); Brainstorm #2; Sprint 7–8 результаты |

## Контекст

### Постановка вопроса

OQ-6 из Roadmap: «Dynamic остаётся в Orchestrator навсегда или планируется физический split в отдельный модуль?» Решение запланировано после Sprint 8, когда Integration-паттерн будет валидирован на ≥2 стратегиях (критерий G6). Sprint 8 завершён — пора принимать решение.

### Что уже сделано

| Спринт | Результат | Значение для решения                                  |
|--------|-----------|-------------------------------------------------------|
| Sprint 3 | `ExecutionStrategyInterface` + `StaticExecutionStrategy` + `DynamicExecutionStrategy` | Стратегии инкапсулированы, CommandHandler = диспетчер |
| Sprint 4 | `ChainDefinitionVo` split, `SharedChainDefinitionVo` | Shared Kernel формализован (ADR-008)                  |
| Sprint 7 | StaticExecution → отдельный модуль | Integration-паттерн работает: 3 bridge-интерфейса, 167 LOC Integration-слой |
| Sprint 8 | `ConditionalExecutionStrategy` + `when:` DSL | Integration-паттерн валидирован на 2-й стратегии (G6 ✅) |

### G6-критерий: пройден ✅

Integration-паттерн работает для ≥2 стратегий. StaticExecution выделен в отдельный модуль (22 файла, 1758 LOC) с Integration-слой из 3 bridge-классов (167 LOC). Conditional Branching добавлен через тот же `ExecutionStrategyInterface` без God-interface.

### Dynamic: масштаб и сложность

| Метрика                              | Static (split)       | Dynamic (в Orchestrator)       |
|--------------------------------------|-----------------------|--------------------------------|
| Domain-сервисы (интерфейсы)          | 7                     | **11**                         |
| Domain-сервисы (реализации)          | 5                     | **7**                          |
| Domain VO / Entity                   | 5 VO                  | **10 VO + 1 Entity**           |
| Domain LOC (services + VO)           | ~860                  | **~2385**                      |
| Application-файлы (strategy + DTO)   | 2                     | **4** (~350 LOC)               |
| Infrastructure-файлы                 | 5                     | **3** (Dynamic-specific)       |
| Integration bridge-интерфейсов       | 3                     | **оценка: 11–14**              |

### Cross-зависимости Dynamic

Dynamic Domain-сервисы имеют **0 cross-imports** от других Orchestrator subdomain-сервисов (Condition, Shared). Единственные внешние зависимости:
- Session-интерфейсы (`ChainSessionWriterInterface`, `ChainSessionReaderInterface`) — 5 import-точек
- Integration-интерфейс `RunAgentServiceInterface` — доступ через `RunDynamicLoopAgentServiceInterface`
- `AuditLoggerInterface` / `AuditLoggerFactoryInterface` — для журналирования

Граница чистая, но **широкая**: 11+ интерфейсов требуют bridging при split.

## Решение

**Dynamic остаётся в Orchestrator. Физический split не планируется.**

### Обоснование

**1. Dynamic — это ядро домена Orchestrator.**

Static — пошаговое выполнение (step → agent → next step). Conditional — вариант Static с условиями. Оба — линейные модели, естественно выделяемые в отдельные модули.

Dynamic — agent loop с бюджетом, fix_iterations, quality gates, facilitator-парсингом, round-нотификациями. Это не «одна из стратегий оркестрации» — это **суть оркестратора**: управление интерактивной сессией AI-агента с политиками повторов и контроля качества. Без Dynamic Orchestrator превращается в тонкий routing-слой (query handlers + chain loader + dispatcher), а не в модуль с собственной доменной логикой.

**2. Integration-слой для Dynamic нарушит критерий успеха split.**

Критерий успеха из brainstorm #2: «Integration-слой для второй стратегии создан по тому же паттерну без God-interface на 15 методов».

StaticExecution Integration: 3 bridge-интерфейса, 167 LOC — **критерий выполнен** ✅

Dynamic Integration: 11+ bridge-интерфейсов (`RunDynamicLoopAgentServiceInterface`, `BuildDynamicContextServiceInterface`, `CheckDynamicLoopBudgetServiceInterface`, `ExecuteDynamicTurnServiceInterface`, `FacilitatorResponseParserInterface`, `FinalizeDynamicLoopServiceInterface`, `FormatDynamicJournalServiceInterface`, `RecordDynamicRoundServiceInterface`, `RoundCompletedNotifierInterface`, `SessionCompletedNotifierInterface`, `RunDynamicLoopServiceInterface`) + 3 session-интерфейса + audit-интерфейсы. Оценка Integration-слоя: **500+ LOC**. Критерий **не выполнен** ❌.

Это не Design-проблема — это отражение реальной сложности Dynamic path. 11 интерфейсов — это не God-interface, а ISP-корректная декомпозиция. Но их суммарный bridge-слой создаёт coupling, который не окупается modular-бенефитом.

**3. G6-критерий подтверждает паттерн, но не требует его применения ко всем стратегиям.**

G6 ответил на вопрос: «масштабируется ли Integration-паттерн?» Да — для стратегий с 3–5 bridge-интерфейсами. G6 не утверждал, что каждая стратегия **должна** быть выделена. Dynamic — стратегия с 11+ bridge-интерфейсами, где стоимость Integration-слоя превышает стоимость текущей архитектуры.

**4. 0 cross-imports в Domain — архитектура уже чистая.**

Dynamic Domain-сервисы не зависят от Condition, Shared или других Orchestrator subdomain-сервисов. Namespace `Service/Chain/Dynamic/` — это уже де-факто bounded context внутри Orchestrator. Физический split даёт Deptrac-проверку границ, но не меняет реальную архитектуру.

**5. LOC-прогноз: Orchestrator стабилизируется.**

Текущий Orchestrator: 10 311 LOC (Domain: 5441). Триггер G1 (Domain LOC ≥ 7000) не сработает, потому что:
- Static уже вынесен (−1758 LOC)
- Основные декомпозиции завершены (Sprint 6–7)
- Новые фичи (failover, metrics, hooks) добавляют LOC в AgentRunner и Infrastructure, не в Orchestrator Domain

Прогноз к концу Q3: Orchestrator Domain ~5600–5800 LOC. G1 не сработает.

## Последствия

### Положительные

1. **Нет Integration-overhead** для 11+ bridge-интерфейсов — экономия 2–3 спринта работы.
2. **Орiscinator сохраняет доменную идентичность** — модуль с собственной Domain-логикой, а не routing-прослойка.
3. **Команда работает в знакомой структуре** — Dynamic развивается через `Service/Chain/Dynamic/` namespace без cross-module coordination.
4. **Deptrac не нужен для Dynamic** — 0 cross-imports уже обеспечивают чистоту границ.
5. **Sprint 9–10 фокусируются на value** (failover, metrics, hooks), а не на infrastructural рефакторинге.

### Отрицательные

1. **Orchestrator остаётся largest module** (~10 311 LOC). Но это осознанный выбор: размер отражает сложность домена, а не архитектурную проблему.
2. **Deptrac не проверяет границу Dynamic** — формально, Dynamic и Condition находятся в одном модуле. Митигируется: 0 cross-imports в коде + code review discipline.
3. **Если в Q4 появится SubOrchestration** (sub-agents), модуль может вырасти. Решение: SubOrchestration выделяется в отдельный модуль (solution #3 из brainstorm #2, принято консенсусом). Dynamic при этом не затрагивается.

### Риски

1. **G1 (Domain LOC ≥ 7000) может сработать в Q1 2027** при добавлении новых фич. Митигация: ADR пересматривается при срабатывании G1 или при добавлении 4-й стратегии (parallel execution).
2. **Когнитивная нагрузка** на разработчиков при работе с Orchestrator. Митигация: namespace-организация `Dynamic/`, `Condition/`, `Session/` уже обеспечивает навигацию. Code review следит за границами.

## Альтернативы

### 1. Физический split Dynamic в отдельный модуль

**Отвергнуто.** Integration-слой для 11+ интерфейсов (оценка: 500+ LOC) нарушает критерий успеха split из brainstorm #2. Dynamic — ядро Orchestrator, split превращает Orchestrator в hollow shell. Стоимость не окупается.

### 2. Partial split: вынести RunDynamicLoopService + бюджет в отдельный модуль

**Отвергнуто.** Искусственное разрезание 7 Domain-сервисов и 10 VO по «важности» создаёт circular dependencies: `RunDynamicLoopService` зависит от `ExecuteDynamicTurnService`, `BuildDynamicContextService`, `FinalizeDynamicLoopService`, `RecordDynamicRoundService`. Все 7 сервисов — единый loop lifecycle.

### 3. Отложенное решение (revisit в Q4)

**Отвергнуто.** OQ-6 открыт с brainstorm #2 (2026-04-30). Команда ждёт решения для планирования Q4. Каждый спринт неопределённости — риск re-litigation. ADR фиксирует решение сейчас с критериями пересмотра.

## Критерии пересмотра решения

ADR пересматривается при выполнении **любого** из условий:

| Критерий | Порог | Обоснование |
|----------|-------|-------------|
| **C1: Domain LOC** | Orchestrator Domain ≥ 7000 LOC | Модуль перестаёт быть обозримым |
| **C2: 4-я стратегия** | Parallel execution реализован | Integration-паттерн валидирован на 3 стратегиях, стоимость Dynamic bridge может снизиться |
| **C3: Integration-layer toolkit** | Появился automated bridge-generator или аналогичный инструмент | Стоимость создания Integration-слоя резко снижается |
| **C4: SubOrchestration split** | Sub-agents выделены в отдельный модуль | Orchestrator снова теряет доменную логику, баланс меняется |
| **C5: Командный consensus** | 2+ участника считают Dynamic split необходимым | Архитектурное решение — командное, не индивидуальное |

При пересмотре проводится повторный анализ с оценкой LOC, Integration-слой complexity и blast radius.

## Связанные ADR

- [ADR-006: ExecutionStrategy Composition](006-execution-strategy-composition.md) — паттерн Strategy, определивший архитектуру стратегий
- [ADR-008: Shared Kernel Contract](008-shared-kernel-contract.md) — формализация контракта между bounded context'ами

## Changelog

| Дата | Автор | Изменение |
|------|-------|-----------|
| 2026-05-02 | Гэндальф | ADR создан. Решение: Dynamic остаётся в Orchestrator |
