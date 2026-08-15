# Идентичность агента

**Идентичность агента** — отдельная учётная сущность GitHub, от имени которой AI-агент создаёт запросы на слияние и выполняет операции с репозиторием, отделённая от учётной записи владельца репозитория.

В этом проекте роль такой сущности выполняет `GitHub App` (далее — приложение), имя которого **выбирает сам пользователь**, а получение токенов автоматизировано командой `bin/console agent:token` (DDD-модуль `GitIdentity`). task-orchestrator — публичный продукт: имя приложения выбирает пользователь и в гайде представлено заполнителем `<your-app>`. Везде, где в примерах встречается `prikotov-agent` или владелец `prikotov`, — это иллюстрация автора гайда (см. [Выбор имени приложения](#a-создание-github-app)).

## Границы ответственности

- Документ объясняет концепцию разделения идентичностей и описывает настройку и использование `GitHub App` (пользователь выбирает имя).
- Команда `bin/console agent:token` — точный интерфейс см. в разделе [Использование команды](#4-использование-команды-binconsole-agenttoken) и в справке `bin/console agent:token --help`.
- Правила создания запросов на слияние см. в [Запросы на слияние](../git-workflow/pull-request.md), правила веток — в [Ветки (Branches)](../git-workflow/branches.md).

## 1. Контекст и проблема

AI-агенты (`pi`, Codex, OpenCode) создают запросы на слияние через CLI `gh`. Если `gh` авторизован как владелец репозитория (далее — **человек**), возникает физическое ограничение GitHub:

- **Автор запроса на слияние не может одобрить собственный запрос.** Это запрещено на уровне платформы.
- На ветке `main` включена **защита ветки** (защита ветки): требуется `required_approving_review_count: 1`.
- Так как автор и единственный возможный проверяющий — один и тот же человек, одобрение получить неоткуда.
- Единственный обход, который работает в такой ситуации, — `gh pr merge --admin`. Этот флаг **принудительно игнорирует правила защиты** для администратора. По факту он отключает защиту: слияние становится возможным вообще без проверки.

Итог: защита ветки существует только на бумаге, а каждое слияние изменений агента — это административный обход собственного правила.

> Принципиальная проблема в том, что **одна и та же идентичность** и создаёт запрос на слияние и должна его одобрить. Решение — разделить идентичности: запрос создаёт отдельная сущность (приложение), а одобряет человек.

## 2. Решение: `GitHub App`

### Принцип: Идентичность ≠ Токен ≠ Доступ

Три независимых слоя, которые нельзя путать:

| Слой | Что это | Сколько | Срок жизни |
|---|---|---|---|
| **Идентичность** | Само приложение как участник GitHub (`<your-app>[bot]`) | **Один** на все проекты и всех агентов | Постоянно |
| **Токен** | Токен доступа установки (`installation access token`), который агент подставляет в `GITHUB_TOKEN` | По одному на установку в моменте | Короткоживущий, время жизни ~1 ч (TTL) |
| **Доступ** | Список репозиториев, к которым у установки приложения есть права | Управляется галочками в настройках установки | До явного отзыва |

Ключевой вывод: **одна идентичность — много короткоживущих токенов — гранулярный доступ по репо**. Утечка токена компрометирует максимум один репо на один час, а не все проекты навсегда.

### Почему именно `GitHub App`

`GitHub App` — официальный механизм GitHub для автоматизации: детализированные разрешения, установка на конкретные репозитории, встроенные короткоживущие токены.

Альтернатива — «бот-аккаунт» (второй обычный пользовательский аккаунт под выдуманной личностью с личным токеном доступа (PAT, Personal Access Token)). У неё три минуса:

- **Нарушает условия использования GitHub (ToS, Terms of Service).** Один человек — один личный аккаунт; служебный аккаунт с личным токеном (PAT) — серая зона, GitHub официально для автоматизации предлагает именно `GitHub Apps`.
- **Большой радиус поражения (радиус поражения).** Токен (PAT) долгоживущий и привязан к аккаунту. Утечка = доступ ко всем репо, где аккаунт — соавтор.
- **Не масштабируется.** На десятках репозиториев добавлять второй аккаунт соавтором в каждый — ручная и хрупкая операция.

### Почему одно приложение на всех агентов

`pi`, Codex CLI и OpenCode — разные инструменты, но **все они работают через CLI `gh`**, который читает токен из переменных окружения `GH_TOKEN`/`GITHUB_TOKEN`. Поэтому:

- Одно приложение → одна команда `bin/console agent:token` → одна переменная `GITHUB_TOKEN`.
- Любой агент, запущенный после `eval "$(bin/console agent:token <owner>/<repo> --format=env)"`, автоматически работает от имени приложения.
- Отдельные приложения, токены или аккаунты на каждый агент избыточны.

### Имя приложения выбирает пользователь

Имя приложения выбирает пользователь. Это **свойство GitHub**: короткое имя (идентификатор) приложения глобально уникально на всей платформе, поэтому каждый берёт свободное имя самостоятельно.

- Рекомендуемый паттерн имени: `<your-username>-agent` (например, для автора гайда это `prikotov-agent`).
- После создания приложение отображается в репозитории как `<your-app>[bot]` (например, `prikotov-agent[bot]` — **только пример**; у вас будет ваше имя).

> Все примеры команд ниже используют заполнители `<your-app>` (имя приложения), `<your-username>` (ваш логин GitHub) и `<owner>/<repo>` (репозиторий). Везде, где показано `prikotov-agent` / `prikotov` — это иллюстрация автора гайда; подставьте свои значения.

## 3. Пошаговая настройка (выполняет пользователь)

> Создание приложения, выпуск PEM (приватного ключа), установка и финальная проверка — ручные действия в GitHub UI, доступные **только владельцу аккаунта**. Агент эти шаги выполнить не может.

### a. Создание `GitHub App`

1. GitHub → `Settings` (учётной записи/организации) → `Developer settings` → `GitHub Apps` → `New GitHub App`.
2. `GitHub App name:` — выберите собственное имя, например `<your-username>-agent`. Короткое имя (slug) приложения глобально уникально на GitHub — если имя занято, выберите другое.
   > Иллюстративный пример: автор гайда использует `prikotov-agent`. Выберите **своё** имя.
3. `Homepage URL:` любой (например, URL профиля владельца).
4. `Webhook` → `Active`: можно снять галочку (нам веб-перехватчик не нужен).

### b. Разрешения

В разделе `Repository permissions` выставить:

| Разрешение | Уровень | Зачем |
|---|---|---|
| `Contents` | `Read and write` | Пуш веток, работа с файлами |
| `Pull requests` | `Read and write` | Создание и обновление запросов на слияние |
| `Workflows` | `Read and write` | Если агент пушит изменения в `.github/workflows` |
| `Metadata` | `Read-only` | **Обязательно** — GitHub требует её для любого доступа к репо |

Остальные разрешения оставляем `No access` — принцип минимальных привилегий.

### c. Выпуск приватного ключа (PEM)

1. После создания приложения откройте его страницу (`Settings` → `Developer settings` → `GitHub Apps` → ваше приложение).
2. Запомните `App ID` (число вверху страницы) — оно понадобится на шаге `e`.
3. Внизу в разделе `Private keys` → `Generate a private key`.
4. GitHub скачает файл вида `<your-app>.private-key.<date>.pem`.

### d. Сохранить PEM в каталог секретов проекта

PEM — это **постоянный секрет** (средство подписи JWT), поэтому он хранится локально в каталоге `secrets/` проекта, а не во временных данных.

```bash
mkdir -p secrets/agent-identity
mv ~/Downloads/<your-app>.private-key.*.pem secrets/agent-identity/private-key.pem
chmod 0600 secrets/agent-identity/private-key.pem
```

> `chmod 0600` обязателен: модуль `GitIdentity` немедленно завершит работу с ошибкой, если ключ доступен группе или остальным.

### e. Сохранить `App ID`

`App ID` — это обязательная env-переменная `AGENT_APP_ID`, которую модуль GitIdentity читает напрямую при старте команды (см. [раздел 5](#5-конфигурация-и-хранение-секретов)). Проще всего — через `.env.local`, который `bin/console` загружает через Symfony Dotenv при старте:

```bash
# .env.local (в корне проекта)
AGENT_APP_ID=1234567
```

Путь к PEM-ключу задаётся рядом (тот же `.env.local`):

```bash
# .env.local (продолжение)
AGENT_PRIVATE_KEY_PATH=/absolute/path/to/secrets/agent-identity/private-key.pem
# либо содержимое PEM в переменной окружения (альтернатива файлу):
# AGENT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
```

> Никакой секции `git_identity` в `config/services.yaml` настраивать не нужно: модуль читает конфиг целиком из env с дефолтами-константами внутри себя.

Подставьте вместо `1234567` реальный **App ID** со страницы приложения.

### f. `Install App`: установка на репозиторий

1. На странице `GitHub App` → `Install App` (слева в меню).
2. Выбрать аккаунт/организацию для установки.
3. `Repository access` → `Only select repositories` → отметить галочками нужные репо.
4. Для пилота — отметить целевой репо (например, `task-orchestrator` — подставьте свой).

После установки приложение получает `installation_id` (внутренний идентификатор установки) — он нужен команде для запроса токена. Команда находит его автоматически по `<owner>/<repo>`.

> Идентичность `<your-app>[bot]` после установки автоматически становится участником выбранных репо. Добавлять отдельного соавтора вручную избыточно.

### g. Убедиться, что `secrets/` в `.gitignore`

Каталог `secrets/` содержит постоянные секреты (PEM, ключи) и **никогда не должен попадать в коммит**. Проверьте, что в `.gitignore` проекта есть строка для него. Если нет — добавьте:

```gitignore
# Постоянные секреты (PEM-ключ приложения, ключи) — никогда в коммит
/secrets/
```

> `.env.local` уже игнорируется в этом проекте (как, например, `CODEX_HTTP_PROXY`). Каталог `secrets/` должен быть закрыт так же надёжно. См. подробнее [семантику каталогов в разделе 5](#5-конфигурация-и-хранение-секретов).

## 4. Использование команды `bin/console agent:token`

Команда `bin/console agent:token` инкапсулирует весь цикл получения токена: чтение PEM → сборка JWT (RS256, через `ext-openssl`, без сторонних зависимостей) → поиск `installation_id` по `<owner>/<repo>` → запрос токена доступа установки (`installation access token`) → кеширование.

> **Архитектура.** Функционал идентичности агента — полноценная часть продукта, а не утилита разработки. Реализован как DDD-модуль `src/Module/GitIdentity` со слоями Domain/Application/Infrastructure; команда `agent:token` — точка входа в Presentation-слое CLI (`apps/console/src/Module/GitIdentity/Command/AgentTokenCommand.php`), делегирующая в сценарий использования `ObtainTokenCommandHandler`.

### Справка

```bash
bin/console agent:token --help
```

### Сигнатура

```
bin/console agent:token <owner>/<repo> [--format=plain|json|env]
```

- Аргумент `repo` (обязательный): идентификатор репозитория в формате `<owner>/<repo>`.
- Опция `--format` (по умолчанию `plain`): `plain`, `json` или `env`.

### Основной сценарий — выставить токен через `eval`

```bash
eval "$(bin/console agent:token <owner>/<repo> --format=env)"
# Пример (подставьте свой `owner/repo`):
# eval "$(bin/console agent:token prikotov/task-orchestrator --format=env)"
```

Формат `env` печатает строку `export GITHUB_TOKEN='<token>'`, которую `eval` выполняет в текущей оболочке. После этого `gh` (и любой агент, работающий через `gh`) использует этот токен.

> `gh` CLI читает обе переменные окружения — `GH_TOKEN` и `GITHUB_TOKEN`. Команда экспортирует `GITHUB_TOKEN`; этого достаточно для `gh` и работающих через него агентов (`pi`, Codex CLI, OpenCode).

### Локальный режим проекта: `push` PR-веток токеном бота

> **⚠️ Важно.** Следующие правила — локальная политика сопровождения репозитория `prikotov/task-orchestrator`, а не обязательный контракт библиотеки для внешних потребителей. В этом репозитории каждая отправка ветки запроса на слияние, начиная с первой, должна выполняться через HTTPS с токеном установки настроенного `GitHub App`. Имя приложения выбирает владелец установки; `prikotov-agent` — лишь локальный пример. SSH владельца, обычный `git push` и `gh auth git-credential` запрещены: отправка человеком может сделать его одобрение неучитываемым при `require_last_push_approval`. Не рассчитывайте, что последующая отправка ботом надёжно исправит уже нарушенный процесс.

Токен нельзя выводить в терминал или сессию агента. Единственный ручной рецепт проекта:

```bash
set +x
(
    unset GIT_CURL_VERBOSE
    for VARIABLE in "${!GIT_TRACE@}"; do unset "$VARIABLE"; done
    BOT_TOKEN="$(bin/console agent:token <owner>/<repo> --format=plain)"
    AUTH_HEADER="$(printf 'x-access-token:%s' "$BOT_TOKEN" | base64 | tr -d '\r\n')"
    unset BOT_TOKEN
    unset GIT_CONFIG GIT_CONFIG_PARAMETERS
    export GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_COUNT=7
    export GIT_CONFIG_KEY_0=credential.helper GIT_CONFIG_VALUE_0=
    export GIT_CONFIG_KEY_1=credential.interactive GIT_CONFIG_VALUE_1=never
    export GIT_CONFIG_KEY_2=core.hooksPath GIT_CONFIG_VALUE_2=/dev/null
    export GIT_CONFIG_KEY_3=http.https://github.com/.extraHeader GIT_CONFIG_VALUE_3=
    export GIT_CONFIG_KEY_4=http.https://github.com/.extraHeader
    export GIT_CONFIG_VALUE_4="Authorization: Basic $AUTH_HEADER"
    export GIT_CONFIG_KEY_5=http.version GIT_CONFIG_VALUE_5=HTTP/1.1
    export GIT_CONFIG_KEY_6=http.proxyAuthMethod GIT_CONFIG_VALUE_6=basic
    unset AUTH_HEADER
    GIT_ASKPASS=/bin/false GIT_TERMINAL_PROMPT=0 \
        git push "https://github.com/<owner>/<repo>.git" \
        "HEAD:refs/heads/$(git branch --show-current)"
)
```

Для прокси важны только две строки: `Key_6` (`http.proxyAuthMethod=basic`) посылает учётные данные в первом `CONNECT` и исключает цикл `407`/reconnect с `SSL_write()`; `Key_5` задаёт `HTTP/1.1`. Остальные переменные не относятся к прокси: они изолируют токен бота от трассировки, обработчиков Git и учётных данных владельца. Прокси не отключается, резервных вариантов нет, удалённый репозиторий и вышестоящая ветка не меняются.

Перед запросом одобрения сверьтесь с журналом собственных действий: **все** отправки ветки должны быть выполнены по этому рецепту. GitHub API не предоставляет надёжной проверки полной истории отправителей ветки, поэтому не заявляйте о такой API-проверке. Если способ любой отправки неизвестен или был иным, остановитесь и согласуйте восстановление с пользователем.

### Проверка смены авторизации на приложение

```bash
gh auth status              # показывает вход от <your-app>[bot]
gh api user --jq .login     # → <your-app>[bot]
```

### Форматы вывода

| Вызов | Вывод | Назначение |
|---|---|---|
| `bin/console agent:token <owner>/<repo>` | голый токен (по умолчанию `plain`) | для `gh auth login --with-token` / `pipe` |
| `bin/console agent:token <owner>/<repo> --format=plain` | голый токен | то же (явно) |
| `bin/console agent:token <owner>/<repo> --format=json` | JSON `{token, expires_at, installation_id}` | для инспекции/скриптов |
| `bin/console agent:token <owner>/<repo> --format=env` | `export GITHUB_TOKEN='...'` | для `eval` |
| `bin/console agent:token --help` / `-h` | справка | — |

Постоянная авторизация `gh` под приложением (токен пишется в конфигурацию `gh`):

```bash
bin/console agent:token <owner>/<repo> --format=plain | gh auth login --with-token
```

### Как агенты подхватывают токен

`pi`, Codex CLI и OpenCode работают через CLI `gh`, который читает `GITHUB_TOKEN`/`GH_TOKEN` из окружения. Любой процесс, запущенный в той же оболочке после `eval "$(bin/console agent:token ... --format=env)"`, автоматически работает от имени `<your-app>[bot]` — отдельной конфигурации на агент не требуется.

### Механика и кеш

- JWT собирается модулем самостоятельно: `header.payload.signature`, подпись RS256 через `ext-openssl`; данные `{iat = now − clock_skew, exp = iat + jwt_ttl, iss = App ID}` (env `AGENT_JWT_TTL_SECONDS`/`AGENT_JWT_CLOCK_SKEW_SECONDS`, см. [раздел 5](#5-конфигурация-и-хранение-секретов)).
- `installation_id` запрашивается через `GET /repos/{owner}/{repo}/installation` (коротко кешируется по `owner/repo`).
- Токен доступа запрашивается через `POST /app/installations/{id}/access_tokens`; при `AGENT_SCOPE_TO_REPOSITORY=true` ограничивается репозиторием (`repository_names`).
- Токен **кешируется** в каталоге `var/cache/git-identity/`: файл `<installation_id>.token.json` с правами `0600` (каталог `0700`, атомарная запись под `flock`). TTL = `expires_at` минус `AGENT_TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS`. Пока кеш валиден, запросов к API нет. Каталог кеша = `<base_path>/var/cache/git-identity` (пробрасывается в `FilesystemTokenCacheService` из параметра модуля `module.git_identity.cache_dir`, по умолчанию `<base_path>/var/cache/git-identity`).
- При ошибке (сеть/4xx/5xx/конфигурация) команда завершается с ненулевым кодом и сообщением без чувствительных данных — **JWT, PEM и сам токен никогда не попадают в вывод/stderr**, кроме целенаправленной печати токена в выбранном формате.

## 5. Конфигурация и хранение секретов

### Где что хранится: `secrets/` vs `var/`

В проекте два разных по смыслу каталога локальных данных. Их нельзя путать:

| Каталог | Семантика | Что внутри | Восстановление |
|---|---|---|---|
| **`secrets/`** | **Постоянные секреты** — долговременные ключи доступа | PEM-ключ приложения | **Только вручную** (перевыпуск через GitHub UI) |
| **`var/`** | **Временные данные** — кеш, протухающие артефакты | Кеш токена в `var/cache/git-identity/` | Чистится без последствий; протухает/пересоздаётся автоматически |

**Почему PEM в `secrets/`, а кеш в `var/`:** PEM — долговременное средство подписи JWT, перевыпускается только вручную через GitHub UI (см. [Ротация PEM](#8-ротация-pem-private-key)). Кеш токена — короткоживущий артефакт, пересоздаётся автоматически и чистится без последствий. Поэтому постоянные секреты живут в `secrets/`, а кеш — в `var/`.

### Конфигурация: env-переменные модуля GitIdentity

Настройка модуля принадлежит **самому модулю** и читается из env-переменных напрямую — реализация в `src/Module/GitIdentity/Infrastructure/Service/LoadGitIdentityConfigService.php`. Дефолты — это знание модуля о протоколе аутентификации `GitHub App`, поэтому они хранятся как закрытые константы внутри сервиса, а не в конфигурации бандла. Никакой секции `git_identity` в `config/services.yaml` настраивать не нужно.

`.env.local` загружает сам `bin/console` через Symfony Dotenv при старте (как уже загружается, например, `CODEX_HTTP_PROXY`), а модуль читает уже выставленные env-переменные через `getenv()`.

Env-переменные модуля (`AGENT_*`):

| Env | Тип | По умолчанию | Назначение |
|---|---|---|---|
| `AGENT_APP_ID` | `int` (> 0) | — (обязателен) | Идентификатор `GitHub App` (`App ID`). **Обязателен** при использовании команды |
| `AGENT_PRIVATE_KEY_PATH` | путь \| `null` | `null` | Путь к файлу ключа (PEM; обязательно `chmod 0600`). Предпочтительный источник ключа |
| `AGENT_PRIVATE_KEY` | строка \| `null` | `null` | Содержимое ключа из переменной (PEM). Альтернатива файлу |
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

> **Источник ключа:** `AGENT_PRIVATE_KEY` (содержимое из переменной) имеет приоритет над `AGENT_PRIVATE_KEY_PATH` (файл). Если задан ключ из переменной, файл не требуется и проверка `chmod` не выполняется. `App ID` принимается как строка/число и приводится к положительному целому. Булевы параметры принимают `true|false|1|0|yes|no|on|off` (без учёта регистра). Числа с нецифровым значением немедленно завершают работу с ошибкой; диапазоны (`jwt_ttl_seconds`, `request_timeout_seconds` и т.д.) дополнительно валидируются в `GitIdentityConfigVo`.

### Пример конфигурации

**Вариант A — PEM-файл в `secrets/`, `App ID` через `.env.local` (локальная разработка):**

```bash
# .env.local (в корне проекта, игнорируется Git) — загружается bin/console через Symfony Dotenv
AGENT_APP_ID=1234567
AGENT_PRIVATE_KEY_PATH=/absolute/path/to/secrets/agent-identity/private-key.pem
# остальные AGENT_* — по умолчанию из LoadGitIdentityConfigService
```

**Вариант B — PEM из переменной окружения (`CI` / сервер, без файла на диске):**

```bash
# .env.local или переменные окружения CI:
AGENT_APP_ID=1234567
AGENT_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----\n"
```

> `.env.local` уже игнорируется git'ом в этом проекте. Каталог `secrets/` должен быть закрыт так же надёжно (см. шаг [3g](#g-убедиться-что-secrets-в-gitignore)).

### Сводная таблица источников секрета

| Секрет | Источник | Примечание |
|---|---|---|
| `App ID` | env `AGENT_APP_ID` (или переменная окружения процесса) | Обязателен; положительное целое |
| Приватный ключ (PEM-ключ) | `AGENT_PRIVATE_KEY_PATH` (файл, `0600`) **либо** `AGENT_PRIVATE_KEY` (значение переменной окружения) | ключ из переменной приоритетнее; файл требует `chmod 0600` |

## 6. Переключение авторизации: человек ↔ приложение

Возможны два режима работы.

### Режим 1: агент всегда под приложением, человек — в браузере

- В CLI `gh` всегда выставлен токен приложения (через `eval ... --format=env` перед запуском агента или `gh auth login --with-token` из `--format=plain`).
- Человек одобряет запрос на слияние и сливает изменения вручную через GitHub UI в браузере от своего аккаунта.

### Режим 2: обе учётки в `gh` с переключением

`gh` умеет хранить несколько аккаунтов. Команда `gh auth switch` переключает активный:

```bash
gh auth switch -u <your-username>          # переключиться на личный аккаунт
gh auth switch -u <your-app>[bot]          # переключиться на приложение
gh auth status                             # посмотреть активную учётку
```

> Если активная сессия принадлежит приложению, все команды `gh` идут от его имени. Перед ручным одобрением и слиянием в CLI убедитесь, что активен личный аккаунт человека, а не приложение (оно не может одобрить собственный запрос на слияние).

## 7. Мульти-проект (масштабирование)

Подключение нового репозитория — **одно действие**, без новых токенов и соавторов:

1. GitHub → `Settings` → `Developer settings` → `GitHub Apps` → ваше приложение → `Configure` → `Install App`.
2. В настройках установки добавить нужный репо галочкой в `Repository access`.
3. Получить токен под новый репо:

```bash
eval "$(bin/console agent:token <owner>/<new-repo> --format=env)"
```

Поскольку PEM, `App ID` и команда одни на все проекты, новый репо начинает работать сразу после выставления галочки.

> **Конфигурация для разных проектов.** Либо **локальная**: PEM в `secrets/` + переменные окружения `AGENT_APP_ID`/`AGENT_PRIVATE_KEY_PATH` настраиваются в каждом проекте отдельно. Либо **общая**: одно содержимое PEM в переменной задаётся переменной окружения `AGENT_PRIVATE_KEY` и переиспользуется всеми проектами — дублировать PEM-файл в каждый репозиторий необязательно. См. [раздел 5](#5-конфигурация-и-хранение-секретов).

## 8. Ротация PEM (`private key`)

Рекомендуется ротировать PEM примерно **раз в год** либо **немедленно при подозрении на компрометацию**.

1. На странице `GitHub App` → **`Generate a private key`** (новый).
2. Заменить файл `secrets/agent-identity/private-key.pem` новым содержимым, выставить `chmod 0600`.
   - При использовании ключа из переменной (env `AGENT_PRIVATE_KEY`) — обновите его значение.
3. В UI нажать **Delete** рядом со старым ключом (старый PEM при этом перестаёт действовать).
4. Проверить:

```bash
eval "$(bin/console agent:token <owner>/<repo> --format=env)" && gh api user --jq .login
# → <your-app>[bot]
```

> Токены установки при ротации трогать избыточно — они короткоживущие (срок действия ~1 ч) и перевыпускаются сами. Ротируется только PEM (средство подписи JWT).

## 9. Чек-лист готовности (эксплуатационная часть)

Задача считается закрытой, когда проверены **все** пункты. Имя приложения здесь представлено заполнителем — подставьте выбранное вами имя.

- [ ] `GitHub App` (с выбранным вами именем) создан и установлен на целевой репо.
- [ ] PEM-ключ лежит в `secrets/agent-identity/private-key.pem` с правами `0600` (либо задан переменной окружения `AGENT_PRIVATE_KEY`).
- [ ] `App ID` задан переменной окружения `AGENT_APP_ID` (в `.env.local` или как переменная окружения).
- [ ] Каталог `secrets/` присутствует в `.gitignore` проекта.
- [ ] `eval "$(bin/console agent:token <owner>/<repo> --format=env)"` выполняется без ошибок.
- [ ] `gh api user --jq .login` → `<your-app>[bot]`.
- [ ] Для локального режима этого репозитория первая и каждая последующая отправка ветки запроса на слияние выполнена от настроенного `GitHub App` по [безопасному HTTPS-рецепту](#локальный-режим-проекта-push-pr-веток-токеном-бота), никогда не через SSH или обычный `git push`.
- [ ] Перед запросом одобрения по журналу собственных действий подтверждено, что все отправки выполнены безопасным рецептом; недоступная через GitHub API проверка полной истории отправителей не заявлялась.
- [ ] Тестовый запрос на слияние создан от имени `<your-app>[bot]` (`gh pr view --json author --jq .author.login`).
- [ ] Владелец (человек) видит и может нажать **Одобрить** в UI.
- [ ] Слияние проходит **без** `--admin` (защита ветки реально работает).

## 10. Устранение неполадок

| Симптом | Причина | Действие |
|---|---|---|
| `GitHub App ID is not configured (env AGENT_APP_ID).` | Не задан `App ID` | Задать переменную окружения `AGENT_APP_ID` в `.env.local` или в окружении — [раздел 5](#5-конфигурация-и-хранение-секретов) |
| `GitHub App ID must be a positive integer, got "...".` | В `AGENT_APP_ID` не число | Указать числовой `App ID` со страницы приложения |
| `GitHub App private key is not configured: set AGENT_PRIVATE_KEY or AGENT_PRIVATE_KEY_PATH.` | Не задан ключ | Задать переменную окружения `AGENT_PRIVATE_KEY_PATH` или `AGENT_PRIVATE_KEY` |
| `GitHub App private key file not found: <path>` | Путь к PEM-файлу неверный или файла нет | Проверить путь в `AGENT_PRIVATE_KEY_PATH`, положить ключ в `secrets/agent-identity/private-key.pem` |
| `Private key file has insecure permissions (expected 0600): <path>` | Файл ключа читается группой/остальными | `chmod 0600 secrets/agent-identity/private-key.pem` |
| `Failed to read private key file: <path>` | Файл существует, но нечитается/повреждён | Проверить содержимое PEM; при подозрении — перевыпустить (раздел [8](#8-ротация-pem-private-key)) |
| `GitHub API error: HTTP 404 ... /repos/.../installation` | Приложение **не установлено** на репо | Установить приложение (раздел [3, шаг f](#f-install-app-установка-на-репозиторий)), проверить `owner/repo` на опечатки |
| `GitHub API error: HTTP 403/404` при операциях в репо | Недостаточные разрешения приложения | Проверить разрешения репозитория (раздел [3, шаг b](#b-разрешения)) |
| `GitHub API request failed: network error ...` | Нет сети / прокси / DNS | Проверить подключение (как `CODEX_HTTP_PROXY`); при GHES — env `AGENT_API_BASE_URI` |
| `git push` падает с `SSL_write() failed: …` через HTTPS-прокси | По умолчанию `anyauth` сначала получает `407`, прокси закрывает соединение (`Connection: close`), libcurl/OpenSSL падает на повторном подключении | Использовать [безопасный рецепт](#локальный-режим-проекта-push-pr-веток-токеном-бота) с `http.proxyAuthMethod=basic`: он посылает учётные данные прокси в первом `CONNECT`, минуя цикл `407`/reconnect. Прокси не отключать |
| `gh auth status` показывает старую учётку после `eval` | `gh` кеширует авторизацию поверх `GITHUB_TOKEN` | `bin/console agent:token <owner>/<repo> --format=plain \| gh auth login --with-token`, либо `gh auth switch -u <your-app>[bot]` |
| Команды `gh` внезапно падают с `401` спустя ~1 ч | Токен установки (installation token) протух (срок действия ~1 ч) | Перевыпустить: повторный `eval "$(bin/console agent:token ... --format=env)"` (кеш обновится автоматически) |

## Источники

- [Authenticating as a GitHub App](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app) — JWT (RS256), подпись.
- [Authenticating with an installation access token](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app-installation) — installation access tokens.
- [Choosing permissions for a GitHub App](https://docs.github.com/en/apps/creating-github-apps/registering-a-github-app/choosing-permissions-for-a-github-app) — список разрешений репозитория.
- [Approving a pull request with required reviews](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/reviewing-changes-in-pull-requests/approving-a-pull-request-with-required-reviews) — защита ветки и почему автор не может одобрить свой запрос на слияние.
- [git-config — http.proxyAuthMethod](https://git-scm.com/docs/git-config#Documentation/git-config.txt-httpproxyAuthMethod) — метод аутентификации HTTP-прокси (`anyauth` по умолчанию против `basic`).
