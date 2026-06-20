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
| **Токен (Token)** | Installation access token, который агент подставляет в `GITHUB_TOKEN` | По одному на установку в моменте | Короткоживущий, TTL ~1 ч |
| **Доступ (Access)** | Список репозиториев, к которым у установки App есть права | Управляется галочками в настройках установки | До явного отзыва |

Ключевой вывод: **одна идентичность — много короткоживущих токенов — гранулярный доступ по репо**. Утечка токена компрометирует максимум один репо на один час, а не все проекты навсегда.

### Почему именно GitHub App

GitHub App — официальный механизм GitHub для автоматизации: гранулярные permissions, установка на конкретные репозитории, встроенные короткоживущие токены.

Альтернатива — «бот-аккаунт» (второй обычный пользовательский аккаунт под выдуманной личностью с PAT). У неё три минуса:

- **Нарушает ToS (Terms of Service, условия использования) GitHub.** Один человек — один личный аккаунт; служебный аккаунт с PAT (Personal Access Token, личный токен доступа) — серая зона, GitHub официально для автоматизации предлагает именно GitHub Apps.
- **Большой blast radius (радиус поражения).** PAT долгоживущий и привязан к аккаунту. Утечка = доступ ко всем репо, где аккаунт — collaborator (соавтор).
- **Не масштабируется.** На десятках репозиториев добавлять второй аккаунт collaborator'ом в каждый — ручная и хрупкая операция.

### Почему один App на всех агентов

`pi`, Codex CLI и OpenCode — разные инструменты, но **все они работают через CLI `gh`**, который читает токен из переменных окружения `GH_TOKEN`/`GITHUB_TOKEN`. Поэтому:

- Один App → одна команда `bin/console agent:token` → одна переменная `GITHUB_TOKEN`.
- Любой агент, запущенный после `eval "$(bin/console agent:token <owner>/<repo> --format=env)"`, автоматически работает от имени App.
- Отдельные App'ы, токены или аккаунты на каждый агент избыточны.

### Имя App выбирает пользователь

Имя App выбирает пользователь. Это **свойство GitHub**: slug (короткое имя) App глобально уникален на всей платформе, поэтому каждый берёт свободное имя самостоятельно.

- Рекомендуемый паттерн имени: `<your-username>-agent` (например, для автора гайда это `prikotov-agent`).
- После создания App фигурирует в репозитории как `<your-app>[bot]` (например, `prikotov-agent[bot]` — **только пример**; у вас будет ваше имя).

> Все примеры команд ниже используют placeholder'ы `<your-app>` (имя App), `<your-username>` (ваш логин GitHub) и `<owner>/<repo>` (репозиторий). Везде, где показано `prikotov-agent` / `prikotov` — это иллюстрация автора гайда; подставьте свои значения.

## 3. Пошаговая настройка (выполняет пользователь)

> Создание App, выпуск PEM (private key, приватного ключа), установка и финальная проверка — ручные действия в GitHub UI, доступные **только владельцу аккаунта**. Агент эти шаги выполнить не может.

### a. Создание GitHub App

1. GitHub → **Settings** (учётной записи/организации) → **Developer settings** → **GitHub Apps** → **New GitHub App**.
2. **GitHub App name:** выберите собственное имя, например `<your-username>-agent`. Slug App глобально уникален на GitHub — если имя занято, выберите другое.
   > Иллюстративный пример: автор гайда использует `prikotov-agent`. Выберите **своё** имя.
3. **Homepage URL:** любой (например, URL профиля владельца).
4. **Webhook** → **Active**: можно снять галочку (нам webhook не нужен).

### b. Permissions (разрешения)

В разделе **Repository permissions** выставить:

| Permission | Уровень | Зачем |
|---|---|---|
| **Contents** | Read and write | Пуш веток, работа с файлами |
| **Pull requests** | Read and write | Создание и обновление PR |
| **Workflows** | Read and write | Если агент пушит изменения в `.github/workflows` |
| **Metadata** | Read-only | **Обязательно** — GitHub требует её для любого доступа к репо |

Остальные permissions оставляем **No access** — принцип минимальных привилегий.

### c. Generate private key (PEM)

1. После создания App открыть его страницу (Settings → Developer settings → GitHub Apps → ваше App).
2. Запомнить **App ID** (число вверху страницы) — оно понадобится на шаге `e`.
3. Внизу в разделе **Private keys** → **Generate a private key**.
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

