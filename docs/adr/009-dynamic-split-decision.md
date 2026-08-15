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

OQ-6 из Roadmap: «Dynamic остаётся в Orchestrator навсегда или планируется физическое разделение в отдельный модуль?» Решение запланировано после Sprint 8, когда Integration-паттерн будет валидирован на ≥2 стратегиях (критерий G6). Sprint 8 завершён — пора принимать решение.

### Что уже сделано

| Спринт | Результат | Значение для решения                                  |
|--------|-----------|-------------------------------------------------------|
| Sprint 3 | `ExecutionStrategyInterface` + `StaticExecutionStrategy` + `DynamicExecutionStrategy` | Стратегии инкапсулированы, CommandHandler = диспетчер |
| Sprint 4 | разделение `ChainDefinitionVo`, `SharedChainDefinitionVo` | общее ядро формализовано (ADR-008)                  |
| Sprint 7 | StaticExecution → отдельный модуль | Integration-паттерн работает: 3 bridge-интерфейса, 167 LOC Integration-слой |
| Sprint 8 | `ConditionalExecutionStrategy` + `when:` DSL | Integration-паттерн валидирован на 2-й стратегии (G6 ✅) |

### G6-критерий: пройден ✅

Integration-паттерн работает для ≥2 стратегий. StaticExecution выделен в отдельный модуль (22 файла, 1758 LOC) с Integration-слой из 3 bridge-классов (167 LOC). Conditional Branching добавлен через тот же `ExecutionStrategyInterface` без божественного интерфейса (God interface).

### Dynamic: масштаб и сложность

| Метрика                              | Static (разделение)       | Dynamic (в Orchestrator)       |
|--------------------------------------|-----------------------|--------------------------------|
| Domain-сервисы (интерфейсы)          | 7                     | **11**                         |
| Domain-сервисы (реализации)          | 5                     | **7**                          |
| Domain VO / Entity                   | 5 VO                  | **10 VO + 1 Entity**           |
| Domain LOC (services + VO)           | ~860                  | **~2385**                      |
| Application-файлы (strategy + DTO)   | 2                     | **4** (~350 LOC)               |
| Infrastructure-файлы                 | 5                     | **3** (Dynamic-specific)       |
| Integration bridge-интерфейсов       | 3                     | **оценка: 11–14**              |

### Cross-зависимости Dynamic

Dynamic Domain-сервисы имеют **0 взаимных импортов** от других Orchestrator сервисов поддоменов (Condition, Shared). Единственные внешние зависимости:
- Session-интерфейсы (`ChainSessionWriterInterface`, `ChainSessionReaderInterface`) — 5 import-точек
- Integration-интерфейс `RunAgentServiceInterface` — доступ через `RunDynamicLoopAgentServiceInterface`
- `AuditLoggerInterface` / `AuditLoggerFactoryInterface` — для журналирования

Граница чистая, но **широкая**: 11+ интерфейсов требуют bridging при разделении.

## Решение

**Dynamic остаётся в Orchestrator. Физическое разделение не планируется.**

### Обоснование

**1. Dynamic — это ядро домена Orchestrator.**

Static — пошаговое выполнение (шаг → агент → следующий шаг). Conditional — вариант Static с условиями. Оба — линейные модели, естественно выделяемые в отдельные модули.

Dynamic — agent loop с бюджетом, `fix_iterations`, quality gates, facilitator-парсингом, round-нотификациями. Это не «одна из стратегий оркестрации» — это **суть оркестратора**: управление интерактивной сессией AI-агента с политиками повторов и контроля качества. Без Dynamic Orchestrator превращается в тонкий слой маршрутизации (обработчики запросов + загрузчик цепочек + диспетчер), а не в модуль с собственной доменной логикой.

**2. Integration-слой для Dynamic нарушит критерий успеха разделения.**

Критерий успеха из brainstorm #2: «Integration-слой для второй стратегии создан по тому же паттерну без божественного интерфейса (God interface) на 15 методов».

StaticExecution Integration: 3 bridge-интерфейса, 167 LOC — **критерий выполнен** ✅

Dynamic Integration: 11+ bridge-интерфейсов (`RunDynamicLoopAgentServiceInterface`, `BuildDynamicContextServiceInterface`, `CheckDynamicLoopBudgetServiceInterface`, `ExecuteDynamicTurnServiceInterface`, `FacilitatorResponseParserInterface`, `FinalizeDynamicLoopServiceInterface`, `FormatDynamicJournalServiceInterface`, `RecordDynamicRoundServiceInterface`, `RoundCompletedNotifierInterface`, `SessionCompletedNotifierInterface`, `RunDynamicLoopServiceInterface`) + 3 session-интерфейса + audit-интерфейсы. Оценка Integration-слоя: **500+ LOC**. Критерий **не выполнен** ❌.

Это не проектировочная проблема — это отражение реальной сложности динамического пути. 11 интерфейсов — это не божественный интерфейс (God interface), а декомпозиция, соответствующая ISP. Но их суммарный связующий слой создаёт связанность, которая не окупается выгодой от модульности.

**3. G6-критерий подтверждает паттерн, но не требует его применения ко всем стратегиям.**

G6 ответил на вопрос: «масштабируется ли Integration-паттерн?» Да — для стратегий с 3–5 связующими интерфейсами. G6 не утверждал, что каждая стратегия **должна** быть выделена. Dynamic — стратегия с 11+ связующими интерфейсами, где стоимость Integration-слоя превышает стоимость текущей архитектуры.

