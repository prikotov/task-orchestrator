# ZCode (Z.AI / Zhipu) — Исследование для интеграции как сабагент

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-06-17
**Объект:** ZCode v3.1.1 (Z.AI / Zhipu AI, desktop GUI-агент, модель GLM-5.2)
**Задача:** [TASK-research-zcode-coding-agent](../../../todo/done/TASK-research-zcode-coding-agent.todo.md)

---

## Сводка

ZCode — **desktop GUI-приложение** для разработки от Zhipu AI (Z.AI / BigModel), распространяемое как установочный пакет (`.dmg`/`.exe`/Linux beta), v3.1.1. **Проприетарный** продукт (Terms of Use, © 2026 ZCode), глубоко адаптированный под собственное семейство моделей **GLM-5.2 / GLM-5-Turbo**. Взаимодействие — только через графический интерфейс: workspace, модель-пикер, Execution Modes, Skill/MCP/Model Settings. Язык интерфейса — английский + китайский.

**Архитектурно ZCode — не CLI-coding agent и не headless-агент.** Это интерактивное IDE-подобное приложение. Вся кастомизация (модели, MCP, скиллы, контекст проекта) выполняется через панель **Settings → …** в GUI, а не через CLI-флаги или конфигурационные файлы. Программного (headless / JSON / JSONL / programmatic API) интерфейса для запуска как сабагент **нет**: «Remote Control» — это подключение телефона по QR-коду, «Bot Channel» — мосты в WeChat/Feishu, а «Subagents» — встроенный read-only помощник **Explore** (кастомные сабагенты пока не поддерживаются).

**Ключевой вывод:** ZCode **не подходит** для использования как сабагент с нашей системой ролей и скиллов — фундаментальное отсутствие headless/JSON-режима (К6) и возможности замены системного промпта (К1) делает интеграцию через wrapper невозможной. При этом ZCode — ценный **объект изучения паттернов**: Goal Mode (self-verifying iterative loop — прямой аналог нашего dynamic-loop + quality-gate), 5 Execution Modes, AGENTS.md(High)+CLAUDE.md(Low), импорт skills/MCP из внешних агентов.

> 📎 **Принадлежность к эпику.** ZCode — кодовый агент, его `subagents` — это feature продукта (read-only Explore + roadmap кастомных), а не отдельная система оркестрации. Отдельного исследования в `EPIC-research-agent-frameworks-comparison` **не требуется**. Прецедент: `oh-my-openagent` был перенесён *из* coding-agents *в* frameworks как «система оркестрации»; ZCode — строго обратный случай. Аналогия: Claude Code и Codex также имеют механизмы сабагентов, но исследуются как один продукт в одном эпике.

---

## Критерий 1. Системный промпт

### Возможности

ZCode **не предоставляет** программного механизма замены или дополнения системного промпта. Нет CLI-флагов (`--system-prompt`, `--append-system-prompt`), нет env-переменных, нет конфигурационного поля для системного промпта.

| Механизм | Поведение |
|----------|-----------|
| CLI-замена (`--system-prompt`) | ❌ Нет (нет headless CLI вообще) |
| CLI-дополнение (`--append-system-prompt`) | ❌ Нет |
| Файл замены системного промпта | ❌ Не документирован |
| Execution Modes | ⚠️ Меняют уровень автономии (Default/Confirm/Auto Edit/Plan/Full Access), а **не** системный промпт |
| AGENTS.md / CLAUDE.md | ⚠️ Это контекстные инструкции проекта (см. К4), а не замена системного промпта |

Системный промпт контролируется самим продуктом ZCode и адаптирован под GLM-5.2. Пользователь не может его переопределить или расширить.

### Сравнение с Pi и Codex

| Аспект | Pi | Codex CLI | ZCode |
|--------|-----|-----------|-------|
| Полная замена (CLI) | ✅ `--system-prompt` | ⚠️ `model_instructions_file` | ❌ Нет |
| Дополнение (CLI) | ✅ `--append-system-prompt` | ❌ Нет | ❌ Нет |
| Файловая замена | ✅ `.pi/SYSTEM.md` | ✅ config | ❌ Нет |
| Файловое дополнение | ✅ `.pi/APPEND_SYSTEM.md` | ❌ Нет | ❌ Нет |

