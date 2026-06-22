---
type: fix
created: 2026-06-22
updated: 2026-06-22
value: V3
complexity: C2
priority: P1
depends_on:
epic:
author: prikotov
assignee: Тимлид Алекс
branch: fix/httpclient-runtime-github-api
pr:
status: in_progress
---

# TASK-fix-httpclient-runtime-github-api: env-processor синтаксис ломал timeout в scoped http_client

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Команда `bin/console agent:token` падала с «network error» (idle timeout за ~4мс, curl даже не устанавливал
TCP) в рантайме, хотя тот же HTTP-клиент standalone работал. Причина оказалась не в Symfony Kernel/curl
(как первоначально предполагалось), а в **неправильном синтаксисе env-processor** в `Resource/config/services.yaml`:
`%env(int:default::30:AGENT_REQUEST_TIMEOUT_SECONDS)%` резолвился в `0` вместо `30`. `timeout: 0` =
бесконечный неблокирующий режим → мгновенный idle-timeout. То же с `max_duration`, `api_version`, `user_agent`
(все NULL/0 → scoped-клиент malformed).

### Варианты или путь решения (Solution Sketch)
Правильный Symfony-синтаксис каскада env-processors с **literal-default**: `%env(default::30:int:VAR)%`
(processor `default` с literal-аргументом `30` через двойное двоеточие, затем `int`-cast).
Проверено в рантайме: параметры резолвятся корректно, scoped-клиент получает таймауты и заголовки.

### Ожидаемый результат (Expected Result)
`bin/console agent:token <owner>/<repo>` реально получает installation token от GitHub API в рантайме.
Бизнес-DoD задачи bot-account (PR от App, Approve без `--admin`) достижим.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда я запускаю `bin/console agent:token`, я хочу, чтобы команда реально дошла до GitHub API и выдала
валидный токен, чтобы агент работал от имени GitHub App.

### Goal (Цель по SMART)
Исправить env-processor синтаксис параметров scoped http_client в GitIdentity; закрыть мини-баг
`body => null` на GET-запросах в GitHubHttpComponent. Подтверждение: `bin/console agent:token` выдаёт
валидный токен, `gh api user` после eval → `prikotov-agent[bot]`.

## 2. Context and Scope (Контекст и Границы)

* **Где делаем:**
  - `src/Module/GitIdentity/Resource/config/services.yaml` — env-processor синтаксис (главный фикс).
  - `src/Module/GitIdentity/Infrastructure/Component/GitHub/GitHubHttpComponent.php` — body-for-GET фикс.
* **Факты расследования (2026-06-22):**
  - `getParameter('module.git_identity.request_timeout_seconds')` возвращал `0` (должен 30), `api_version` → `NULL`.
  - CurlHttpClient с `timeout: 0` → idle-timeout мгновенно (неблокирующий режим без ожидания ответа).
  - Корректный scoped-клиент standalone (с `timeout:30`) → STATUS 200 за 1с.
  - App `prikotov-agent` (ID 4115617) установлен, PEM валиден, ручной JWT-запрос → 200, installation_id=141893171.
* **Границы (Out of Scope):**
  - Бизнес-логика модуля GitIdentity не трогается (Domain/Application/VO корректны).
  - Глубокое расследование Symfony Kernel × curl не требуется — реальная причина локализована в env-processor.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [x] **env-processor фикс**: параметры scoped http_client резолвятся корректно через
      `%env(default::<literal>:<processor>:VAR)%`.
- [x] **body-for-GET фикс**: `GitHubHttpComponent::request()` передаёт `body` только для POST.
- [x] Runtime-подтверждение: `bin/console agent:token` выдаёт валидный installation token.

### ⚫ Won't Have (Не будем делать)

- Отказ от Symfony HttpClient (остаёмся по конвенции external-service).

## 4. Implementation Plan (План реализации)

1. [x] Локализация: probe через reflection на compiled container → параметры = 0/NULL.
2. [x] env-processor фикс (default::literal:int каскад).
3. [x] body-for-GET фикс в GitHubHttpComponent.
4. [x] Проверки: phpunit, psalm, deptrac, runtime smoke `bin/console agent:token`.

## 5. Definition of Done (Критерии приёмки)

- [x] `bin/console agent:token prikotov/task-orchestrator --format=plain` печатает валидный токен.
- [x] body-for-GET фиксирован.
- [x] phpunit/psalm/deptrac зелёные.
- [ ] Первопричина (env-processor синтаксис) описана — данный файл задачи.

## 6. Verification (Самопроверка)

```bash
eval "$(bin/console agent:token prikotov/task-orchestrator --format=env)"
gh api user --jq .login   # → prikotov-agent[bot]
vendor/bin/phpunit tests/Unit/Module/GitIdentity
vendor/bin/psalm
make deptrac
```

## 7. Risks and Dependencies (Риски и зависимости)

- **Глубокий Symfony-bug отсутствует**: проблема была локальной (env-синтаксис), не инфраструктурной.
- **Зависимость:** App `prikotov-agent` настроен (ID 4115617, PEM в `secrets/agent-identity/`, конфиг в `.env.local`).

## 8. Sources (Источники)

- Symfony docs: Environment Variables (env processors, cascade syntax, `default::` literal).
- Расследование тимлида 2026-06-22 (reflection на compiled container — параметры 0/NULL).

## 9. Comments (Комментарии)

Первоначальная гипотеза (глубокий Symfony Kernel × curl конфликт в PHP 8.4) оказалась неверной —
реальная причина банальна: неправильный env-processor синтаксис. Урок: прежде чем обвинять
framework/окружение, проверить значения параметров через `getParameter()` на compiled container.

Связано с PR #275 (бот-account) — этот фикс закрывает эксплуатационный DoD.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-22 | Тимлид Алекс | Создание задачи (изначально как блокер runtime, C3). |
| 2026-06-22 | Тимлид Алекс | Реальная причина локализована: env-processor синтаксис (не Symfony-bug). Фикс готов, сложность снижена до C2. Runtime работает, токен получен. |
