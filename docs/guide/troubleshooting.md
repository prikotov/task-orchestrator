# Устранение неполадок

Типичные проблемы при работе Orchestrator, их симптомы, причины и решения.

## Содержание

- [Запускатель не найден](#запускатель-не-найден)
- [Цепочка не найдена](#цепочка-не-найдена)
- [Роль не найдена](#роль-не-найдена)
- [Таймаут выполнения](#таймаут-выполнения)
- [Ошибка парсинга JSONL](#ошибка-парсинга-jsonl)
- [Circuit Breaker заблокировал вызов](#circuit-breaker-заблокировал-вызов)
- [Превышен бюджет — цепочка прервана](#превышен-бюджет-цепочка-прервана)
- [Резервный запускатель не сработал](#резервный-запускатель-не-сработал)
- [Проверка качества не пройдена](#проверка-качества-не-пройдена)
- [Ошибки конфигурации](#ошибки-конфигурации)
- [Отладочные команды](#отладочные-команды)
- [`agent:init` недоступен в PHAR](#agentinit-недоступен-в-phar)
- [PHAR собирается, но команды модулей недоступны (пустой контейнер)](#phar-собирается-но-команды-модулей-недоступны-пустой-контейнер)
- [Таблица исключений](#таблица-исключений)

---

## Запускатель не найден

**Симптом:**
```
Agent runner "codex" not found.
```

**Причина:** Запрашиваемый runner не зарегистрирован в DI-контейнере через тег `agent.runner`. `AgentRunnerRegistryService` заполняется только классами, реализующими `AgentRunnerInterface` и имеющими тег.

**Решение:**

1. Убедитесь, что класс запускателя реализует `AgentRunnerInterface`:
   ```php
   final class CodexAgentRunner implements AgentRunnerInterface { ... }
   ```

2. Проверьте, что класс не исключён из автообнаружения сервисов.
   Автообнаружение выполняет `ModuleServiceRegistrar` (PHAR-safe регистратор), а исключения
   объявляются в контракте модуля — `ModuleInterface::getServiceExcludePaths()` (а не в `services.yaml`).
   Тег `agent.runner` назначается автоматически через на уровне контейнера autoconfiguration
   (`Kernel::build()` → `registerForAutoconfiguration()`), единообразно для классов модуля
   `AgentRunner` и реализаций в других модулях. Module-local `_instanceof` больше не используется.

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

4. Параметр `task_orchestrator.roles_dir` задаётся ядром `TaskOrchestrator\Common\Kernel` в `getKernelParameters()` (значение по умолчанию — `<project_root>/docs/agents/roles/team` с fallback на корень пакета). Независимой YAML-настройки этого параметра нет — путь определяется каталогами ядра.

---

## Таймаут выполнения

**Симптом:**
```
Agent timed out after 1800 seconds.
```
Или на более раннем этапе — процесс pi не отвечает.

**Причина:** Долгий ответ LLM-провайдера, сложная задача с большим контекстом, или проблемы с сетью.

**Решение:**

1. Увеличьте таймаут (в секундах). Действует порядок приоритета (от высшего к низшему):

   **явный CLI `--timeout`** → **`chain.timeout`** (YAML) → **hard default**.

   Жёсткое значение по умолчанию — **600 с** (10 мин) для Static, Dynamic и Conditional; для Dynamic также
   `max_time=3600` с (1 ч). Значение передаётся в `Symfony Process::setTimeout()`.

   > CLI-опция `--timeout` учитывается **только при явном указании** — значение по умолчанию
   > из `--help` не затирает `chain.timeout`. Поэтому static-цепочка без `chain.timeout`
   > использует таймаут 600 с (как и dynamic). Подробности: [chains.md → Таймаут уровня цепочки](chains.md#chain-level-timeout-и-maxtime).

2. Если проблема в сети — проверьте доступность API-эндпоинта LLM-провайдера.

3. Разбейте задачу на более мелкие — используйте цепочку `analyze` вместо `implement`.

4. Проверьте нагрузку через журнал аудита.

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

1. Выясните причину падений запускателя (недоступность API, неверные ключи, таймауты).

2. Circuit Breaker хранит состояние **в памяти** — перезапуск процесса сбрасывает состояние.

3. После `resetTimeoutSeconds` Breaker переходит в `half_open` — один пробный вызов. При успехе → `closed`, при ошибке → снова `open`.

4. Подробнее о состояниях Circuit Breaker — в [Надёжность](reliability.md#circuit-breaker).

---

## Превышен бюджет — цепочка прервана

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

## Резервный запускатель не сработал

**Симптом:** Основной запускатель завершился с ошибкой, но резервный запуск не был выполнен — в логе:
```
[ResolveChainRunnerService] Fallback runner "codex" not found: ...
```
или
```
[ResolveChainRunnerService] Fallback runner "codex" also failed for role "backend_developer": ...
```

**Причина:** Резервный запускатель не зарегистрирован в реестре, или его команда содержит ошибки, или fallback тоже упал.

**Решение:**

1. Убедитесь, что резервный запускатель зарегистрирован (см. [Запускатель не найден](#запускатель-не-найден)).

2. Проверьте конфигурацию резервного запуска в YAML — он указывается на уровне роли:
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

4. При ошибке резервного запуска — `ResolveChainRunnerService` возвращает `null`, шаг считается упавшим.

---

## Проверка качества не пройдена

**Симптом:**
```
[5/7] 🔍 PHP CodeSniffer: ✗ (11s)
Quality gate "PHP CodeSniffer" failed (exit code 1)
```

**Причина:** Команда оболочки проверки качества вернула ненулевой код завершения. Проверка не прерывает цепочку, но помечается как неуспешная с предупреждением.

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

## Ошибки конфигурации

### PR-ветка отправлена от имени владельца

**Симптом:** владелец не может дать учитываемое одобрение, хотя PR создан от GitHub App.

**Возможная причина:** PR-ветку пушили через SSH или обычный `git push` с учётными данными владельца.

**Решение для локального режима сопровождения этого репозитория:** не применяйте force-push или `--admin`. Остановитесь и согласуйте восстановление с пользователем. Новые ветки с первого push отправляйте по [безопасному HTTPS-рецепту настроенного GitHub App](agent-identity.md#локальный-режим-проекта-push-pr-веток-токеном-бота).

**Симптом:**
```
The service "TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Prompt\RolePromptBuilder" has a dependency on a non-existent parameter "task_orchestrator.roles_dir".
```

**Причина:** Параметры `task_orchestrator.*` задаются ядром `TaskOrchestrator\Common\Kernel` на этапе `getKernelParameters()` — это самый ранний шаг сборки контейнера (раньше любого импорта конфигурации). Если параметр отсутствует, значит ядро не собралось либо собралось в неверном контексте путей.

**Решение:**

1. Проверьте, что контейнер собирается ядром. Entry points (`bin/console`, `bin/task-orchestrator`) создают `new Kernel($env, $debug, $projectRoot)`, затем `boot()` и `getContainer()`. Если ядро падает на этапе сборки — запустите любую команду, и Symfony покажет причину:
   ```bash
   bin/console agent:token --help
   ```
   Ошибка `Kernel`/compile укажет на отсутствующий bundle, модуль или битый `config/services.yaml`.

2. Параметры вычисляются в `Kernel::getKernelParameters()` из двух каталогов: **корень пакета** (`getPackageDir()` = `dirname(__DIR__)` от `src/Kernel.php` — CWD-независимо, источник `config/`, `task_orchestrator.package_dir`) и **проект-потребитель** (`getProjectRoot()`, источник ролей, цепочек и `base_path`). Проверьте, что файлы существуют и разрешаются корректно:
   - роли: `<project_root>/docs/agents/roles/team` (fallback на корень пакета);
   - цепочки: `<project_root>/config/chains.yaml` (fallback на корень пакета);
   - конфигурация контейнера: `config/bundles.php`, `config/modules.php`, `config/packages/`, `config/services.yaml`, `config/console_services.yaml`.

3. Если параметр нужен доменному модулю — убедитесь, что модуль зарегистрирован в `config/modules.php` и содержит `Resource/config/services.yaml` (его подгружает `ModuleCompilerPass` через `ModuleKernelTrait`).

---

## Отладочные команды

> **Примечание:** Команды ниже относятся к Presentation-слою приложения `apps/console`. В библиотечном режиме (vendor binary) путь `bin/console` заменяется на `vendor/bin/task-orchestrator`, а имена команд остаются прежними.

### Проверить доступные движки

```bash
bin/console agent:runners
```

Вывод: таблица Runner | Status (available/unavailable).

### Показать план без запуска

```bash
vendor/bin/task-orchestrator agent:orchestrate "Test task" --chain=implement --dry-run
```

Выводит список шагов цепочки с ролями и запускателями без фактического запуска.

### Проверка запуска ролей из chains.yaml (`validate:connectivity`)

```bash
vendor/bin/task-orchestrator validate:connectivity --dry-run
vendor/bin/task-orchestrator validate:connectivity --role=system_analyst_sherlock --timeout=30
```

Проверяет top-level `roles` из `chains.yaml`: резолвит `@system-prompt`/`@append-system-prompt`, запускает `command` как argv array, добавляет минимальный user prompt последним argv-аргументом и считает роль успешной при exit code `0` и непустом stdout.

### Ручной запуск одного агента

```bash
bin/console agent:run --role=system_analyst --task="Analyze codebase"
```

Полезно для проверки, что pi корректно запускается и возвращает валидный JSONL.

### Проверить журнал аудита

```bash
cat var/log/agent_audit.jsonl | python3 -m json.tool
```

Каждая строка — JSON с метриками шага: токены, стоимость, длительность.

---

## `agent:init` недоступен в PHAR

**Симптом:** `php task-orchestrator.phar agent:init` или вариант с `--force` завершается с кодом `1` и предлагает использовать Composer.

**Причина:** в `v0.2.0` Composer/Packagist — основной канал с полной поддержкой, а PHAR — secondary/best-effort. Команда `agent:init` зарегистрирована в PHAR, но установка `become-role` из виртуальной файловой системы `phar://` не поддерживается.

Это безопасный fail-fast: команда завершается до любых файловых записей, не создаёт `.agents` и не изменяет существующие файлы. `--force` не снимает ограничение.

**Решение:** установите пакет через Composer в проект-потребитель и повторите инициализацию:

```bash
composer require prikotov/task-orchestrator
php vendor/bin/task-orchestrator agent:init
.agents/skills/become-role/scripts/become-role.sh <role|file>
```

Для локальную копию исходников используйте `bin/console agent:init`. Полный контракт и матрица возможностей приведены в разделе [`agent:init`](cli.md#agentinit).

---

## PHAR собирается, но команды модулей недоступны (пустой контейнер)

**Симптом:** собранный `task-orchestrator.phar` запускается (`--version` работает), но `list` не показывает команд модулей (`agent:init`, `agent:role-skills`, `agent:token`, `agent:run`, `validate:connectivity`), либо команда падает на этапе autowire (автосвязывания) с `ServiceNotFoundException`.

**Причина:** контейнер собран «пустым» по модулям (пустым). Обычно это сборка, зависящая от рабочего каталога в PHAR:

- сломан корень пакета: наследуемый `getProjectDir()` в PHAR даёт неверный `phar://.../src` (`composer.json` не упакован в PHAR) → `getModules()` возвращает `[]` → ни один модуль не грузится. Фикс — `Kernel::getPackageDir() = dirname(__DIR__)`;
- сломан PHAR-safe регистратор (`ModuleServiceRegistrar`): если вместо него остался оператор `resource:` — `GlobResource` возвращает 0 файлов по `phar://`.

**Диагностика:**

```bash
# Команды модулей должны быть видны из произвольного CWD (не только из checkout):
cd /tmp && php /path/to/task-orchestrator.phar list \
  | grep -E 'agent:init|agent:role-skills|agent:token|agent:run|validate:connectivity'
```

Если пусто — контейнер пуст. Проверьте, что `bin/phar-smoke` (усиленный) зелёный из рабочего каталога распространяемой версии: он специально ловит этот случай (`--version` ложнозелёный и проходит даже при hollow). См. [ADR-012, раздел PHAR-переносимость](../adr/012-module-configuration-convention.md#phar-переносимость-эволюция-автообнаружения-вариант-4).

---

## Таблица исключений

Все исключения библиотеки находятся в namespace `TaskOrchestrator\`.

| Исключение | Полный класс | Когда возникает |
|---|---|---|
| `RunnerNotFoundException` | `TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\RunnerNotFoundException` | Runner не найден в реестре (`AgentRunnerRegistryService::get()`) |
| `ChainNotFoundException` | `TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\ChainNotFoundException` | Цепочка не найдена в YAML (`YamlChainLoader::load()`) |
| `RoleNotFoundException` | `TaskOrchestrator\Common\Module\ChainDefinition\Domain\Exception\RoleNotFoundException` | Файл роли не найден (`RolePromptBuilder::getPrompt()`) |
| `InvalidArgumentException` | `\InvalidArgumentException` | Некорректная конфигурация YAML: отсутствует `type`, `role`, `command` в шаге и т.д. |
| `ProcessTimedOutException` | `Symfony\Process\Exception\ProcessTimedOutException` | Превышен таймаут Symfony Process — перехватывается в `PiAgentRunner::run()` |
| `RuntimeException` | `\RuntimeException` | Невозможно прочитать файл промпта (`YamlChainLoader::readFile()`) |
