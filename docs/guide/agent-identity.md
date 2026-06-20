# Идентичность агента (Agent Identity)

**Идентичность агента (Agent Identity)** — отдельная учётная сущность GitHub, от имени которой AI-агент создаёт PR и выполняет операции с репозиторием, отделённая от учётной записи владельца репозитория.

В этом проекте роль такой сущности выполняет **GitHub App** (далее — App), имя которого **выбирает сам пользователь**, а получение токенов автоматизировано скриптом `bin/agent-token`. task-orchestrator — публичный продукт, поэтому в гайде **нет захардкоженного имени App**: везде, где в примерах встречается `prikotov-agent` или владелец `prikotov`, — это **только иллюстрация**. Автор гайда использует `prikotov-agent`; выберите собственное имя (см. [Выбор имени App](#a-создание-github-app)).

## Границы ответственности

- Документ объясняет концепцию разделения идентичностей и описывает настройку/использование GitHub App (имя выбирает пользователь).
- Скрипт `bin/agent-token` — точный интерфейс см. в разделе [Использование скрипта](#4-использование-скрипта-binagent-token) и в справке `bin/agent-token --help`.
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
| **Токен (Token)** | Installation access token, который агент подставляет в `GH_TOKEN` | По одному на установку в моменте | Короткоживущий, TTL ~1 ч |
| **Доступ (Access)** | Список репозиториев, к которым у установки App есть права | Управляется галочками в настройках установки | До явного отзыва |

Ключевой вывод: **одна идентичность — много короткоживущих токенов — гранулярный доступ по репо**. Утечка токена компрометирует максимум один репо на один час, а не все проекты навсегда.

### Почему именно GitHub App

GitHub App — официальный механизм GitHub для автоматизации: гранулярные permissions, установка на конкретные репозитории, встроенные короткоживущие токены.

Альтернатива — «бот-аккаунт» (второй обычный пользовательский аккаунт под выдуманной личностью с PAT). У неё три минуса:

- **Нарушает ToS (Terms of Service, условия использования) GitHub.** Один человек — один личный аккаунт; служебный аккаунт с PAT (Personal Access Token, личный токен доступа) — серая зона, GitHub официально для автоматизации предлагает именно GitHub Apps.
- **Большой blast radius (радиус поражения).** PAT долгоживущий и привязан к аккаунту. Утечка = доступ ко всем репо, где аккаунт — collaborator (соавтор).
- **Не масштабируется.** На десятках репозиториев добавлять второй аккаунт collaborator'ом в каждый — ручная и хрупкая операция.

### Почему один App на всех агентов

`pi`, Codex CLI и OpenCode — разные инструменты, но **все они читают токен из переменной окружения `GH_TOKEN`** (или `GITHUB_TOKEN`). Поэтому:

- Один App → один скрипт `bin/agent-token` → одна переменная `GH_TOKEN`.
- Любой агент, запущенный после `eval "$(bin/agent-token <owner>/<repo>)"`, автоматически работает от имени App.
- Не нужны отдельные App'ы, токены или аккаунты на каждый агент.

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

> `chmod 0600` обязателен: скрипт `bin/agent-token` откажется читать ключ с открытыми для группы/остальных правами (fail-fast по безопасности).

### e. Сохранить App ID

App ID можно сохранить **файлом** в каталог секретов:

```bash
echo 1234567 > secrets/agent-identity/app-id
```

Либо задать через `.env.local` (см. [раздел 5](#5-конфигурация-и-хранение-секретов)):

```bash
# .env.local (в корне проекта)
AGENT_APP_ID=1234567
```

Подставьте вместо `1234567` реальный **App ID** со страницы App.

### f. Install App (установка на репозиторий)

1. На странице App → **Install App** (слева в меню).
2. Выбрать аккаунт/организацию для установки.
3. **Repository access** → **Only select repositories** → отметить галочками нужные репо.
4. Для пилота — отметить целевой репо (например, `task-orchestrator` — подставьте свой).

После установки App получает `installation_id` (внутренний идентификатор установки) — он нужен скрипту для запроса токена. Скрипт находит его автоматически по `<owner>/<repo>`.

> Идентичность `<your-app>[bot]` после установки автоматически становится участником выбранных репо. Добавлять отдельного collaborator'а вручную не нужно.

### g. Убедиться, что `secrets/` в `.gitignore`

Каталог `secrets/` содержит постоянные секреты (PEM, ключи) и **никогда не должен попадать в коммит**. Проверьте, что в `.gitignore` проекта есть строка для него. Если нет — добавьте:

```gitignore
# Постоянные секреты (PEM App, ключи) — никогда в коммит
/secrets/
```

> .env.local уже игнорируется в этом проекте (как, например, `CODEX_HTTP_PROXY`). Каталог `secrets/` должен быть закрыт так же надёжно. См. подробнее [семантику каталогов в разделе 5](#5-конфигурация-и-хранение-секретов).

## 4. Использование скрипта `bin/agent-token`

Скрипт `bin/agent-token` инкапсулирует весь цикл получения токена: чтение PEM → сборка JWT (RS256, через `ext-openssl`, без сторонних зависимостей) → поиск `installation_id` по `<owner>/<repo>` → запрос installation access token → кеширование.

### Справка

```bash
bin/agent-token --help
```

### Основной сценарий — выставить `GH_TOKEN` через `eval`

```bash
eval "$(bin/agent-token <owner>/<repo>)"
# Пример (подставьте свой owner/repo):
# eval "$(bin/agent-token prikotov/task-orchestrator)"
```

По умолчанию скрипт печатает строку вида `export GH_TOKEN='ghs_...'`, которую `eval` выполняет в текущей оболочке. После этого `gh` (и любой агент) использует этот токен.

### Проверка, что авторизация сменилась на App

```bash
gh auth status              # показывает вход от <your-app>[bot]
gh api user --jq .login     # → <your-app>[bot]
```

### Форматы вывода

| Вызов | Вывод | Назначение |
|---|---|---|
| `bin/agent-token <owner>/<repo>` | `export GH_TOKEN='...'` | для `eval` (по умолчанию) |
| `bin/agent-token <owner>/<repo> --raw` | голый токен | для `gh auth login --with-token` / pipe |
| `bin/agent-token <owner>/<repo> --json` | JSON `{token, expires_at, installation_id}` | для инспекции/скриптов |
| `bin/agent-token --help` / `-h` | справка | — |

Режим `--raw` удобно использовать для постоянной авторизации `gh` под App:

```bash
bin/agent-token <owner>/<repo> --raw | gh auth login --with-token
```

### Как агенты подхватывают токен

`pi`, Codex CLI и OpenCode читают `GH_TOKEN` из окружения. Любой процесс, запущенный в той же оболочке после `eval "$(bin/agent-token ...)"`, автоматически работает от имени `<your-app>[bot]` — отдельной конфигурации на агент не требуется.

### Механика и кеш

- JWT собирается скриптом самостоятельно: `header.payload.signature`, подпись RS256 через `ext-openssl`, payload `{iat, exp = iat + 9 мин, iss = App ID}`.
- `installation_id` запрашивается через `GET /repos/{owner}/{repo}/installation`.
- Access token запрашивается через `POST /app/installations/{id}/access/tokens`.
- Токен **кешируется** в `var/cache/agent-token/<installation_id>.json`. TTL = `expires_at` минус 60 секунд запаса. Пока кеш валиден, запросов к API нет.
- При HTTP-ошибке (сеть/4xx/5xx) скрипт завершается с кодом `1` и сообщением без чувствительных данных — **JWT, PEM и сам токен никогда не попадают в вывод/stderr**.

## 5. Конфигурация и хранение секретов

### Где что хранится: `secrets/` vs `var/`

В проекте два разных по смыслу каталога локальных данных. Их нельзя путать:

| Каталог | Семантика | Что внутри | Восстановление |
|---|---|---|---|
| **`secrets/`** | **Постоянные секреты** — долговременные ключи доступа | PEM App, App ID, иные ключи | **Только вручную** (перевыпуск через GitHub UI) |
| **`var/`** | **Временные данные** — кеш, протухающие артефакты | Кеш токена в `var/cache/agent-token/` | Чистится без последствий; протухает/пересоздаётся автоматически |

**Почему PEM в `secrets/`, а кеш в `var/`:** PEM — долговременное средство подписи JWT, перевыпускается только вручную через GitHub UI (см. [Ротация PEM](#8-ротация-pem-private-key)). Кеш токена — короткоживущий артефакт, пересоздаётся автоматически и чистится без последствий. Поэтому постоянные секреты живут в `secrets/`, а кеш — в `var/`.

### Способы задания конфигурации

Скрипт `bin/agent-token` разрешает PEM и App ID по цепочке приоритетов. Ниже — от высшего приоритета к низшему:

1. **Реальное окружение** (экспортировано в shell): `AGENT_PRIVATE_KEY_PATH`, `AGENT_APP_ID` — высший приоритет.
2. **`.env.local`** (в корне проекта): те же ключи. Скрипт **сам читает `.env.local`** при старте — вручную подключать его не нужно. Значения применяются, только если ключ не задан в реальном окружении (Symfony-конвенция: `.env.local` не перекрывает реальное окружение).
3. **`AGENT_CONFIG_DIR`** — каталог-override **целиком** (PEM в `<AGENT_CONFIG_DIR>/private-key.pem`, App ID в `<AGENT_CONFIG_DIR>/app-id`). Используется, только если не заданы точечные `AGENT_PRIVATE_KEY_PATH`/`AGENT_APP_ID`. Редкий сценарий — например, глобальный конфиг в `~/.config/task-orchestrator/`.
4. **Дефолт:** локальный каталог проекта `secrets/agent-identity/`.

> Итого: **точечные переменные (реальное окружение > `.env.local`) > `AGENT_CONFIG_DIR` > дефолт `secrets/agent-identity/`**.

### Дефолт: локально в проекте

Самый частый путь — всё лежит в проекте:

```
secrets/agent-identity/private-key.pem   # PEM, chmod 0600
secrets/agent-identity/app-id            # App ID
```

Никаких env-переменных задавать не нужно — скрипт найдёт файлы по дефолтным путям.

### Точечные переопределения через `.env.local`

Файл `.env.local` в корне проекта уже игнорируется git'ом (в этом проекте через него задан, например, `CODEX_HTTP_PROXY`). Те же переменные подходят для точечной переопределения конфигурации App, не трогая файлы в `secrets/`:

```bash
# .env.local (в корне проекта, gitignored)
AGENT_APP_ID=1234567
AGENT_PRIVATE_KEY_PATH=secrets/agent-identity/private-key.pem
```

### Глобальный конфиг: `AGENT_CONFIG_DIR` (редко)

Если App используется сразу для нескольких проектов и неудобно дублировать PEM в каждый `secrets/`, можно держать конфиг в одном месте (например, `~/.config/task-orchestrator/`) и указать каталог целиком:

```bash
export AGENT_CONFIG_DIR=~/.config/task-orchestrator
# ожидается:
#   ~/.config/task-orchestrator/private-key.pem
#   ~/.config/task-orchestrator/app-id
```

`AGENT_CONFIG_DIR` перекрывает **дефолт** `secrets/agent-identity/`, но **уступает** точечным `AGENT_PRIVATE_KEY_PATH`/`AGENT_APP_ID` (если они заданы — они важнее).

### Сводная таблица параметров

| Параметр | env (приоритет) | Файл/дефолт |
|---|---|---|
| PEM private key | `AGENT_PRIVATE_KEY_PATH` | `secrets/agent-identity/private-key.pem` (обязательно `chmod 0600`) |
| App ID | `AGENT_APP_ID` | `secrets/agent-identity/app-id` |
| Override каталог целиком | `AGENT_CONFIG_DIR` | `<dir>/private-key.pem`, `<dir>/app-id` |

## 6. Переключение авторизации: человек ↔ App

Возможны два режима работы.

### Режим 1: агент всегда под App, человек — в браузере

- В CLI `gh` всегда выставлен `GH_TOKEN` App (через `eval` перед запуском агента или `gh auth login --with-token`).
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
eval "$(bin/agent-token <owner>/<new-repo>)"
```

Поскольку PEM, App ID и скрипт одни на все проекты, новый репо начинает работать сразу после выставления галочки.

> **Конфигурация для разных проектов.** Либо **локальная**: `secrets/` (с PEM и App ID) живёт в каждом проекте отдельно. Либо **глобальная**: один каталог задаётся через `AGENT_CONFIG_DIR` (например, `~/.config/task-orchestrator/`) и используется всеми проектами. Дублировать PEM во все репозитории не обязательно — см. [раздел 5](#5-конфигурация-и-хранение-секретов).

## 8. Ротация PEM (private key)

Рекомендуется ротировать PEM примерно **раз в год** либо **немедленно при подозрении на компрометацию**.

1. На странице App → **Generate a private key** (новый).
2. Заменить файл `secrets/agent-identity/private-key.pem` новым содержимым, выставить `chmod 0600`.
   - При использовании `AGENT_CONFIG_DIR` — замените PEM в этом каталоге.
3. В UI нажать **Delete** рядом со старым ключом (старый PEM при этом перестаёт действовать).
4. Проверить:

```bash
eval "$(bin/agent-token <owner>/<repo>)" && gh api user --jq .login
# → <your-app>[bot]
```

> Installation tokens при ротации трогать **не нужно** — они короткоживущие (TTL ~1 ч) и перевыпускаются сами. Ротируется только PEM (средство подписи JWT).

## 9. Чек-лист DoD (эксплуатационная часть)

Задача считается закрытой, когда проверены **все** пункты. Имя App здесь не зафиксировано — подставьте выбранное вами имя.

- [ ] GitHub App (с выбранным вами именем) создан и установлен на целевой репо.
- [ ] PEM лежит в `secrets/agent-identity/private-key.pem` с правами `0600`; App ID задан (`secrets/agent-identity/app-id` или `AGENT_APP_ID` в `.env.local`).
- [ ] Каталог `secrets/` присутствует в `.gitignore` проекта.
- [ ] `eval "$(bin/agent-token <owner>/<repo>)"` выполняется без ошибок.
- [ ] `gh api user --jq .login` → `<your-app>[bot]`.
- [ ] Тестовый PR создан от имени `<your-app>[bot]` (`gh pr view --json author --jq .author.login`).
- [ ] Владелец (человек) видит и может нажать **Approve** в UI.
- [ ] Merge проходит **без** `--admin` (branch protection реально работает).

## 10. Troubleshooting

| Симптом | Причина | Действие |
|---|---|---|
| `Error: PEM file has insecure permissions. Expected 0600` | Файл ключа читается группой/остальными | `chmod 0600 secrets/agent-identity/private-key.pem` |
| `Error: PEM private key not found...` | Не задан путь к ключу | Задать `AGENT_PRIVATE_KEY_PATH` или положить ключ в `secrets/agent-identity/private-key.pem` |
| `Error: App ID not found...` | Не задан App ID | Задать `AGENT_APP_ID` или создать `secrets/agent-identity/app-id` |
| `Error: Invalid App ID: must be a positive integer` | В `app-id` не число | Записать числовой App ID со страницы App |
| `GitHub API error: HTTP 404` на шаге поиска installation | App **не установлен** на репо | Установить App (раздел 3, шаг `f`), проверить `owner/repo` на опечатки |
| `GitHub API error: HTTP 403/404` при операциях в репо | Недостаточные permissions App | Проверить repository permissions (раздел 3, шаг `b`) |
| `gh auth status` показывает старую учётку после `eval` | `gh` кеширует авторизацию поверх `GH_TOKEN` | `gh auth login --with-token` из `--raw`, либо `gh auth switch -u <your-app>[bot]` |
| Команды `gh` внезапно падают с `401` спустя ~1 ч | Installation token протух (TTL ~1 ч) | Перевыпустить: повторный `eval "$(bin/agent-token ...)"` (кеш обновится автоматически) |
| `Error: Failed to load private key` / `Failed to sign JWT` | Повреждён или некорректен PEM | Перевыпустить private key в UI и заменить файл (раздел 8) |

## Источники

- [Authenticating as a GitHub App](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app) — JWT (RS256), подпись.
- [Authenticating with an installation access token](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app-installation) — installation access tokens.
- [Choosing permissions for a GitHub App](https://docs.github.com/en/apps/creating-github-apps/registering-a-github-app/choosing-permissions-for-a-github-app) — список repository permissions.
- [Approving a pull request with required reviews](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/reviewing-changes-in-pull-requests/approving-a-pull-request-with-required-reviews) — branch protection и почему автор не может approve свой PR.
