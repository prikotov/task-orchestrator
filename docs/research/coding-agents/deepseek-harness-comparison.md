# DeepSeek Harness (`dsh`) — исследование для интеграции как сабагент

**Дата исследования:** 2026-08-16
**Объект:** [`deepseek-ai/deepseek-harness`](https://github.com/deepseek-ai/deepseek-harness) — DeepSeek Harness (`dsh`), «Everything is a Plugin»
**Snapshot:** commit [`47f9438`](https://github.com/deepseek-ai/deepseek-harness/tree/47f943859bef60e4160492346772ded9b24f765a) (master, 2026-08-13); npm `@deepseek-ai/dsh` [0.1.0-rc.6](https://www.npmjs.com/package/@deepseek-ai/dsh) (2026-08-10); PyPI `deepseek-harness-sdk` 0.1.0rc6
**Задача:** [TASK-research-deepseek-harness](../../../todo/TASK-research-deepseek-harness.todo.md)
**Эпик:** [EPIC-research-coding-agents-comparison](../../../todo/done/EPIC-research-coding-agents-comparison.md) (стадия 1l, строка #22)

---

## Сводка

**DeepSeek Harness (`dsh`)** — open-source агентный харнес (agent harness, каркас для сборки агентов) от DeepSeek AI: TypeScript-монорепозиторий на vendored [Cordis](https://github.com/cordiverse/cordis), архитектура «everything is a plugin» (всё — плагин). Продуктовые поверхности:

- **Web UI** — `npx @deepseek-ai/dsh web`, браузерный интерфейс на `127.0.0.1:3080` (сессии, approval, presets);
- **Headless CLI** — `dsh --profile headless "task"`: одна сессия, финальный ответ в stdout, код возврата 0/1;
- **JSON-RPC SDK** — TypeScript (`packages/sdk`: protocol/client/server, stdio JSON-RPC) и Python (`deepseek-harness-sdk`: `DeepSeekHarness.run()`, turns API, bundled runtime без системного Node.js);
- **ACP-сервер** — automation-only Agent Client Protocol over stdio (отдельная композиция, не в shipped-профилях).

Метрики snapshot: создан 2026-08-13, **≈116.8k★ / ≈11.4k forks** (взрывной рост за 3 дня), MIT, TypeScript (24 MB TS + Python SDK), no releases (developer preview, «THERE WILL BE COMPATIBILITY-BREAKING CHANGES»).

Сильные стороны под наш сценарий (сабагент под роли команды): **эталонная реализация AGENTS.md (К4) и `.agents/skills/` (К5)** — ровно наш стек скиллов; первоклассный JSON-RPC SDK (TS+Python); встроенные субагент-провайдеры **codex / claude-code / acp / dsh-sdk** (обратная интеграция — dsh сам делегирует в Codex и Claude Code); LLM-адаптер на `@earendil-works/pi-ai` — **тот же каталог провайдеров, что у нашего baseline Pi**; полный контроль системного промпта через реестр `PromptSection` (`complete: true`), persona-плагин и `DSH_SYSTEM_PROMPT` в SDK-композиции.

Слабые стороны: **developer preview без единого релиза** (задекларированы ломающие изменения, npm 0.1.0-rc.6); CLI headless печатает **plain text** — JSONL event stream в stdout нет; $-стоимость не считается (только токены); Web UI — единственная интерактивная поверхность (стабильного TUI нет).

**Вердикт: ⚠️ Частично подходит (7/10, 26/30); SDK — ✅ Подходит для программной интеграции (9/10).**

| # | Критерий | Оценка | Балл |
|---|----------|--------|------|
| 1 | Системный промпт | ⚠️ | 2 |
| 2 | Промпт агента / роль | ⚠️ | 2 |
| 3 | Скиллы | ✅ | 3 |
| 4 | AGENTS.md | ✅ | 3 |
| 5 | `.agents/skills/` автосканирование | ✅ | 3 |
| 6 | Запуск как сабагент | ⚠️ | 2 |
| 7 | Токены и стоимость | ⚠️ | 2 |
| 8 | Free tier | ✅ | 3 |
| 9 | Провайдеры и модели | ✅ | 3 |
| 10 | Лицензия | ✅ | 3 |
| | **Итого CLI** | | **26/30** |

---

## Источники

- [README — deepseek-ai/deepseek-harness](https://github.com/deepseek-ai/deepseek-harness/blob/master/README.md) — запуск, developer preview
- [`apps/cli/README.md` + `apps/cli/reference/README.md`](https://github.com/deepseek-ai/deepseek-harness/blob/master/apps/cli/README.md) — режимы `dsh`, профили, AGENTS.md-бюджет 65 536 байт, permissions, telemetry
- [`docs/subsystems/skills.md`](https://github.com/deepseek-ai/deepseek-harness/blob/master/docs/subsystems/skills.md) — skill-реестр, ranks discovery, frontmatter
- [`docs/subsystems/system-prompt.md`](https://github.com/deepseek-ai/deepseek-harness/blob/master/docs/subsystems/system-prompt.md) — `PromptSection`, `complete: true`, persona
- [`docs/subsystems/subagent.md`](https://github.com/deepseek-ai/deepseek-harness/blob/master/docs/subsystems/subagent.md) — субагент-контракт, continuable-дети
- [`packages/context/agent-instructions/src/config.ts`](https://github.com/deepseek-ai/deepseek-harness/blob/master/packages/context/agent-instructions/src/config.ts) — `AGENTS.md`/`CLAUDE.md`(+`.local`) discovery, `~/.dsh` user-global
- [`docs/user/guide/providers.md`](https://github.com/deepseek-ai/deepseek-harness/blob/master/docs/user/guide/providers.md) — настройки моделей, кастомные провайдеры
- [`docs/user/guide/python-sdk.md`](https://github.com/deepseek-ai/deepseek-harness/blob/master/docs/user/guide/python-sdk.md) — `DSH_SYSTEM_PROMPT`, `DeepSeekHarness.run()`
- [`packages/subagent/subagent-codex/README.md`](https://github.com/deepseek-ai/deepseek-harness/blob/master/packages/subagent/subagent-codex/README.md), [`subagent-claude-code`](https://github.com/deepseek-ai/deepseek-harness/blob/master/packages/subagent/subagent-claude-code/README.md) — бриджи в Codex/Claude Code

---

## Критерий 1. Системный промпт

**⚠️ — полная замена есть на всех поверхностях, но нет единого CLI-флага.**

- **Реестр `ctx.systemPrompt`** ([subsystems/system-prompt.md](https://github.com/deepseek-ai/deepseek-harness/blob/master/docs/subsystems/system-prompt.md)): упорядоченные `PromptSection` (конвенция порядков: `-100` — идентичность харнеса, `0` — deployment persona, 100–199 — tool guidance). Секция с флагом **`complete: true` становится единственной секцией** — кооперативная сборка (tools/contexts/variables) всё равно выполняется, затем секция восстанавливается как весь промпт. Более одной эффективной `complete`-секции — ошибка сборки. Это самая развитая модель системного промпта среди исследованных агентов.
- **Persona-плагин `@deepseek-ai/dsh-persona`**: текст с `{{variable}}`-интерполяцией (`{{model}}`, `{{cwd}}`); в shipped base-бандле persona пустая (`''`) — выбор деплоймента; agent-presets (`standard`/`code`/`cordis`/`minimal`) переопределяют (shadow) её для своего агента; preset `minimal` фиксирует **полный** промпт `You are a helpful software engineer assistant.`
- **Per-child persona**: запрос сабагента принимает `persona` (capability `persona`) — приватная persona конкретного ребёнка, затеняет deployment-persona, та же семантика шаблона.
- **Python SDK**: переменная **`DSH_SYSTEM_PROMPT`** в композиции `examples/jsonrpc-agent/minimal.cordis.yml` заменяет системный промпт (fallback — `You are a helpful software engineer assistant.`); произвольная композиция передаётся через `cordis=...`.
- **CLI**: у headless-раннера флага `--system-prompt` **нет** — замена через patch-слой профиля (`cordis.patch.yml`, слои: bundles → profile → `$DSH_HOME` → `--patch`), что документировано и работает, но требует файла композиции, а не флага.

Файл роли (наш формат `docs/agents/roles/team/*.ru.md`) передаётся: через `DSH_SYSTEM_PROMPT`/композицию (SDK) или persona-строку в patch-слое (CLI). Прямого «подай файл» — нет.

## Критерий 2. Промпт агента / роль

**⚠️ — механика инъекции роли есть (persona + композиции + env), специализированного механизма ролей нет.**

- Контекст роли вводится тремя путями: (а) persona-текст с интерполяцией (см. К1); (б) `!!js`-выражения в `cordis.patch.yml` (в т.ч. чтение env: `!!js process.env.MY_ROLE`); (в) `DSH_SYSTEM_PROMPT` в SDK-композиции.
- Задача агента — positional-аргумент headless (`dsh --profile headless "task"`) или первый аргумент `harness.run()` в Python SDK.
- Динамический контекст: `PromptContext` — упорядоченные вкладки, материализуются как durable user-role snapshot (кэш-безопасное дополнение промпта).
- Ограничение: роли как продуктовой концепции (как наш `become-role`) нет; всё выражается через persona/patch/композицию. Для субагентов persona поддерживается на уровне контракта (`SubagentStartRequest.persona` + capability-флаг).

## Критерий 3. Скиллы

**✅ — полнофазная подсистема, включая remote-провайдеров.**

- Семейство пакетов `packages/skill`: реестр `ctx.skills` (host + per-scope слои, shadowing, ranks), локальный провайдер filesystem, опциональный badge-провайдер, потребитель — model-facing `skill` tool (каталог + загрузка по требованию).
- Формат: **каталожные бандлы `<name>/SKILL.md`** и плоские `<name>.md`; имя kebab-case `^[a-z0-9]+(?:-[a-z0-9]+)*$`; рекурсивный `**/SKILL.md` поиск не поддерживается.
- Frontmatter: `description`, `whenToUse`, **`disable-model-invocation`**, **`user-invocable`** — нормализуются в `SkillInvocationPolicy` (model-invocable / user-invocable независимо); в модельный каталог попадают только `name` + `description` (тело и абсолютные пути не раскрываются).
- Chokidar-наблюдение корней (IDE/Git/внешние мутации), инвалидация при `write`/`edit` от самой модели, LRU на проектные вотчеры; неполные наблюдения не кэшируются (last-good + retry).
- Расширяемость: `SkillProvider`-интерфейс — remote/embedded провайдеры описываются плагином; репозитории плагинов помечаются топиком [`dsh-plugin`](https://github.com/topics/dsh-plugin).

## Критерий 4. AGENTS.md

**✅ — эталонная реализация, богатейшая из всех 22 исследованных.**

- Пакет `dsh-agent-instructions`: авто-загрузка **`AGENTS.md` и `CLAUDE.md`** (+ оверлеи **`AGENTS.local.md`/`CLAUDE.local.md`**) — поднимаясь от cwd к корню проекта (маркер `.git`), все существующие кандидаты в каталоге загружаются с дедупликацией по обрезанному содержимому.
- **User-global**: фиксированный `AGENTS.md` в `$DSH_HOME` (по умолчанию `~/.dsh`).
- **Бюджеты**: render-бюджет 65 536 байт (shipped CLI), per-file cap `maxSourceBytes` (1 MiB); `maxBytes: false` — отключение. Список кандидатов настраивается (`instructionFileCandidates`), маркеры корня проекта — тоже.
- CLI behavior reference явно фиксирует: «All modes … load applicable `AGENTS.md` or `CLAUDE.md` instructions with a 65,536-byte render budget».
- Файл проекта читается через `ctx.fs`, когда доступен (remote/sandboxed workspace), а не только по хост-ФС.

## Критерий 5. Стандартная папка `.agents/skills/`

**✅ — прямая поддержка нашего стандартного расположения, в приоритетах discovery.**

Таблица рангов локального провайдера (`docs/subsystems/skills.md`):

| Ранг | Источник | Корень |
|---|---|---|
| 100 | `project-dsh` | `<projectRoot>/.dsh/skills` |
| 200 | **`project-agents`** | **`<projectRoot>/.agents/skills`** |
| 300 | `custom` | `Config.customSkillDirs` |
| 400 | `user-dsh` | `<dshHome>/skills` |
| 500 | **`user-agents`** | **`<agentsHome>/skills`** |

Проектный корень — ближайший предок с `.git` (проба через `ctx.fs`). То есть наш каталог `docs/agents/skills/` подключается либо напрямую как `.agents/skills` (симлинк/копия), либо через `customSkillDirs` — оба пути first-class.

## Критерий 6. Запуск как сабагент

**⚠️ (CLI: plain-text headless; SDK/ACP: ✅) — зеркальная ситуация с Deep Agents.**

- **Headless CLI**: `dsh --profile headless "task"` — одна fresh persisted-сессия, ждёт quiescence, **печатает последний непустой assistant-текст в stdout**, exit 0 при `turn/end completed`, иначе 1; при успехе stderr пуст, слушающих портов нет. **JSONL event stream в stdout нет** (наш `watch-subagent.sh`-контракт не выполняется), но полный JSONL-лог сессии (собранные model-запросы + tool calls) пишется в `--session-root`.
- **JSON-RPC SDK (TS)**: `packages/sdk` — protocol + client + server (stdio JSON-RPC); callers supply runtime executable + `cordis.yml`.
- **Python SDK** (`deepseek-harness-sdk` 0.1.0rc6): `DeepSeekHarness(provider=…, model=…, cwd=…, session_root=…, cordis=…)` контекст-менеджер; `run(task, session_id=…)` → `result.final_response`; реюз сессии сохраняет персистентный bash-процесс; bundled runtime — системный Node.js не нужен (Linux x64/arm64, macOS 14+ arm64).
- **ACP**: automation-only ACP-сервер over stdio (интероп-транспорт; в shipped web/headless не смонтирован).
- **Сам делегирует**: субагент-провайдеры `in-process`, `fork`, `acp`, **`codex`** (`codex app-server --stdio`, ephemeral-тред), **`claude-code`** (Claude Agent SDK), `dsh-sdk`; continuable-фоновые дети, `send_message`/`interrupt_agent`/`list_agents`, отчёт-канал `report`, output schema, depth limit, tool filter, persona.
- **Таймауты**: `timeout-policy` (guard) — per-tool кооперативный дедлайн из `ToolDefinition.timeoutMs` (zero-config), bash-timeout (300 s в SDK-примере), `AbortSignal` сквозной (запрос → provider → процесс-дерево), эскалация терминации дерева, `disposeGraceMs`.

## Критерий 7. Токены и стоимость

**⚠️ — точный токен-метр есть; долларовой стоимости нет.**

- `dsh-token-meter`: detached-снапшот `TokenMeasurement` — request pressure (`totalTokens`) и surface-разбивка (`surfaceTokens`, positional `nodes[]`); baseline — **provider usage anchor** (последний успешный вызов с совпадающим каноническим конвертом) либо фиксированная эвристика; signed `surfaceDeltaTokens` (рост/сжатие поверхности).
- JSONL-лог сессии содержит собранные model-запросы — usage провайдера доступен постфактум.
- **Расчёта стоимости в $ нет** (ни в метре, ни в CLI-выводе). Телеметрия — OTLP/HTTP (`DSH_TELEMETRY_MODE=FULL`), по умолчанию выключена; hard opt-out `DSH_TELEMETRY_DISABLED`.

## Критерий 8. Free tier

**✅ — бесплатный open-source инструмент, BYOK (платите только за API моделей).**

- Сам `dsh` бесплатен и MIT; аккаунт/подписка не нужны. Ключи: env (`DEEPSEEK_API_KEY` и др.), `$DSH_HOME/.credentials.yaml`, `.env` в каталоге запуска, `$DSH_HOME/.env`; ключи write-only (redacted descriptor в UI).
- DeepSeek API — pay-as-you-go, собственного free tier у харнеса нет (как у Pi/omp — инструмент бесплатный, модели оплачиваются провайдеру).

## Критерий 9. Провайдеры и модели

**✅ — родной DeepSeek-адаптер + каталог pi-ai (тот же, что у Pi) + произвольные custom-роуты.**

- **`dsh-llm-deepseek`** (native, монтируется в base-бандле): `deepseek-v4-flash`, `deepseek-v4-pro` (+ reasoning controls `off`/`high`).
- **`dsh-llm-pi-ai`** на `@earendil-works/pi-ai` ^0.82.1 — **та же библиотека адаптеров, что в нашем baseline Pi**: каталог провайдеров (OpenAI, Anthropic, Google, DeepSeek, xAI, ZAI и др.), override полей каталога, retry/backoff, `streamIdleTimeoutMs`, reasoning-диалекты.
- **Catalog-провайдеры Web UI**: Anthropic, OpenAI, Bedrock (AWS creds), Vertex (ADC), Azure (`api-version`), Codex (OAuth).
- **Custom-провайдер**: Provider ID + baseURL + протокол (`openai-completions` и др. из `supportedProtocols()`) + модели (fetch `GET /models` или вручную; `input: [text, image]` для vision) → любой OpenAI-compatible шлюз, self-hosted, Ollama/vLLM/LM Studio.
- Python SDK: `provider="deepseek-official"` либо `DEEPSEEK_BASE_URL` на OpenAI-compatible прокси.

## Критерий 10. Лицензия

**✅ — MIT.** Репозиторий `deepseek-ai/deepseek-harness`, third-party notices в `THIRD_PARTY_NOTICES.md`, vendored Cordis (свой манифест и процедура синхронизации).

---

## Итоговая оценка

**CLI-вердикт: ⚠️ Частично подходит — 7/10 (26/30). SDK-вердикт: ✅ Подходит — 9/10.**

Что закрывает наш чек-лист сабагента лучше всех в выборке:
1. **К4/К5 — эталон**: AGENTS.md (+CLAUDE.md, +.local, user-global, бюджеты) и `.agents/skills` + `~/.agents/skills` — наш формат скиллов и контекстных файлов подхватывается нативно, без обвязки.
2. **Контроль промпта**: `complete: true` + persona с интерполяцией + per-child persona — роль команды внедряется детерминированно (SDK: `DSH_SYSTEM_PROMPT`).
3. **Программная поверхность**: JSON-RPC SDK на двух языках, персистентные сессии (JSONL-логи с полными запросами), cancellation/таймауты на каждом уровне.
4. **Обратная интеграция**: провайдеры `codex`/`claude-code` — dsh способен оркестрировать тех же агентов, что и мы; интересный кандидат на «агента-агрегатора», а не только исполнителя.

Что удерживает от ✅ Подходит (CLI):
- **Developer preview**: репозиторию 3 дня, ни одного релиза/тега, npm 0.1.0-rc.6, README прямо предупреждает о ломающих изменениях; внутренние форматы (`SESSION_FORMAT_VERSION 0`) без обещаний совместимости.
- **Нет JSONL-событий в CLI stdout** (наш `watch-subagent.sh`-контракт): headless печатает только финальный текст; детальные события — в JSONL-файле сессии или через SDK.
- **К7**: стоимость в $ не считается.
- Интерактивная поверхность — только Web UI (стабильного TUI-профиля в поставке нет).

SDK-оценка 9/10: К1–К5, К8–К10 ✅; К6 ✅ (JSON-RPC turns API, cancellation, персистентные сессии); минус один балл за К7 (токены — да, $ — нет) и preview-статус runtime.

## Практические примеры

### Web UI

```sh
npx @deepseek-ai/dsh web          # http://127.0.0.1:3080
# Settings → Models: DeepSeek API key (или Add provider / Add a custom provider)
```

### Headless one-shot

```sh
export DEEPSEEK_API_KEY=sk-...
dsh --profile headless "Inspect the repository and fix the failing tests."
# stdout: последний непустой ответ ассистента; exit 0/1; сессия-JSONL в DSH_SESSION_ROOT
```

### Python SDK (сабагент с ролью)

```sh
pip install deepseek-harness-sdk
export DEEPSEEK_API_KEY=sk-...
export DSH_SYSTEM_PROMPT="$(cat docs/agents/roles/team/backend_developer_levsha.ru.md)"
export DSH_MODEL=deepseek-v4-flash
```

```python
from pathlib import Path
from deepseek_harness import DeepSeekHarness

with DeepSeekHarness(
    provider="deepseek-official",
    model="deepseek-v4-flash",
    cwd="/abs/workspace",
    session_root="/abs/sessions",
    cordis="examples/jsonrpc-agent/minimal.cordis.yml",
) as h:
    result = h.run("Реализуй задачу TASK-… по конвенциям проекта.", session_id="levsha-001")
print(result.final_response)
```

### Скиллы проекта

```text
<projectRoot>/.agents/skills/become-role/SKILL.md   # rank 200 — подхватится автоматически
# альтернатива: Config.customSkillDirs → docs/agents/skills
```

## Сравнение с ближайшими аналогами

| | **dsh** | omp (#1) | Pi (#2) | Deep Agents (#4) | OpenCode (#5) |
|---|---|---|---|---|---|
| К1 замена промпта | ⚠️ patch/persona/env, без флага | ✅ | ✅ | ⚠️ | ✅ |
| К4 AGENTS.md | ✅ +CLAUDE.md, +.local, бюджеты | ✅ | ✅ | ✅ | ✅ |
| К5 `.agents/skills` | ✅ rank 200/500 | ✅ | ✅ | ✅ | ✅ |
| К6 JSON-режим | ⚠️ SDK/ACP ✅, CLI plain | ✅ `--mode json` | ✅ `--mode json` | ⚠️ ACP | ⚠️ `--format json` |
| К7 $ стоимость | ❌ токены ✅ | ✅ | ✅ | ✅ | ✅ |
| К9 провайдеры | ✅ DeepSeek native + pi-ai каталог + custom | ✅ 40+ | ✅ 20+ (pi-ai) | ✅ BYO LLM | ✅ 75+ |
| Субагенты наружу | ✅ codex/claude-code/acp | ✅ native | ✅ native | ✅ sub-agents | ✅ |
| Стабильность | ❌ preview, 0 релизов | ✅ | ✅ | ✅ | ✅ |

Уникальное в выборке: пер-секционная сборка промпта с `complete`-семантикой; ранжированное многослойное discovery скиллов; continuable-фоновые субагенты; бриджи в сторонние CLI-агенты как субагент-провайдеры.

## Риски и рекомендации

1. **Preview-статус — главный риск.** До первого тегированного релиза любое обновление может сломать композицию/протокол. Для продакшена — пинновать версии `@deepseek-ai/dsh`/`deepseek-harness-sdk` и snapshot-коммит.
2. **Контракт `watch-subagent.sh`** (JSONL stdout) не выполняется — интеграция через Python SDK (наш раннер оборачивает `DeepSeekHarness.run()`), а не через CLI-пайп.
3. **К7**: стоимость считать на нашей стороне по usage из JSONL-логов сессии.
4. Рекомендация: **не менять текущий стек (omp #1 / Pi fallback)**; внести dsh в watch-list — после первого стабильного релиза пересмотреть вердикт (кандидат в топ-5, потенциально выше OpenCode: К3–К5/К8–К10 сильнее при равной сумме 26). Отдельный быстрый эксперимент: субагент-провайдеры `codex`/`claude-code` внутри dsh как альтернативный агрегатор для мультиагентных цепочек.
