---
type: docs
created: 2026-08-12
value: V3
complexity: C0
priority: P0
depends_on:
epic:
author: codex-cli
assignee: codex-cli
branch: task/fix-git-proxy-basic-auth
pr:
status: in_progress
---

# TASK-fix-git-proxy-basic-auth: Безопасный рецепт push: принудительный Basic auth прокси

## 0. Простое описание (Human Brief)
*Пишем для человека без погружения в код. Коротко, без внутреннего жаргона.*

### Проблема простыми словами (Problem)
- Безопасный рецепт push PR-ветки (через HTTPS installation token бота) иногда падает с ошибкой `SSL_write()`, когда агент работает за аутентифицируемым HTTPS-прокси.
- Из-за этого push не проходит, и агент не может отправить ветку для PR, блокируя всю дальнейшую работу.

### Варианты или путь решения (Solution Sketch)
- Явно задать в рецепте `http.proxyAuthMethod=basic`: тогда Git сразу посылает учётные данные прокси в первом подключении, не дожидаясь отказа `407`, и не попадает в цикл переподключения.
- Документировать причину в поясняющем тексте и добавить строку troubleshooting для симптома `SSL_write()`.

### Ожидаемый результат (Expected Result)
- Агент надёжно пушит PR-ветки через аутентифицируемый HTTPS-прокси: рецепт из документации работает «как написано», без ручных доработок.
- При симптоме `SSL_write()` документ подсказывает точное действие.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда AI-агент пушит PR-ветку через аутентифицируемый HTTPS-прокси, я хочу, чтобы Git сразу использовал Basic-аутентификацию прокси и не попадал в цикл `407`/reconnect, чтобы push надёжно проходил без падения на `SSL_write()`.

### Goal (Цель по SMART)
*S (Specific) — Конкретно | M (Measurable) — Измеримо | A (Achievable) — Достижимо | R (Relevant) — Релевантно | T (Time-bound) — Ограниченно во времени*

