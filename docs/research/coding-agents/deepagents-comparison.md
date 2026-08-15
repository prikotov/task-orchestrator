# Deep Agents Code и Deep Agents SDK — исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)  
**Дата анализа:** 2026-08-14  
**Объект:** `langchain-ai/deepagents` (`deepagents` 0.7.6, `deepagents-code` 0.1.55, Python, MIT)  
**Задача:** [TASK-research-deepagents](../../../todo/done/TASK-research-deepagents.todo.md)
**Срез источников:** GitHub `main` commit `822f7c9b02e6d99bdb46b5545bb2543783c01769` от 2026-08-13 22:54:26 UTC; PyPI версии: `deepagents` 0.7.6 от 2026-08-13, `deepagents-code` 0.1.55 от 2026-08-12.  
**GitHub metadata:** ~27.7k stars (звёзд), ~3.9k forks (форков), лицензия MIT.

---

## Сводка

`deepagents` — открытый agent harness (каркас агента) поверх LangGraph и LangChain. Он даёт `create_deep_agent(...)` как Python API (программный интерфейс Python): системный промпт, инструменты, subagents (сабагенты), skills (скиллы), AGENTS.md-memory (память из файлов AGENTS.md), filesystem (файловую систему), Human-in-the-loop (подтверждение действий человеком), MCP (Model Context Protocol, протокол подключения инструментов) и потоковый запуск через LangGraph.

`deepagents-code` / **Deep Agents Code** — отдельный CLI-продукт (инструмент командной строки) `dcode`, построенный на SDK. По README это «pre-built coding agent in your terminal — similar to Claude Code or Cursor», вдохновлённый Claude Code. CLI поддерживает interactive TUI (интерактивный терминальный интерфейс), headless mode (без интерактива) через `-n/--non-interactive`, persistent memory (постоянную память), custom skills (пользовательские скиллы), subagents (сабагентов), MCP, sandboxes (изолированные окружения), approval controls (подтверждения действий), токены/стоимость.

**Ключевое разделение:**

| Поверхность | Что подтверждено | Влияние на наш сценарий |
|---|---|---|
| **Deep Agents SDK (`create_deep_agent`)** | Полный программный контроль: `system_prompt`, `subagents`, `skills`, `memory`, `interrupt_on`, `checkpointer`, `store`, LangGraph `invoke/astream` | Сильная база для собственной интеграции, если писать Python-wrapper (обёртку Python) |
| **Deep Agents Code CLI (`dcode`)** | Готовый coding agent (агент кодинга) с `-n`, `--quiet`, `--no-stream`, `--max-turns`, `--timeout`, `--agent`, `--skill`, `--model`, `--acp`, `--json` для служебных подкоманд | Не drop-in replacement (прямая замена) для Pi/omp: нет подтверждённых `--system-prompt`, `--append-system-prompt`, JSONL event stream (потока событий JSONL) основного запуска; `dcode --acp` — отдельная ACP/RPC-интеграция, а не stdout JSONL |

**Вердикт для подключения как CLI-сабагента:** ⚠️ **Частично подходит (7/10, 27/30)**.  
Причина: сильная открытая основа, MIT, BYO LLM (свой провайдер модели), skills, AGENTS.md, `.agents/skills/`, cost tracking (учёт стоимости), timeout (таймауты) и headless mode есть. Но для нашего текущего `watch-subagent.sh`-контракта критичны три оговорки: нет подтверждённых CLI-флагов замены/дополнения системного промпта, роль в CLI придётся передавать через user prompt (пользовательский промпт) или memory, а основной headless-запуск выдаёт текстовый поток, а не структурированный JSONL событий.

**Вердикт для Python API:** ✅ **Подходит как программный harness (9/10)** для отдельной интеграции. Ограничение не техническое, а продуктово-интеграционное: это уже не простая CLI-замена Pi/omp, а новый Python-runner (исполнитель Python) вокруг LangGraph. Оценка SDK ниже дана отдельно от CLI-суммы 27/30 и не складывается с ней.

**SDK scorecard (оценочная шкала 10/10, не CLI-сумма 30):**

