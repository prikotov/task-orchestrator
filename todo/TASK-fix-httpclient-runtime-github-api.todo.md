---
type: fix
created: 2026-06-22
value: V2
complexity: C3
priority: P1
depends_on:
epic:
author: Тимлид Алекс
assignee:
branch:
pr:
status: todo
---

# TASK-fix-httpclient-runtime-github-api: HTTP-клиент в Kernel-процессе не доходит до GitHub API

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Команда `bin/console agent:token prikotov/task-orchestrator` падает с «network error» при запросе к GitHub API
(`GET /repos/.../installation`). При этом тот же HTTP-клиент, созданный вручную (без Symfony Kernel),
работает корректно за ~1 секунду. Проблема — в том, как FrameworkBundle/Kernel собирает и настраивает
HTTP-клиент в скомпилированном контейнере: внутри Kernel-процесса curl и PHP-streams ломаются
(idle timeout за 4мс или «SSL/TLS already set up for this stream»). Команда `agent:token` неработоспособна
в рантайме, хотя код корректен и unit/integration-тесты (на MockHttpClient) зелёные.

### Варианты или путь решения (Solution Sketch)
Расследовать runtime-конфликт Symfony HttpClient × FrameworkBundle × Kernel × PHP 8.4 в нашем окружении.
Гипотезы: (1) FrameworkBundle регистрирует stream wrapper для https, конфликтующий с curl;
(2) опции scoped-клиента (proxy/max_duration) резолвятся иначе через DI; (3) PHP 8.4 + Symfony 8 + ext-curl
несовместимость в CLI-SAPI. Решение: либо явно задать `framework.http_client` с рабочим transport,
либо переключить модуль GitIdentity на NativeHttpClient/явный CurlHttpClient вне DI, либо обойти конфликт.
Также отдельный микро-баг: `GitHubHttpComponent` передаёт `'body' => null` для GET-запросов — нужно
передавать `body` только для POST (Content-Length: 0 на GET иногда вешает сервер).

### Ожидаемый результат (Expected Result)
`bin/console agent:token <owner>/<repo>` реально получает installation token от GitHub API
(в рантайме, не только в тестах на MockHttpClient). Unit/integration тесты остаются зелёными.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда я запускаю `bin/console agent:token`, я хочу, чтобы команда реально дошла до GitHub API и
выдала валидный токен, чтобы агент работал от имени GitHub App (бизнес-цель задачи bot-account).

### Goal (Цель по SMART)
Найти и устранить runtime-причину, по которой Symfony HttpClient внутри Kernel-процесса не выполняет
запрос к GitHub API (idle timeout / SSL-stream conflict), при том что тот же клиент standalone работает.
Плюс закрыть микро-баг `'body' => null` на GET-запросах в `GitHubHttpComponent`.

## 2. Context and Scope (Контекст и Границы)

* **Где делаем:**
  - `src/Module/GitIdentity/Infrastructure/Component/GitHub/GitHubHttpComponent.php` (body-for-GET фикс).
  - `src/Module/GitIdentity/Resource/config/services.yaml` (scoped http_client.git_identity).
  - `config/packages/framework.yaml` (возможно `framework.http_client` с явным transport).
  - Возможно `src/Kernel.php` / окружение.
* **Текущее поведение (факты расследования 2026-06-22):**
  - `HttpClient::create([], 6)` + Monolog logger в standalone-скрипте → STATUS 200 за 1с (работает).
  - Точная копия compiled `http_client.transport` standalone → работает (200).
  - Тот же scoped client через `new Kernel('prod', false)->boot()` → `Idle timeout reached` за 4мс,
    curl даже не устанавливает TCP-соединение (`connect_time: 0`, `primary_ip: ""`, `closing connection #0`).
  - NativeHttpClient в Kernel-процессе → `fopen(): SSL/TLS already set-up for this stream`.
  - Unit/integration тесты на MockHttpClient — зелёные (проблема только в реальном рантайме).
  - App `prikotov-agent` (ID 4115617) установлен на `task-orchestrator`, PEM валиден — ручной JWT-запрос
    к `/repos/.../installation` через HttpClient::create() возвращает 200 с installation_id=141893171.