**4. 0 взаимных импортов в Domain — архитектура уже чистая.**

Dynamic Domain-сервисы не зависят от Condition, общего ядра или других Orchestrator сервисов поддоменов. Namespace `Service/Chain/Dynamic/` — это уже де-факто ограниченный контекст внутри Orchestrator. Физический split даёт Deptrac-проверку границ, но не меняет реальную архитектуру.

**5. LOC-прогноз: Orchestrator стабилизируется.**

Текущий Orchestrator: 10 311 LOC (Domain: 5441). Триггер G1 (Domain LOC ≥ 7000) не сработает, потому что:
- Static уже вынесен (−1758 LOC)
- Основные декомпозиции завершены (Sprint 6–7)
- Новые возможности (failover, metrics, hooks) добавляют LOC в AgentRunner и Infrastructure, не в Orchestrator Domain

Прогноз к концу Q3: Orchestrator Domain ~5600–5800 LOC. G1 не сработает.

## Последствия

### Положительные

1. **Нет избыточных затрат на Integration** для 11+ bridge-интерфейсов — экономия 2–3 спринта работы.
2. **Orchestrator сохраняет доменную идентичность** — модуль с собственной Domain-логикой, а не прослойка маршрутизации.
3. **Команда работает в знакомой структуре** — Dynamic развивается через `Service/Chain/Dynamic/` namespace без межмодульной координации.
4. **Deptrac не нужен для Dynamic** — 0 взаимных импортов уже обеспечивают чистоту границ.
5. **Sprint 9–10 фокусируются на ценности** (failover, metrics, hooks), а не на infrastructural рефакторинге.

### Отрицательные

1. **Orchestrator остаётся largest module** (~10 311 LOC). Но это осознанный выбор: размер отражает сложность домена, а не архитектурную проблему.
2. **Deptrac не проверяет границу Dynamic** — формально, Dynamic и Condition находятся в одном модуле. Митигируется: 0 взаимных импортов в коде + дисциплина ревью кода.
3. **Если в Q4 появится SubOrchestration** (sub-agents), модуль может вырасти. Решение: SubOrchestration выделяется в отдельный модуль (solution #3 из brainstorm #2, принято консенсусом). Dynamic при этом не затрагивается.

### Риски

1. **G1 (Domain LOC ≥ 7000) может сработать в Q1 2027** при добавлении новых фич. Митигация: ADR пересматривается при срабатывании G1 или при добавлении 4-й стратегии (параллельное выполнение).
2. **Когнитивная нагрузка** на разработчиков при работе с Orchestrator. Митигация: namespace-организация `Dynamic/`, `Condition/`, `Session/` уже обеспечивает навигацию. Code review следит за границами.

## Альтернативы

### 1. Физическое разделение Dynamic в отдельный модуль

**Отвергнуто.** Integration-слой для 11+ интерфейсов (оценка: 500+ LOC) нарушает критерий успеха split из brainstorm #2. Dynamic — ядро Orchestrator, разделение превращает Orchestrator в hollow shell. Стоимость не окупается.

### 2. Частичное разделение: вынести RunDynamicLoopService + бюджет в отдельный модуль

**Отвергнуто.** Искусственное разрезание 7 Domain-сервисов и 10 VO по «важности» создаёт циклические зависимости: `RunDynamicLoopService` зависит от `ExecuteDynamicTurnService`, `BuildDynamicContextService`, `FinalizeDynamicLoopService`, `RecordDynamicRoundService`. Все 7 сервисов — единый жизненный цикл цикла.

### 3. Отложенное решение (пересмотр в Q4)

**Отвергнуто.** OQ-6 открыт с brainstorm #2 (2026-04-30). Команда ждёт решения для планирования Q4. Каждый спринт неопределённости — риск повторного обсуждения. ADR фиксирует решение сейчас с критериями пересмотра.

## Критерии пересмотра решения

ADR пересматривается при выполнении **любого** из условий:

| Критерий | Порог | Обоснование |
|----------|-------|-------------|
| **C1: Domain LOC** | Orchestrator Domain ≥ 7000 LOC | Модуль перестаёт быть обозримым |
| **C2: 4-я стратегия** | Parallel execution реализован | Integration-паттерн валидирован на 3 стратегиях, стоимость Dynamic bridge может снизиться |
| **C3: набор инструментов Integration-слоя** | Появился автоматический генератор связующих интерфейсов или аналогичный инструмент | Стоимость создания Integration-слоя резко снижается |
| **C4: SubOrchestration split** | Sub-agents выделены в отдельный модуль | Orchestrator снова теряет доменную логику, баланс меняется |
| **C5: Командный консенсус** | 2+ участника считают Dynamic split необходимым | Архитектурное решение — командное, не индивидуальное |

При пересмотре проводится повторный анализ с оценкой LOC, Integration-слой complexity и радиус влияния.

## Связанные ADR

- [ADR-006: ExecutionStrategy Composition](006-execution-strategy-composition.md) — паттерн Strategy, определивший архитектуру стратегий
- [ADR-008: общее ядро Contract](008-shared-kernel-contract.md) — формализация контракта между ограниченными контекстами

## Changelog

| Дата | Автор | Изменение |
|------|-------|-----------|
| 2026-05-02 | Гэндальф | ADR создан. Решение: Dynamic остаётся в Orchestrator |