| Проверка SDK | Статус | Основание |
|---|---|---|
| System prompt / role / subagents | ✅ | `create_deep_agent(system_prompt=...)`, declarative `SubAgent.system_prompt`. |
| Skills / memory / AGENTS.md / filesystem / HITL / MCP | ✅ | Поддержаны на уровне SDK/Deep Agents middleware или через CLI-настроенный `create_cli_agent(...)`. |
| Programmatic run | ✅ | `invoke(...)` и LangGraph `astream(...)` доступны для собственного runner. |
| Providers / usage / license | ✅ | LangChain model ecosystem, usage metadata/cost tracking, MIT. |
| Готовый `watch-subagent.sh` JSONL contract | ⚠️ | Ограниченный критерий: SDK даёт поток LangGraph, но не готовую схему JSONL событий `watch-subagent`; нужен Python-runner или adapter. |

**Итого SDK:** 9/10: ограничен только готовый контракт вывода для нашего wrapper, а не возможности agent harness.

---

## Источники

Использованы только первичные источники:

1. GitHub: [`langchain-ai/deepagents`](https://github.com/langchain-ai/deepagents/tree/822f7c9b02e6d99bdb46b5545bb2543783c01769), commit `822f7c9b02e6d99bdb46b5545bb2543783c01769`.
2. README SDK: [`libs/deepagents/README.md`](https://github.com/langchain-ai/deepagents/blob/822f7c9b02e6d99bdb46b5545bb2543783c01769/libs/deepagents/README.md).
3. README CLI: [`libs/code/README.md`](https://github.com/langchain-ai/deepagents/blob/822f7c9b02e6d99bdb46b5545bb2543783c01769/libs/code/README.md).
4. API source: [`create_deep_agent` в `libs/deepagents/deepagents/graph.py`](https://github.com/langchain-ai/deepagents/blob/822f7c9b02e6d99bdb46b5545bb2543783c01769/libs/deepagents/deepagents/graph.py).
5. CLI source/help (исходный код и официальная справка CLI): [`libs/code/deepagents_code/main.py`](https://github.com/langchain-ai/deepagents/blob/822f7c9b02e6d99bdb46b5545bb2543783c01769/libs/code/deepagents_code/main.py) — подтверждает `--acp` как ACP server over stdio (сервер ACP поверх стандартного ввода-вывода), `-M/--model` help (справку), `-n/--non-interactive`, `--quiet`, `--no-stream`, `--timeout`; также [`agent.py`](https://github.com/langchain-ai/deepagents/blob/822f7c9b02e6d99bdb46b5545bb2543783c01769/libs/code/deepagents_code/agent.py), [`config.py`](https://github.com/langchain-ai/deepagents/blob/822f7c9b02e6d99bdb46b5545bb2543783c01769/libs/code/deepagents_code/config.py).
6. PyPI: [`deepagents`](https://pypi.org/project/deepagents/0.7.6/) 0.7.6, [`deepagents-code`](https://pypi.org/project/deepagents-code/0.1.55/) 0.1.55.
7. Официальная документация: [Deep Agents overview](https://docs.langchain.com/oss/python/deepagents/overview), [Deep Agents Code](https://docs.langchain.com/deepagents-code), [API reference](https://reference.langchain.com/python/deepagents/).

---

## Критерий 1. Системный промпт

| Поверхность | Поддержка | Детали |
|---|---|---|
| SDK | ✅ | `create_deep_agent(system_prompt=...)` принимает `str` или `SystemMessage`. Исходный код описывает сборку prompt (промпта) как `USER -> BASE -> SUFFIX`; при `system_prompt=None` authored prompt (авторская часть системного промпта) пустой, если профиль не добавляет базу/суффикс. |
| CLI | ⚠️ | В `dcode` не найдено подтверждённых флагов `--system-prompt` / `--append-system-prompt`. Внутренний `create_cli_agent(system_prompt=...)` умеет заменить автосгенерированный prompt, но публичный CLI parser (разборщик аргументов) такого флага не даёт. |

**Оценка:** ⚠️ Частичная поддержка.  
Для SDK — полная. Для Deep Agents Code CLI — нет Pi/omp-совместимого способа заменить или дополнить системный prompt из командной строки. Передача файла роли возможна только через пользовательский prompt (`-n "Прочитай файл роли..."`) или через AGENTS.md-memory.

---

## Критерий 2. Промпт агента / роль

| Механизм | Поддержка | Комментарий |
|---|---|---|
| CLI `--agent NAME` | ⚠️ | Выбирает agent identifier (идентификатор агента): память и user skills (пользовательские скиллы) лежат в `~/.deepagents/<agent>/`. Это не полноценная инъекция нашей роли из `docs/agents/roles/team/*.ru.md`. |
| CLI `-n/--non-interactive` | ⚠️ | Можно передать инструкцию «возьми роль из файла...», но это user prompt, а не system prompt. |
| Project/user `AGENTS.md` | ⚠️ | MemoryMiddleware загружает `~/.deepagents/<agent>/AGENTS.md`, `.deepagents/AGENTS.md` и `AGENTS.md` в системный контекст. Можно положить роль туда, но это смешивает роль с памятью проекта. |
| SDK `system_prompt` / declarative `SubAgent.system_prompt` | ✅ | Роль можно передать как системный prompt основного агента или отдельного subagent. |

**Оценка:** ⚠️ Частичная поддержка.  
Роли реализуемы, но CLI-поверхность не повторяет наш текущий контракт `--append-system-prompt "Возьми на себя роль из файла..."`.

---

## Критерий 3. Скиллы

Deep Agents Code поддерживает Agent Skills standard (стандарт скиллов агента) через `SKILL.md` с YAML frontmatter. Встроенный `skill-creator` прямо описывает источники загрузки от низшего к высшему приоритету:

1. `<package>/built_in_skills/`
2. `~/.deepagents/<agent>/skills/`
3. `~/.agents/skills/`
4. `.deepagents/skills/`
5. `.agents/skills/`

Дополнительно исходный код CLI подключает plugin skills (скиллы плагинов) и экспериментальные `.claude/skills/`. Есть команды `dcode skills list`, `dcode skills create`, `dcode skills trust`, а основной CLI имеет `-s/--skill NAME` для запуска скилла при старте интерактивной или headless-сессии.

**Разным ролям — разные скиллы:** частично решается через `--agent NAME` и каталог `~/.deepagents/<agent>/skills/`. Project-level (проектные) `.agents/skills/` остаются общими для проекта.

**Оценка:** ✅ Полная поддержка.

---

## Критерий 4. AGENTS.md

Deep Agents Code загружает AGENTS.md как memory (память):

| Локация | Поддержка | Источник |
|---|---|---|
| `~/.deepagents/<agent>/AGENTS.md` | ✅ | `settings.get_user_agent_md_path(agent_name)` |
| `<project>/.deepagents/AGENTS.md` | ✅ | `get_project_agent_md_path()` и `find_project_agent_md()` |
| `<project>/AGENTS.md` | ✅ | `find_project_agent_md()` |

При наличии обоих project-файлов порядок — `.deepagents/AGENTS.md`, затем `AGENTS.md`. В коде есть защита от symlink (символических ссылок) за пределы проекта.

**Отключение:** публичного CLI-флага уровня `--no-context-files` не найдено. Есть внутренний параметр `enable_memory=False` у `create_cli_agent(...)`, но в обычном `dcode` он не вынесен как аргумент.

**Оценка:** ✅ Полная поддержка с эксплуатационной оговоркой.  
Автообнаружение есть; отключение в CLI не подтверждено.

---

## Критерий 5. Стандартная папка `.agents/skills/`

| Локация | Поддержка | Комментарий |
|---|---|---|
| `.agents/skills/<skill>/SKILL.md` | ✅ | Project Agents source (проектный общий источник), высший приоритет среди нативных источников. |
| `~/.agents/skills/<skill>/SKILL.md` | ✅ | User Agents source (пользовательский общий источник). |
| `.deepagents/skills/` | ✅ | Project Deepagents alias (проектный alias). |
| `~/.deepagents/<agent>/skills/` | ✅ | Agent-specific user skills (пользовательские скиллы конкретного агента). |
| `docs/agents/skills/` | ⚠️ | Нашу директорию нужно подключить symlink (символической ссылкой) `.agents/skills -> docs/agents/skills` или копированием. Явного CLI-флага добавления произвольной директории для основной сессии не подтверждено; trust extra dirs есть на уровне конфигурации/безопасности. |

**Оценка:** ✅ Полная поддержка.

---

## Критерий 6. Запуск как сабагент

### CLI Deep Agents Code

| Возможность | Поддержка | Комментарий |
|---|---|---|
| Headless one-shot (без интерактива) | ✅ | `dcode -n "task"` / `--non-interactive`. |
| Чистый текстовый stdout | ✅ | `--quiet` оставляет в stdout только ответ агента; `--no-stream` буферизует полный ответ. |
| Таймаут | ✅ | `--timeout SECONDS`, exit code 124 при истечении. |
| Лимит ходов | ✅ | `--max-turns N`, exit code 124 при превышении. |
| JSON для основного запуска | ❌ | `--json` есть у служебных команд (`skills list`, `threads list`, `agents list` и т.п.), но не подтверждает JSONL event stream для `dcode -n`. |
| ACP server over stdio | ✅/⚠️ | `dcode --acp` подтверждён official CLI help (официальной справкой CLI) как запуск ACP server over stdio. Это программная RPC-интеграция для ACP-клиента, но не JSONL stdout основного `dcode -n`. |
| Tool-call events (события вызовов инструментов) | ❌/⚠️ | Внутри LangGraph/ACP поток есть, но CLI `dcode -n` печатает человекочитаемый output; стабильной схемы JSONL-событий stdout не найдено. |
| Session isolation (изоляция сессии) | ⚠️ | Headless создаёт новый `thread_id`, но явного `--no-session` как у Pi/omp нет. Есть `threads` и `--resume`. |

### Python API

SDK возвращает `CompiledStateGraph`; доступен `invoke(...)` и потоковый `astream(...)` с LangGraph stream modes (режимами потока). `create_deep_agent(...)` поддерживает `checkpointer`, `store`, `subagents`, `response_format`, а Deep Agents Code имеет `create_cli_agent(...)` для программного создания CLI-настроенного агента.

**Оценка:** ⚠️ Частичная поддержка.  
Для scripting/CI (скрипты и непрерывная интеграция) CLI подходит. `dcode --acp` важен для программной интеграции: вместо интерактивного TUI он поднимает ACP/RPC server over stdio, с которым может общаться отдельный host/client (хост/клиент). Но это не равно подтверждённому `watch-subagent.sh` JSONL-контракту: наш wrapper ждёт line-delimited JSON events (построчные JSON-события) в stdout основного запуска, а ACP требует отдельного протокольного клиента и маппинга сообщений в нашу event schema. Полная интеграция потребует Python-runner, ACP-client adapter или adapter поверх LangGraph stream.

---

## Критерий 7. Токены и стоимость

Deep Agents Code содержит `CostTrackingMiddleware` и расчёт стоимости через `genai-prices`:

- учитывает `usage_metadata` LangChain: input/output tokens (входные/выходные токены), cache read/write (чтение/запись кэша), reasoning/audio buckets (категории reasoning/audio);
- `/cost` и таблица usage (использования) показывают оценку по текущему thread (потоку диалога);
- `PRICING.md` описывает локальные overrides (переопределения) `~/.deepagents/prices.json`;
- оценки display-only (только отображение), не лимитируют расходы;
- LangSmith используется для tracing/evaluation/monitoring (трассировки, оценки, мониторинга).

Ограничение: в headless `--quiet` диагностическая таблица подавляется, а стабильный JSON usage для основного `dcode -n` не подтверждён.

**Оценка:** ✅ Полная поддержка для учёта, ⚠️ частично для машинного парсинга.

---

## Критерий 8. Free tier

| Аспект | Поддержка | Комментарий |
|---|---|---|
| Стоимость инструмента | ✅ | `deepagents` и `deepagents-code` — MIT open source. |
| BYO LLM / BYOK | ✅ | Пользователь приносит свои ключи и провайдеров. |
| Бесплатные модели | ⚠️ | Сам инструмент бесплатный; бесплатность зависит от выбранного провайдера или локальной модели. |
| Локальный запуск моделей | ✅ | README SDK подтверждает self-hosted models через Ollama, vLLM, llama.cpp; CLI source содержит провайдер `ollama`, extras и локальные настройки. |

**Оценка:** ✅ Полная поддержка как бесплатного open source инструмента; расходы LLM внешние.

---

## Критерий 9. Провайдеры и модели

SDK заявляет model-agnostic (независимость от модели): любой LLM с tool calling — frontier APIs (OpenAI, Anthropic, Google), open-weight модели через Baseten/Fireworks, self-hosted через Ollama/vLLM/llama.cpp.

Deep Agents Code:

- `-M/--model MODEL` в official CLI help (официальной справке CLI) описан как модель с автоопределением провайдера; подтверждённые примеры help (справки): `claude-opus-4-7`, `gpt-5.5`;
- для других model-полей help показывает `provider:model`-примеры (`--default-model`, `--auto-classifier-model`, `--rubric-model`), но это не переносится без проверки на основной `--model`;
- README CLI говорит, что OpenAI, Anthropic и Gemini включены по умолчанию, extras ставятся через `DEEPAGENTS_CODE_EXTRAS="nvidia,ollama"`;
- source registry (реестр в коде) использует LangChain provider registry (реестр провайдеров LangChain), пользовательские `[providers]` в `config.toml`, `class_path`, base URL (базовый адрес) и env vars (переменные окружения);
- подтверждены env vars для Anthropic, OpenAI, Azure OpenAI, Google GenAI/VertexAI, Fireworks, Nvidia, Ollama и OpenAI-compatible (OpenRouter/DeepSeek/Together/xAI/Baseten через OpenAI SDK).

**Оценка:** ✅ Полная поддержка.

---

## Критерий 10. Лицензия

| Компонент | Лицензия | Источник |
|---|---|---|
| `langchain-ai/deepagents` | MIT | GitHub metadata и `LICENSE` |
| `deepagents` PyPI | MIT | PyPI metadata |
| `deepagents-code` PyPI | MIT | PyPI metadata |

**Оценка:** ✅ Полная поддержка.

---

## Итоговая оценка

| Критерий | Оценка | Кратко |
|---|---:|---|
| 1. Системный prompt | ⚠️ | SDK full; CLI без `--system-prompt` / `--append-system-prompt`. |
| 2. Роль | ⚠️ | Через `-n`/AGENTS.md/`--agent`, но не system-level CLI injection. |
| 3. Skills | ✅ | `SKILL.md`, `--skill`, built-in/user/project/plugin sources. |
| 4. AGENTS.md | ✅ | `~/.deepagents/<agent>/AGENTS.md`, `.deepagents/AGENTS.md`, `AGENTS.md`. |
| 5. `.agents/skills/` | ✅ | Project/user `.agents/skills/` поддержаны. |
| 6. Сабагент / JSON | ⚠️ | Headless + timeout есть; JSONL event stream CLI не подтверждён. |
| 7. Токены/стоимость | ✅ | Usage/cost tracking через LangChain metadata и genai-prices. |
| 8. Free tier | ✅ | MIT + BYO LLM; бесплатность модели зависит от провайдера. |
| 9. Провайдеры | ✅ | Model-agnostic, LangChain providers, local/open-weight. |
| 10. Лицензия | ✅ | MIT. |

**Сумма:** 27/30.  
**Pass-count:** 7/10.  
**Вердикт CLI:** ⚠️ **Частично подходит (7/10)**.  
**Вердикт SDK:** ✅ **Подходит для отдельной программной интеграции (9/10)**.

> ⚠️ **SDK не складывается с CLI-суммой 27/30.** Это отдельная качественная шкала с максимумом 10 (а не 30): CLI-сумма отражает готовность `dcode` как CLI-сабагента, а SDK-оценка — возможности программного API `create_deep_agent(...)` для собственного Python-runner (обёртки Python). Две шкалы нельзя суммировать или усреднять между собой.

**SDK-оценка по тем же 10 критериям (максимум 10, не 30):**

| Критерий | SDK | Основание |
|---|:--:|---|
| К1. Системный prompt | ✅ | `create_deep_agent(system_prompt=str\|SystemMessage)`. |
| К2. Роль | ✅ | Роль в `system_prompt` основного агента или declarative `SubAgent.system_prompt`. |
| К3. Скиллы | ✅ | skills на уровне Deep Agents middleware. |
| К4. AGENTS.md | ✅ | memory/AGENTS.md через middleware. |
| К5. `.agents/skills/` | ✅ | project/user skills sources на уровне SDK/config. |
| К6. Запуск как сабагент | ⚠️ | LangGraph `astream`/`invoke` есть, но **нет готового `watch-subagent.sh` JSONL contract** — нужен Python-runner или ACP/astream adapter. |
| К7. Токены/стоимость | ✅ | LangChain `usage_metadata`, cost tracking через genai-prices. |
| К8. Free tier | ✅ | MIT + BYO LLM; локальные модели через Ollama/vLLM. |
| К9. Провайдеры | ✅ | model-agnostic, LangChain provider registry. |
| К10. Лицензия | ✅ | MIT. |

**Итог SDK: 9/10** — ограничен единственным критерием **К6**: программный запуск и поток событий доступны (LangGraph `astream`, `dcode --acp` over stdio), но готовой схемы JSONL-событий `watch-subagent` из коробки нет — требуется собственный Python-runner или adapter поверх `astream`/ACP. Остальные 9 критериев SDK покрывает полностью.

---

## Практические примеры

### Deep Agents Code CLI

```bash
# Установка CLI-продукта
curl -LsSf https://langch.in/dcode | bash

# Headless-задача с моделью из official CLI help и таймаутом
# Роль передаётся как часть пользовательского prompt, не как system prompt.
dcode -M claude-opus-4-7 \
  --non-interactive "Возьми на себя роль из файла docs/agents/roles/team/system_analyst_sherlock.ru.md и выполни задачу" \
  --quiet --no-stream --max-turns 20 --timeout 1800

# Запуск с конкретным скиллом по имени
dcode --non-interactive "Подготовь отчёт" --skill agent-report --quiet

# Служебный JSON доступен для management-команд
dcode skills list --json

# ACP server over stdio для отдельного ACP-клиента; это не JSONL stdout для watch-subagent
dcode --acp
```

### Deep Agents SDK

```python
from deepagents import create_deep_agent

agent = create_deep_agent(
    model="openai:gpt-5.5",
    tools=[my_custom_tool],
    system_prompt="Ты — Аналитик Шерлок. Следуй AGENTS.md и docs/conventions.",
    skills=["/skills/project/"],
    memory=["/memory/AGENTS.md"],
)

result = agent.invoke({"messages": "Исследуй Deep Agents Code по 10 критериям"})
```

### Вариант интеграции для `task-orchestrator`

```text
task-orchestrator
  -> Python runner (обёртка) или ACP-client adapter
    -> create_cli_agent(...) / create_deep_agent(...) / dcode --acp
      -> LangGraph astream(...) или ACP messages
        -> adapter в наш JSONL event schema
```

Этот путь сильнее, чем прямой `dcode -n`, но требует отдельной реализации и контрактных тестов.

---

## Сравнение с ближайшими аналогами

| Агент | Главная сила | Главный риск для нас | Итог |
|---|---|---|---|
| **omp** | Pi-compatible JSONL + prompt flags + skills + subagents | Нужно проверить точные flags `--skill`/context disable | Лучший CLI-кандидат |
| **Pi** | Уже интегрирован, стабильный JSONL contract | Меньше возможностей, чем omp/deepagents | Baseline/fallback |
| **Qwen Code** | Prompt flags + stream-json | Skills CLI и стоимость слабее | Резерв |
| **Deep Agents Code** | MIT, BYO LLM, skills, AGENTS.md, subagents, SDK | Нет CLI JSONL и system-prompt flags | SDK-кандидат, CLI — частично |
| **Claude Code** | Богатый проприетарный coding UX | Proprietary, Anthropic-only, платный | Claude-specific fallback |
| **OpenCode** | 75+ providers | JSON события беднее, prompt surface иной | Provider fallback |

---

## Риски и рекомендации

1. **Не делать прямую замену `pi`/`omp` на `dcode` без adapter.** Основные блокеры — отсутствие JSONL event stream и system prompt flags в CLI; `dcode --acp` даёт ACP/RPC over stdio, но требует отдельного клиента и маппинга в наш JSONL contract.
2. **Рассматривать `deepagents` как SDK/ACP-кандидат.** Если нужна LangGraph-native интеграция с subagents, memory, HITL и MCP — это сильный вариант; для `dcode --acp` нужен отдельный spike.
3. **Для CLI/ACP smoke test проверить минимум:** exit codes, `--timeout`, `--max-turns`, `--quiet`, `--no-stream`, `--skill`, `--agent`, `--acp`, чтение `AGENTS.md`, поведение `.agents/skills/`.
4. **Для production-интеграции нужен contract test (контрактный тест):** schema событий, tool calls, usage/cost, cancellation (отмена), filesystem boundaries (границы файловой системы), sandbox режим, ACP-to-JSONL adapter behavior.
5. **Не смешивать роль и memory без решения архитектора.** AGENTS.md в Deep Agents Code — memory layer, а наши роли — runtime persona (роль выполнения). Перенос ролей в AGENTS.md может загрязнить проектный контекст.