### Оценка: ❌ Не поддерживается

Полное отсутствие механизмов управления системным промптом. Это сразу исключает инъекцию произвольной роли на уровне системы.

---

## Критерий 2. Промпт агента / Роль

### Возможности

У ZCode **нет понятия ролей** как отдельной переключаемой сущности. Единственная пользовательская настройка «поведения» — Execution Modes (уровень подтверждений). Никакого CLI, env или конфигурационного механизма инъекции контекста роли нет.

| Механизм | Описание |
|----------|----------|
| Execution Modes | Default / Confirm Before Changes / Auto Edit / Plan Mode / Full Access — уровень автономии, не роль |
| AGENTS.md | Можно описать операционные инструкции команды, но это контекст проекта, а не role-switching |
| User prompt | Описание роли можно дать в задаче вручную (наименее надёжно) |

Изоляция ролей (как у OpenCode `.opencode/agent/*.md` или Zeroclaw workspace-per-role) **отсутствует** — один workspace = одна «persona» продукта ZCode.

### Оценка: ❌ Не поддерживается

Нет механизма инъекции роли и изоляции ролей. Невозможно запускать разные роли как отдельных сабагентов.

---

## Критерий 3. Скиллы

### Возможности

ZCode **полностью поддерживает** стандарт Agent Skills (`SKILL.md` с frontmatter `name`/`description`) — это одна из сильных сторон продукта.

| Механизм | Поведение |
|----------|-----------|
| `~/.zcode/skills/<name>/SKILL.md` | ✅ User-level скиллы (глобально для всех workspace) |
| Project-level скиллы | ✅ При импорте с target = Project (текущий workspace) |
| `$skill-name` в чате | ✅ Вызов скилла инлайн в пользовательском промпте |
| Settings → Skills | ✅ GUI-управление: search, enable/disable, refresh, New Skill |
| Создание скилла через агента | ✅ «New Skill» — ZCode генерирует скилл в чате |
| **Импорт из внешних агентов** | ✅ Claude Code, Codex CLI, OpenClaw, Augment, Windsurf — symlink или copy, target Global/Project |

Минимальный `SKILL.md` полностью совместим с нашим форматом:

```markdown
---
name: code-review-checklist
description: Review code changes with a focused checklist for correctness, regressions, tests, and maintainability.
---
# Code Review Checklist
Use this skill when reviewing a pull request...
```

### Ограничения

- ❌ **Нет CLI-флага `--skill`** — управление только через GUI.
- ❌ **Нет per-role назначения** — все скиллы глобальны в рамках workspace; нельзя дать разным ролям разные наборы (как `--skill` у Pi/Warp/Hermes).
- ⚠️ Импорт из внешних агентов сканирует их собственные директории, а не стандарт `.agents/skills/` (см. К5).

### Сравнение с Pi и Codex

| Аспект | Pi | Codex CLI | ZCode |
|--------|-----|-----------|-------|
| Agent Skills standard | ✅ | ⚠️ Глобальные | ✅ Полная |
| CLI `--skill` | ✅ | ❌ | ❌ Нет (GUI only) |
| Разным ролям — разные скиллы | ✅ | ❌ | ❌ Нет |
| Импорт из внешних агентов | ❌ | ❌ | ✅ Уникальная фича |

### Оценка: ⚠️ Частичная поддержка

Стандарт SKILL.md поддержан полно, плюс ценная фича импорта из внешних агентов. Но нет CLI-управления и per-role назначения — для нашей системы ролей (разным ролям разные скиллы) этого недостаточно.

---

## Критерий 4. AGENTS.md (контекстные файлы)

### Возможности

Одна из **сильнейших** сторон ZCode — первоклассная поддержка контекстных файлов проекта.

| Механизм | Приоритет | Поведение |
|----------|-----------|-----------|
| `AGENTS.md` | **High** | Рекомендованный файл инструкций проекта для ZCode Agent |
| `CLAUDE.md` | Low | Файл совместимости для существующих проектов Claude Code |

