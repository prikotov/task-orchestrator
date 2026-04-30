---
type: feat
created: 2026-04-28
value: V3
complexity: C2
priority: P1
depends_on: TASK-agent-runner-proxy
epic:
author: Бэкендер Левша (backend_developer_levsha)
assignee: Бэкендер Левша (backend_developer_levsha)
branch: task/codex-https-proxy-bridge
pr: '#108'
status: done
---

# TASK-codex-https-proxy-bridge: PHP HTTPS→HTTP прокси-мост для CodexAgentRunner

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда upstream-прокси требует TLS-соединение (HTTPS-прокси), а codex CLI (reqwest 0.12.28) не поддерживает схему `https://` для прокси, я хочу, чтобы CodexAgentRunner автоматически запускал локальный HTTP-прокси-мост на PHP, чтобы codex мог работать через HTTPS-прокси без внешних зависимостей.

### Goal (Цель по SMART)
Реализовать PHP-класс `HttpsProxyBridge`, который запускает локальный HTTP-прокси на `127.0.0.1:<random_port>`, пересылающий CONNECT-запросы через TLS на upstream HTTPS-прокси. `CodexAgentRunner` автоматически запускает мост при обнаружении `CODEX_HTTP_PROXY` с `https://` схемой и корректно останавливает его после завершения процесса codex.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php` — новый класс
    *   `src/Module/AgentRunner/Infrastructure/Service/Codex/CodexAgentRunner.php` — интеграция моста
    *   `tests/Unit/Infrastructure/Service/AgentRunner/Codex/HttpsProxyBridgeTest.php` — тесты моста
*   **Текущее поведение:** При `CODEX_HTTP_PROXY=https://user:pass@host:port` codex получает ошибку «Proxy URL scheme not supported» (reqwest не поддерживает HTTPS-прокси). При `http://` схеме — «HTTP CONNECT response missing status code» (upstream требует TLS).
*   **Границы (Out of Scope):**
    *   SOCKS-прокси
    *   Поддержка нескольких upstream-прокси
    *   Кэширование соединений (connection pool)
    *   Логирование трафика (mitm)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `HttpsProxyBridge` — PHP-класс, запускающий локальный HTTP-прокси (TCP-сервер на `127.0.0.1`)
- [ ] Мост принимает HTTP CONNECT-запросы и пересылает их через TLS на upstream HTTPS-прокси
- [ ] Авторизация на upstream-прокси через `Proxy-Authorization: Basic` (credentials из URL)
- [ ] `CodexAgentRunner` автоматически определяет `https://` схему в `CODEX_HTTP_PROXY` и запускает мост
- [ ] После завершения процесса codex мост корректно останавливается (SIGTERM / stop)
- [ ] Мост использует случайный свободный порт (не конфликтует с другими процессами)
- [ ] Unit-тесты: мок-сервер + проверка конвертации URL, старт/стоп, передача credentials

### 🟡 Should Have (Желательно)
- [ ] Timeout на соединение с upstream-прокси (15 сек по умолчанию)
- [ ] Graceful shutdown: завершение активных соединений перед остановкой
- [ ] PHPDoc и документация в `docs/` по настройке HTTPS-прокси

### 🟢 Could Have (Опционально)
- [ ] Конфигурируемый timeout через env (`CODEX_PROXY_BRIDGE_TIMEOUT`)
- [ ] Метрики: количество CONNECT-запросов, ошибки

### ⚫ Won't Have (Не будем делать)
- [ ] SOCKS-прокси
- [ ] Поддержка прокси с клиентскими сертификатами (mTLS)
- [ ] GUI / мониторинг
- [ ] Внешние зависимости (только PHP stream sockets + OpenSSL)

## 4. Implementation Plan (План реализации)

### Reverse Briefing (подтверждение понимания задачи)

Codex CLI использует reqwest 0.12.28, который не поддерживает HTTPS-схему для прокси.
Upstream-прокси (например, `***:***`) принимает только TLS-соединения.
Нужен локальный HTTP-прокси-мост на PHP, который:
1. Слушает на `127.0.0.1:<random_port>` как обычный HTTP-прокси.
2. Принимает CONNECT-запросы от codex.
3. Устанавливает TLS-соединение с upstream HTTPS-прокси.
4. Пересылает CONNECT-запрос с `Proxy-Authorization: Basic`.
5. Проксирует данные bidirectionally между codex и целевым сервером через upstream.

### Архитектурные решения

1. **HttpsProxyBridge** — Infrastructure-сервис без интерфейса (внутренний для CodexAgentRunner, не доменный контракт).
2. Запуск через `proc_open` (отдельный PHP-процесс), а не `pcntl_fork` — более надёжно и переносимо.
3. Скрипт моста — inline PHP-код, передаваемый через `proc_open` как `php -r '...'` — avoids отдельного файла.
4. Port assignment: `stream_socket_server('tcp://127.0.0.1:0')` — ОС назначит свободный порт.
5. Communication parent↔child: bridge пишет port number в stdout при старте, parent читает.

