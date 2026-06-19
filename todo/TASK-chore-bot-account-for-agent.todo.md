---
type: chore
created: 2026-06-19
updated: 2026-06-19
value: V2
complexity: C2
priority: P2
depends_on: []
epic:
author: prikotov
assignee: team_lead_alex
branch: task/chore-bot-account-for-agent
pr:
status: in_progress
---

# TASK-chore-bot-account-for-agent: Разделение идентичности AI-агента и владельца репо (GitHub App `prikotov-agent`)

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)

Когда AI-агент (pi/codex/opencode) создаёт PR, он действует от имени `prikotov` — владельца репо.
GitHub запрещает автору PR ставить approve на свой PR. При работе в одиночку это блокирует
branch protection (требуется 1 review), и единственный обход — merge через `--admin`,
что фактически отключает защиту. Нужно разделить идентичности: агент работает от отдельной
идентичности, а владелец может approve'нуть PR.

### Решение

**GitHub App `prikotov-agent`** — единая идентичность на все проекты пользователя (~36 репо),
без заведения второго «человеческого» аккаунта.

Принципы (важные для понимания):

- **Идентичность ≠ токен ≠ доступ.** Идентичность одна на все проекты; токены короткоживущие
  (installation tokens, TTL ~1 ч); доступ — по репо через галочки установки App.
- **Один App на всех агентов.** pi, codex, opencode и любой другой агент подхватывают
  токен через переменную окружения `GH_TOKEN`/`GITHUB_TOKEN`. Никаких отдельных аккаунтов на агент.
- **GitHub App — официальный механизм** для автоматизации, в отличие от «второго личного аккаунта».

### Goal (Цель по SMART)

Настроить GitHub App `prikotov-agent`, чтобы в течение спринта:
- PR создаются от имени App (`prikotov-agent[bot]`) → владелец (`prikotov`) видит кнопку Approve.
- Branch protection работает по-настоящему: merge без approval невозможен (убираем `--admin`).
- Любой агент (pi/codex/opencode) работает под App через `GH_TOKEN`.
- Процесс задокументирован и масштабируется на новые репо добавлением галочки установки.

## 2. Context and Scope (Контекст и Границы)

* **Проблема:** `gh` авторизован как `prikotov` (PAT, scope `repo`/`workflow`/`read:org`/`delete_repo`/`gist`).
  Все PR от его имени → GitHub не даёт approve свои PR → обход через `gh pr merge --admin`.
* **Branch protection `main`:** `required_approving_review_count: 1`, `required_signatures: false`,
  `enforce_admins: false` (поэтому сейчас и работает `--admin`).
* **Пилотный репозиторий:** `task-orchestrator` (этот). Развёртывание на остальные ~35 репо —
  отдельная задача, в гайде описан мульти-проектный сценарий.
* **Физическое ограничение:** создание App, выпуск PEM, установка и финальная проверка тестовым PR —
  ручные действия в GitHub UI, доступные **только пользователю**. Агент выполняет техническую часть
  (гайд + скрипт-обёртка), пользователь — UI-часть по чек-листу из гайда.

### Границы (Out of Scope)

- Изменение branch protection rules (правила `main` не трогаем).
- Изменение кода проекта (бизнес-логика `src/`).
- Развёртывание App на остальные репозитории (отдельная задача).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

- [ ] Гайд `docs/git-workflow/agent-identity.md`: концепция (идентичность≠токен≠доступ),
      пошаговое создание GitHub App `prikotov-agent`, список permissions, установка на репо,
      выпуск PEM, использование скрипта, `gh auth login`/`switch`, мульти-проект, чек-лист, troubleshooting.
- [ ] Скрипт `bin/agent-token` (PHP): PEM → JWT RS256 (через `ext-openssl`, без сторонних зависимостей)
      → installation token. Вход: `<owner>/<repo>`. Кеш токена (TTL по `expires_at`).
      Вывод: `export GH_TOKEN=...` для `eval` + режим `--raw` для `gh auth login --with-token`.
      PEM — из env `AGENT_PRIVATE_KEY_PATH` или `~/.config/prikotov-agent/` (chmod 600),
      **никогда** не в репо/args/логах.
- [ ] Актуализация ссылок: `docs/git-workflow/index.md`, `docs/index.md`, упоминание в `pull-request.md`.
- [ ] Документировано: как переключать авторизацию (человек ↔ бот), как ротатить PEM (~раз в год),
      как добавить новый репо.

### 🟡 Should Have (Желательно)

- [ ] Короткоживущие installation tokens вместо long-lived PAT — **закрыто архитектурно** (TTL ~1 ч).
- [ ] Кеш токена между вызовами (не дёргать API на каждый запуск агента).

### ⚫ Won't Have (Не будем делать)

- Изменение branch protection rules.
- Заведение второго «человеческого» аккаунта (бот-аккаунта).
- Развёртывание App на остальные репозитории.
- Отзыв/сужение PAT `prikotov` (scope `delete_repo`) — отдельная задача `TASK-fix-...` (предложить).

## 4. Implementation Plan (План реализации)

### Часть A — делает агент (этот PR)