App ID задаётся параметром `app_id` секции `git_identity` (см. [раздел 5](#5-конфигурация-и-хранение-секретов)). Проще всего — через `.env.local`, который `bin/console` загружает через Symfony Dotenv при старте:

```bash
# .env.local (в корне проекта)
GIT_IDENTITY_APP_ID=1234567
```

и сослаться на него в конфигурации модуля:

```yaml
# config/services.yaml (секция task_orchestrator.git_identity)
task_orchestrator:
    git_identity:
        app_id: '%env(GIT_IDENTITY_APP_ID)%'
        private_key_path: '%task_orchestrator.base_path%/secrets/agent-identity/private-key.pem'
```

Подставьте вместо `1234567` реальный **App ID** со страницы App.

### f. Install App (установка на репозиторий)

1. На странице App → **Install App** (слева в меню).
2. Выбрать аккаунт/организацию для установки.
3. **Repository access** → **Only select repositories** → отметить галочками нужные репо.
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

Команда `bin/console agent:token` инкапсулирует весь цикл получения токена: чтение PEM → сборка JWT (RS256, через `ext-openssl`, без сторонних зависимостей) → поиск `installation_id` по `<owner>/<repo>` → запрос installation access token → кеширование.

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

- JWT собирается модулем самостоятельно: `header.payload.signature`, подпись RS256 через `ext-openssl`; payload `{iat = now − clock_skew, exp = iat + jwt_ttl, iss = App ID}` (параметры `jwt_ttl_seconds`/`jwt_clock_skew_seconds`, см. [раздел 5](#5-конфигурация-и-хранение-секретов)).
- `installation_id` запрашивается через `GET /repos/{owner}/{repo}/installation` (коротко кешируется по `owner/repo`).
- Access token запрашивается через `POST /app/installations/{id}/access_tokens`; при `scope_to_repository=true` ограничивается репозиторием (`repository_names`).
- Токен **кешируется** в каталоге `var/cache/task-orchestrator/git-identity/`: файл `<installation_id>.token.json` с правами `0600` (каталог `0700`, атомарная запись под `flock`). TTL = `expires_at` минус `token_expiry_safety_margin_seconds`. Пока кеш валиден, запросов к API нет.
- При ошибке (сеть/4xx/5xx/конфигурация) команда завершается с ненулевым кодом и сообщением без чувствительных данных — **JWT, PEM и сам токен никогда не попадают в вывод/stderr**, кроме целенаправленной печати токена в выбранном формате.

## 5. Конфигурация и хранение секретов

### Где что хранится: `secrets/` vs `var/`

В проекте два разных по смыслу каталога локальных данных. Их нельзя путать:

| Каталог | Семантика | Что внутри | Восстановление |
|---|---|---|---|
| **`secrets/`** | **Постоянные секреты** — долговременные ключи доступа | PEM App | **Только вручную** (перевыпуск через GitHub UI) |
| **`var/`** | **Временные данные** — кеш, протухающие артефакты | Кеш токена в `var/cache/task-orchestrator/git-identity/` | Чистится без последствий; протухает/пересоздаётся автоматически |

**Почему PEM в `secrets/`, а кеш в `var/`:** PEM — долговременное средство подписи JWT, перевыпускается только вручную через GitHub UI (см. [Ротация PEM](#8-ротация-pem-private-key)). Кеш токена — короткоживущий артефакт, пересоздаётся автоматически и чистится без последствий. Поэтому постоянные секреты живут в `secrets/`, а кеш — в `var/`.

### Конфигурация: секция `task_orchestrator.git_identity`

Настройка модуля — Symfony-нативная. Схема параметров определена в `src/DependencyInjection/Configuration.php` (секция `task_orchestrator.git_identity`). Отдельного самописного парсера `.env.local` у модуля нет: `.env.local` загружает сам `bin/console` через Symfony Dotenv при старте (как уже загружается, например, `CODEX_HTTP_PROXY`), а модуль читает уже разрешённые значения из конфигурации контейнера.

Параметры секции (`task_orchestrator.git_identity.*`):

| Параметр | Тип | По умолчанию | Назначение |
|---|---|---|---|
| `app_id` | строка\|null | `null` | GitHub App ID. **Обязателен** при использовании команды |
| `private_key_path` | строка\|null | `null` | Путь к PEM-файлу (обязательно `chmod 0600`). Предпочтительный источник ключа |
| `private_key` | строка\|null | `null` | Inline-содержимое PEM (через env). Альтернатива файлу |
| `api_base_uri` | строка | `https://api.github.com` | Базовый URI GitHub API (переопределяется для GitHub Enterprise) |
| `github_api_version` | строка | `2026-03-10` | Значение заголовка `X-GitHub-Api-Version` |
| `user_agent` | строка | `task-orchestrator-git-identity` | HTTP `User-Agent` (требование GitHub) |
| `cache_dir` | строка | `%base_path%/var/cache/task-orchestrator/git-identity` | Каталог кеша (создаётся с правами `0700`) |
| `jwt_ttl_seconds` | int | `540` | TTL JWT, диапазон `1..600` (лимит GitHub — 600) |
| `jwt_clock_skew_seconds` | int | `60` | Сдвиг `iat` назад (толерантность к drift NTP) |
| `token_expiry_safety_margin_seconds` | int | `60` | Запас, вычитаемый из expiry для TTL кеша токена |
| `installation_id_cache_ttl_seconds` | int | `86400` | TTL кеша installation id |
| `scope_to_repository` | bool | `true` | Ограничивать installation token запрошенным репозиторием |
| `request_timeout_seconds` | int | `30` | Таймаут HTTP-запросов к GitHub |

> **Источник ключа:** `private_key` (inline) имеет приоритет над `private_key_path` (файл). Если задан inline-ключ, файл не требуется и проверка `chmod` не выполняется. App ID принимается как строка или число и приводится к положительному целому.

### Пример конфигурации

**Вариант A — PEM-файл в `secrets/`, App ID через `.env.local` (локальная разработка):**

```yaml
# config/services.yaml (секция task_orchestrator, параметры модуля GitIdentity)
task_orchestrator:
    git_identity:
        app_id: '%env(GIT_IDENTITY_APP_ID)%'
        private_key_path: '%task_orchestrator.base_path%/secrets/agent-identity/private-key.pem'
        # остальные параметры — по умолчанию из Configuration.php
```

```bash
# .env.local (в корне проекта, gitignored) — загружается bin/console через Symfony Dotenv
GIT_IDENTITY_APP_ID=1234567
```

**Вариант B — inline PEM через env (CI / сервер, без файла на диске):**

```yaml
task_orchestrator:
    git_identity:
        app_id: '%env(GIT_IDENTITY_APP_ID)%'
        private_key: '%env(GIT_IDENTITY_PRIVATE_KEY)%'
```

```bash
# .env.local или переменные окружения CI:
GIT_IDENTITY_APP_ID=1234567
GIT_IDENTITY_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----"
```

> `.env.local` уже игнорируется git'ом в этом проекте. Каталог `secrets/` должен быть закрыт так же надёжно (см. шаг [3g](#g-убедиться-что-secrets-в-gitignore)).

### Сводная таблица источников секрета

| Секрет | Источник | Примечание |
|---|---|---|
| App ID | параметр `app_id` (через env `GIT_IDENTITY_APP_ID` или напрямую) | Обязателен; положительное целое |
| PEM private key | `private_key_path` (файл, `0600`) **либо** `private_key` (inline env) | Inline приоритетнее; файл требует `chmod 0600` |

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

1. GitHub → Settings → Developer settings → GitHub Apps → ваше App → **Configure** → **Install App**.
2. В настройках установки добавить нужный репо галочкой в **Repository access**.
3. Получить токен под новый репо:

```bash
eval "$(bin/console agent:token <owner>/<new-repo> --format=env)"
```

Поскольку PEM, App ID и команда одни на все проекты, новый репо начинает работать сразу после выставления галочки.

> **Конфигурация для разных проектов.** Либо **локальная**: секция `git_identity` + `secrets/` (с PEM) настраивается в каждом проекте отдельно. Либо **общая**: один inline-PEM задаётся через env (`GIT_IDENTITY_PRIVATE_KEY`) и переиспользуется всеми проектами — дублировать PEM-файл в каждый репозиторий необязательно. См. [раздел 5](#5-конфигурация-и-хранение-секретов).

## 8. Ротация PEM (private key)

Рекомендуется ротировать PEM примерно **раз в год** либо **немедленно при подозрении на компрометацию**.

1. На странице App → **Generate a private key** (новый).
2. Заменить файл `secrets/agent-identity/private-key.pem` новым содержимым, выставить `chmod 0600`.
   - При использовании inline-ключа (`private_key` через env) — обновите значение `GIT_IDENTITY_PRIVATE_KEY`.
3. В UI нажать **Delete** рядом со старым ключом (старый PEM при этом перестаёт действовать).
4. Проверить:

```bash
eval "$(bin/console agent:token <owner>/<repo> --format=env)" && gh api user --jq .login
# → <your-app>[bot]
```

> Installation tokens при ротации трогать избыточно — они короткоживущие (TTL ~1 ч) и перевыпускаются сами. Ротируется только PEM (средство подписи JWT).

## 9. Чек-лист DoD (эксплуатационная часть)

Задача считается закрытой, когда проверены **все** пункты. Имя App здесь представлено placeholder'ом — подставьте выбранное вами имя.

- [ ] GitHub App (с выбранным вами именем) создан и установлен на целевой репо.
- [ ] PEM лежит в `secrets/agent-identity/private-key.pem` с правами `0600` (либо задан через `GIT_IDENTITY_PRIVATE_KEY`).
- [ ] App ID задан параметром `app_id` (через `GIT_IDENTITY_APP_ID` в `.env.local` или напрямую).
- [ ] Каталог `secrets/` присутствует в `.gitignore` проекта.
- [ ] `eval "$(bin/console agent:token <owner>/<repo> --format=env)"` выполняется без ошибок.
- [ ] `gh api user --jq .login` → `<your-app>[bot]`.
- [ ] Тестовый PR создан от имени `<your-app>[bot]` (`gh pr view --json author --jq .author.login`).
- [ ] Владелец (человек) видит и может нажать **Approve** в UI.
- [ ] Merge проходит **без** `--admin` (branch protection реально работает).

## 10. Troubleshooting

| Симптом | Причина | Действие |
|---|---|---|
| `GitHub App ID is not configured (parameter task_orchestrator.git_identity.app_id).` | Не задан App ID | Задать `app_id` (через `GIT_IDENTITY_APP_ID` или напрямую) — [раздел 5](#5-конфигурация-и-хранение-секретов) |
| `GitHub App ID must be a positive integer.` | В `app_id` не число | Указать числовой App ID со страницы App |
| `GitHub App private key is not configured: set private_key or private_key_path.` | Не задан ключ | Задать `private_key_path` или `private_key` в секции `git_identity` |
| `GitHub App private key file not found: <path>` | Путь к PEM-файлу неверный или файла нет | Проверить путь в `private_key_path`, положить ключ в `secrets/agent-identity/private-key.pem` |
| `Private key file has insecure permissions (expected 0600): <path>` | Файл ключа читается группой/остальными | `chmod 0600 secrets/agent-identity/private-key.pem` |
| `Failed to read private key file: <path>` | Файл существует, но нечитается/повреждён | Проверить содержимое PEM; при подозрении — перевыпустить (раздел [8](#8-ротация-pem-private-key)) |
| `GitHub API error: HTTP 404 ... /repos/.../installation` | App **не установлен** на репо | Установить App (раздел [3, шаг f](#f-install-app-установка-на-репозиторий)), проверить `owner/repo` на опечатки |
| `GitHub API error: HTTP 403/404` при операциях в репо | Недостаточные permissions App | Проверить repository permissions (раздел [3, шаг b](#b-permissions-разрешения)) |
| `GitHub API request failed: network error ...` | Нет сети / прокси / DNS | Проверить подключение (как `CODEX_HTTP_PROXY`); при GHES — параметр `api_base_uri` |
| `gh auth status` показывает старую учётку после `eval` | `gh` кеширует авторизацию поверх `GITHUB_TOKEN` | `bin/console agent:token <owner>/<repo> --format=plain \| gh auth login --with-token`, либо `gh auth switch -u <your-app>[bot]` |
| Команды `gh` внезапно падают с `401` спустя ~1 ч | Installation token протух (TTL ~1 ч) | Перевыпустить: повторный `eval "$(bin/console agent:token ... --format=env)"` (кеш обновится автоматически) |

## Источники

- [Authenticating as a GitHub App](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app) — JWT (RS256), подпись.
- [Authenticating with an installation access token](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app-installation) — installation access tokens.
- [Choosing permissions for a GitHub App](https://docs.github.com/en/apps/creating-github-apps/registering-a-github-app/choosing-permissions-for-a-github-app) — список repository permissions.
- [Approving a pull request with required reviews](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/reviewing-changes-in-pull-requests/approving-a-pull-request-with-required-reviews) — branch protection и почему автор не может approve свой PR.