### Пошаговый план

#### Step 1: HttpsProxyBridge (Infrastructure)
- **Файл:** `src/Module/AgentRunner/Infrastructure/Service/Codex/HttpsProxyBridge.php`
- **Класс:** `HttpsProxyBridge` (final, не readonly — содержит состояние процесса)
- **Зависимости:** нет внешних (только PHP stream functions + pcntl для сигналов)
- **API:**
  - `__construct(string $upstreamProxyUrl)` — парсит URL, извлекает host/port/user/pass
  - `start(): string` — запускает PHP-процесс моста, возвращает `http://127.0.0.1:<port>`
  - `stop(): void` — отправляет SIGTERM дочернему процессу, ждет завершения
  - `isRunning(): bool` — проверяет жив ли процесс
  - `static parseUpstreamUrl(string $url): ?array` — парсит https://user:pass@host:port, возвращает компоненты или null
- **Внутренний метод:** `buildBridgeScript(): string` — генерирует PHP-код моста

#### Step 2: Интеграция в CodexAgentRunner
- **Файл:** `src/Module/AgentRunner/Infrastructure/Service/Codex/CodexAgentRunner.php`
- Изменения в `run()`:
  1. Проверить `CODEX_HTTP_PROXY` на `https://` схему.
  2. Если да — создать `HttpsProxyBridge`, вызвать `start()`, получить локальный URL.
  3. Подставить локальный URL в env вместо оригинального.
  4. Выполнить `process->run()`.
  5. В `finally` — вызвать `bridge->stop()`.
- Изменения в `buildProcessEnv()` — поддержка моста (передача локального URL).

#### Step 3: Unit-тесты
- **Файл:** `tests/Unit/Infrastructure/Service/AgentRunner/Codex/HttpsProxyBridgeTest.php`
- Тест-кейсы:
  1. `parseUpstreamUrl` — корректный HTTPS URL → компоненты
  2. `parseUpstreamUrl` — HTTP URL → null (не нужен мост)
  3. `parseUpstreamUrl` — URL без credentials → компоненты с пустым user/pass
  4. `parseUpstreamUrl` — невалидный URL → null
  5. `start()` — запускает процесс, возвращает `http://127.0.0.1:<port>`
  6. `stop()` — корректно останавливает процесс
  7. `stop()` — безопасен при повторном вызове (idempotent)
  8. Интеграция в CodexAgentRunner — HTTPS-прокси активирует мост
  9. Интеграция в CodexAgentRunner — HTTP-прокси НЕ активирует мост

#### Step 4: Проверки
- `vendor/bin/phpunit`
- `vendor/bin/psalm`

## 5. Definition of Done (Критерии приёмки)
- [ ] `HttpsProxyBridge` запускается и корректно пересылает CONNECT-запросы через TLS
- [ ] `CodexAgentRunner` автоматически использует мост для `https://` прокси
- [ ] Unit-тесты покрывают ключевые сценарии
- [ ] PHPUnit и Psalm без новых ошибок
- [ ] E2E-тест: codex через мост к реальному HTTPS-прокси возвращает ответ

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- PHP `stream_socket_*` может иметь ограничения по производительности при множественных одновременных соединениях (для одного codex-процесса — не критично)
- PHP fork (`pcntl_fork`) может быть недоступен в некоторых окружениях — рассмотреть альтернативу через `proc_open` (отдельный PHP-скрипт)
- TLS к upstream-прокси: PHP должен быть собран с OpenSSL
- Порт может быть занят — использовать `0` (ОС назначит свободный) или перебор портов

## 8. Sources (Источники)
- [TASK-agent-runner-proxy](done/TASK-agent-runner-proxy.todo.md) — реализация передачи прокси в env
- Python-прототип моста (30 строк), проверенный в E2E: работает с codex через `***:***`
- [PHP stream sockets](https://www.php.net/manual/en/function.stream-socket-server.php)
- [reqwest proxy limitations](https://github.com/seanmonstar/reqwest/issues/26) — reqwest не поддерживает HTTPS-прокси

## 9. Comments (Комментарии)
Проблема обнаружена при E2E-тестировании: прокси `***:***` принимает только TLS-соединения. Codex CLI (reqwest 0.12.28) поддерживает только `http://` схему для прокси. Python-прототип моста (HTTP → TLS upstream) подтвердил работоспособность — codex через мост вернул `PING_OK` от OpenAI API.

Требование: реализация моста **на PHP** (без Python, socat и других внешних зависимостей).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-28 | Бэкендер Левша | Создание задачи |
| 2026-04-30 | Бэкендер Левша | Реализация (CR-01..CR-06 доработки после ревью Пуаро) |
| 2026-04-30 | Тимлид Алекс | PR #108, статус done |