1. [ ] Гайд `docs/git-workflow/agent-identity.md` — **Тех. писатель Гермиона**.
2. [ ] Скрипт `bin/agent-token` (PHP, `ext-openssl`, без зависимостей) + unit-тесты — **Бэкендер Тони**.
3. [ ] Self-review (Гермиона) + Code review с упором на безопасность — **Ревьювер Бэка Пуаро**.
4. [ ] Актуализация ссылок в `docs/git-workflow/index.md`, `docs/index.md`, `docs/git-workflow/pull-request.md`.
5. [ ] Проверки: `vendor/bin/phpunit` + `vendor/bin/psalm` для PHP-части; docs-часть — пропуск по исключению.

### Часть B — делает пользователь (после merge PR)

1. [ ] Создать GitHub App `prikotov-agent` с permissions: Contents RW, Pull requests RW, Workflows RW, Metadata R.
2. [ ] Сгенерировать private key (PEM), сохранить в `~/.config/prikotov-agent/` (chmod 600).
3. [ ] Установить App на `task-orchestrator`, добавить `prikotov-agent` collaborator при необходимости.
4. [ ] Проверить: `eval "$(bin/agent-token prikotov/task-orchestrator)"` → `gh auth status` → PR от App.
5. [ ] Проверить DoD: PR от App → Approve от `prikotov` → merge без `--admin`.

## 5. Definition of Done (Критерии приёмки)

**Техническая часть (агент):**
- [ ] Гайд `docs/git-workflow/agent-identity.md` написан и актуализированы ссылки.
- [ ] Скрипт `bin/agent-token` работает: `eval "$(bin/agent-token prikotov/task-orchestrator)"`
      выставляет валидный `GH_TOKEN`; есть unit-тесты.
- [ ] Секреты (PEM) не попадают в репо/args/логи; права минимальны.

**Эксплуатационная часть (пользователь):**
- [ ] GitHub App `prikotov-agent` создан и установлен на `task-orchestrator`.
- [ ] AI-агент создаёт PR от имени `prikotov-agent[bot]`.
- [ ] Владелец (`prikotov`) видит кнопку Approve в UI.
- [ ] Merge без approval невозможен (branch protection работает, `--admin` не нужен).

## 6. Verification (Самопроверка)

```bash
# Статич. проверки (PHP-часть)
vendor/bin/phpunit tests/Unit/
vendor/bin/psalm

# Скрипт: получить токен и проверить, что он валиден
eval "$(bin/agent-token prikotov/task-orchestrator)"
gh auth status          # должен показать вход от prikotov-agent[bot]
gh api user --jq .login # →  prikotov-agent[bot]

# Тестовый PR от App → проверить автора
gh pr create --head test-bot-pr --base main --title "test: bot PR" --body "test"
gh pr view --json author --jq .author.login   # →  prikotov-agent[bot]
```

## 7. Risks and Dependencies (Риски и зависимости)

- **PEM утечка** — Mitigation: хранение в `~/.config/prikotov-agent/` (chmod 600), не в репо;
  скрипт не пишет ключ в args/логи; ротация ключа через UI (~раз в год).
- **`ext-openssl` отсутствует** — проверено: доступен. JWT RS256 собирается без сторонних зависимостей.
- **App permissions недостаточны** — Mitigation: точный список permissions в гайде; troubleshooting-раздел.
- **Installation token TTL (~1 ч)** — Mitigation: кеш в скрипте с TTL по `expires_at`; при истечении — повторный запрос.
- **Зависимость DoD от ручных действий пользователя** — задача в `done` только после эксплуатационной проверки.

## 8. Sources (Источники)

- GitHub Docs: Authenticating as a GitHub App (JWT, installation tokens).
- GitHub Docs: Managing allowed IP addresses / permissions for GitHub Apps.
- AGENTS.md проекта — правила безопасности (секреты, fail-fast, без fallback).

## 9. Comments (Комментарии)

Сравнение вариантов (итог обсуждения с пользователем):

| | Бот-аккаунт + PAT | **GitHub App `prikotov-agent`** ✅ |
|---|---|---|
| Идентичностей | 1 аккаунт | **1 App** (легитимный механизм GitHub) |
| Доступ в N репо | collaborator в каждый вручную | установка App, галочки по списку |
| Токен на репо | N PAT (ротация = боль) | **1 скрипт**, короткоживущие токены (TTL ~1 ч) |
| Blast radius | 1 репо × PAT TTL | **1 репо × 1 час** |
| `gh` совместимость | нативная | обёртка `bin/agent-token` (разовая работа) |
| ToS-риски | «второй аккаунт» — серая зона | официальный механизм |

Выбран GitHub App: при ~36 репо один App + скрипт выгоднее кучи PAT, безопаснее и легитимнее.

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-19 | Тимлид | Создание задачи (исходная постановка, варианты A/B). |
| 2026-06-19 | Тимлид Алекс | Рефрейминг: бот не «на агента», а единая идентичность на всех агентов. Учтён мульти-агентный (pi/codex/opencode) и мульти-проектный (~36 репо) контекст. Выбран GitHub App `prikotov-agent`. Перенос `backlog → todo`, начало реализации. |
