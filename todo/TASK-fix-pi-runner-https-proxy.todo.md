---
type: fix
created: 2026-07-03
value: V2
complexity: C1
priority: P1
depends_on:
epic:
author: Тимлид Алекс
assignee: Бэкендер Левша
branch: task/fix-pi-runner-https-proxy
pr:
status: in_progress
---

# TASK-fix-pi-runner-https-proxy: HTTPS-прокси для pi-runner

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
`pi`-runner падает с `403 Forbidden`, когда провайдер модели за Cloudflare-блокировкой
по региону: в `PiAgentRunnerService` вообще нет обработки HTTPS-прокси.

### Варианты или путь решения (Solution Sketch)
Сделать `PiAgentRunnerService` симметричным `CodexAgentRunnerService`: тот же
`HttpsProxyBridge`, методы `buildProcessEnv()`/`createBridgeIfNeeded()`, жизненный цикл
bridge через try/finally. Прокси берётся из `CODEX_HTTP_PROXY`.

### Ожидаемый результат (Expected Result)
`pi` + профиль `openai-codex` запускаются через прокси без 403. Проверки
`phpunit`/`psalm`/`deptrac` зелёные.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда провайдер модели находится за Cloudflare-блокировкой по региону, я хочу, чтобы
`pi`-runner (как и `codex`-runner) прозрачно ходил через HTTPS-прокси из `CODEX_HTTP_PROXY`,
чтобы `pi` + профиль `openai-codex` запускались без `403 Forbidden`.

### Goal (Цель по SMART)
Сделать `PiAgentRunnerService` симметричным `CodexAgentRunnerService`: использовать тот же
`HttpsProxyBridge`, прокидывать переменные окружения прокси в дочерний процесс `pi`, корректно
управлять жизненным циклом bridge (try/finally). Проверки `phpunit`/`psalm`/`deptrac` зелёные.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** `src/Module/AgentRunner/Infrastructure/Service/Pi/PiAgentRunnerService.php`.
- **Текущее поведение:** `pi`-runner вообще не имеет обработки прокси → 403 при работе через Cloudflare.
  `CodexAgentRunnerService` уже решает это через `HttpsProxyBridge` — он же референс.
- **Границы (Out of Scope):** fallback на другого провайдера при ошибках — отдельная backlog-задача
  (fallback предназначен для сбоев провайдера, а не для багов собственного кода).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Импорт `HttpsProxyBridge`, методы `buildProcessEnv()` + `createBridgeIfNeeded()`.
- [ ] Жизненный цикл bridge в `run()` через try/finally (симметрично codex-раннеру).
- [ ] Unit-тесты на сценарии: прокси задан → bridge создан и env прокинут; прокси не задан → bridge не создаётся.

### ⚫ Won't Have (Не будем делать)
- Не добавляем fallback по ошибкам (отдельная задача для genuine provider outages).

## 4. Implementation Plan (План реализации)
1. [x] Перенести логику прокси из `CodexAgentRunnerService` в `PiAgentRunnerService` byte-for-byte.
2. [x] Покрыть unit-тестами (11 новых кейсов в `PiAgentRunnerTest`).

## 5. Definition of Done (Критерии приёмки)
- [x] `pi`-runner прокидывает HTTPS-прокси в дочерний процесс.
- [x] `vendor/bin/phpunit`, `vendor/bin/psalm`, `make deptrac` — зелёные.
- [x] Нет регрессий в codex-раннере.

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Infrastructure/Service/AgentRunner/Pi/
vendor/bin/psalm; make deptrac
```

## 7. Risks and Dependencies (Риски и зависимости)
- `CODEX_HTTP_PROXY` — секрет, значение нигде не светится (только чтение в bridge).

## 8. Sources (Источники)
- Референс: `src/Module/AgentRunner/Infrastructure/Service/Codex/CodexAgentRunnerService.php`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-03 | Тимлид Алекс | Создание задачи (диагностирован 403 Cloudflare из-за отсутствия прокси в pi-раннере). |
