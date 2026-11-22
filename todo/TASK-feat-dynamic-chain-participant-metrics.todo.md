---
type: feat
created: 2026-11-21 03:10:22 (1795230622)
due: 
started: 2026-11-22 11:02:52 (1795345372)
completed: 
cancelled: 
value: V3
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Арагорн (pi)
assignee: Бэкендер Левша (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/participant-metrics
pr: 
status: in_progress
---

# TASK-feat-dynamic-chain-participant-metrics: Метрики участников в отчётах dynamic-цепочек (модель, выступления, время, токены, кэш)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Владелец запрашивает в финальных протоколах brainstorm-сессий таблицу «Участники» с колонками: модель (provider/model, thinking), число выступлений, суммарное время шагов, токены in/out, стоимость и **доля кэша (%)**.
- Сейчас этого собрать из артефактов сессии нельзя полностью:
  - `audit.jsonl` / `session.json` пишут `role`, `input_tokens`, `output_tokens`, `cost`, `duration_ms` — но **не пишут модель шага и кэш-токены**;
  - pi CLI в `--mode json` отдаёт `usage.cacheRead` / `usage.cacheWrite` в `message_end` — JSONL-парсер AgentRunner эти поля отбрасывает;
  - модель шага восстанавливается только вручную из host `config/chains.yaml` (role → command_profile → `--provider/--model/--thinking`).
- Прецедент: сессия `var/sessions/brainstorm/2026-11-20_15-11-26` (host TasK, 3 итерации, 53 раунда) — таблицу участников пришлось собирать пост-обработкой вне оркестратора, кэш-доля осталась «н/д».

### Варианты или путь решения (Solution Sketch)
- Парсер JSONL (`Module/AgentRunner/Infrastructure/...Parser`): сохранять `usage.cacheRead` / `usage.cacheWrite` (и провайдера/модель из `message_end.api/provider/model`) в шаг-результат.
- `ChainSessionWriter` / `audit.jsonl` / `session.json`: добавить в step-записи поля `model`, `provider`, `cache_read_tokens`, `cache_write_tokens`.
- Отчёт (`agent:orchestrate --report-file`, text-формат): секция «Participants metrics» — агрегация по роли: model, speeches (шаги с `is_facilitator=false`), facilitator steps, суммарные duration/tokens/cost, `cache_hit_rate = cache_read / (cache_read + input)` .
- Dynamic-цикл: в `DynamicLoopSessionStateVo.rounds[]` дополнить записи теми же полями (обратимо: старые сессии без полей — рендер «н/д», без ломания resume).

### Ожидаемый результат (Expected Result)
- Финальный протокол любой dynamic-сессии содержит самодостаточную таблицу «Участники» (модель, выступления, время, токены, кэш %, стоимость) — без ручной пост-обработки артефактов сессии.

## 1. Концепция и Цель (Concept and Goal)
- Любой отчёт dynamic-цепочки (brainstorm и будущие) самодостаточен для пост-анализа: кто на какой модели работал, сколько выступал, сколько времени/токенов/денег потребил и какова эффективность кэша.

## 2. Контекст и Границы (Context and Scope)
- В скоупе: JSONL-парсер, session/audit-логи, text-отчёт `agent:orchestrate`, resume-совместимость (старые session.json без новых полей).
- Вне скоупа: web-UI, отчёты статических (non-dynamic) цепочек, cost-прайсинг кэша (если провайдер не отдаёт цену кэша — показывать токены без стоимости кэша).

## 3. Требования, MoSCoW (Requirements)

### 🔴 Обязательно (Must Have)
- [ ] Кэш-токены и модель шага попадают в `audit.jsonl` и `session.json.rounds[]`.
- [ ] Text-отчёт содержит таблицу участников с колонками: участник, модель (provider/model, thinking), выступлений, шагов фасилитатора, время, tok in/out, cache_read, cache %, cost.
- [ ] Resume старых сессий (без полей) не падает; в отчёте «н/д».

### 🟡 Желательно (Should Have)
- [ ] Та же таблица в json-формате отчёта (`--report-format=json`).

### ⚫ Не будем делать (Won't Have)
- [ ] Ретроактивный пересчёт старых сессий.

## 4. План реализации (Implementation Plan)
1. Парсер: извлечь `usage.cacheRead/cacheWrite`, `provider/model` из финального `message_end`.
2. Протянуть поля через `AgentResultVo` → `ChainSessionWriter` → `audit.jsonl`/`session.json`.
3. Агрегатор отчёта: группировка по роли, расчёт cache %, рендер таблицы.
4. Тесты: фиксация JSONL-стримов (с кэшем и без), golden-тест таблицы отчёта, resume-тест старой сессии.

## 5. Критерии приёмки (Definition of Done)
- `make check` в репозитории task-orchestrator зелёный.
- На новой сессии brainstorm таблица участников в отчёте заполнена включая кэш-%; на resume старой сессии — «н/д» без ошибок.

## 6. Самопроверка (Verification)
```bash
make check                                            # статический анализ + тесты
php vendor/bin/todo-md validate todo/TASK-feat-dynamic-chain-participant-metrics.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- Не все провайдеры/раннеры отдают кэш-токены (codex/ollama) — колонка «н/д».
- Формат `usage` может отличаться между версиями pi — парсер должен быть толерантен к отсутствию полей.

## 8. Источники (Sources)
- Прецедент: host TasK, сессия `var/sessions/brainstorm/2026-11-20_15-11-26`, ручная пост-обработка 2026-11-21.
- Сырой стрим pi: `{"type":"message_end", ..., "usage":{"cacheRead":...,"cacheWrite":...}}`.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-21 03:10:22 (1795230622) | Тимлид Арагорн (pi) | Создание задачи |
| 2026-11-21 | Тимлид (pi) | Приведение структуры к шаблону проекта: добавлены Expected Result и Verification, требования переведены в MoSCoW-подсекции, формат author нормализован; содержание сохранено |
