# Идентичность агента (Agent Identity)

**Идентичность агента (Agent Identity)** — отдельная учётная сущность GitHub, от имени которой AI-агент создаёт PR и выполняет операции с репозиторием, отделённая от учётной записи владельца репозитория.

В этом проекте роль такой сущности выполняет **GitHub App `prikotov-agent`** (далее — App), а получение токенов автоматизировано скриптом `bin/agent-token`.

## Границы ответственности

- Документ объясняет концепцию разделения идентичностей и описывает настройку/использование GitHub App `prikotov-agent`.
- Скрипт `bin/agent-token` — точный интерфейс см. в разделе [Использование скрипта](#4-использование-скрипта-binagent-token) и в справке `bin/agent-token --help`.
- Правила создания PR см. в [Pull Request (PR)](../git-workflow/pull-request.md), правила веток — в [Ветки (Branches)](../git-workflow/branches.md).

## 1. Контекст и проблема

AI-агенты (`pi`, Codex, OpenCode) создают PR через CLI `gh`. Если `gh` авторизован как `prikotov` — владелец репозитория, — возникает физическое ограничение GitHub:

- **Автор PR не может approve (одобрить) свой собственный PR.** Это запрещено на уровне платформы.
- На ветке `main` включена **branch protection** (защита ветки): требуется `required_approving_review_count: 1`.
- Так как автор и единственный возможный ревьюер — один и тот же человек, approval получить неоткуда.
- Единственный обход, который сейчас работает, — `gh pr merge --admin`. Этот флаг **принудительно игнорирует protection rules** для администратора. По факту он отключает защиту: merge становится возможным без review вообще.

Итог: защита ветки существует только на бумаге, а каждый merge агента — это административный обход собственного правила.

> Принципиальная проблема в том, что **одна и та же идентичность** и создаёт PR, и должна его одобрить. Решение — разделить идентичности.

## 2. Решение: GitHub App `prikotov-agent`

### Принцип: Идентичность ≠ Токен ≠ Доступ

Три независимых слоя, которые нельзя путать:

| Слой | Что это | Сколько | Срок жизни |
|---|---|---|---|
| **Идентичность (Identity)** | Сам App `prikotov-agent` как участник GitHub (`prikotov-agent[bot]`) | **Одна** на все проекты и всех агентов | Постоянно |
| **Токен (Token)** | Installation access token, который агент подставляет в `GH_TOKEN` | По одному на установку в моменте | Короткоживущий, TTL ~1 ч |
| **Доступ (Access)** | Список репозиториев, к которым у установки App есть права | Управляется галочками в настройках установки | До явного отзыва |

Ключевой вывод: **одна идентичность — много короткоживущих токенов — гранулярный доступ по репо**. Утечка токена компрометирует максимум один репо на один час, а не все проекты навсегда.

### Почему GitHub App, а не бот-аккаунт

«Бот-аккаунт» — это второй обычный пользовательский аккаунт GitHub под выдуманной личностью. Это плохой вариант:

- **Нарушает ToS (Terms of Service) GitHub.** Один человек — один личный аккаунт; служебный аккаунт с PAT — серая зона, GitHub официально для автоматизации предлагает именно GitHub Apps.
- **Боль blast radius (радиус поражения).** PAT (Personal Access Token, личный токен доступа) долгоживущий и привязан к аккаунту, а не к репо. Утечка = доступ ко всем репо, где аккаунт — collaborator.
- **Не масштабируется.** На ~36 репозиториях добавлять второй аккаунт collaborator'ом в каждый — ручная и хрупкая операция.

GitHub App — **легитимный механизм** для автоматизации, с гранулярными permissions, установкой на конкретные репо и встроенными короткоживущими токенами.

### Почему один App на всех агентов

`pi`, Codex CLI и OpenCode — разные инструменты, но **все они читают токен из переменной окружения `GH_TOKEN`** (или `GITHUB_TOKEN`). Поэтому:

- Один App `prikotov-agent` → один скрипт `bin/agent-token` → одна переменная `GH_TOKEN`.
- Любой агент, запущенный после `eval "$(bin/agent-token <owner>/<repo>)"`, автоматически работает от имени App.
- Не нужны отдельные App'ы, токены или аккаунты на каждый агент.

## 3. Пошаговая настройка (выполняет пользователь)

> Создание App, выпуск PEM (private key), установка и финальная проверка — ручные действия в GitHub UI, доступные **только владельцу аккаунта**. Агент эти шаги выполнить не может.

### a. Создание GitHub App

1. GitHub → **Settings** (учётной записи/организации) → **Developer settings** → **GitHub Apps** → **New GitHub App**.
2. **GitHub App name:** `prikotov-agent`.
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

1. После создания App открыть его страницу (Settings → Developer settings → GitHub Apps → `prikotov-agent`).
2. Запомнить **App ID** (число вверху страницы) — оно понадобится на шаге `e`.
3. Внизу в разделе **Private keys** → **Generate a private key**.
4. GitHub скачает файл вида `prikotov-agent.private-key.<date>.pem`.

### d. Сохранить PEM

```bash
mkdir -p ~/.config/prikotov-agent
mv ~/Downloads/prikotov-agent.private-key.*.pem ~/.config/prikotov-agent/private-key.pem
chmod 0600 ~/.config/prikotov-agent/private-key.pem
```

> `chmod 0600` обязателен: скрипт `bin/agent-token` откажется читать ключ с открытыми для группы/остальных правами (fail-fast по безопасности).

### e. Сохранить App ID

```bash
echo 1234567 > ~/.config/prikotov-agent/app-id
```

Подставьте вместо `1234567` реальный **App ID** со страницы App.

### f. Install App (установка на репозиторий)

1. На странице App → **Install App** (слева в меню).
2. Выбрать аккаунт/организацию для установки.
3. **Repository access** → **Only select repositories** → отметить галочками нужные репо.
4. Для пилота — отметить **`task-orchestrator`**.

После установки App получает `installation_id` (внутренний идентификатор установки) — он нужен скрипту для запроса токена. Скрипт находит его автоматически по `<owner>/<repo>`.

> Идентичность `prikotov-agent[bot]` после установки автоматически становится участником выбранных репо. Добавлять отдельного collaborator'а вручную не нужно.

## 4. Использование скрипта `bin/agent-token`

Скрипт `bin/agent-token` инкапсулирует весь цикл получения токена: чтение PEM → сборка JWT (RS256, через `ext-openssl`, без сторонних зависимостей) → поиск `installation_id` по `<owner>/<repo>` → запрос installation access token → кеширование.

### Справка

```bash
bin/agent-token --help
```

### Основной сценарий — выставить `GH_TOKEN` через `eval`

```bash
eval "$(bin/agent-token prikotov/task-orchestrator)"
```

По умолчанию скрипт печатает строку вида `export GH_TOKEN='ghs_...'`, которую `eval` выполняет в текущей оболочке. После этого `gh` (и любой агент) использует этот токен.

### Проверка, что авторизация сменилась на бота

```bash
gh auth status              # показывает вход от prikotov-agent[bot]
gh api user --jq .login     # → prikotov-agent[bot]
```

### Форматы вывода

| Вызов | Вывод | Назначение |
|---|---|---|
| `bin/agent-token <owner>/<repo>` | `export GH_TOKEN='...'` | для `eval` (по умолчанию) |
| `bin/agent-token <owner>/<repo> --raw` | голый токен | для `gh auth login --with-token` / pipe |
| `bin/agent-token <owner>/<repo> --json` | JSON `{token, expires_at, installation_id}` | для инспекции/скриптов |
| `bin/agent-token --help` / `-h` | справка | — |

Режим `--raw` удобно использовать для постоянной авторизации `gh` под ботом:

```bash
bin/agent-token prikotov/task-orchestrator --raw | gh auth login --with-token
```

### Как агенты подхватывают токен

`pi`, Codex CLI и OpenCode читают `GH_TOKEN` из окружения. Любой процесс, запущенный в той же оболочке после `eval "$(bin/agent-token ...)"`, автоматически работает от имени `prikotov-agent[bot]` — отдельной конфигурации на агент не требуется.

### Конфигурация

| Параметр | env (приоритет) | Файл по умолчанию |
|---|---|---|
| PEM private key | `AGENT_PRIVATE_KEY_PATH` | `~/.config/prikotov-agent/private-key.pem` (обязательно `chmod 0600`) |
| App ID | `AGENT_APP_ID` | `~/.config/prikotov-agent/app-id` |

### Механика и кеш

- JWT собирается скриптом самостоятельно: `header.payload.signature`, подпись RS256 через `ext-openssl`, payload `{iat, exp = iat + 9 мин, iss = App ID}`.
- `installation_id` запрашивается через `GET /repos/{owner}/{repo}/installation`.
- Access token запрашивается через `POST /app/installations/{id}/access/tokens`.
- Токен **кешируется** в `var/cache/agent-token/<installation_id>.json`. TTL = `expires_at` минус 60 секунд запаса. Пока кеш валиден, запросов к API нет.
- При HTTP-ошибке (сеть/4xx/5xx) скрипт завершается с кодом `1` и сообщением без чувствительных данных — **JWT, PEM и сам токен никогда не попадают в вывод/stderr**.

## 5. Переключение авторизации: человек ↔ бот

Возможны два режима работы.

### Режим 1: агент всегда под ботом, человек — в браузере

- В CLI `gh` всегда выставлен `GH_TOKEN` App (через `eval` перед запуском агента или `gh auth login --with-token`).
- Человек одобряет PR и выполняет merge вручную через GitHub UI в браузере от своего аккаунта `prikotov`.

### Режим 2: обе учётки в `gh` с переключением

`gh` умеет хранить несколько аккаунтов. Команда `gh auth switch` переключает активный:

```bash
gh auth switch -u prikotov          # переключиться на личный аккаунт
gh auth switch -u prikotov-agent[bot]  # переключиться на бота
gh auth status                       # посмотреть активную учётку
```

> Если активная сессия — бот, все команды `gh` идут от его имени. Перед ручным approve/merge в CLI убедитесь, что активен аккаунт `prikotov`, а не `prikotov-agent[bot]` (бот не может approve свой же PR).

## 6. Мульти-проект (масштабирование)

Подключение нового репозитория — **одно действие**, без новых токенов и collaborator'ов:

1. GitHub → Settings → Developer settings → GitHub Apps → `prikotov-agent` → **Configure** → **Install App**.
2. В настройках установки добавить нужный репо галочкой в **Repository access**.
3. Получить токен под новый репо:

```bash
eval "$(bin/agent-token prikotov/<new-repo>)"
```

Поскольку PEM, App ID и скрипт одни на все проекты, новый репо начинает работать сразу после выставления галочки.

## 7. Ротация PEM (private key)

Рекомендуется ротировать PEM примерно **раз в год** либо **немедленно при подозрении на компрометацию**.

1. На странице App → **Generate a private key** (новый).
2. Заменить файл `~/.config/prikotov-agent/private-key.pem` новым содержимым, выставить `chmod 0600`.
3. В UI нажать **Delete** рядом со старым ключом (старый PEM при этом перестаёт действовать).
4. Проверить: `eval "$(bin/agent-token prikotov/task-orchestrator)" && gh api user --jq .login`.

> Installation tokens при ротации трогать **не нужно** — они короткоживущие (TTL ~1 ч) и перевыпускаются сами. Ротируется только PEM (средство подписи JWT).

## 8. Чек-лист DoD (эксплуатационная часть)

Задача считается закрытой, когда проверены **все** пункты:

- [ ] GitHub App `prikotov-agent` создан и установлен на `task-orchestrator`.
- [ ] PEM лежит в `~/.config/prikotov-agent/private-key.pem` с правами `0600`; App ID в `~/.config/prikotov-agent/app-id`.
- [ ] `eval "$(bin/agent-token prikotov/task-orchestrator)"` выполняется без ошибок.
- [ ] `gh api user --jq .login` → `prikotov-agent[bot]`.
- [ ] Тестовый PR создан от имени `prikotov-agent[bot]` (`gh pr view --json author --jq .author.login`).
- [ ] Владелец `prikotov` видит и может нажать **Approve** в UI.
- [ ] Merge проходит **без** `--admin` (branch protection реально работает).

## 9. Troubleshooting

| Симптом | Причина | Действие |
|---|---|---|
| `Error: PEM file has insecure permissions. Expected 0600` | Файл ключа читается группой/остальными | `chmod 0600 ~/.config/prikotov-agent/private-key.pem` |
| `Error: PEM private key not found...` | Не задан путь к ключу | Задать `AGENT_PRIVATE_KEY_PATH` или положить ключ в `~/.config/prikotov-agent/private-key.pem` |
| `Error: App ID not found...` | Не задан App ID | Задать `AGENT_APP_ID` или создать `~/.config/prikotov-agent/app-id` |
| `Error: Invalid App ID: must be a positive integer` | В `app-id` не число | Записать числовой App ID со страницы App |
| `GitHub API error: HTTP 404` на шаге поиска installation | App **не установлен** на репо | Установить App (раздел 3, шаг `f`), проверить `owner/repo` на опечатки |
| `GitHub API error: HTTP 403/404` при операциях в репо | Недостаточные permissions App | Проверить repository permissions (раздел 3, шаг `b`) |
| `gh auth status` показывает старую учётку после `eval` | `gh` кеширует авторизацию поверх `GH_TOKEN` | `gh auth login --with-token` из `--raw`, либо `gh auth switch -u prikotov-agent[bot]` |
| Команды `gh` внезапно падают с `401` спустя ~1 ч | Installation token протух (TTL ~1 ч) | Перевыпустить: повторный `eval "$(bin/agent-token ...)"` (кеш обновится автоматически) |
| `Error: Failed to load private key` / `Failed to sign JWT` | Повреждён или некорректен PEM | Перевыпустить private key в UI и заменить файл (раздел 7) |

## Источники

- [Authenticating as a GitHub App](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app) — JWT (RS256), подпись.
- [Authenticating with an installation access token](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app-installation) — installation access tokens.
- [Choosing permissions for a GitHub App](https://docs.github.com/en/apps/creating-github-apps/registering-a-github-app/choosing-permissions-for-a-github-app) — список repository permissions.
- [Approving a pull request with required reviews](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/reviewing-changes-in-pull-requests/approving-a-pull-request-with-required-reviews) — branch protection и почему автор не может approve свой PR.
