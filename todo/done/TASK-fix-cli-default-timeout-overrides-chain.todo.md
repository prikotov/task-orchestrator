---
type: fix
created: 2026-06-01
value: V2
complexity: C2
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер Левша
branch: task/fix-cli-default-timeout-overrides-chain
pr: "267"
status: done
---

# TASK-fix-cli-default-timeout-overrides-chain: Не затирать chain timeout CLI default-значениями

## 0. Простое описание (Human Brief)
### Проблема простыми словами (Problem)
В dynamic chain `timeout` и `max_time` из `chains.yaml` могут не примениться, если пользователь не передал CLI-опции явно: `agent:orchestrate` задаёт default `--timeout=600` и `--max-time=3600`, а execution strategy считает эти значения пользовательским override.

### Варианты или путь решения (Solution Sketch)
Отличать явно переданные CLI-опции от default-значений Symfony Console. Если опция не передана, в `OrchestrateChainCommand` нужно отправлять `null`, чтобы execution strategy взяла `timeout`/`max_time` из chain config.

### Ожидаемый результат (Expected Result)
Chain-level `timeout` и `max_time` из YAML работают как заявлено, а CLI default используется только если значение не задано ни в CLI, ни в config.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда я задаю `timeout` и `max_time` в `chains.yaml`, я хочу, чтобы эти значения реально управляли dynamic chain без обязательного дублирования CLI-опций, чтобы длинные brainstorm/resume-сессии не обрывались из-за неявного default.

### Goal (Цель по SMART)
Исправить CLI mapping для `agent:orchestrate` и покрыть его тестом: при отсутствии явных `--timeout`/`--max-time` execution получает `null` и применяет config-level значения.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php`, тесты CLI/use case mapping.
* **Текущее поведение:** CLI option default `600`/`3600` всегда передаётся как command override; `DynamicExecutionStrategy` использует `$command->timeout ?? $config->getTimeout()` и `$command->maxTime ?? $config->getMaxTime()`.
* **Наблюдение из stocks2:** `portfolio-architecture-brainstorm` имел `timeout: 1800`, но audit зафиксировал `Agent timed out after 600 seconds` на final synthesis; resume с явным `--timeout=1800` завершился успешно.
* **Границы (Out of Scope):** не менять модель dynamic loop, не менять agent runner timeouts, не запускать реальные LLM chains в тестах.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `--timeout` без явной передачи не должен затирать `timeout` из chain config.
- [ ] `--max-time` без явной передачи не должен затирать `max_time` из chain config.
- [ ] Явные CLI `--timeout` и `--max-time` продолжают иметь приоритет над config.
- [ ] Добавлен тест на precedence: CLI explicit > chain config > hard default.
- [ ] Документация CLI/chains обновлена с описанием precedence.

### 🟡 Should Have (Желательно)
- [ ] Audit/session invocation явно показывает effective timeout/max_time и источник значения (`cli`, `chain`, `default`).
- [ ] Resume audit содержит marker `resume_start`/`attempt`/`resumed_from` без неоднозначности step ids.

### 🟢 Could Have (Опционально)
- [ ] Добавить warning, если config timeout есть, но effective timeout меньше config timeout.

### ⚫ Won't Have (Не будем делать)
- [ ] Не меняем shell runner implementation.
- [ ] Не добавляем реальные network/LLM вызовы в тесты.

## 4. Implementation Plan (План реализации)
1. [ ] Проверить, как Symfony Console позволяет определить явность передачи option (`hasParameterOption`).
2. [ ] Передавать `null` в `OrchestrateChainCommand`, если `--timeout`/`--max-time` не были указаны явно.
3. [ ] Сохранить backward-compatible hard defaults в execution strategy.
4. [ ] Добавить тест на precedence для initial run и resume.
5. [ ] Обновить docs/guide для CLI/chains.

## 5. Definition of Done (Критерии приёмки)
- [ ] Config-level `timeout`/`max_time` применяются без явных CLI-опций.
- [ ] Explicit CLI options продолжают переопределять config.
- [ ] Resume использует тот же precedence.
- [ ] Tests/docs обновлены.

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Поведение CLI default могло использоваться неявно; нужно проверить backward compatibility для chains без `timeout`/`max_time`.
- Улучшение audit/resume observability может потребовать отдельной follow-up задачи, если diff разрастётся.

## 8. Sources (Источники)
- [ ] `apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php`
- [ ] `src/Module/DynamicLoop/Integration/Service/ChainExecution/DynamicExecutionStrategy.php`
- [ ] stocks2 `docs/retrospectives/2026-05-25-portfolio-architecture-brainstorm-chain.md`

## 9. Comments (Комментарии)
Задача заведена по результатам анализа stocks2 brainstorm stability: причиной 600s timeout, вероятнее всего, является CLI default precedence, а не `chains.yaml`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-01 | Тимлид (Алекс) | Создание upstream task по итогам анализа stocks2 |