**Логика автообнаружения:**
- ZCode стартует от текущего рабочего каталога и ищет **вверх по родительским директориям**, пока не найдет первый доступный файл инструкций, не достигнет корня проекта или корня файловой системы.
- Если в одном каталоге есть и `AGENTS.md`, и `CLAUDE.md` — **ZCode читает `AGENTS.md` первым** (приоритет High).
- ZCode читает **один** совпавший файл инструкций: **не мержит** несколько `AGENTS.md`/`CLAUDE.md` по уровням каталогов, **не сканирует** подкаталоги, не раскрывает `@import`/`@include`, не выбирает файл правил по типу задачи.

Рекомендуемое содержание `AGENTS.md` (по документации): стек проекта, структура каталогов и важные модули; стиль кода, правила именования и команды валидации; особый уход для high-risk файлов/production-конфигов; предпочтения по коллаборации (план → реализация, без лишних рефакторингов).

### Ограничения

- ❌ **Отключение через CLI** — не предусмотрено (нет CLI вообще). Обход — просто не размещать файл.

### Сравнение с Pi и Codex

| Аспект | Pi | Codex CLI | ZCode |
|--------|-----|-----------|-------|
| AGENTS.md авто | ✅ | ✅ | ✅ (вверх по дереву) |
| CLAUDE.md авто | ✅ | ❌ | ✅ (Low, fallback) |
| Приоритет AGENTS > CLAUDE | ✅ | — | ✅ (явно задокументирован) |
| CLI-отключение | ✅ `--no-context-files` | ❌ | ❌ |

### Оценка: ✅ Полная поддержка

Первоклассная поддержка `AGENTS.md` (High) + `CLAUDE.md` (Low) с явной семантикой приоритета и поиском вверх по дереву. На уровне Pi. Единственный минус — нет CLI-отключения (но для GUI-продукта это ожидаемо).

---

## Критерий 5. Стандартная папка `.agents/skills/`

### Автосканирование

ZCode **не поддерживает** автосканирование `.agents/skills/` или `docs/agents/skills/` для скиллов.

| Локация | Поддержка |
|---------|-----------|
| `~/.zcode/skills/` | ✅ User-level (основная локация) |
| `<workspace>` (Project target при импорте) | ✅ Project-level |
| Внешние директории агентов (через Import) | ✅ Claude/Codex/OpenClaw/Augment/Windsurf |
| `.agents/skills/` | ❌ Не автосканируется |
| `docs/agents/skills/` | ❌ Не автосканируется |

### Частичная поддержка стандарта `.agents/`

Важно: ZCode поддерживает стандарт `.agents/` **для MCP**, но **не для скиллов**:

> ZCode discovers importable MCP servers from the following sources: … **Generic `.agents`: `~/.agents/mcp.json`**

То есть `~/.agents/mcp.json` распознаётся для импорта MCP-серверов, но аналогичного автосканирования `~/.agents/skills/` или `.agents/skills/` в проекте — нет.

### Наша структура

Наши скиллы лежат в `docs/agents/skills/`. Для загрузки в ZCode нужно:
1. **Скопировать** в `~/.zcode/skills/` (user-level), либо
2. **Симлинкнуть** через флоу Import (если источник — одна из поддерживаемых внешних директорий), либо
3. Импортировать через GUI Import с режимом Symlink/Copy.

### Оценка: ❌ Не поддерживается

Нет автосканирования `.agents/skills/`. Проектные скиллы нужно явно устанавливать/импортировать через GUI. (Стандарт `.agents/` поддержан только для MCP.)

---

## Критерий 6. Запуск как сабагент (JSON-режим)

### Возможности

Это **ключевой блокер**. ZCode — интерактивное desktop GUI-приложение, у него **нет** headless CLI, JSON/JSONL-стриминга, programmatic API или pipe-управления.

| Канал/режим | Что это | Пригодно как сабагент? |
|-------------|---------|------------------------|
| Интерактивный GUI | Основной режим работы (workspace + чат) | ❌ Не программно |
| **Remote Control** | Подключение **телефона** по QR-коду к desktop-workspace (desktop остаётся runtime) | ❌ Не API |
| **Bot Channel** | Открытие workspace из WeChat/Feishu через бота | ❌ Мессенджер-мост, не JSON |
| **Subagents (Explore)** | Встроенный read-only сабагент: поиск/исследование кода, call-chain, evidence gathering — запускается главным агентом через «Agent tool» в изолированном контексте | ❌ Внутренний, не программный |
| Headless CLI / `--print` / JSONL | — | ❌ **Не существует** |

