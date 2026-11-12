---
type: test
created: 2026-11-12 10:15:00 (1794453300)
due: 
started: 
completed: 
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Бэкендер Тони (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: todo
---

# TASK-test-watchsubagent-stall-flaky: Стабилизировать флаки-тест WatchSubagentScriptTest про stall/soft-timeout

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Интеграционный тест `WatchSubagentScriptTest::stallDoesNotKillActiveProcessAndWaitsUntilSoftTimeout` периодически падает при запуске полного набора `vendor/bin/phpunit` под нагрузкой (2026-11-12, предрелизный прогон релиза v0.15.1 — assertion на строке 718 `assertStringContainsString('reason=soft_timeout', …)`; повторные прогоны и CI зелёные). Ложные падения замедляют релизы и подрывают доверие к прогонам: непонятно, реальная это поломка скрипта `watch-subagent.sh` или гонка таймингов в тесте.

### Варианты или путь решения (Solution Sketch)
- Детерминировать тест по прецеденту `TASK-fix-static-chain-metrics-flaky-test`: убрать зависимость от реального тайминга (управлять watcher'ом/фейковым раннером детерминированно, например через событие вместо ожидания интервала), либо — если гонка в самом скрипте — устранить её в `watch-subagent.sh` с сохранением поведенческого контракта (active-процесс не убивается stall'ом).

### Ожидаемый результат (Expected Result)
- Тест стабильно проходит при многократных полных прогонах `vendor/bin/phpunit` под нагрузкой; ложные падения `reason=soft_timeout` прекращаются, поведенческий контракт скрипта сохранён.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **Job Story:** Когда я прогоняю тесты перед релизом под нагрузкой, я хочу, чтобы тест liveness-гейта не падал от тайминговой гонки, чтобы красный прогон означал реальную поломку, а не шум.

### Цель по SMART (Goal)
- Устранить флакимость `stallDoesNotKillActiveProcessAndWaitsUntilSoftTimeout` в `tests/Integration/Docs/Agents/Skills/RunSubagent/WatchSubagentScriptTest.php`: 10+ подряд полных прогонов `vendor/bin/phpunit` под нагрузкой без падений этого теста, поведение скрипта не изменено.

## 2. Контекст и Границы (Context and Scope)

- **Где делаем:** `tests/Integration/Docs/Agents/Skills/RunSubagent/WatchSubagentScriptTest.php`; при подтверждённой гонке в скрипте — `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`.
- **Текущее поведение:** тест запускает скрипт с `-s 4 -t 2 -m 30`, фейковый раннер в busy-loop (`FAKE_RUNNER_HANG_ACTIVE=1`), `WATCH_WATCHER_INTERVAL=1`; ожидает в `run.log` строк `live=active` и `reason=soft_timeout` и отсутствия `reason=stall`. Под нагрузкой один из прогонов не укладывается в тайминг — `reason=soft_timeout` не появляется (падение assertion на строке 718).
- **Связь с реестром:** `docs/agents/team-retro/RETRO-ROADMAP.md` — запись `N-4` (флаки именно этого теста, счётчик 1 от ретро 2026-08-18) и запись «Тайминговые интеграционные тесты флакат под нагрузкой CI» (правило: при новом повторе — детерминировать конкретный тест по прецеденту `TASK-fix-static-chain-metrics-flaky-test`); повтор зафиксирован в этой сессии 2026-11-12.
- **Out of scope:** рефакторинг phpmd-замечаний; остальные тесты класса (large-stream, stall-kills-idle) — если их гонка не подтвердится; подключение `phar-e2e` к CI (владелец отклонял 2026-11-03).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Тест детерминирован: 10+ подряд полных прогонов `vendor/bin/phpunit` под параллельной нагрузкой без его падений.
- [ ] Поведенческий контракт сохранён: active-процесс переживает stall и завершается по `soft_timeout`; тест продолжает это проверять (не ослаблять assertion до пустого ожидания).
- [ ] Если гонка обнаружена в `watch-subagent.sh` — исправление в скрипте с сохранением контракта и покрытием изменений.
### 🟡 Should Have (Желательно)
- [ ] Уточнить, не флакают ли соседние тесты класса (`stallKillsIdleProcessEvenWithLivenessGate`, large-stream) той же причиной.
### ⚫ Won't Have (Не будем делать)
- Подключение `phar-e2e` к CI; изменение дефолтов таймингов скрипта «чтобы тест проходил» без подтверждённой причины.

## 4. План реализации (Implementation Plan)
1. [ ] Воспроизвести флакимость: серии полных прогонов под нагрузкой (например, параллельный `make check` или CPU-нагрузка), зафиксировать фактический `run.log` падения.
2. [ ] Локализовать причину: гонка таймингов теста/скрипта или регрессия скрипта (сначала данные, потом гипотезы — по правилу расследования).
3. [ ] Детерминизировать тест (или устранить гонку в скрипте), сохранив поведенческие assertion'ы.
4. [ ] Прогнать 10+ полных `vendor/bin/phpunit` под нагрузкой, `vendor/bin/psalm`, `make md-links`.

## 5. Критерии приёмки (Definition of Done)
- [ ] 10+ подряд полных прогонов `vendor/bin/phpunit` под нагрузкой без падения целевого теста.
- [ ] Assertion'ы контракта (`live=active`, `reason=soft_timeout`, нет `reason=stall`) сохранены.
- [ ] `vendor/bin/psalm` зелёный; CI зелёный.
- [ ] Записи `RETRO-ROADMAP.md` (`N-4`, «Тайминговые интеграционные тесты…») переведены в `resolved` с ссылкой на PR.

## 6. Самопроверка (Verification)
```bash
# Серия прогонов под нагрузкой
for i in $(seq 1 10); do vendor/bin/phpunit tests/Integration/Docs/Agents/Skills/RunSubagent/WatchSubagentScriptTest.php || break; done
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Риски и зависимости (Risks and Dependencies)
- Симптом может повторяться из-за общей тайминговой природы класса (запись реестра «Тайминговые интеграционные тесты флакат под нагрузкой CI») — если причина окажется общей, сузить объём или связать с классом.
- Исправление таймингов скрипта может замедлить реальные запуски — согласовать с владельцем.

## 8. Источники (Sources)
- `tests/Integration/Docs/Agents/Skills/RunSubagent/WatchSubagentScriptTest.php` (строки 694–720).
- `docs/agents/skills/run-subagent/scripts/watch-subagent.sh` (watcher, `reason=soft_timeout`, liveness-gate).
- `docs/agents/team-retro/RETRO-ROADMAP.md` — записи `N-4` и «Тайминговые интеграционные тесты флакат под нагрузкой CI».
- `docs/agents/team-retro/2026-08-18_10-35-codex-idle-kill-investigation.md` (N-4), `2026-11-03_05-40-release-v0100-phar-e2e-flaky.md` (класс, PR #453).
- Прецедент: `todo/done/TASK-fix-static-chain-metrics-flaky-test.todo.md` (PR #234).

## 9. Комментарии (Comments)
- Наблюдение 2026-11-12: падение `assertStringContainsString('reason=soft_timeout', …)` (строка 718) на полном прогоне в предрелизной проверке v0.15.1; повторный полный прогон и отдельный запуск теста — зелёные; CI всех четырёх PR сессии — зелёные. Доказательство — журнал сессии 2026-11-12 (выход phpunit в транскрипте).
- Создана по запросу владельца («заведи») после отчёта о флаки в ходе релиза v0.15.1. Связь с реестром: `docs/agents/team-retro/RETRO-ROADMAP.md`.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-12 10:15:00 (1794453300) | Тимлид Алекс (pi) | Создание задачи |
