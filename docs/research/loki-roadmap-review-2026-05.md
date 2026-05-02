# Roadmap Review: Sprint 9–10 — Честная оценка

**Роль:** Архитектор Локи (system_architect_loki)
**Дата:** 2026-05-02
**Объект:** ROADMAP-2026-Q2-Q3.md, Sprint 9 (Error Handling + Typed I/O), Sprint 10 (Hooks + Sub-agents)
**Задача:** Архитектурный анализ 6 задач двух следующих спринтов — реальная боль или overengineering

---

## Классификация запроса

| Параметр | Значение | Обоснование |
|---|---|---|
| Сложность запроса | 6/10 | Требует глубокого знания кодовой базы и анализа 6 задач с обоснованием каждой |
| Уровень контекста | 8/10 | Дан roadmap, структура проекта, контекст решений; код изучен из репозитория |
| Риск ошибки | 3/10 | Оценка субъективна, но основана на реальном коде, не на гипотезах |

**Запрос не проблемный** — достаточно контекста для аргументированного анализа.

---

## Текущее состояние проекта (snapshot)

| Метрика | Значение |
|---|---|
| Модули | 2: Orchestrator (10 311 LOC), AgentRunner (3 005 LOC), StaticExecution (1 758 LOC) |
| Стратегии | 3: Static, Dynamic, Conditional — через `ExecutionStrategyInterface` |
| Integration-паттерн | Decoupled через Integration Service (StaticExecution) — валидирован на 2 стратегиях |
| Domain LOC (Orchestrator) | ~5 964 (66 файлов) |
| Тестов | 73 файла (62 unit + 11 integration) |
| Завершено | Sprint 1–8: P1–P3 декомпозиция + Conditional Branching |
| Отменено | Security Policy — security theater |

---

## 1. Почерёдная оценка 6 задач

### Sprint 9, Задача 1: Error Classification (FATAL/TRANSIENT/UNKNOWN)

**Вердикт: 🟡 Частично оправдана — упрощённая реализация, не по тексту ошибки**

#### Аргументы «за»

1. **RetryingAgentRunner делает retry-all без разбора** — это факт. Код (строка 63–85 `RetryingAgentRunner.php`) повторяет и при исключении, и при `$result->isError()`. Если API-ключ невалиден (FATAL) — мы тратим 3 попытки × exponential backoff = ~7 секунд бесполезного ожидания.
2. **`AgentResultVo` уже содержит `exitCode`** — есть чем классифицировать, не нужно парсить текст.
3. **`RetryPolicyVo` не различает типы ошибок** — интерфейс `getMaxRetries()` один для всех.

#### Аргументы «против»

1. **Парсинг текста ошибки — хрупко.** Roadmap упоминает «парсить текст ошибки» — это тупик. Но это稻草-man: у нас уже есть `exitCode`, `isTimedOut()`, `isError()` на `AgentResultVo`.
2. **3 попытки за 7 секунд — не катастрофа.** Да, retry на FATAL — потерянные 7 секунд. В контексте LLM-цепочки, где один шаг длится 30–120 секунд, это шум.
3. **Каждый runner может иметь свою семантику exit code.** Pi runner → exit code 1, Codex runner → exit code 1. Унифицировать FATAL/TRANSIENT по exit code — не проще, чем по тексту.

#### Рекомендация

Если делать — **только через `AgentResultVo`-поля** (`exitCode`, `isTimedOut`, `isError`), а не через парсинг текста. Добавить `ErrorClassificationVo` в Domain-слой AgentRunner с правилами:

```php
// Минимальная реализация
if ($result->isTimedOut()) return TRANSIENT;
if ($result->getExitCode() === 0 && $result->isError()) return UNKNOWN; // не должно быть
if ($result->getExitCode() >= 100) return FATAL;  // process-level crash
return TRANSIENT;  // по умолчанию retry
```

Но честно: **это nice-to-have, не боль.** Pain level: 2/10.

---

### Sprint 9, Задача 2: Loop Detection (fix_iterations)

**Вердикт: 🔴 Overengineering — решается max_iterations, проблема качества модели**

#### Аргументы «за»

1. В fix_iterations с `maxIterations: 3` теоретически возможен цикл: агент повторяет один и тот же некачественный ответ → quality gate падает → retry → тот же ответ → retry → max_iterations reached.
2. Исследование фреймворков показывает: Crush, OpenHands SDK, Paperclip AI реализуют loop detection.