### Subagents — детально (по запросу пользователя)

Раздел документации `/subagents` описывает feature всё того же ZCode:

- **Поддерживается:** `Explore` — read-only file-search и codebase-research специалист. Не создаёт/изменяет/перемещает/удаляет файлы. Использует read/search-инструменты (чтение файлов, match имён, regex-поиск, чтение известных URL). Хорошие задачи: найти реализацию возможности, отобразить entry points/call chains/зависимости, изучить риски перед изменениями, параллельный поиск по нескольким каталогам/паттернам. Главный агент вызывает Explore через **Agent tool**, Explore работает в своём контексте и возвращает саммари в основной разговор.
- **Не поддерживается (roadmap):** пользовательские кастомные сабагенты. Нельзя: определить новый сабагент Markdown-файлом, класть agent-файлы в `~/.zcode/cli/agents/` или workspace-директорию, настраивать frontmatter (`name`/`description`/`model`/`tools`), вручную выбирать сабагента через `@`, назначать отдельные модели/инструменты/системные промпты кастомным сабагентам.

**Вывод для оркестратор-эпика:** механизм сабагентов ZCode — это внутренняя оптимизация контекста одного агента (read-only research-делегирование), а не оркестрация нескольких ролей/моделей/провайдеров. Модели оркестрации, state management, workflow-engine — отсутствуют. Поэтому в `EPIC-research-agent-frameworks-comparison` ZCode **не включается**.

### Контроль таймаутов / Ephemeral

| Механизм | Поведение |
|----------|-----------|
| Внешний `timeout` | ❌ Не применим (нет CLI-процесса) |
| Таймаут сессии | ⚠️ Goal Mode показывает elapsed time/iteration count, но программной отмены по таймауту нет |
| Ephemeral / `--no-session` | ❌ Нет — продукт stateful и workspace-oriented |

### Mermaid-диаграмма: почему ZCode не сабагент и не оркестратор

```mermaid
flowchart TD
    Z[ZCode desktop GUI] -->|interactive workspace| A[ZCode Agent]
    A -->|Agent tool, internal| E[Explore subagent<br/>read-only research]
    A -->|settings GUI| S[Skills / MCP / Models]
    Z -->|phone via QR| RC[Remote Control]
    Z -->|WeChat / Feishu| BC[Bot Channel]

    ORC{Наш orchestrator<br/>wrapper (watch-subagent.sh)?}
    ORC -.->|нужен headless CLI / JSONL / API| X[❌ У ZCode этого нет]

    FW{EPIC agent-frameworks<br/>система оркестрации?}
    FW -.->|нужен workflow/state/routing| Y[❌ У ZCode этого нет]

    style X fill:#fdd
    style Y fill:#fdd
    style E fill:#ffd
```

### Оценка: ❌ Не поддерживается

Полное отсутствие headless/JSON/programmatic-интерфейса — автоматический дисквалификатор для сабагентной интеграции. Все «каналы» (Remote Control, Bot Channel, Explore) — это UX-фичи GUI-продукта, а не программные точки входа.

---

## Критерий 7. Токены и стоимость

### Возможности

ZCode имеет богатую **внутреннюю телеметрию**, но доступна она только через **GUI-панели** Usage Stats.

| Источник | Метрики |
|----------|---------|
| **App Usage** (локально) | ✅ Token usage, sessions, messages, active days, current/longest streak, peak hour, daily token trends, activity heatmap, model usage ranking |
| **Coding Plan** (remote Z.ai/BigModel) | ✅ Quota (5-hour prompt pool, weekly quota, monthly MCP quota), токены по моделям (GLM-5.2, GLM-5-Turbo), tool calls (Network Search MCP, Web Reader MCP) |
| **Goal Mode** summary card | ✅ Elapsed time, total tokens, iteration count |

Breakdown по моделям доступен при наведении на день в daily trend chart.

### Ограничения

