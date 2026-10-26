---
type: fix
created: 2026-10-26 11:44:04 (1793015044)
due: 
started: 2026-10-26 11:44:04 (1793015044)
completed: 
cancelled: 
value: V2
complexity: C1
priority: P1
cost_plan: 
cost_fact: 
depends_on: 
epic: EPIC-docs-retro-skill-improvements
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/fix-run-subagent-local-proxy
pr: 
status: in_progress
---

# TASK-fix-run-subagent-local-proxy: NO_PROXY для локальных адресов в watch-subagent: прокси из CODEX_HTTP_PROXY ломает ollama/nserv

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
`watch-subagent.sh` подгружает `.env.local` и функцией `apply_proxy_env_defaults` экспортирует `HTTP_PROXY`/`HTTPS_PROXY` из `CODEX_HTTP_PROXY` (прокси нужен для codex и внешних провайдеров). `NO_PROXY` при этом не выставляется вовсе. В результате запросы раннера pi к локальным сервисам — в частности, к ollama на `http://nserv:11434/v1` (профиль ревьювера Пуаро из `config/chains.yaml`) — уходят во внешний HTTPS-прокси и падают с «Connection error» за доли секунды. Воспроизведено 2026-10-26: pi + ollama + `HTTP_PROXY` из `CODEX_HTTP_PROXY` → «Connection error»; то же с `NO_PROXY=nserv` → работает.

### Варианты или путь решения (Solution Sketch)
В `apply_proxy_env_defaults` добавить дефолтный `NO_PROXY`/`no_proxy` с локальными адресами (`localhost`, `127.0.0.1`, `::1`, `.local`, локальные подсети RFC 1918 и `nserv`), не перекрывая уже заданное пользователем значение. Покрыть функцию интеграционным тестом (вырезка функции из скрипта + исполнение в bash с разными входными условиями).

### Ожидаемый результат (Expected Result)
Сабагенты на локальном провайдере ollama (nserv) запускаются через `watch-subagent.sh` при заданном `CODEX_HTTP_PROXY`; внешние провайдеры (zai/codex) продолжают ходить через прокси как раньше; пользовательский `NO_PROXY` имеет приоритет.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как владелец проекта, я хочу, чтобы запуск сабагентов на локальном ollama (nserv) работал при настроенном `CODEX_HTTP_PROXY`, чтобы профиль ревьювера Пуаро (`qwen3.8:27b-pi-100k`) не падал с «Connection error» и не требовал обходных переопределений провайдера.

### Цель по SMART (Goal)
Изменить `apply_proxy_env_defaults` в `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`: при экспорте прокси-переменных выставлять `NO_PROXY`/`no_proxy` значением по умолчанию `localhost,127.0.0.1,::1,.local,nserv,192.168.0.0/16,10.0.0.0/8,172.16.0.0/12`, если только переменная ещё не задана (приоритет окружения пользователя). Добавить интеграционный тест функции: три случая (без прокси — переменные не трогаются; прокси без NO_PROXY — дефолт проставлен; прокси с пользовательским NO_PROXY — не перекрыт). Проверка: тест зелёный, контрольный запуск связи через штатный профиль Пуаро (`-r ...code_reviewer_backend_puaro.ru.md`) отвечает.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `docs/agents/skills/run-subagent/scripts/watch-subagent.sh` (функция `apply_proxy_env_defaults`); новый тест `tests/Integration/Docs/Agents/Skills/RunSubagent/`
*   **Текущее поведение:** экспортируются только `HTTP_PROXY`/`HTTPS_PROXY` (+ нижний регистр); `NO_PROXY` не выставляется; локальные адреса уходят в прокси
*   **Границы (Out of Scope):** изменение логики загрузки `.env.local`; настройка самих профилей ролей в `config/chains.yaml`; CIDR-совместимость конкретных HTTP-клиентов (дефолт консервативен: имена + подсети; не поддерживаемые клиентом подсети игнорируются без вреда)

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `apply_proxy_env_defaults` выставляет `NO_PROXY` и `no_proxy` дефолтом локальных адресов при экспорте прокси
- [x] Уже заданный пользователем `NO_PROXY`/`no_proxy` не перекрывается (приоритет окружения)
- [x] Без прокси (`CODEX_HTTP_PROXY`/`ALL_PROXY` пусты) функция по-прежнему ничего не меняет (ранний выход)
- [x] Интеграционный тест функции: три случая выше
### ⚫ Won't Have (Не будем делать)
- Изменение принципов приоритета прокси (CODEX_HTTP_PROXY > ALL_PROXY)
- Перечисление конкретных внешних хостов в NO_PROXY