#### Аргументы «против»

1. **`max_iterations: 3` уже ограничивает циклы.** Код в `FixIterationGroupVo` (строка 50): валидирует `maxIterations >= 1`. В `RunStaticChainService::handlePostStep()` — `shouldRetryGroup()` проверяет лимит. Цикл невозможен по определению — максимум 3 итерации, потом цепочка продолжает.
2. **LLM всегда выдаёт разный текст.** Даже при одинаковом промпте, модель генерирует различные токены. Similarity detection на LLM-output — это угадывание с высокой false-positive rate.
3. **Проблема качества модели, не оркестратора.** Если модель не может исправить ошибку за 3 итерации — это повод сменить модель или промпт, а не добавлять heuristic в оркестратор.
4. **Ненужная сложность.** Window-based similarity (Crush), evidence-based (Paperclip) — это 200+ строк кода, который решает проблему, которой нет.

#### Рекомендация

**Не делать.** Если появится реальный кейс зацикливания (а не гипотетический) — добавить метрику `iteration_warning` в audit (уже есть: `StaticStepResultVo::$iterationWarning`). Это observability, а не control.

Pain level: 0/10.

---

### Sprint 9, Задача 3: Typed I/O per Step (JSON Schema)

**Вердикт: 🔴 Overengineering сейчас — строить не на чем**

#### Аргументы «за»

1. LangGraph, Mastra AI, Archon — все используют typed I/O (Zod, TypedDict, JSON Schema).
2. Fail-fast при невалидном input — идея правильная.

#### Аргументы «против**

1. **Между шагами — сырой текст.** Код в `StaticExecutionStrategy::buildPreviousContext()` (ConditionalExecutionStrategy, строка 168): `implode("\n\n", $parts)` из `outputText`. В `RunStaticChainService` — `$execution->getPreviousContext()` — та же конкатенация текста.
2. **Нет структуры → нет JSON Schema.** `outputText` — это строка от LLM. Как вы будете валидировать её через JSON Schema? Для этого нужно:
   - LLM выдаёт JSON (а не свободный текст)
   - Парсер извлекает поля
   - Schema валидирует
   - Следующий шаг получает типизированный контекст
3. **Это не спринт, это архитектурная трансформация.** Typed I/O требует:
   - `StepOutputVo` (structured output) вместо `string $outputText`
   - Parser per step type (JSON extraction from LLM text)
   - Schema registry в ChainDefinitionVo
   - Маппинг на границе Integration-слоёв
   - Это Sprint 9 → 12, а не один Sprint 9.
4. **ACL-паттерн между Orchestrator и AgentRunner уже существует.** `ChainRunResultVo` — дубликат `AgentResultVo` (подтверждено ADR-007). Typed I/O сломает этот ACL — понадобится реальный маппинг полей, а не copy-paste VO.

#### Рекомендация

**Не делать сейчас.** Typed I/O — это Q4/Q1 2027 фича, которая требует сначала **structured output contract** между шагами. Без этого JSON Schema — ornamental complexity.

Если хочется подготовить почву — добавить опциональное поле `output_schema` в `ChainStepVo` (string, JSON Schema reference), но не реализовывать валидацию. Это 30 минут работы, bookmark на будущее.

Pain level: 1/10.

---

### Sprint 10, Задача 1: Hooks System (pre/post step shell-скрипты)

**Вердикт: 🟢 Реальная боль, но с оговорками**

#### Аргументы «за»

1. **Единственный способ добавить custom-логику без изменения Domain.** Сейчас, чтобы добавить «после шага X отправить webhook» или «перед шагом Y проверить наличие файла» — нужно писать новый сервис и тащить его в DI. Hooks дают declarative расширение.
2. **Подтверждён 3+ фреймворками** (Claude Code 20+ events, OpenHands SDK 6 lifecycle events, Codex hooks).
3. **Natural fit для YAML DSL.** `pre_step: "scripts/check-env.sh"`, `post_step: "scripts/notify.sh"` — читаемо, декларативно.
4. **Альтернатива Decorator pattern** — вместо `LoggingAgentRunner`, `TimingAgentRunner`, `WebhookAgentRunner` — один hook pipeline.

#### Аргументы «против»

