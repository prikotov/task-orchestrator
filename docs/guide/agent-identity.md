# Идентичность агента (Agent Identity)

**Идентичность агента (Agent Identity)** — отдельная учётная сущность GitHub, от имени которой AI-агент создаёт PR и выполняет операции с репозиторием, отделённая от учётной записи владельца репозитория.

В этом проекте роль такой сущности выполняет **GitHub App** (далее — App), имя которого **выбирает сам пользователь**, а получение токенов автоматизировано командой `bin/console agent:token` (DDD-модуль `GitIdentity`). task-orchestrator — публичный продукт: имя App выбирает пользователь и в гайде представлено placeholder'ом `<your-app>`. Везде, где в примерах встречается `prikotov-agent` или владелец `prikotov`, — это иллюстрация автора гайда (см. [Выбор имени App](#a-создание-github-app)).

## Границы ответственности

- Документ объясняет концепцию разделения идентичностей и описывает настройку/использование GitHub App (имя выбирает пользователь).
- Команда `bin/console agent:token` — точный интерфейс см. в разделе [Использование команды](#4-использование-команды-binconsole-agenttoken) и в справке `bin/console agent:token --help`.
- Правила создания PR см. в [Pull Request (PR)](../git-workflow/pull-request.md), правила веток — в [Ветки (Branches)](../git-workflow/branches.md).

## 1. Контекст и проблема

AI-агенты (`pi`, Codex, OpenCode) создают PR через CLI `gh`. Если `gh` авторизован как владелец репозитория (далее — **человек**), возникает физическое ограничение GitHub:

- **Автор PR не может approve (одобрить) свой собственный PR.** Это запрещено на уровне платформы.
- На ветке `main` включена **branch protection** (защита ветки): требуется `required_approving_review_count: 1`.
- Так как автор и единственный возможный ревьюер — один и тот же человек, approval получить неоткуда.
- Единственный обход, который работает в такой ситуации, — `gh pr merge --admin`. Этот флаг **принудительно игнорирует protection rules** для администратора. По факту он отключает защиту: merge становится возможным без review вообще.

Итог: защита ветки существует только на бумаге, а каждый merge агента — это административный обход собственного правила.

> Принципиальная проблема в том, что **одна и та же идентичность** и создаёт PR, и должна его одобрить. Решение — разделить идентичности: PR создаёт отдельная сущность (App), а одобряет — человек.

## 2. Решение: GitHub App

### Принцип: Идентичность ≠ Токен ≠ Доступ

Три независимых слоя, которые нельзя путать:

| Слой | Что это | Сколько | Срок жизни |
|---|---|---|---|
| **Идентичность (Identity)** | Сам App как участник GitHub (`<your-app>[bot]`) | **Один** на все проекты и всех агентов | Постоянно |
| **Токен (Token)** | Токен доступа установки (installation access token), который агент подставляет в `GITHUB_TOKEN` | По одному на установку в моменте | Короткоживущий, время жизни ~1 ч (TTL) |
| **Доступ (Access)** | Список репозиториев, к которым у установки App есть права | Управляется галочками в настройках установки | До явного отзыва |

Ключевой вывод: **одна идентичность — много короткоживущих токенов — гранулярный доступ по репо**. Утечка токена компрометирует максимум один репо на один час, а не все проекты навсегда.

### Почему именно GitHub App

GitHub App — официальный механизм GitHub для автоматизации: гранулярные разрешения (permissions), установка на конкретные репозитории, встроенные короткоживущие токены.

Альтернатива — «бот-аккаунт» (второй обычный пользовательский аккаунт под выдуманной личностью с личным токеном доступа (PAT, Personal Access Token)). У неё три минуса:

- **Нарушает условия использования GitHub (ToS, Terms of Service).** Один человек — один личный аккаунт; служебный аккаунт с личным токеном (PAT) — серая зона, GitHub официально для автоматизации предлагает именно GitHub Apps.
- **Большой радиус поражения (blast radius).** Токен (PAT) долгоживущий и привязан к аккаунту. Утечка = доступ ко всем репо, где аккаунт — соавтор (collaborator).
- **Не масштабируется.** На десятках репозиториев добавлять второй аккаунт соавтором (collaborator) в каждый — ручная и хрупкая операция.

### Почему один App на всех агентов

`pi`, Codex CLI и OpenCode — разные инструменты, но **все они работают через CLI `gh`**, который читает токен из переменных окружения `GH_TOKEN`/`GITHUB_TOKEN`. Поэтому:

- Один App → одна команда `bin/console agent:token` → одна переменная `GITHUB_TOKEN`.
- Любой агент, запущенный после `eval "$(bin/console agent:token <owner>/<repo> --format=env)"`, автоматически работает от имени App.
- Отдельные приложения (App'ы), токены или аккаунты на каждый агент избыточны.

### Имя App выбирает пользователь

Имя App выбирает пользователь. Это **свойство GitHub**: короткое имя (slug) App глобально уникально на всей платформе, поэтому каждый берёт свободное имя самостоятельно.

- Рекомендуемый паттерн имени: `<your-username>-agent` (например, для автора гайда это `prikotov-agent`).
- После создания App фигурирует в репозитории как `<your-app>[bot]` (например, `prikotov-agent[bot]` — **только пример**; у вас будет ваше имя).

> Все примеры команд ниже используют placeholder'ы `<your-app>` (имя App), `<your-username>` (ваш логин GitHub) и `<owner>/<repo>` (репозиторий). Везде, где показано `prikotov-agent` / `prikotov` — это иллюстрация автора гайда; подставьте свои значения.

## 3. Пошаговая настройка (выполняет пользователь)

> Создание App, выпуск PEM (private key, приватного ключа), установка и финальная проверка — ручные действия в GitHub UI, доступные **только владельцу аккаунта**. Агент эти шаги выполнить не может.

### a. Создание GitHub App

1. GitHub → `Settings` (учётной записи/организации) → `Developer settings` → `GitHub Apps` → `New GitHub App`.
2. `GitHub App name:` выберите собственное имя, например `<your-username>-agent`. Короткое имя (slug) App глобально уникально на GitHub — если имя занято, выберите другое.
   > Иллюстративный пример: автор гайда использует `prikotov-agent`. Выберите **своё** имя.
3. `Homepage URL:` любой (например, URL профиля владельца).
4. `Webhook` → `Active`: можно снять галочку (нам webhook не нужен).

### b. Разрешения (Permissions)

В разделе `Repository permissions` выставить:

| Permission | Уровень | Зачем |
|---|---|---|
| `Contents` | `Read and write` | Пуш веток, работа с файлами |
| `Pull requests` | `Read and write` | Создание и обновление PR |
| `Workflows` | `Read and write` | Если агент пушит изменения в `.github/workflows` |
| `Metadata` | `Read-only` | **Обязательно** — GitHub требует её для любого доступа к репо |

Остальные разрешения (permissions) оставляем `No access` — принцип минимальных привилегий.

### c. Выпуск приватного ключа (PEM)

1. После создания App открыть его страницу (`Settings` → `Developer settings` → `GitHub Apps` → ваше App).
2. Запомнить `App ID` (число вверху страницы) — оно понадобится на шаге `e`.
3. Внизу в разделе `Private keys` → `Generate a private key`.
4. GitHub скачает файл вида `<your-app>.private-key.<date>.pem`.

### d. Сохранить PEM в каталог секретов проекта

PEM — это **постоянный секрет** (средство подписи JWT), поэтому он хранится локально в каталоге `secrets/` проекта, а не во временных данных.

```bash
mkdir -p secrets/agent-identity
mv ~/Downloads/<your-app>.private-key.*.pem secrets/agent-identity/private-key.pem
chmod 0600 secrets/agent-identity/private-key.pem
```

> `chmod 0600` обязателен: модуль `GitIdentity` откажется читать ключ с открытыми для группы/остальных правами (fail-fast по безопасности).

### e. Сохранить App ID

App ID — это обязательная env-переменная `AGENT_APP_ID`, которую модуль GitIdentity читает напрямую при старте команды (см. [раздел 5](#5-конфигурация-и-хранение-секретов)). Проще всего — через `.env.local`, который `bin/console` загружает через Symfony Dotenv при старте:

```bash
# .env.local (в корне проекта)
AGENT_APP_ID=1234567
```

Путь к PEM-ключу задаётся рядом (тот же `.env.local`):

```bash
# .env.local (продолжение)
AGENT_PRIVATE_KEY_PATH=/absolute/path/to/secrets/agent-identity/private-key.pem
# либо inline-PEM в одной строке (альтернатива файлу):
# AGENT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
```

> Никакой секции `git_identity` в `config/services.yaml` настраивать не нужно: модуль читает конфиг целиком из env с дефолтами-константами внутри себя.

Подставьте вместо `1234567` реальный **App ID** со страницы App.

### f. Install App (установка на репозиторий)

1. На странице App → `Install App` (слева в меню).
2. Выбрать аккаунт/организацию для установки.
3. `Repository access` → `Only select repositories` → отметить галочками нужные репо.
4. Для пилота — отметить целевой репо (например, `task-orchestrator` — подставьте свой).

После установки App получает `installation_id` (внутренний идентификатор установки) — он нужен команде для запроса токена. Команда находит его автоматически по `<owner>/<repo>`.

> Идентичность `<your-app>[bot]` после установки автоматически становится участником выбранных репо. Добавлять отдельного collaborator'а вручную избыточно.

### g. Убедиться, что `secrets/` в `.gitignore`

Каталог `secrets/` содержит постоянные секреты (PEM, ключи) и **никогда не должен попадать в коммит**. Проверьте, что в `.gitignore` проекта есть строка для него. Если нет — добавьте:

```gitignore
# Постоянные секреты (PEM App, ключи) — никогда в коммит
/secrets/
```

> `.env.local` уже игнорируется в этом проекте (как, например, `CODEX_HTTP_PROXY`). Каталог `secrets/` должен быть закрыт так же надёжно. См. подробнее [семантику каталогов в разделе 5](#5-конфигурация-и-хранение-секретов).

## 4. Использование команды `bin/console agent:token`

Команда `bin/console agent:token` инкапсулирует весь цикл получения токена: чтение PEM → сборка JWT (RS256, через `ext-openssl`, без сторонних зависимостей) → поиск `installation_id` по `<owner>/<repo>` → запрос токена доступа установки (installation access token) → кеширование.

> **Архитектура.** Функционал идентичности агента — полноценная часть продукта, а не dev-утилита. Реализован как DDD-модуль `src/Module/GitIdentity` со слоями Domain/Application/Infrastructure; команда `agent:token` — точка входа в Presentation-слое CLI (`apps/console/src/Module/GitIdentity/Command/AgentTokenCommand.php`), делегирующая в use case `ObtainTokenCommandHandler`.

### Справка

```bash
bin/console agent:token --help
```

### Сигнатура

```
bin/console agent:token <owner>/<repo> [--format=plain|json|env]
```

- Аргумент `repo` (обязательный): repository slug в формате `<owner>/<repo>`.
- Опция `--format` (по умолчанию `plain`): `plain`, `json` или `env`.

### Основной сценарий — выставить токен через `eval`

```bash
eval "$(bin/console agent:token <owner>/<repo> --format=env)"
# Пример (подставьте свой owner/repo):
# eval "$(bin/console agent:token prikotov/task-orchestrator --format=env)"
```

Формат `env` печатает строку `export GITHUB_TOKEN='<token>'`, которую `eval` выполняет в текущей оболочке. После этого `gh` (и любой агент, работающий через `gh`) использует этот токен.

> `gh` CLI читает обе переменные окружения — `GH_TOKEN` и `GITHUB_TOKEN`. Команда экспортирует `GITHUB_TOKEN`; этого достаточно для `gh` и работающих через него агентов (`pi`, Codex CLI, OpenCode).

### Локальный режим проекта: push PR-веток токеном бота

> **⚠️ Важно.** Следующие правила — локальная политика сопровождения репозитория `prikotov/task-orchestrator`, а не обязательный контракт библиотеки для внешних потребителей. В этом репозитории каждый push PR-ветки, начиная с первого, выполняйте через HTTPS installation token'ом настроенного GitHub App. Имя App выбирает владелец установки; `prikotov-agent` — лишь локальный пример. SSH владельца, обычный `git push` и `gh auth git-credential` запрещены: push человека может сделать его approval неучитываемым при `require_last_push_approval`. Не рассчитывайте, что последующий bot push надёжно исправит уже нарушенный процесс.

Токен нельзя выводить в терминал или сессию агента, поэтому безопасный push выполняется исполняемым helper'ом `bin/push-pr-branch` — он и есть единственный источник истины (source of truth) для публикации PR-ветки. Ручной shell-рецепт намеренно не дублируется: контракт и изоляция реализованы в скрипте и покрыты тестами.

```bash
bin/push-pr-branch <owner>/<repo>
# Пример (подставьте свой owner/repo):
# bin/push-pr-branch prikotov/task-orchestrator
```

**Контракт helper'а** (fail-fast, без guessed defaults):

- Обязательный аргумент `<owner>/<repo>`; пустой/некорректный slug отклоняется.
- Текущая ветка определяется через Git; detached HEAD запрещён.
- `main`, `master` и `release/*` отклоняются как целевые защищённые ветки — helper публикует только PR-ветки (`task/*`, `hotfix/*` и т. п.).
- installation token получается только через `bin/console agent:token <owner>/<repo> --format=plain` в command substitution и **никогда не печатается**; недопустимые символы в токене вызывают отказ.
- Git изолирован: `GIT_CONFIG_GLOBAL=/dev/null`, `GIT_CONFIG_NOSYSTEM=1`, пустые `credential.helper` и первый `http.extraHeader` отсекают учётные данные и заголовки владельца; `core.hooksPath=/dev/null` и сброс переменных `GIT_TRACE*` не дают им унаследовать `Authorization` header.
- Обязательно `http.proxyAuthMethod=basic` и `HTTP/1.1` (см. ниже).
- Прокси **не отключается**, fallback'ов нет, `remote`/`upstream` не меняется — каждый push выполняется одним и тем же вызовом.

**Почему `http.proxyAuthMethod=basic`.** По умолчанию Git использует `anyauth`: первый `CONNECT` выполняется без учётных данных прокси, прокси отвечает `407 Proxy Authentication Required` и закрывает соединение (`Connection: close`), после чего текущая связка libcurl/OpenSSL может упасть на повторном подключении с ошибкой `SSL_write()`. `basic` заставляет Git послать учётные данные прокси в первом же `CONNECT` внутри TLS к HTTPS-прокси, обходя цикл `407`/reconnect, не отключая прокси. `HTTP/1.1` обеспечивает совместимость с используемым HTTPS-прокси.

Перед запросом approval сверьтесь с журналом собственных действий: **все** push ветки должны быть выполнены через `bin/push-pr-branch`. GitHub API не предоставляет надёжной проверки полной истории отправителей ветки, поэтому не заявляйте о такой API-проверке. Если способ любого push неизвестен или был иным (например, SSH владельца или обычный `git push`), остановитесь и согласуйте восстановление с пользователем.

### Проверка, что авторизация сменилась на App

```bash
gh auth status              # показывает вход от <your-app>[bot]
gh api user --jq .login     # → <your-app>[bot]
```

### Форматы вывода

| Вызов | Вывод | Назначение |
|---|---|---|
| `bin/console agent:token <owner>/<repo>` | голый токен (по умолчанию `plain`) | для `gh auth login --with-token` / pipe |
| `bin/console agent:token <owner>/<repo> --format=plain` | голый токен | то же (явно) |
| `bin/console agent:token <owner>/<repo> --format=json` | JSON `{token, expires_at, installation_id}` | для инспекции/скриптов |
| `bin/console agent:token <owner>/<repo> --format=env` | `export GITHUB_TOKEN='...'` | для `eval` |
| `bin/console agent:token --help` / `-h` | справка | — |

Постоянная авторизация `gh` под App (токен пишется в конфигурацию `gh`):

```bash
bin/console agent:token <owner>/<repo> --format=plain | gh auth login --with-token
```

### Как агенты подхватывают токен

`pi`, Codex CLI и OpenCode работают через CLI `gh`, который читает `GITHUB_TOKEN`/`GH_TOKEN` из окружения. Любой процесс, запущенный в той же оболочке после `eval "$(bin/console agent:token ... --format=env)"`, автоматически работает от имени `<your-app>[bot]` — отдельной конфигурации на агент не требуется.

### Механика и кеш

- JWT собирается модулем самостоятельно: `header.payload.signature`, подпись RS256 через `ext-openssl`; payload `{iat = now − clock_skew, exp = iat + jwt_ttl, iss = App ID}` (env `AGENT_JWT_TTL_SECONDS`/`AGENT_JWT_CLOCK_SKEW_SECONDS`, см. [раздел 5](#5-конфигурация-и-хранение-секретов)).
- `installation_id` запрашивается через `GET /repos/{owner}/{repo}/installation` (коротко кешируется по `owner/repo`).
- Access token запрашивается через `POST /app/installations/{id}/access_tokens`; при `AGENT_SCOPE_TO_REPOSITORY=true` ограничивается репозиторием (`repository_names`).
- Токен **кешируется** в каталоге `var/cache/git-identity/`: файл `<installation_id>.token.json` с правами `0600` (каталог `0700`, атомарная запись под `flock`). TTL = `expires_at` минус `AGENT_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS`. Пока кеш валиден, запросов к API нет. Каталог кеша = `<base_path>/var/cache/git-identity` (пробрасывается в `FilesystemTokenCacheService` из параметра модуля `module.git_identity.cache_dir`, по умолчанию `<base_path>/var/cache/git-identity`).
- При ошибке (сеть/4xx/5xx/конфигурация) команда завершается с ненулевым кодом и сообщением без чувствительных данных — **JWT, PEM и сам токен никогда не попадают в вывод/stderr**, кроме целенаправленной печати токена в выбранном формате.

## 5. Конфигурация и хранение секретов

### Где что хранится: `secrets/` vs `var/`

В проекте два разных по смыслу каталога локальных данных. Их нельзя путать:

| Каталог | Семантика | Что внутри | Восстановление |
|---|---|---|---|
| **`secrets/`** | **Постоянные секреты** — долговременные ключи доступа | PEM App | **Только вручную** (перевыпуск через GitHub UI) |
| **`var/`** | **Временные данные** — кеш, протухающие артефакты | Кеш токена в `var/cache/git-identity/` | Чистится без последствий; протухает/пересоздаётся автоматически |

**Почему PEM в `secrets/`, а кеш в `var/`:** PEM — долговременное средство подписи JWT, перевыпускается только вручную через GitHub UI (см. [Ротация PEM](#8-ротация-pem-private-key)). Кеш токена — короткоживущий артефакт, пересоздаётся автоматически и чистится без последствий. Поэтому постоянные секреты живут в `secrets/`, а кеш — в `var/`.

### Конфигурация: env-переменные модуля GitIdentity

Настройка модуля принадлежит **самому модулю** и читается из env-переменных напрямую — реализация в `src/Module/GitIdentity/Infrastructure/Service/LoadGitIdentityConfigService.php`. Дефолты — это знание модуля о протоколе GitHub App auth, поэтому они хранятся как private-константы внутри сервиса, а не в конфигурации бандла. Никакой секции `git_identity` в `config/services.yaml` настраивать не нужно.

`.env.local` загружает сам `bin/console` через Symfony Dotenv при старте (как уже загружается, например, `CODEX_HTTP_PROXY`), а модуль читает уже выставленные env-переменные через `getenv()`.

Env-переменные модуля (`AGENT_*`):

| Env | Тип | По умолчанию | Назначение |
|---|---|---|---|
| `AGENT_APP_ID` | `int` (> 0) | — (обязателен) | Идентификатор GitHub App (`App ID`). **Обязателен** при использовании команды |
| `AGENT_PRIVATE_KEY_PATH` | путь \| `null` | `null` | Путь к файлу ключа (PEM; обязательно `chmod 0600`). Предпочтительный источник ключа |
| `AGENT_PRIVATE_KEY` | строка \| `null` | `null` | Inline-содержимое ключа (PEM). Альтернатива файлу |
| `AGENT_API_BASE_URI` | строка | `https://api.github.com` | Базовый URI GitHub API (переопределяется для GitHub Enterprise (GHES)) |
| `AGENT_GITHUB_API_VERSION` | строка | `2022-11-28` | Значение заголовка `X-GitHub-Api-Version` |
| `AGENT_USER_AGENT` | строка | `task-orchestrator-git-identity` | HTTP `User-Agent` (требование GitHub) |
| `AGENT_JWT_TTL_SECONDS` | `int` | `540` | Время жизни (TTL) токена (JWT), диапазон `1..600` (лимит GitHub — 600) |
| `AGENT_JWT_CLOCK_SKEW_SECONDS` | `int` | `60` | Сдвиг `iat` назад (толерантность к рассинхрону часов NTP (drift)) |
| `AGENT_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS` | `int` | `60` | Запас, вычитаемый из момента истечения (expiry) для TTL кеша токена |
| `AGENT_INSTALLATION_ID_CACHE_TTL_SECONDS` | `int` \| `null` | `86400` | TTL кеша идентификатора установки (installation id); значение `null` = без истечения (expiry) |
| `AGENT_SCOPE_TO_REPOSITORY` | `bool` | `true` | Ограничивать токен установки (installation token) запрошенным репозиторием |
| `AGENT_REQUEST_TIMEOUT_SECONDS` | `int` | `30` | Таймаут HTTP-запросов к GitHub |

> Каталог кеша токенов не задаётся через env: он всегда `<base_path>/var/cache/git-identity` (пробрасывается в `FilesystemTokenCacheService` из параметра модуля `module.git_identity.cache_dir`, по умолчанию `<base_path>/var/cache/git-identity`).

> **Источник ключа:** `AGENT_PRIVATE_KEY` (inline) имеет приоритет над `AGENT_PRIVATE_KEY_PATH` (файл). Если задан inline-ключ, файл не требуется и проверка `chmod` не выполняется. App ID принимается как строка/число и приводится к положительному целому. Булевы параметры принимают `true|false|1|0|yes|no|on|off` (без учёта регистра). Числа с нецифровым значением вызывают fail-fast ошибку; диапазоны (`jwt_ttl_seconds`, `request_timeout_seconds` и т.д.) дополнительно валидируются в `GitIdentityConfigVo`.

### Пример конфигурации

**Вариант A — PEM-файл в `secrets/`, App ID через `.env.local` (локальная разработка):**

```bash
# .env.local (в корне проекта, gitignored) — загружается bin/console через Symfony Dotenv
AGENT_APP_ID=1234567
AGENT_PRIVATE_KEY_PATH=/absolute/path/to/secrets/agent-identity/private-key.pem
# остальные AGENT_* — по умолчанию из LoadGitIdentityConfigService
```

**Вариант B — inline PEM через env (CI / сервер, без файла на диске):**

```bash
# .env.local или переменные окружения CI:
AGENT_APP_ID=1234567
AGENT_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----\n"
```

> `.env.local` уже игнорируется git'ом в этом проекте. Каталог `secrets/` должен быть закрыт так же надёжно (см. шаг [3g](#g-убедиться-что-secrets-в-gitignore)).

### Сводная таблица источников секрета

| Секрет | Источник | Примечание |
|---|---|---|
| App ID | env `AGENT_APP_ID` (или переменная окружения процесса) | Обязателен; положительное целое |
| Приватный ключ (PEM private key) | `AGENT_PRIVATE_KEY_PATH` (файл, `0600`) **либо** `AGENT_PRIVATE_KEY` (inline env) | inline-ключ (Inline) приоритетнее; файл требует `chmod 0600` |

## 6. Переключение авторизации: человек ↔ App

Возможны два режима работы.

### Режим 1: агент всегда под App, человек — в браузере

- В CLI `gh` всегда выставлен токен App (через `eval ... --format=env` перед запуском агента или `gh auth login --with-token` из `--format=plain`).
- Человек одобряет PR и выполняет merge вручную через GitHub UI в браузере от своего аккаунта.

### Режим 2: обе учётки в `gh` с переключением

`gh` умеет хранить несколько аккаунтов. Команда `gh auth switch` переключает активный:

```bash
gh auth switch -u <your-username>          # переключиться на личный аккаунт
gh auth switch -u <your-app>[bot]          # переключиться на App
gh auth status                             # посмотреть активную учётку
```

> Если активная сессия — App, все команды `gh` идут от его имени. Перед ручным approve/merge в CLI убедитесь, что активен личный аккаунт человека, а не App (App не может approve свой же PR).

## 7. Мульти-проект (масштабирование)

Подключение нового репозитория — **одно действие**, без новых токенов и collaborator'ов:

1. GitHub → `Settings` → `Developer settings` → `GitHub Apps` → ваше App → `Configure` → `Install App`.
2. В настройках установки добавить нужный репо галочкой в `Repository access`.
3. Получить токен под новый репо:

```bash
eval "$(bin/console agent:token <owner>/<new-repo> --format=env)"
```

Поскольку PEM, App ID и команда одни на все проекты, новый репо начинает работать сразу после выставления галочки.

> **Конфигурация для разных проектов.** Либо **локальная**: PEM в `secrets/` + env `AGENT_APP_ID`/`AGENT_PRIVATE_KEY_PATH` настраиваются в каждом проекте отдельно. Либо **общая**: один inline-PEM задаётся через env `AGENT_PRIVATE_KEY` и переиспользуется всеми проектами — дублировать PEM-файл в каждый репозиторий необязательно. См. [раздел 5](#5-конфигурация-и-хранение-секретов).

## 8. Ротация PEM (private key)

Рекомендуется ротировать PEM примерно **раз в год** либо **немедленно при подозрении на компрометацию**.

1. На странице App → **Generate a private key** (новый).
2. Заменить файл `secrets/agent-identity/private-key.pem` новым содержимым, выставить `chmod 0600`.
   - При использовании inline-ключа (env `AGENT_PRIVATE_KEY`) — обновите его значение.
3. В UI нажать **Delete** рядом со старым ключом (старый PEM при этом перестаёт действовать).
4. Проверить:

```bash
eval "$(bin/console agent:token <owner>/<repo> --format=env)" && gh api user --jq .login
# → <your-app>[bot]
```

> Токены установки (installation tokens) при ротации трогать избыточно — они короткоживущие (TTL ~1 ч) и перевыпускаются сами. Ротируется только PEM (средство подписи JWT).

## 9. Чек-лист DoD (эксплуатационная часть)

Задача считается закрытой, когда проверены **все** пункты. Имя App здесь представлено placeholder'ом — подставьте выбранное вами имя.

- [ ] GitHub App (с выбранным вами именем) создан и установлен на целевой репо.
- [ ] PEM лежит в `secrets/agent-identity/private-key.pem` с правами `0600` (либо задан через env `AGENT_PRIVATE_KEY`).
- [ ] App ID задан через env `AGENT_APP_ID` (в `.env.local` или как переменная окружения).
- [ ] Каталог `secrets/` присутствует в `.gitignore` проекта.
- [ ] `eval "$(bin/console agent:token <owner>/<repo> --format=env)"` выполняется без ошибок.
- [ ] `gh api user --jq .login` → `<your-app>[bot]`.
- [ ] Для локального режима этого репозитория первый и каждый последующий push PR-ветки выполнен от настроенного GitHub App через [`bin/push-pr-branch`](#локальный-режим-проекта-push-pr-веток-токеном-бота), никогда не через SSH или обычный `git push`.
- [ ] Перед запросом approval по журналу собственных действий подтверждено, что все push выполнены через `bin/push-pr-branch`; недоступная через GitHub API проверка полной истории отправителей не заявлялась.
- [ ] Тестовый PR создан от имени `<your-app>[bot]` (`gh pr view --json author --jq .author.login`).
- [ ] Владелец (человек) видит и может нажать **Approve** в UI.
- [ ] Merge проходит **без** `--admin` (branch protection реально работает).

## 10. Troubleshooting

| Симптом | Причина | Действие |
|---|---|---|
| `GitHub App ID is not configured (env AGENT_APP_ID).` | Не задан App ID | Задать env `AGENT_APP_ID` в `.env.local` или в окружении — [раздел 5](#5-конфигурация-и-хранение-секретов) |
| `GitHub App ID must be a positive integer, got "...".` | В `AGENT_APP_ID` не число | Указать числовой App ID со страницы App |
| `GitHub App private key is not configured: set AGENT_PRIVATE_KEY or AGENT_PRIVATE_KEY_PATH.` | Не задан ключ | Задать env `AGENT_PRIVATE_KEY_PATH` или `AGENT_PRIVATE_KEY` |
| `GitHub App private key file not found: <path>` | Путь к PEM-файлу неверный или файла нет | Проверить путь в `AGENT_PRIVATE_KEY_PATH`, положить ключ в `secrets/agent-identity/private-key.pem` |
| `Private key file has insecure permissions (expected 0600): <path>` | Файл ключа читается группой/остальными | `chmod 0600 secrets/agent-identity/private-key.pem` |
| `Failed to read private key file: <path>` | Файл существует, но нечитается/повреждён | Проверить содержимое PEM; при подозрении — перевыпустить (раздел [8](#8-ротация-pem-private-key)) |
| `GitHub API error: HTTP 404 ... /repos/.../installation` | App **не установлен** на репо | Установить App (раздел [3, шаг f](#f-install-app-установка-на-репозиторий)), проверить `owner/repo` на опечатки |
| `GitHub API error: HTTP 403/404` при операциях в репо | Недостаточные permissions App | Проверить repository permissions (раздел [3, шаг b](#b-разрешения-permissions)) |
| `GitHub API request failed: network error ...` | Нет сети / прокси / DNS | Проверить подключение (как `CODEX_HTTP_PROXY`); при GHES — env `AGENT_API_BASE_URI` |
| `git push` падает с `SSL_write() failed: …` через HTTPS-прокси | По умолчанию `anyauth` сначала получает `407`, прокси закрывает соединение (`Connection: close`), libcurl/OpenSSL падает на повторном подключении | Публиковать PR-ветку через [`bin/push-pr-branch`](#локальный-режим-проекта-push-pr-веток-токеном-бота): он задаёт `http.proxyAuthMethod=basic` и посылает учётные данные прокси в первом `CONNECT`, минуя цикл `407`/reconnect. Прокси не отключать |
| `bin/push-pr-branch` отказывает с «refusing to push protected branch» | helper запустили на `main`/`master`/`release/*` — целевых защищённых ветках | Перейти на PR-ветку (`task/*`, `hotfix/*`) и повторить вызов; прямая публикация целевых веток намеренно запрещена |
| `bin/push-pr-branch` отказывает с «detached HEAD» | текущее состояние репозитория — detached HEAD (нет активной ветки) | `git checkout <branch>` на PR-ветку и повторить вызов |
| `bin/push-pr-branch` отказывает с «invalid repository slug» | аргумент не вида `<owner>/<repo>` или содержит недопустимые символы | Передать корректный slug, например `prikotov/task-orchestrator` |
| `gh auth status` показывает старую учётку после `eval` | `gh` кеширует авторизацию поверх `GITHUB_TOKEN` | `bin/console agent:token <owner>/<repo> --format=plain \| gh auth login --with-token`, либо `gh auth switch -u <your-app>[bot]` |
| Команды `gh` внезапно падают с `401` спустя ~1 ч | Токен установки (installation token) протух (TTL ~1 ч) | Перевыпустить: повторный `eval "$(bin/console agent:token ... --format=env)"` (кеш обновится автоматически) |

## Источники

- [Authenticating as a GitHub App](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app) — JWT (RS256), подпись.
- [Authenticating with an installation access token](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app-installation) — installation access tokens.
- [Choosing permissions for a GitHub App](https://docs.github.com/en/apps/creating-github-apps/registering-a-github-app/choosing-permissions-for-a-github-app) — список repository permissions.
- [Approving a pull request with required reviews](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/reviewing-changes-in-pull-requests/approving-a-pull-request-with-required-reviews) — branch protection и почему автор не может approve свой PR.
- [git-config — http.proxyAuthMethod](https://git-scm.com/docs/git-config#Documentation/git-config.txt-httpproxyAuthMethod) — метод аутентификации HTTP-прокси (`anyauth` по умолчанию против `basic`).