Поправить единственный безопасный push-рецепт в `docs/guide/agent-identity.md` так, чтобы он включал `http.proxyAuthMethod=basic` в изолированной конфигурации `GIT_CONFIG` (со скорректированным `GIT_CONFIG_COUNT`), и задокументировать причину в поясняющем тексте и в таблице troubleshooting. Измеримо: рецепт содержит ровно 7 пар `GIT_CONFIG_KEY_*`/`GIT_CONFIG_VALUE_*`, `GIT_CONFIG_COUNT=7`, добавлена строка симптома `SSL_write()`, проверки `make md-links`/`validate-todo`/`validate-language` и `git diff --check` проходят.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/guide/agent-identity.md` — раздел «Локальный режим проекта: push PR-веток токеном бота» (рецепт + поясняющий абзац), таблица «10. Troubleshooting», раздел «Источники».
*   **Текущее поведение:** безопасный рецепт изолирует push через 6 пар `GIT_CONFIG` (`GIT_CONFIG_COUNT=6`) и выставляет `HTTP/1.1` для совместимости с HTTPS-прокси. При этом метод аутентификации прокси не задаётся явно — Git использует значение по умолчанию `anyauth`.
*   **Проблема:** при `anyauth` Git (libcurl) сначала шлёт `CONNECT` без учётных данных прокси, прокси отвечает `407 Proxy Authentication Required`, а для этого ответа отдаёт `Connection: close`. После закрытия соединения libcurl переподключается к HTTPS-прокси (новое TLS-рукопожатие), и текущая связка libcurl/OpenSSL может упасть на повторном подключении с ошибкой `SSL_write()`. Push не проходит.
*   **Границы (Out of Scope):** код библиотеки, конфигурация модуля `GitIdentity`, команда `agent:token`, любые изменения вне `docs/guide/agent-identity.md`; не меняем настройки прокси и не вводим обходных путей (fallback).

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] В рецепт добавлена пара `GIT_CONFIG_KEY_6=http.proxyAuthMethod` / `GIT_CONFIG_VALUE_6=basic`.
- [x] `GIT_CONFIG_COUNT` увеличен с `6` до `7`.
- [x] В поясняющем абзаце объяснено: `anyauth` сначала получает `407`, прокси закрывает соединение (`Connection: close`), libcurl/OpenSSL может упасть на повторном подключении (`SSL_write()`); `basic` посылает учётные данные прокси в первом `CONNECT` внутри TLS к HTTPS-прокси и обходит цикл `407`/reconnect.
- [x] В таблицу «10. Troubleshooting» добавлена строка для симптома `SSL_write()` с точным действием (использовать актуальный рецепт с `http.proxyAuthMethod=basic`).
- [x] В раздел «Источники» добавлена ссылка на `git-config` → `http.proxyAuthMethod`.

### 🟡 Should Have (Желательно)
- [x] Согласованность формулировок: никаких запасных режимов (fallback) и отключения прокси не предлагается.

### 🟢 Could Have (Опционально)
- [ ] —

### ⚫ Won't Have (Не будем делать)
- [ ] Не дублировать рецепт в других документах (поиском подтверждено: единственное место — `docs/guide/agent-identity.md`).
- [ ] Не добавлять обходных путей (fallback) и не отключать прокси.
- [ ] Не менять код, конфигурацию модуля, команду `agent:token`.

## 4. Implementation Plan (План реализации)
*План заполнен исполнителем (Гермиона) перед стартом. Реализация подтверждена пользователем.*
1. [x] Создать рабочую ветку `task/fix-git-proxy-basic-auth` (уже активна).
2. [x] Проверить поиском, что рецепт существует в единственном месте и не дублируется (`GIT_CONFIG_COUNT`, `AUTH_HEADER`, `proxyAuthMethod`, `SSL_write`).
3. [x] В рецепте: `GIT_CONFIG_COUNT=6` → `GIT_CONFIG_COUNT=7`; добавить `export GIT_CONFIG_KEY_6=http.proxyAuthMethod GIT_CONFIG_VALUE_6=basic`.
4. [x] В поясняющем абзаце добавить предложение-обоснование про `http.proxyAuthMethod=basic`.
5. [x] В таблицу «10. Troubleshooting» добавить строку симптома `SSL_write()` с действием.
6. [x] В раздел «Источники» добавить ссылку на `http.proxyAuthMethod`.
7. [x] Проверки: `make md-links`, `make validate-todo`, `make validate-language`, `git diff --check`.

## 5. Definition of Done (Критерии приёмки)
- [x] Безопасный рецепт в `docs/guide/agent-identity.md` содержит `http.proxyAuthMethod=basic` и `GIT_CONFIG_COUNT=7`.
- [x] Причина фикса объяснена в поясняющем тексте (цикл `407`/reconnect → `SSL_write()`).
- [x] Добавлена строка troubleshooting для `SSL_write()` с точным действием.
- [x] Рецепт не дублирован; копий не создано.
- [x] Нет обходных путей (fallback) и отключения прокси.
- [x] `make md-links`, `make validate-todo`, `make validate-language`, `git diff --check` проходят.

## 6. Verification (Самопроверка)
*Рекомендуемые команды для проверки результата.*
```bash
make md-links
make validate-todo
make validate-language
git diff --check
```
> Документационная правка (docs-only): `make check` (PHPUnit/Psalm и др.) не требуется — код, конфигурации и скрипты не затронуты.

## 7. Risks and Dependencies (Риски и зависимости)
- Точность технической формулировки: причина описывается по механике libcurl/`http.proxyAuthMethod`; источник — официальная документация `git-config` (см. раздел «Источники» в задаче и в документе).
- Англицизмы: `docs/guide/` проверяется `validate-language`; новые термины держим в backticks, формулировки — на русском.
- Согласованность ссылок: заголовок рецепта и якорь не меняются, существующая ссылка из `docs/guide/troubleshooting.md` остаётся валидной.

## 8. Sources (Источники)
*Ссылки на документацию, RFC, связанные задачи.*
- [x] [git-config — http.proxyAuthMethod](https://git-scm.com/docs/git-config#Documentation/git-config.txt-httpproxyAuthMethod) — первичный источник: документирует `anyauth` по умолчанию против `basic`; добавлен в раздел «Источники» документа.
- [x] [libcurl — CURLOPT_PROXYAUTH](https://curl.se/libcurl/c/CURLOPT_PROXYAUTH.html) — подтверждает механику причины (`anyauth` → лишний round-trip с `407` → повторное подключение → возможный `SSL_write()`).
- [x] [Безопасный HTTPS-рецепт (локальный режим проекта)](../docs/guide/agent-identity.md#локальный-режим-проекта-push-pr-веток-токеном-бота) — целевой артефакт правки.

## 9. Comments (Комментарии)
*Дополнительная информация, примечания, важные нюансы.*

Отдельная атомарная задача, не связанная с TRIZ PR. Пользователь явно подтвердил реализацию. Рецепт использует proxy URL/credentials из стандартной конфигурации Git/libcurl (env `HTTPS_PROXY`/`HTTP_PROXY`/`ALL_PROXY` или `git config http.proxy`), а `http.proxyAuthMethod` задаёт лишь метод (`basic` против умолчательного `anyauth`), а не сами учётные данные.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-12 | Технический писатель (Гермиона) | Создание задачи, перевод в `in_progress`, план реализации заполнен. |
| 2026-08-12 | Технический писатель (Гермиона) | Точечная доработка: поля `author`/`assignee` приведены к справочнику `AI_AGENTS.md` (`codex-cli`); из Risks удалён `@techdebt` про внешний парсер — причина warnings устранена заменой значений во front matter. |
| 2026-08-12 | Технический писатель (Гермиона) | Self-review по замечаниям ревью (Пуаро, Approval): отмечены фактически выполненные Must/Should критерии; из Problem/Story убрано недоказанное указание `(Cloudflare)` (это пользовательский HTTPS-прокси, принадлежность Cloudflare не подтверждена); в Comments уточнён источник proxy-credentials — стандартная конфигурация Git/libcurl (env или git config), а не `CODEX_HTTP_PROXY`. |