- ❌ **Нет программного доступа** — нет JSON/JSONL/CLI-вывода токенов (следствие К6: нет headless режима).
- ❌ **Расчёт стоимости в $** не представлен — Usage Stats показывает токены и квоту Coding Plan, но не прямую стоимость в долларах (цена — через подписку Coding Plan Lite/Pro/Max).
- ⚠️ Для Coding Plan показывается «remaining quota» для пулов, а не money-cost.

### Сравнение с Pi и Codex

| Аспект | Pi | Codex CLI | ZCode |
|--------|-----|-----------|-------|
| Токены в JSON/JSONL | ✅ per-turn | ⚠️ TUI only | ❌ Только GUI |
| Стоимость в $ | ✅ `cost{...}` | ❌ Только лимиты | ❌ Только квота плана |
| Per-model breakdown | ✅ | ❌ | ✅ (в GUI) |

### Оценка: ⚠️ Частичная поддержка

Телеметрия существует и довольно детальна (per-model токены, квоты, trends), но доступна **только через GUI** — программно (для wrapper/сабагента) её получить нельзя. Для нашего сценария headless-сабагента — бесполезна.

---

## Критерий 8. Free tier

### ZCode как продукт

ZCode — **условно-бесплатный** GUI-клиент. Стоимость определяется модельным провайдером (Z.ai / BigModel) либо BYOK.

| Источник бесплатного использования | Описание |
|------------------------------------|----------|
| **Free Trial Quota** | ✅ Новые пользователи получают trial-план сразу после подключения аккаунта BigModel или Z.ai — без оплаты, с **бесплатным дневным квотой** для флагманских GLM-моделей (GLM-5.2, GLM-5-Turbo) |
| **Coding Plans** (Lite / Pro / Max) | Платная подписка (USD для Z.ai, CNY для BigModel), биллинг monthly/quarterly/yearly — покупка in-app |
| **BYOK** | ✅ Любой совместимый Anthropic/OpenAI-провайдер со своим ключом, включая бесплатные модели OpenRouter |

### Ограничения и комплаенс

- ⚠️ Конкретные лимиты free trial (RPM, токенов/день, запросов) **не задокументированы** явно — только «free daily quota».
- ⚠️ По Terms of Use (раздел III.3): бесплатный сервис может быть «скорректирован по мере исследования» — ZCode не несёт ответственности за изменение free-условий.
- ⚠️ **Юрисдикция — КНР** (Haidian District, Beijing); экспортные ограничения и комплаенс для использования вне Китая — на пользователе (Terms, раздел II.5).

### Сравнение с Pi и Codex

| Аспект | Pi | Codex CLI | ZCode |
|--------|-----|-----------|-------|
| Бесплатность | ✅ Полностью (MIT) | ⚠️ Требует подписку OpenAI $20+/мес | ⚠️ Free trial quota + платные Coding Plans |
| BYOK | ✅ | ✅ | ✅ |
| Локальные модели | ✅ Ollama/LM Studio | ✅ `--oss` | ⚠️ Через compatible endpoint (неявно) |

### Оценка: ⚠️ Частичная поддержка

Есть бесплатный дневной trial quota + BYOK, но это проприетарный продукт с платными Coding Plans, без документированных конкретных лимитов free tier и с комплаенс-нюансами юрисдикции КНР.

---

## Критерий 9. Провайдеры и модели

### Поддерживаемые провайдеры

ZCode поддерживает три способа подключения моделей (Settings → Model Settings, **через GUI**):

| Группа | Провайдеры | Модели |
|--------|-----------|--------|
| **Built-in (Zhipu)** | Z.ai (международный), BigModel (Китай) | GLM-5.2, GLM-5-Turbo — основной фокус, «deeply adapted» |
| **BYOK, named** | Anthropic (Claude API), OpenAI, OpenRouter, Moonshot (Kimi), MiniMax, Xiaomi MiMo | По API-ключу, endpoint авто-детектит список моделей |
| **BYOK, custom** | Любой сервис, совместимый с **Anthropic** или **OpenAI** протоколом (DeepSeek, enterprise-каналы, self-hosted) | Endpoint auto-fetch модели; `Add Model` вручную |