## 4. План реализации (Implementation Plan)
1. [x] Диагноз: воспроизведение «Connection error» и проверка `NO_PROXY=nserv` напрямую (2026-10-26, по поручению владельца)
2. [x] Правка `apply_proxy_env_defaults` + дефолт `NO_PROXY`
3. [x] Интеграционный тест (`tests/Integration/Docs/Agents/Skills/RunSubagent/`)
4. [x] Контрольный запуск связи через штатный профиль Пуаро
5. [ ] Ревью и приёмка

## 5. Критерии приёмки (Definition of Done)
- [x] Все пункты Must Have выполнены
- [x] `vendor/bin/phpunit tests/Integration/Docs/Agents/Skills/RunSubagent/` — зелёный (3 теста, 22 утверждения)
- [x] `vendor/bin/psalm` — зелёный (затронут только новый тест)
- [x] Контрольный запуск связи через `-r docs/agents/roles/team/code_reviewer_backend_puaro.ru.md` возвращает ответ: «На связи, штатный профиль работает.»
- [x] `php vendor/bin/todo-md validate` — зелёный; PHPUnit всего набора зелёный (1575 тестов, включая 3 новых); `make check` падает только на предсуществующем `md-links` эпик-ветки — фикс уже в PR #433 (aba4404e), на этой ветке не дублируется

## 6. Самопроверка (Verification)
```bash
vendor/bin/phpunit tests/Integration/Docs/Agents/Skills/RunSubagent/
vendor/bin/psalm
docs/agents/skills/run-subagent/scripts/watch-subagent.sh -s 300 -t 120 -o text -r docs/agents/roles/team/code_reviewer_backend_puaro.ru.md <<< 'Проверка связи: ответь одним предложением.'
php vendor/bin/todo-md validate todo/TASK-fix-run-subagent-local-proxy.todo.md
```

## 7. Риски и зависимости
- **CIDR в NO_PROXY:** не все HTTP-клиенты понимают подсети; дефолт консервативен — прямые имена (`nserv`, `localhost`) покрывают главный кейс, подсети — бонус для клиентов с поддержкой
- **Новые локальные хосты в будущем:** добавляются в дефолт одной строкой или через собственный `NO_PROXY` в окружении (приоритет сохранён)

## 8. Источники (Sources)
- `docs/agents/skills/run-subagent/scripts/watch-subagent.sh` — изменяемая функция
- `docs/agents/skills/run-subagent/SKILL.md` — контракт запуска сабагентов
- `docs/guide/codex.md` — зачем нужен `CODEX_HTTP_PROXY`
- `config/chains.yaml` — профиль роли `code_reviewer_backend_puaro` (ollama/nserv)

## 9. Комментарии (Comments)
- Задача создана по прямому поручению владельца в ходе эпика EPIC-docs-retro-skill-improvements (ответ на сообщение «проверь должно работать»): сбой блокировал штатный профиль ревьювера, ревью PR #433 было выполнено на резервном профиле с явным override
- Ветка — от ветки эпика (фикс инфраструктуры сабагентов, нужен этому эпику); не стек над чужими PR

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-10-26 11:44:04 (1793015044) | Тимлид Алекс (pi) | Создание задачи |
| 2026-10-26 18:47:54 (1793015274) | Тимлид Алекс (pi) | Заполнение постановки по итогам диагностики (воспроизведение + NO_PROXY-эксперимент) |
