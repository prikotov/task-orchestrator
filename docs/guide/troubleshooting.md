# Troubleshooting

Типичные проблемы при работе Orchestrator, их симптомы, причины и решения.

## Содержание

- [Runner не найден](#runner-не-найден)
- [Цепочка не найдена](#цепочка-не-найдена)
- [Роль не найдена](#роль-не-найдена)
- [Таймаут выполнения](#таймаут-выполнения)
- [Ошибка парсинга JSONL](#ошибка-парсинга-jsonl)
- [Circuit Breaker заблокировал вызов](#circuit-breaker-заблокировал-вызов)
- [Budget exceeded — цепочка прервана](#budget-exceeded--цепочка-прервана)
- [Fallback runner не сработал](#fallback-runner-не-сработал)
- [Quality Gate упал](#quality-gate-упал)
- [Bundle configuration errors](#bundle-configuration-errors)
- [Отладочные команды](#отладочные-команды)
- [Таблица исключений](#таблица-исключений)

---

## Runner не найден

**Симптом:**
```
Agent runner "codex" not found.
```

**Причина:** Запрашиваемый runner не зарегистрирован в DI-контейнере через тег `agent.runner`. `AgentRunnerRegistryService` заполняется только классами, реализующими `AgentRunnerInterface` и имеющими тег.

**Решение:**

1. Убедитесь, что класс runner'а реализует `AgentRunnerInterface`:
   ```php
   final class CodexAgentRunner implements AgentRunnerInterface { ... }
   ```

2. Проверьте, что класс не исключён из auto-discovery в `config/services.yaml`. Тег `agent.runner` автоматически назначается через `_instanceof`:
   ```yaml
   _instanceof:
     TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\AgentRunner\AgentRunnerInterface:
       tags: ['agent.runner']
   ```

3. При использовании `--runner=<name>` убедитесь, что `getName()` возвращает то же имя.

---

## Цепочка не найдена

**Симптом:**
```
Chain "brainstorm" not found.
```

**Причина:** Цепочка с указанным именем отсутствует в секции `chains` YAML-конфигурации.

**Решение:**

1. Проверьте, что цепочка определена в YAML (параметр `%task_orchestrator.chains_yaml%`).

2. Проверьте отступы — YAML чувствителен к пробелам. Ключи в `chains:` должны иметь 2-пробельный отступ.

3. Проверьте валидность YAML:
   ```bash
   php -r "var_dump(Symfony\Component\Yaml\Yaml::parseFile('path/to/chains.yaml'));"
   ```

---

## Роль не найдена

**Симптом:**
```
Agent role "verifier" not found.
```

**Причина:** Файл роли `<role_name>.ru.md` отсутствует в директории ролей (параметр `%task_orchestrator.roles_dir%`). `RolePromptBuilder` сканирует эту директорию по паттерну `*.ru.md` и маппит имя файла (без `.ru`) на роль.

**Решение:**

1. Проверьте, что файл существует в директории, указанной в `%task_orchestrator.roles_dir%`.

2. Убедитесь, что имя файла совпадает с именем роли в YAML-конфигурации (без суффикса `.ru.md`).

3. Файл должен содержать заголовок `# Имя Роли` — из него извлекается описание:
   ```markdown
   # Verifier
   Описание роли...
   ```

4. Проверьте параметр `task_orchestrator.roles_dir` в конфигурации bundle:
   ```yaml
   task_orchestrator:
       roles_dir: '%kernel.project_dir%/docs/agents/roles/team'
   ```

---

## Таймаут выполнения

**Симптом:**
```
Agent timed out after 1800 seconds.
```
Или на более раннем этапе — процесс pi не отвечает.

**Причина:** Долгий ответ LLM-провайдера, сложная задача с большим контекстом, или проблемы с сетью.

**Решение:**

1. Увеличьте таймаут (в секундах, по умолчанию = 1800 с / 30 мин). Значение передаётся в `Symfony Process::setTimeout()`.

2. Если проблема в сети — проверьте доступность API-эндпоинта LLM-провайдера.

3. Разбейте задачу на более мелкие — используйте цепочку `analyze` вместо `implement`.

4. Проверьте нагрузку через audit-лог.

---

## Ошибка парсинга JSONL

**Симптом:** Результат пустой (`outputText` = `""`), либо токены = 0, либо runner возвращает `AgentResultVo::createFromError`.

**Причина:** Pi вернул нестандартный JSONL-поток — отсутствует `message_end` или `agent_end`, нарушена структура JSON, или pi упал до завершения.

**Решение:**

1. Запустите pi вручную для проверки вывода:
   ```bash
   pi --mode json -p --no-session "Simple test"
   ```

2. `PiJsonlParser` ожидает строки с `"type": "message_end"` (usage-метрики) и `"type": "agent_end"` (текст ответа). Если хотя бы одна отсутствует — результат может быть неполным.

3. Проверьте версию pi — JSON-режим мог измениться:
   ```bash
   pi --version
   ```

4. Если pi использует `message_update` → `text_delta` — парсер собирает текст из дельт как fallback.

---

## Circuit Breaker заблокировал вызов

**Симптом:**
```
Circuit breaker is open for runner "pi". CircuitBreaker(state=open, failures=5/5, resetTimeout=60s, lastFailure=1713123456)
```

**Причина:** Runner последовательно упал N раз (достигнут `failureThreshold`). Circuit Breaker перешёл в состояние `open` — вызовы блокируются на `resetTimeoutSeconds`.

**Решение:**

1. Выясните причину падений runner'а (недоступность API, неверные ключи, таймауты).

2. Circuit Breaker хранит состояние **in-memory** — перезапуск процесса сбрасывает состояние.

3. После `resetTimeoutSeconds` Breaker переходит в `half_open` — один пробный вызов. При успехе → `closed`, при ошибке → снова `open`.

4. Подробнее о состояниях Circuit Breaker — в [Надёжность](reliability.md#circuit-breaker).

---

## Budget exceeded — цепочка прервана

**Симптом:**
```
💰 Budget exceeded: spent $5.2340 of $5.00 limit. Chain interrupted.
```

**Причина:** Суммарная стоимость цепочки превысила `max_cost_total` из секции `budget` в YAML-конфигурации. Проверка выполняется до и после каждого шага.

**Решение:**

1. Увеличьте бюджет:
   ```yaml
   budget:
     max_cost_total: 10.0
   ```

2. Используйте более дешёвую модель для части шагов:
   ```yaml
   steps:
     - { type: agent, role: system_analyst, model: glm-4.7 }
     - { type: agent, role: backend_developer, model: glm-5-turbo }
   ```

3. Ограничьте бюджет на отдельные роли через `per_role`:
   ```yaml
   budget:
     max_cost_total: 5.0
     per_role:
       backend_developer:
         max_cost_total: 2.0
   ```

4. О стоимости и наблюдаемости — в [Наблюдаемость](observability.md).

---

## Fallback runner не сработал

**Симптом:** Основной runner упал, но fallback не был выполнен — в логе:
```
[ResolveChainRunnerService] Fallback runner "codex" not found: ...
```
или
```
[ResolveChainRunnerService] Fallback runner "codex" also failed for role "backend_developer": ...
```

**Причина:** Fallback runner не зарегистрирован в реестре, или его команда содержит ошибки, или fallback тоже упал.

**Решение:**

1. Убедитесь, что fallback runner зарегистрирован (см. [Runner не найден](#runner-не-найден)).

2. Проверьте конфигурацию fallback в YAML — он указывается на уровне роли:
   ```yaml
   roles:
     backend_developer:
       fallback:
         command:
           - codex
           - --model
           - gpt-4o
           - --full-auto
   ```

3. Слот `@system-prompt` в fallback-команде автоматически резолвится в путь к `prompt_file` роли через `PromptFormatterInterface::resolveSlot()`.

4. При ошибке fallback — `ResolveChainRunnerService` возвращает `null`, шаг считается упавшим.

---

## Quality Gate упал

**Симптом:**
```
[5/7] 🔍 PHP CodeSniffer: ✗ (11s)
Quality gate "PHP CodeSniffer" failed (exit code 1)
```

**Причина:** Shell-команда quality gate вернула ненулевой exit code. Gate не прерывает цепочку, но помечается как failed (warning).

**Решение:**

1. Запустите команду вручную для диагностики.

2. Проверьте `timeout_seconds` — команда могла не успеть:
   ```yaml
   - type: quality_gate
     command: 'make tests-unit'
     label: 'Unit Tests'
     timeout_seconds: 120
   ```

3. Используйте `fix_iterations`, чтобы прогонять gate итерационно вместе с шагом исправления:
   ```yaml
   fix_iterations:
     - group: dev-review
       steps: [implement, review]
       max_iterations: 3
   ```

---

## Bundle configuration errors

**Симптом:**
```
The service "TaskOrchestrator\Common\Module\Orchestrator\Infrastructure\Service\Prompt\RolePromptBuilder" has a dependency on a non-existent parameter "task_orchestrator.roles_dir".
```

**Причина:** `TaskOrchestratorBundle` не зарегистрирован в приложении, или параметры bundle не сконфигурированы.

**Решение:**

1. Убедитесь, что bundle зарегистрирован:
   ```php
   // apps/console/config/bundles.php
   return [
       \TaskOrchestrator\Common\Infrastructure\Symfony\TaskOrchestratorBundle::class => ['all' => true],
   ];
   ```

2. Создайте файл конфигурации:
   ```yaml
   # config/packages/task_orchestrator.yaml
   task_orchestrator:
       roles_dir: '%kernel.project_dir%/docs/agents/roles/team'
       chains_yaml: '%kernel.project_dir%/apps/console/config/agent_chains.yaml'
       audit_log_path: '%kernel.project_dir%/var/log/agent_audit.jsonl'
       chains_session_dir: '%kernel.project_dir%/var/agent/chains'
       base_path: '%kernel.project_dir%'
   ```

3. Очистите кэш:
   ```bash
   bin/console cache:clear
   ```

---

## Отладочные команды

> **Примечание:** Команды ниже относятся к TasK Console — Presentation-слою проекта TasK. Если вы используете библиотеку в другом приложении, замените `bin/console` на CLI вашего приложения, а имена команд — на свои.

### Проверить доступные движки

```bash
bin/console app:agent:runners
```

Вывод: таблица Runner | Status (available/unavailable).

### Показать план без запуска

```bash
bin/console app:agent:orchestrate "Test task" --chain=implement --dry-run
```

Выводит список шагов цепочки с ролями и runner'ами без фактического запуска.

### Ручной запуск одного агента

```bash
bin/console app:agent:run --role=system_analyst --task="Analyze codebase"
```

Полезно для проверки, что pi корректно запускается и возвращает валидный JSONL.

### Проверить audit-лог

```bash
cat var/log/agent_audit.jsonl | python3 -m json.tool
```

Каждая строка — JSON с метриками шага: токены, стоимость, длительность.

---

## Таблица исключений

Все исключения библиотеки находятся в namespace `TaskOrchestrator\`.

| Исключение | Полный класс | Когда возникает |
|---|---|---|
| `RunnerNotFoundException` | `TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\RunnerNotFoundException` | Runner не найден в реестре (`AgentRunnerRegistryService::get()`) |
| `ChainNotFoundException` | `TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\ChainNotFoundException` | Цепочка не найдена в YAML (`YamlChainLoader::load()`) |
| `RoleNotFoundException` | `TaskOrchestrator\Common\Module\Orchestrator\Domain\Exception\RoleNotFoundException` | Файл роли не найден (`RolePromptBuilder::getPrompt()`) |
| `InvalidArgumentException` | `\InvalidArgumentException` | Некорректная конфигурация YAML: отсутствует `type`, `role`, `command` в шаге и т.д. |
| `ProcessTimedOutException` | `Symfony\Process\Exception\ProcessTimedOutException` | Превышен таймаут Symfony Process — перехватывается в `PiAgentRunner::run()` |
| `RuntimeException` | `\RuntimeException` | Невозможно прочитать файл промпта (`YamlChainLoader::readFile()`) |