Поддерживаемые Base URLs (из документации):
- BigModel: `https://open.bigmodel.cn/api/anthropic`
- Z.ai: `https://api.z.ai/api/anthropic`
- Anthropic: `https://api.anthropic.com`
- OpenRouter: `https://openrouter.ai/api`
- DeepSeek (пример custom): `https://api.deepseek.com/anthropic` (Anthropic) / `…/v1` (OpenAI)

### BYOK и переключение

- ✅ Полный BYOK: API Key + Base URL для любого совместимого провайдера.
- ✅ Через OpenRouter — доступ к 100+ моделям всех вендоров.
- ⚠️ **Локальные модели** (Ollama, LM Studio) — явно не задокументированы, но технически подключаемы через custom OpenAI-compatible endpoint (Ollama отдаёт `/v1`).
- ❌ Переключение — через модель-пикер в GUI, **не** через CLI `--provider`/`--model`.

### Сравнение с Pi и Codex

| Аспект | Pi (20+) | Codex CLI | ZCode |
|--------|----------|-----------|-------|
| Native providers | 5 подписочных + 15 API | OpenAI + Ollama/LM Studio | Z.ai + BigModel + named BYOK + any compatible |
| OpenRouter | ✅ | ❌ | ✅ |
| Custom compatible | ✅ | ❌ | ✅ (Anthropic/OpenAI) |
| Локальные модели | ✅ явные | ✅ `--oss` | ⚠️ Через compatible (неявно) |
| Переключение | ✅ CLI `--provider` | ⚠️ config | ❌ Только GUI |

### Оценка: ✅ Полная поддержка

Широкая поддержка провайдеров через named BYOK + custom Anthropic/OpenAI-compatible + OpenRouter. Технически покрывает «любую модель», хотя основной фокус и оптимизация — под GLM-5.2. Минус — управление только через GUI (нет CLI).

---

## Критерий 10. Лицензия

### Информация

| Параметр | Значение |
|----------|----------|
| Продукт | ZCode (desktop-агент) |
| Версия | v3.1.1 (на дату исследования) |
| Вендор | Zhipu AI (Z.AI / BigModel); контакт `codegeex@zhipuai.cn` |
| Платформы | macOS (Apple Silicon/Intel), Windows, Linux beta |
| **Лицензия** | **Проприетарная** (Terms of Use, © 2026 ZCode) |
| Исходный код | Закрытый |

### Ключевые ограничения (Terms of Use)

Из официальных Terms of Use (раздел II.3 и VI.2):

1. ❌ **Reverse engineering запрещён**: «Reverse engineering of any algorithm, source code, mechanism, etc. of ZCode».
2. ❌ **Запрет на разработку конкурирующих продуктов**: «You may not use ZCode to develop products or services that compete with ZCode».
3. ❌ **Запрет на обучение конкурирующих моделей**: «Use ZCode to develop, train or improve other algorithms and models that have a direct or indirect competition relationship with us».
4. ❌ **Запрет на извлечение данных**: «Trying to extract data from ZCode in any way».
5. ❌ **Запрет на перепродажу API**: «buy, sell, or transfer our API without our individually expressed consent».
6. ⚠️ **Юрисдикция — КНР** (раздел X): применяется право PRC (без коллизионных норм), споры — суд Haidian District, Beijing.

IP (логотипы, тексты, имена сервисов) принадлежат Zhipu и защищены законами КНР и международными договорами. Контент, сгенерированный пользователем, — copyright пользователя, но без права удалять/изменять IP-уведомления ZCode.

### Оценка: ❌ Проприетарная

Закрытый код с жёсткими ограничениями (reverse engineering, конкурирующие продукты/модели, перепродажа API) и китайской юрисдикцией. Максимальный vendor lock-in — на уровне GitHub Copilot CLI / Claude Code, но с дополнительным комплаенс-бременем юрисдикции КНР.

---

## Вердикт

### ❌ Не подходит (Score: 17/30; пригодность 4/10)

ZCode **не подходит** для использования как сабагент с нашей системой ролей и скиллов. Фундаментальное архитектурное несоответствие нашему паттерну «headless CLI-сабагент, запускаемый через wrapper»: ZCode — интерактивное desktop GUI-приложение без headless/JSON/programmatic-интерфейса.