1. **Shell-скрипты = новый attack surface.** Если мы только что отменили Security Policy как security theater, добавление shell-скриптов как хуков — противоречиво. Кто валидирует, что hook-скрипт не делает `rm -rf /`?
2. **Error handling хуков.** Что если pre-step hook упал? Пропускаем шаг? Фейлим цепочку? Retry hook? Каждое решение — код.
3. **Timeout management.** Shell-скрипт может зависнуть. Нужен таймаут. Нужен process management. Это `proc_open()` или Symfony Process —Infrastructure-код, который нужно тестировать.
4. **Observability.** Как логировать hook execution? Отдельный audit log? Интеграция в существующий `AuditLoggerInterface`?

#### Рекомендация

**Делать, но MVP: только `post_step` hooks (не `pre_step`).** Обоснование:
- `post_step` — observability/notification, минимальный риск. «Отправить webhook после шага», «записать метрику».
- `pre_step` — control flow, высокий риск. «Пропустить шаг», «изменить контекст» — это уже conditional branching, который у нас есть через `when:`.

MVP-scope:
1. `post_step` hook в YAML: `post_step: "scripts/notify.sh"` (опционально)
2. Hook выполняется через `Symfony\Process` с таймаутом (30 секунд)
3. Hook failure = warning в лог, не failure цепочки
4. Hook stdout/stderr — в audit log

Pain level: 6/10 (для `post_step`), 3/10 (для `pre_step`).

---

### Sprint 10, Задача 2: Sub-agent Pattern (ADR + Design Only)

**Вердикт: 🟡 Оправдана как ADR, но не сейчас**

#### Аргументы «за»

1. **«Chain внутри chain»** — реальный use case. Пример: review-цепочка внутри dev-цепочки («напиши код» → «запусти sub-chain: lint + test + review» → «исправь»).
2. ADR без кода — низкий риск. Фиксируем паттерн, не реализуем.
3. Q4 планируется реализация — ADR нужен как input.

#### Аргументы «против»

1. **У нас ещё нет опыта эксплуатации 3 стратегий.** Conditional Branching добавлен в Sprint 8 — мы не знаем, какие боли он принесёт. Писать ADR на sub-agents, не понимая, как работают conditional chains в production — premature.
2. **Integration-паттерн масштабируется?** Мы валидировали на 2 стратегиях (Static + Conditional). Sub-agents добавляют nested execution — это качественно другой уровень. ADR будет speculative.
3. **Срочность низкая.** Q4 реализация — значит ADR можно написать в Sprint Q4-1. Нет смысла тратить Sprint 10 на speculative ADR.

#### Рекомендация

**Отложить ADR до Q4.** Вместо этого — записать OQ (Open Question) в roadmap: «Sub-agent pattern: ADR запланирован на Q4 Sprint 1». Это bookmark, не блокер.

Pain level: 1/10 (ADR не решает текущую боль).

---

### Sprint 10, Задача 3: Model Failover с Cooldown

**Вердикт: 🟢 Реальная боль — но с конкретными замечаниями по дизайну**

#### Аргументы «за»

1. **Circuit Breaker уже есть** (`CircuitBreakerAgentRunner`), но он **не переключает на fallback модель**. Когда CB opens — вызов блокируется, возвращается ошибка. Цепочка падает.
2. **Fallback-механизм уже существует** — `FallbackConfigVo` + `RoleConfigVo::$fallback`. Но это **другой runner**, не другая модель того же runner'а. FallbackConfigVo переключает с Pi на Codex, а не с Claude на GPT.
3. **Real production pain.** LLM API rate limits, 529 overloaded, 503 service unavailable — обычное дело. Сейчас вся цепочка падает при недоступности модели.

#### Аргументы «против»

1. **Semantic confusion.** Roadmap говорит «model failover», но существующий `FallbackConfigVo` — это runner failover (Pi → Codex). Model failover — это Pi (Claude) → Pi (GPT). Это **разные механики**. Нужен ли обе?
2. **Cooldown неясен.** Roadmap упоминает «cooldown» — но Circuit Breaker уже имеет `resetTimeoutSeconds` (в `CircuitBreakerStateVo`). Это тот же cooldown. Дублирование?
3. **Где конфигурировать?** В YAML `roles`? Новый section `models`? Это влияет на `YamlChainLoader`.

#### Рекомендация

**Делать, но уточнить scope.** Два варианта:

**Вариант A: Model failover через RoleConfigVo** (проще)
```yaml
roles:
  developer:
    command: [pi, --model, claude-3.5-sonnet]
    fallback:
      command: [pi, --model, gpt-4o]
```
Это уже работает через `FallbackConfigVo`! Нужно только связать с Circuit Breaker: CB open → trigger fallback.

**Вариант B: Отдельный Model Failover** (сложнее)
Новый декоратор `FailoverAgentRunner` поверх `RetryingAgentRunner` + `CircuitBreakerAgentRunner`. Список моделей с приоритетом. Cooldown = CB reset timeout.

**Мой совет: Вариант A.** Circuit Breaker + FallbackConfigVo уже существуют. Нужно только одно: **CB open → автоматически триггерить fallback** (если сконфигурирован). Сейчас CB open = error, а должно быть CB open → try fallback runner. Это wiring, а не новая архитектура.

Pain level: 7/10.

---

## 2. Что мы упускаем: скрытые боли текущей архитектуры

### 🟡 Боль 1: Observability gap — нет metrics/telemetry

**Проблема:** Текущий `AuditLoggerInterface` пишет в JSONL-файл. Нет способа ответить на вопросы:
- Какая цепочка выполняется дольше всего?
- Какая роль тратит больше токенов?
- Какой runner чаще падает?
- Какова средняя стоимость цепочки?

**Почему важно:** Без observability мы не можем приоритизировать оптимизации. Мы не знаем, какая боль самая острая, потому что не измеряем.

**Рекомендация:** Перед Hooks и Typed I/O — добавить `MetricsCollectorInterface` в Domain с простой in-memory реализацией (Infrastructure). Hook-система (Sprint 10) естественным образом использует metrics.

**Оценка:** 1 день, ~4 файла.

### 🟡 Боль 2: ChainDefinitionVo — всё ещё God-VO (483 LOC)

**Проблема:** `ChainDefinitionVo` содержит 17 параметров в конструкторе, 3 фабричных метода (`createFromSteps`, `createFromDynamic`, `createFromConditionalSteps`), геттеры для static/dynamic/conditional полей одновременно. Это violates ISP (Interface Segregation) — ConditionalExecutionStrategy видит `getFacilitator()`, который ей не нужен.