* **Границы (Out of Scope):**
  - Не трогать бизнес-логику модуля GitIdentity (Domain/Application/VO) — она корректна.
  - Не переписывать на другой HTTP-стек (остаёмся на Symfony HttpClient по конвенции external-service).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] **Runtime-фикс**: `bin/console agent:token prikotov/task-orchestrator` реально получает токен от
      GitHub API в рантайме (не Mock). Подтверждение: `gh api user --jq .login` (после eval) → `prikotov-agent[bot]`.
- [ ] **body-for-GET фикс**: `GitHubHttpComponent::request()` передаёт `body` опцию только для POST
      (для GET — не передаёт, чтобы не плодить `Content-Length: 0`).
- [ ] Найдена и задокументирована первопричина Kernel-runtime конфликта (в задаче/ADR).

### 🟡 Should Have (Желательно)

- [ ] Integration-тест на реальный smoke-запрос (если возможно без сети — через test-httpbin или
      markTestSkipped при отсутствии сети).

### ⚫ Won't Have (Не будем делать)

- Переход на другой HTTP-клиент (guzzle и пр.) — остаёмся на Symfony HttpClient.
- Отказ от Kernel/ModuleSystem (недавний переход сохраняется).

## 4. Implementation Plan (План реализации)

*Заполняется исполнителем перед стартом:*

1. [ ] Воспроизвести баг минимально (bin/console vs standalone — найти delta).
2. [ ] Проверить гипотезы: stream wrapper registration (Symfony), `framework.http_client` config,
      ext-curl поведение в PHP 8.4 CLI, scoped-options resolve.
3. [ ] Применить фикс (transport override / stream-wrapper guard / опции scoped-клиента).
4. [ ] body-for-GET фикс в GitHubHttpComponent.
5. [ ] Проверки: phpunit, psalm, deptrac, **runtime smoke `bin/console agent:token`**.

## 5. Definition of Done (Критерии приёмки)

- [ ] `bin/console agent:token prikotov/task-orchestrator --format=plain` печатает валидный installation token.
- [ ] `gh api user --jq .login` после eval → `prikotov-agent[bot]`.
- [ ] body-for-GET фиксирован, тест на это есть.
- [ ] phpunit/psalm/deptrac зелёные.
- [ ] Первопричина описана в комментарии задачи или ADR.

## 6. Verification (Самопроверка)

```bash
# Runtime-проверка (требует реальной сети + настроенного App)
eval "$(bin/console agent:token prikotov/task-orchestrator --format=env)"
gh api user --jq .login   # → prikotov-agent[bot]

# Тесты
vendor/bin/phpunit tests/Unit/Module/GitIdentity
vendor/bin/psalm
make deptrac
```

## 7. Risks and Dependencies (Риски и зависимости)

- **Риск:** проблема может быть в самом Symfony 8 / PHP 8.4 / ext-curl окружении — тогда фикс
  потребует обхода (явный NativeHttpClient вне DI, или downgrade). Mitigation: зафиксировать окружение
  в задаче, проверить на чистом PHP.
- **Зависимость:** App `prikotov-agent` уже создан и установлен (ID 4115617, PEM в `secrets/agent-identity/`,
  конфиг в `.env.local`) — готово для runtime-проверки.

## 8. Sources (Источники)

- Расследование тимлида 2026-06-22 (diag через reflection на compiled container).
- Конвенция `docs/conventions/core_patterns/external-service.md` (HttpClientInterface обязателен).

## 9. Comments (Комментарии)

Эта задача — **блокер для закрытия бизнес-DoD задачи `TASK-chore-bot-account-for-agent`**: код готов
и смёржен (PR #275), unit/integration тесты зелёные, но runtime-команда не работает из-за
Symfony-HttpClient-в-Kernel конфликта. Бизнес-цель (PR от App, Approve без `--admin`) недостижима,
пока эта runtime-проблема не решена.

До решения — workaround `gh pr merge --admin` остаётся.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-22 | Тимлид Алекс | Создание задачи. Обнаружено при эксплуатационной проверке задачи bot-account (PR #275 смёржен, runtime agent:token падает). App prikotov-agent настроен, проблема — в HTTP-клиенте внутри Kernel-процесса. |