### Сильные стороны

1. **AGENTS.md (High) + CLAUDE.md (Low)** — первоклассная поддержка контекстных файлов с явной семантикой приоритета и поиском вверх по дереву (на уровне Pi).
2. **Agent Skills standard** — полная поддержка `SKILL.md` + `$skill-name`, плюс **импорт скиллов из внешних агентов** (Claude Code, Codex CLI, OpenClaw, Augment, Windsurf) — уникальная фича.
3. **MCP** — поддержка stdio/SSE/HTTP + импорт MCP-серверов из внешних агентов, включая стандарт `.agents/` (`~/.agents/mcp.json`).
4. **Широкая поддержка провайдеров** — Z.ai/BigModel + named BYOK (Anthropic, OpenAI, OpenRouter, Moonshot, MiniMax, Xiaomi MiMo) + любой Anthropic/OpenAI-compatible.
5. **Goal Mode** — встроенный self-verifying iterative loop: агент сам проверяет достижение цели каждую итерацию и продолжает, пока верификация не пройдёт. Прямой аналог нашего dynamic-loop + quality-gate.
6. **5 Execution Modes** — Default / Confirm Before Changes / Auto Edit / Plan / Full Access — градация автономии.

### Ключевые ограничения (vs Pi)

1. **Нет headless/JSON/programmatic-режима** (К6) — нет CLI, нет JSONL, нет API. Это **блокер** для сабагентной интеграции.
2. **Нет замены/дополнения системного промпта** (К1) — нельзя инъецировать роль на уровне системы.
3. **Нет ролей** (К2) — нет изоляции ролей, переключения, per-role конфигурации.
4. **Нет CLI-управления скиллами** (К3) — нет `--skill`, нет per-role наборов; только GUI.
5. **Нет автосканирования `.agents/skills/`** (К5) — только `~/.zcode/skills/` и импорт.
6. **Нет программного доступа к телеметрии** (К7) — токены/квота только в GUI.
7. **Проприетарная лицензия** (К10) — reverse engineering и разработка конкурирующих продуктов запрещены; юрисдикция КНР.
8. **Кастомные сабагенты не поддерживаются** — только встроенный read-only Explore; нет роли-сабагентов через Markdown/frontmatter.

### Сравнительная таблица ZCode vs Pi vs Codex

| Критерий | Pi (10/10) | Codex CLI (6/10) | ZCode (❌ Не подходит) |
|----------|-----------|-----------------|----------------------|
| Системный промпт | ✅ replace + append (CLI) | ⚠️ replace only (file) | ❌ Нет механизма |
| Роль | ✅ `--append-system-prompt` | ⚠️ user prompt / profile | ❌ Нет ролей |
| Скиллы | ✅ `--skill`, auto-scan, per-role | ⚠️ global only | ⚠️ SKILL.md + импорт, но GUI only, global |
| AGENTS.md | ✅ auto + `--no-context-files` | ✅ auto | ✅ auto + CLAUDE.md (приоритет) |
| `.agents/skills/` | ✅ auto-scan | ❌ нет | ❌ нет (только `.agents/` для MCP) |
| JSON-режим | ✅ `--mode json`, JSONL | ⚠️ `--json`, basic | ❌ Нет headless вообще |
| Токены | ✅ per-turn в JSONL | ⚠️ TUI only | ⚠️ Богато, но только GUI |
| Free tier | ✅ MIT + free providers | ⚠️ Apache + paid OpenAI | ⚠️ Free trial quota + платные Coding Plans |
| Провайдеры | ✅ 20+ | ⚠️ OpenAI + OSS | ✅ Z.ai + BYOK + any compatible |
| Лицензия | ✅ MIT | ✅ Apache-2.0 | ❌ Проприетарная, юрисдикция КНР |

### Рекомендации

