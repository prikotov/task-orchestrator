---
type: feat
created: 2026-04-28
value: V2
complexity: C2
priority: P2
depends_on: TASK-orchestrator-codex-runner
epic:
author: Бэкендер Левша (backend_developer_levsha)
assignee: Бэкендер Левша (backend_developer_levsha)
branch: task/agent-runner-proxy
pr:
status: in_progress
---

# TASK-agent-runner-proxy: Поддержка HTTP-прокси для CLI-раннеров (codex)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда запускается сабагент через CLI-раннер (codex) в окружении с ограниченным доступом к API, я хочу иметь возможность настроить HTTP-прокси через env-переменную и/или конфиг chains.yaml, чтобы codex мог работать через прокси-сервер.

### Goal (Цель по SMART)
Реализовать передачу HTTP-прокси в `CodexAgentRunner` при запуске codex CLI. Прокси настраивается через env-переменную `CODEX_HTTP_PROXY` (или `HTTPS_PROXY`) и/или декларативно в `config/chains.yaml` через флаг/параметр в command.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/AgentRunner/Infrastructure/Service/Codex/CodexAgentRunner.php`
    *   `config/chains.yaml` — пример конфигурации с прокси
    *   Возможно: общий механизм прокси для всех CLI-раннеров
*   **Текущее поведение:** `CodexAgentRunner` запускает codex без учёта прокси. В окружениях без прямого доступа к OpenAI API — codex не работает.
*   **Границы (Out of Scope):**
    *   SOCKS-прокси (только HTTP/HTTPS)
    *   Авторизация на прокси (basic auth)
    *   Проверка валидности прокси-соединения
    *   PiAgentRunner — pi поддерживает прокси нативно через env

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `CodexAgentRunner` передаёт HTTP-прокси в процесс codex через env-переменные (`HTTPS_PROXY` / `HTTP_PROXY`)
- [ ] Прокси можно задать через env-переменную `CODEX_HTTP_PROXY` (приоритет) или `HTTPS_PROXY`
- [ ] В `config/chains.yaml` есть пример закомментированной конфигурации с прокси для codex-архитекторов

### 🟡 Should Have (Желательно)
- [ ] Прокси можно включить/выключить через параметр в chains.yaml (булевый флаг или URL прокси)
- [ ] Общий механизм прокси в `AgentRunRequestVo` для всех CLI-раннеров

### 🟢 Could Have (Опционально)
- [ ] Поддержка `NO_PROXY` для исключений

### ⚫ Won't Have (Не будем делать)
- [ ] SOCKS-прокси
- [ ] Авторизация на прокси
- [ ] Проверка доступности прокси перед запуском

## 4. Implementation Plan (План реализации)

1. **CodexAgentRunner::run()** — перед запуском Process добавить env-переменные:
   - `HTTPS_PROXY` = значение из `CODEX_HTTP_PROXY` (приоритет) или `HTTPS_PROXY` (fallback) из окружения.
   - `HTTP_PROXY` = значение из `HTTP_PROXY` из окружения (если задано).
   - Symfony Process: `$process->setEnv([...getenv(), 'HTTPS_PROXY' => $proxy, ...])`.
2. **CodexAgentRunner::run()** — передать полный env (`$_SERVER`/`$_ENV`) в Process через `setEnv()`, чтобы не потерять существующие переменные.
3. **Unit-тесты** — добавить тесты на `buildCommand`/`run` с проверкой прокси-переменных в env процесса.
4. **chains.yaml** — добавить закомментированный пример с прокси для codex-архитекторов (в секции комментариев).

## 5. Definition of Done (Критерии приёмки)
- [ ] CodexAgentRunner передаёт прокси в env процесса
- [ ] Unit-тесты покрывают сценарии с/без прокси
- [ ] chains.yaml содержит закомментированный пример с прокси
- [ ] PHPUnit и Psalm без новых ошибок

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks and Dependencies (Риски и зависимости)
- Codex CLI может не поддерживать стандартные env-переменные прокси — нужно проверить документацию codex
- Может потребоваться передача прокси через `-c` config override вместо env

## 8. Sources (Источники)
- [TASK-orchestrator-codex-runner](done/TASK-orchestrator-codex-runner.todo.md) — реализация CodexAgentRunner
- Codex CLI документация по конфигурации (`~/.codex/config.toml`)

## 9. Comments (Комментарии)
Задача создана по итогам работы над TASK-orchestrator-codex-runner. Codex-архитекторы в chains.yaml временно переключены на pi+zai (glm-5.1), пока не будет решён вопрос с прокси.

## Инструкции для сабагента

**Ветка:** task/agent-runner-proxy (уже создана и активна)

### Порядок действий
1. Переключись в ветку `task/agent-runner-proxy`: `git checkout task/agent-runner-proxy`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-28 | Бэкендер Левша | Создание задачи |
