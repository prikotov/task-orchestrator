---
type: feat
created: 2026-04-28
value: V3
complexity: C2
priority: P1
depends_on: TASK-agent-runner-proxy
epic:
author: Бэкендер Левша (backend_developer_levsha)
assignee:
branch:
pr:
status: todo
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
*Заполняется исполнителем перед стартом.*

### Рекомендуемый подход
1. Создать `HttpsProxyBridge` с использованием PHP `stream_socket_server` и `stream_socket_client` + `stream_context_set_option` для TLS.
2. Метод `start(): string` — возвращает `http://127.0.0.1:<port>`; запускает TCP-сервер в отдельном процессе (fork или proc_open).
3. Метод `stop(): void` — останавливает сервер.
4. Основной цикл моста: `stream_socket_accept` → читать HTTP CONNECT → установить TLS к upstream → отправить CONNECT с auth → пересылать данные bidirectionally.
5. Интеграция в `CodexAgentRunner::run()`: если `CODEX_HTTP_PROXY` содержит `https://`, стартовать мост, заменить URL на `http://127.0.0.1:<port>`, после завершения — остановить.

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