Roadmap упоминал ChainDefinitionVo split в Sprint 4 (AI#10) — был создан `SharedChainDefinitionVo`, но **оригинальный `ChainDefinitionVo` не стал легче** (483 LOC). Split не завершили.

**Рекомендация:** Завершить split — выделить `StaticChainDefinitionVo`, `DynamicChainDefinitionVo`, `ConditionalChainDefinitionVo` с общим `ChainDefinitionInterface`. Это заложено в ADR-008 (Shared Kernel Contract), но не реализовано.

**Оценка:** 1.5 дня, ~15 файлов.

### 🟢 Боль 3: Integration-паттерн не протестирован на Dynamic split

**Проблема:** Integration-паттерн (физический split модулей через Integration Service) валидирован на Static + Conditional. Dynamic всё ещё в Orchestrator. Roadmap говорит: «решение о Dynamic split — после Sprint 8 по результатам Conditional Branching». Sprint 8 завершён — **решение не принято**.

Если Integration-паттерн треснет на Dynamic (а Dynamic — самый сложный модуль с 9 Domain-сервисами), откат будет болезненным.

**Рекомендация:** Принять решение: остаётся ли Dynamic в Orchestrator навсегда, или планируется split? Записать как ADR или обновить OQ-6 в roadmap.

**Оценка:** 2 часа (решение + ADR).

### 🟡 Боль 4: Нет возобновления (resume) для static/conditional цепочек

**Проблема:** Обе стратегии возвращают `LogicException('Static chain does not support resume.')` / `LogicException('Conditional chain does not support resume.')`. Только Dynamic поддерживает resume через `ChainSessionLogger`.

Если цепочка из 10 шагов упала на 8-м — всё начинается с начала. Для дорогих LLM-вызовов ($0.50–2.00 за шаг) это реальная потеря денег.

**Рекомендация:** Не реализовывать в этом квартале, но зафиксировать как Q4 candidate. MVP: checkpoint после каждого шага в JSONL (формат уже есть для Dynamic).

**Оценка:** Bookmark на будущее.

---

## 3. Рекомендованный план

### Что я рекомендую вместо текущих Sprint 9–10

Текущий план Sprint 9: Error Classification + Loop Detection + Typed I/O = **3 задачи, 2 из которых overengineering.**

Текущий план Sprint 10: Hooks + Sub-agent ADR + Model Failover = **2 задачи с ценностью, 1 speculative.**

### Предлагаемый Sprint 9 (переименованный): Resilience + Observability

| # | Задача | Источник | Оценка | Обоснование |
|---|---|---|---|---|
| 1 | **Model failover: CB open → trigger fallback** | Sprint 10 #3 (упрощённый) | 1 день | Pain 7/10. Wiring существующих CB + FallbackConfigVo, не новая архитектура |
| 2 | **Error classification: упрощённая по exitCode/timeout** | Sprint 9 #1 (обрезанный) | 0.5 дня | Pain 2/10, но дёшево (30 строк в RetryingAgentRunner). Не retry на FATAL |
| 3 | **MetricsCollectorInterface + in-memory реализация** | Новая задача (упущенная боль) | 1 день | Foundation для observability. Hook-система в Sprint 10 будет использовать его |
| 4 | **ADR: Dynamic split — решение** | OQ-6 roadmap | 2 часа | Закрыть открытый вопрос. Sprint 8 завершён, пора решать |

**Итого: ~3 дня.** Реальная ценность, ноль overengineering.

### Предлагаемый Sprint 10: Hooks + Debt Cleanup

| # | Задача | Источник | Оценка | Обоснование |
|---|---|---|---|---|
| 1 | **Hooks system: post_step MVP** | Sprint 10 #1 (обрезанный) | 1.5 дня | Pain 6/10 для post_step. Только observability hooks, не control flow |
| 2 | **ChainDefinitionVo завершение split** | Упущенная боль #2 | 1.5 дня | God-VO 483 LOC. ISP violation. Заложено в ADR-008, не реализовано |
| 3 | **Resume для static цепочек: ADR** | Упущенная боль #4 | 0.5 дня | Bookmark. ADR фиксирует паттерн checkpoint + resume для static/conditional |

**Итого: ~3.5 дня.**

### Что не делаем

| Задача | Почему не делаем | Когда вернуться |
|---|---|---|
| Loop detection | `maxIterations` уже ограничивает. LLM-text similarity — unreliable. Pain 0/10 | Если появится реальный кейс (unlikely) |
| Typed I/O (JSON Schema) | Нет structured output между шагами. Строить не на чем. Pain 1/10 | Q4 2026 / Q1 2027, после structured output contract |
| Sub-agent ADR | Speculative, нет опыта эксплуатации conditional chains. Pain 1/10 | Q4 Sprint 1 |

---

## 4. Итоговая матрица

| Задача | Pain | Cost | Value/Cost | Вердикт |
|---|---|---|---|---|
| Error classification (упрощённая) | 2/10 | 0.5 дня | Средний | ✅ Делаем (дёшево) |
| Loop detection | 0/10 | 1 день | Нулевой | ❌ Не делаем |
| Typed I/O (JSON Schema) | 1/10 | 1.5+ дня | Отрицательный | ❌ Не делаем |
| Hooks (post_step MVP) | 6/10 | 1.5 дня | Высокий | ✅ Делаем |
| Sub-agent ADR | 1/10 | 1 день | Низкий | ⏳ Q4 |
| Model failover (CB→fallback) | 7/10 | 1 день | Высокий | ✅ Делаем (приоритет #1) |
| **MetricsCollector** (упущенная) | 5/10 | 1 день | Высокий | ✅ Добавляем |
| **ChainDefinitionVo split** (упущенная) | 4/10 | 1.5 дня | Средний | ✅ Добавляем |

---

## Допущения

1. Проект используется в production или close-to-production. Если это prototype/R&D — приоритеты сдвигаются в сторону скорости эксперимента (тогда hooks важнее, чем я оценил).
2. Observability gap — реальная боль для владельца проекта. Если текущего JSONL-audit достаточно — MetricsCollector можно отложить.
3. Dynamic split решение не блокирует Sprint 9 задачи — это architectural bookmark.
4. Hooks MVP scope (только `post_step`) достаточен для первого спринта. `pre_step` hooks — отдельная задача с другим risk profile.

---

*Локи. Покер — это не карты. Это умение вовремя сбросить. Loop detection и Typed I/O — блеф, который не стоит вскрывать за такую цену.*