1. **Не кандидат в сабагенты** — отсутствие headless/JSON-режима (К6) и управления системным промптом (К1) делает интеграцию через `watch-subagent.sh` невозможной. В multi-tier классификации (`docs/research/coding-agents-summary.md`, часть 4) ZCode не получает места ни в одном Tier.
2. **Интересен как объект изучения паттернов** для нашего Orchestrator:
   - **Goal Mode** — self-verifying iterative loop — переиспользуемая идея для нашего `dynamic-loop` + `quality-gate` (агент сам решает «достиг ли цели» и циклится).
   - **5 Execution Modes** — градация автономии/подтверждений — референс для нашего confirm/auto-approve UX.
   - **Импорт skills/MCP из внешних агентов** — паттерн миграции/совместимости; идея для нашего будущего discovery скиллов.
   - **AGENTS.md(High)+CLAUDE.md(Low)** — явный приоритет контекстных файлов (можно учесть, если добавим multi-format context).
3. **Комплаенс-риск** — проприетарная лицензия с юрисдикцией КНР и запретом reverse engineering: даже изучение на уровне «pattern inspiration» нужно вести по публичной документации, не декомпилируя продукт.
4. **Не включать в `EPIC-research-agent-frameworks-comparison`** — субагенты ZCode (read-only Explore + roadmap кастомных) не образуют системы оркестрации; модели оркестрации/state/workflow отсутствуют.

---

## Приложение А. Параметры оценок по сводной таблице

| Критерий | Оценка | Балл | Обоснование |
|----------|--------|------|-------------|
| К1 Системный промпт | ❌ | 1 | Нет механизмов замены/дополнения |
| К2 Роль | ❌ | 1 | Нет ролей, нет изоляции |
| К3 Скиллы | ⚠️ | 2 | SKILL.md + импорт, но GUI only, global |
| К4 AGENTS.md | ✅ | 3 | AGENTS(High)+CLAUDE(Low), поиск вверх по дереву |
| К5 `.agents/skills/` | ❌ | 1 | Не автосканируется (только `~/.zcode/skills/`) |
| К6 JSON-режим | ❌ | 1 | Нет headless/JSON/API (блокер) |
| К7 Токены/стоимость | ⚠️ | 2 | Богатая телеметрия, но только GUI |
| К8 Free tier | ⚠️ | 2 | Free trial quota + платные Coding Plans, юрисдикция КНР |
| К9 Провайдеры | ✅ | 3 | Z.ai + named BYOK + any compatible + OpenRouter |
| К10 Лицензия | ❌ | 1 | Проприетарная, reverse eng. запрещён, КНР |
| **Итого** | — | **17/30** | Вердикт **❌ Не подходит** (К6 — блокер) |

> По логике сводной таблицы вердикт — качественная оценка: даже при умеренном score (17) критический `К6=❌` (нет headless-режима) дисквалифицирует агента для сабагентной интеграции. Аналогично OpenClaw (К6=❌ → ❌ Не подходит, 4/10).

---

## Источники

1. [ZCode Agent — docs](https://zcode.z.ai/en/docs/agents) — workspace, Execution Modes, AGENTS.md/CLAUDE.md, context
2. [Subagents — docs](https://zcode.z.ai/en/docs/subagents) — Explore (read-only), отсутствие кастомных сабагентов
3. [Skill — docs](https://zcode.z.ai/en/docs/skill) — Agent Skills standard, `~/.zcode/skills/`, импорт из внешних агентов
4. [MCP Servers — docs](https://zcode.z.ai/en/docs/mcp-services) — stdio/SSE/HTTP, импорт, стандарт `.agents/` для MCP
5. [Usage Stats — docs](https://zcode.z.ai/en/docs/usage-stats) — App Usage, Coding Plan quota, per-model tokens
6. [Remote Control — docs](https://zcode.z.ai/en/docs/remote-control) — телефон по QR (не API)
7. [API Key Setup — docs](https://zcode.z.ai/en/docs/configuration) — Z.ai/BigModel/Anthropic/OpenAI/OpenRouter/Moonshot/MiniMax/Xiaomi/custom
8. [Goal Mode — docs](https://zcode.z.ai/en/docs/goal) — self-verifying iterative loop
9. [Install — docs](https://zcode.z.ai/en/docs/install) — desktop-приложение (macOS/Win/Linux beta)
10. [ZCode Terms of Use](https://zcode.z.ai/en/terms) — проприетарная лицензия, ограничения, юрисдикция КНР
