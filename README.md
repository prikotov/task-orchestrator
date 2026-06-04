[![Read in English](https://img.shields.io/badge/Lang-English-blue)](README.en.md)
[![Read in 繁體中文](https://img.shields.io/badge/Lang-繁體中文-blue)](README.zh.md)

# TasK-orchestrator

### Оркестратор CLI-агентов

Продолжительная и разностороняя работа с AI-агентом в одной длинной сессии перегружает его контекст. Агент одновременно держит в памяти инструкции по процессу, детали каждого шага и ваши случайные оговорки. Чем дольше сессия, тем больше шума и тем хуже результат. Происходит деградация контекста и ухудшается качество ответов.

С другой стороны, вы ведёте несколько задач параллельно: переключаетесь между агентами, руками запускаете каждый следующий шаг, теряете контекст, забывая, что просили несколько минут назад, тратите время на его восстановление. Когнитивная нагрузка растёт, вы устаёте, и качество вашей работы тоже падает.

К тому же разные модели сильны в разном: Gemini хорошо пишет тексты, GPT модели точнее следуют инструкциям и работают с кодом, Opus решает сложные задачи с глубоким анализом, новые модели из Китая активно догоняют. Модель — это только часть связки: она работает внутри харнеса, управляющей оболочки вроде Codex, Claude Code или pi, с определённым набором инструментов и своим подходом к контексту. Codex хорошо работает с моделями OpenAI, Claude Code с Anthropic, а OpenCode, Kilocode и pi поддерживают нескольких провайдеров. На каждый участок работы хочется поставить подходящую связку из консольного агента, модели, роли и навыков, но настраивать, переключаться вручную и копировать контекст между окнами утомительно.

TasK-orchestrator решает эти проблемы через имитацию работы реальной команды, где у каждого участника своя роль, свои навыки и свой контекст, а задачи передаются как поручения — ведь у людей нет общего мозга. Для этого предусмотрены четыре инструмента:

**Роли** — Markdown-файл с описанием личности агента: поведенческий профиль (DISC, Big Five), экспертиза, стиль работы, сильные и слабые стороны. К роли привязываются скиллы, которыми она владеет. Модель получает файл как системную инструкцию и действует в рамках этой роли.

**YAML-конфиг** — для процессов, где порядок шагов известен заранее. Вы описываете роли, привязываете к каждой консольного агента, модель и параметры запуска, выстраиваете шаги и условия ветвления. Оркестратор проходит цепочку без отклонений.

**Скиллы** — навыки, которыми владеет роль. Скилл описывается Markdown-файлом с пошаговой инструкцией и вспомогательными скриптами. Роль сама выбирает подходящий скилл и применяет по ситуации.

**Сабагенты** — способ запуска роли как агента в чистом контексте. Агент получает роль, поручение и другой контекст в виде текста и файлов. Никакой истории чата и артефактов предыдущих сессий.

Оркестрация работает в двух режимах. В первом процесс задаёт YAML-конфиг, а исполняет его оркестратор, последовательно запуская агентов для выполнения шага. Во втором процесс ведёт агент: тимлид получает скилл как инструкцию и сам определяет, какого сабагента запустить, когда отправить на доработку и когда эскалировать проблему.

---

## Роли

Роль — Markdown-файл, который модель получает как системную инструкцию. В front matter описывается личность через несколько поведенческих моделей (DISC, Big Five, Адизес, Белбин, юнгианские архетипы), экспертиза и привязанные скиллы. Тело файла раскрывает личность, заданную во front matter: описание роли, личные особенности, стиль работы, принципы и правила поведения.

Одна специализация может иметь несколько ролей с разными поведенческими профилями. Два архитектора с одним поручением, но разными профилями дадут разные решения — один строго следует стандартам, другой ищет слепые зоны и альтернативы. Два бэкендера: один пишет чистый код по всем правилам, другой ценит скорость и простоту. Такая конфронтация взглядов — осознанный выбор, так как приносит пользу как и в реальной команде. Она особенно важна там, где нужен конфликт интересов: на ревью кода проверяющий смотрит на решение под другим углом — его приоритеты простота и скорость работы, а не аккуратность кода; на мозговом штурме участники с разными профилями генерируют больше идей, чем один агент, который склонен соглашаться с собой и другими. Классические системы оркестрации, которые мы изучали, не работают с поведенческими профилями: там не уделяется внимание «личностям» ролей.

Пользователь создаёт новые роли как Markdown-файлы — с уникальной личностью, экспертизой и набором скиллов. Рекомендации по описанию ролей — в [ROLE-CREATION.md](docs/agents/roles/ROLE-CREATION.md).

Примеры ролей: [`docs/agents/roles/team/`](docs/agents/roles/team/).

---

## YAML-конфиг

YAML-файл задаёт детерминированный процесс: вы описываете роли, привязываете к каждой AI-агента, модель и параметры запуска, выстраиваете шаги и условия ветвления. Оркестратор — это PHP-движок, который обходит цепочку шаг за шагом. Поведение определяется конфигом: какие шаги, в каком порядке, с какими условиями.

Поддерживаются два типа цепочек. `static` — фиксированная последовательность шагов, где порядок известен заранее: аналитик разбирает требования → бэкендер реализует → ревьювер проверяет. `dynamic` — ведущий сам решает, кто и когда действует: управляет процессом, назначает исполнителей, отправляет на доработку.

В цепочках поддерживаются: повтор с задержкой при сбоях, предохранитель (временная блокировка при повторных ошибках), запасной исполнитель (fallback), проверки через shell-команды (quality gates), ограничения по лимитам, ветвление через `when:`, журналирование каждого шага в JSONL.

Пример — мозговой штурм, где четыре роли обсуждают архитектурное решение:

```yaml
# config/chains.yaml
roles:
  team_lead_alex:
    prompt_file: docs/agents/roles/team/team_lead_alex.ru.md
    command: [pi, --mode, json, -p, --no-session, --provider, zai, --model, glm-5-turbo, --system-prompt, "@system-prompt"]

  system_architect_gandalf:
    prompt_file: docs/agents/roles/team/system_architect_gandalf.ru.md
    command: [codex, exec, --dangerously-bypass-approvals-and-sandbox, --json, --model, gpt-5.5]

  system_architect_loki:
    prompt_file: docs/agents/roles/team/system_architect_loki.ru.md
    command: [codex, exec, --dangerously-bypass-approvals-and-sandbox, --json, --model, gpt-5.5]

  backend_developer_tony:
    prompt_file: docs/agents/roles/team/backend_developer_tony.ru.md
    command: [pi, --mode, json, -p, --no-session, --provider, zai, --model, glm-5-turbo, --system-prompt, "@system-prompt"]

chains:
  brainstorm:
    type: dynamic
    description: "Фасилитируемый brainstorm с конструктивным конфликтом"
    timeout: 600
    facilitator: team_lead_alex
    participants: [system_architect_gandalf, system_architect_loki, backend_developer_tony]
    max_rounds: 20
    max_time: 3600
    prompts:
      brainstorm_system: prompts/brainstorm/brainstorm_system.txt
      facilitator_append: prompts/brainstorm/facilitator_append.txt
      facilitator_start: prompts/brainstorm/facilitator_start.txt
      facilitator_continue: prompts/brainstorm/facilitator_continue.txt
      facilitator_finalize: prompts/brainstorm/facilitator_finalize.txt
      participant_append: prompts/brainstorm/participant_append.txt
      participant_user: prompts/brainstorm/participant_user.txt
```

```bash
php vendor/bin/task-orchestrator agent:orchestrate \
  "Какие модули выделить из AgentRunner?" \
  --chain=brainstorm
```

Подробности: [документация цепочек](docs/guide/chains.md), [надёжность](docs/guide/reliability.md). Пример конфига: [`config/chains.yaml`](config/chains.yaml).

---

## Скиллы

Скилл — навык, которым владеет роль. Описывается Markdown-файлом (`SKILL.md`) с пошаговой инструкцией и вспомогательными скриптами. Роль сама выбирает и загружает подходящий скилл по ситуации.

Это возможность реализации оркестрации через скилл: роль получает инструкцию и сама решает, как действовать — какого сабагента запустить, когда отправить на доработку, когда эскалировать проблему. Например, тимлид через [`epic-via-subagents`](docs/agents/skills/epic-via-subagents/SKILL.md) ведёт эпик от постановки до merge, а через [`task-via-subagents`](docs/agents/skills/task-via-subagents/SKILL.md) — отдельную задачу. Подход гибче YAML-цепочки, но менее надёжен: агент может забыть шаг, отклониться от инструкции или выбрать неоптимальный путь. Поэтому для процессов, которые можно описать через YAML, лучше использовать YAML-конфиг. А скилл — для orchestration через агента или как обёртка над YAML-цепочкой: например, скилл [`brainstorm`](docs/agents/skills/brainstorm/SKILL.md) описывает, как провести мозговой штурм, а сам штурм реализован как YAML-цепочка.

Скилл привязывается к роли в front matter. Роль получает только те скиллы, которые нужны для её работы.

Доступные скиллы:

| Скилл | Что делает |
|---|---|
| [`run-subagent`](docs/agents/skills/run-subagent/SKILL.md) | Запускает подчинённого агента: роль + поручение + контекст. Контроль таймаутов, stall-детекция, фильтрация вывода |
| [`task-via-subagents`](docs/agents/skills/task-via-subagents/SKILL.md) | Проводит задачу от постановки до merge: реализация → self-review → code review → доработка → PR |
| [`epic-via-subagents`](docs/agents/skills/epic-via-subagents/SKILL.md) | Проводит эпик из нескольких задач через сабагентов |
| [`brainstorm`](docs/agents/skills/brainstorm/SKILL.md) | Мозговой штурм: фасилитатор ведёт дискуссию, участники спорят, итог — протокол с решениями |
| [`retrospective`](docs/agents/skills/retrospective/SKILL.md) | Ретроспектива после эпика: анализ качества процесса, предложения по улучшению |
| [`agent-report`](docs/agents/skills/agent-report/SKILL.md) | Сохраняет отчёт агента в файл для прослеживаемости |

Пользователь может создавать новые скиллы как каталог с `SKILL.md` и скриптами — по аналогии с существующими. Рекомендации: [SKILL-CREATION.md](docs/agents/skills/SKILL-CREATION.md). Примеры: [`docs/agents/skills/`](docs/agents/skills/).

---

## Сабагенты

Сабагент — способ запуска роли как отдельного агента в чистом контексте. Агент получает роль, поручение и контекст в виде текста и файлов. Никакой истории чата — каждый запуск с чистого листа, как если бы вы передавали поручение другому члену команды.

Это решает проблему деградации контекста: вместо одной длинной сессии, где агент копит шум, задача разбивается на поручения. Каждый сабагент делает свою часть и возвращает результат. Тимлид или другая выделенная роль координирует результаты, не загружая контекст исполнителя деталями предыдущих шагов и не перегружая свой собственный контекст лишними деталями реализации.

Запуск через скилл [`run-subagent`](docs/agents/skills/run-subagent/SKILL.md).

---

## Сравнение с другими решениями

Команда ИИ-ролей изучила 25+ фреймворков для оркестрации AI-агентов — LangGraph, CrewAI, Archon и другие. Полное сравнение: [исследование фреймворков](docs/research/agent-frameworks-summary.md) и [сравнение coding-агентов](docs/research/coding-agents-summary.md).

Сильные стороны TasK-orchestrator относительно изученных решений:

- **Процессный контроль**: детерминированные цепочки шагов через YAML, повтор при сбоях, предохранитель, ограничения по лимитам
- **Поведенческие профили ролей**: конфронтирующие роли с разными личностями в рамках одной специализации
- **Делегирование в чистый контекст**: сабагенты без наследия чата, координация через выделенную роль
- **Скиллы как инструкции**: текстовые процессы, которые агент ведёт сам, или обёртки над YAML-цепочками
- **Проверки качества**: линтеры и тесты прямо в цепочке шагов

План развития — в [ROADMAP](docs/releases/ROADMAP-2026-Q2-Q3.md).

---

## Быстрый старт

TasK-orchestrator — CLI-инструмент. Минимальные требования: PHP >= 8.4 и CLI-агент (например, [pi CLI](https://github.com/prikotov/pi) или [Codex CLI](https://github.com/openi/codex)).

Установка:

```bash
composer require prikotov/task-orchestrator
```

Минимальный `config/chains.yaml` — две роли и цепочка из двух шагов:

```yaml
roles:
  analyst:
    prompt_file: prompts/analyst.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

  developer:
    prompt_file: prompts/developer.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

chains:
  implement:
    steps:
      - { type: agent, role: analyst, name: analyze }
      - { type: agent, role: developer, name: implement }
      - { type: quality_gate, command: "vendor/bin/phpunit", label: "Tests", timeout_seconds: 120 }
```

Запуск из корня проекта:

```bash
php vendor/bin/task-orchestrator agent:orchestrate "Add user registration endpoint"
```

Проверка запуска настроенных ролей из chains.yaml без запуска цепочки:

```bash
php vendor/bin/task-orchestrator validate:connectivity --dry-run
php vendor/bin/task-orchestrator validate:connectivity --role=system_analyst_sherlock --timeout=30
```

Как настроить исполнителей, выбрать опции YAML и подключить к проекту — в [документации](docs/guide/chains.md).

---

## Документация

| Документ | Описание |
|---|---|
| [Архитектура](docs/guide/architecture.md) | DDD-слои, модули, CQRS |
| [CLI-команды](docs/guide/cli.md) | Оркестрация, запуск агента, проверка запуска ролей из chains.yaml |
| [Цепочки](docs/guide/chains.md) | Static / Dynamic / Conditional, YAML DSL |
| [Роли](docs/guide/roles.md) | Конфигурация ролей, prompt files, runners |
| [Надёжность](docs/guide/reliability.md) | Retry, Circuit Breaker, Fallback, Sessions/Resume |
| [Observability](docs/guide/observability.md) | Audit trail, hooks, metrics |
| [Hooks](docs/guide/hooks.md) | Post-step hooks |
| [Расширение](docs/guide/extension.md) | Добавление runners и strategies |
| [Конвенции](docs/conventions/index.md) | DDD-паттерны, слои, стиль кода |
| [Исследование 25+ фреймворков](docs/research/agent-frameworks-summary.md) | LangGraph, CrewAI, Archon и 22 других |

---

## License

[MIT](LICENSE) · Copyright © 2025–2026 prikotov
