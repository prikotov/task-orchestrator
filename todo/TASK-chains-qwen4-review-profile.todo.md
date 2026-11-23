---
type: chore
created: 2026-11-23 04:23:42 (1795407822)
due: 
started: 2026-11-23 04:24:30 (1795407870)
completed: 
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Бэкендер Тони (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/chains-qwen4-review-profile
pr: 
status: in_progress
---

# TASK-chains-qwen4-review-profile: Перевод ревью (Пуаро) и Тони-C0 с ollama/huihui на Alibaba qwen4-max-preview

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Ревью кода (Пуаро) и простые задачи Тони (C0) выполняются на локальной модели `ollama/huihui-ai:pi-100k` (`medium`) — вне выборки дашборда моделей; локальный ориентир того класса (Qwen3.8 27B xhigh) — 20,1 балла AA v5.0.1. Это самое слабое звено команды, при том что Пуаро — контролёр качества: он слабее всех, кого ревьюит (исполнители — GLM-5.3-Flash 27,8; Kimi K3.1 Pro 46,5 на C5). У владельца появилась подписка Alibaba Cloud Model Studio Standard (провайдер pi `qwen-token-plan-individual`, модель `qwen4-max-preview`, xhigh = 42,9 балла; токены покрыты планом). Зависимость от локального ollama-узла (nserv) также делает ревью зависимым от локальной инфраструктуры.

### Варианты или путь решения (Solution Sketch)

Решение владельца (2026-11-23, подтверждено в диалоге с Тимлидом):
- Пуаро → `qwen-token-plan-individual/qwen4-max-preview` `medium` (прямой апгрейд с ≈20 баллов; `xhigh` отклонён владельцем как дорогой по поинтам плана).
- Тони C0 → та же модель, уровень поднят на одну ступень от предложенного `low` до `medium` (решение владельца 2026-11-23).
- Профиль `ollama_huihui_medium` удаляется — полного ухода от huihui требует владелец.
- Живая проверка 2026-11-23: pi JSON-режим, `qwen4-max-preview --thinking medium` — reasoning применяется; эндпоинт работает и напрямую, и через прокси из `CODEX_HTTP_PROXY` (в `NO_PROXY` изменений не требуется).

### Ожидаемый результат (Expected Result)

Ревью (Пуаро) и простые задачи (Тони C0) идут на Alibaba qwen4-max-preview `medium`; в `config/chains.yaml` не осталось ссылок на `ollama`/`huihui`; `make check` зелёный.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как владелец пакета, я хочу, чтобы ревью кода и простые задачи исполнялись на подписочной модели Alibaba вместо локальной huihui, чтобы качество ревью соответствовало остальной команде, а ревью-контур не зависел от локального ollama-узла.

### Цель по SMART (Goal)

(S) Заменить в `config/chains.yaml` профиль `ollama_huihui_medium` на `qwen_max_medium` (`pi`, `--provider qwen-token-plan-individual`, `--model qwen4-max-preview`, `--thinking medium`) в двух точках: `code_reviewer_backend_puaro` (по умолчанию) и `backend_developer_tony.commands_by_complexity.C0`; (M) обе точки ссылаются на новый профиль, старый профиль удалён, YAML валиден, `make check` зелёный; (A) модель и уровни присутствуют в `~/.pi/agent/models.json` (`thinkingLevelMap`: low/medium/xhigh); (R) доступность эндпоинта подтверждена пробами 2026-11-23; (T) один PR.

## 2. Контекст и Границы (Context and Scope)

* **Где делаем:** `config/chains.yaml` (секция `command_profiles` и `roles`).
* **Текущее поведение:** `ollama_huihui_medium` → `pi --provider ollama --model huihui-ai:pi-100k --thinking medium`; используется `code_reviewer_backend_puaro` (по умолчанию) и `backend_developer_tony` на C0.
* **Вне репозитория (не трогаем):** `~/.pi/agent/models.json` — провайдер `qwen-token-plan-individual` с моделями `qwen4-max-preview`/`qwen4-max` уже настроен.
* **Границы (Out of Scope):** прочие роли и профили, `.pi/settings.json`, код пакета, `CHANGELOG.md`/версия, перераспределение остальных моделей (отдельная задача).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [ ] Новый профиль `qwen_max_medium`: `pi --mode json -p --provider qwen-token-plan-individual --model qwen4-max-preview --thinking medium --system-prompt "@system-prompt" --append-system-prompt "@append-system-prompt"`
- [ ] `code_reviewer_backend_puaro.command_profile` → `qwen_max_medium`
- [ ] `backend_developer_tony.commands_by_complexity.C0` → `qwen_max_medium`
- [ ] Профиль `ollama_huihui_medium` удалён; в `config/chains.yaml` не осталось упоминаний `ollama`/`huihui`
- [ ] Заголовочный комментарий про `thinkingLevelMap` дополнен строкой про Qwen: поддерживаются `low`/`medium`/`xhigh` (`off`/`minimal`/`high`/`max` → `null` — не поддерживаются)
- [ ] YAML валиден, все `command_profile`/`commands_by_complexity` ссылаются на существующие профили
### ⚫ Won't Have (Не будем делать)
- Изменения прочих ролей/профилей, `.pi/settings.json`, кода пакета
- `CHANGELOG.md`/версия пакета, перераспределение остальных моделей (отдельная задача)
- Слияние PR без подтверждения владельца

## 4. План реализации (Implementation Plan)
1. [ ] Правки `config/chains.yaml` (профиль, две точки, комментарий)
2. [ ] Самопроверка: YAML-парсинг + разрешение профилей
3. [ ] `make check`, коммит, push
4. [ ] PR через prikotov-bot (label `pi`), задача → `done`

## 5. Критерии приёмки (Definition of Done)
- [ ] `config/chains.yaml`: Пуаро и Тони-C0 на `qwen_max_medium`, huihui/ollama отсутствуют
- [ ] `make check` проходит (включая validate-todo, validate-roles)
- [ ] Нет регрессий в смежных модулях
- [ ] Обновлена техническая документация (заголовочный комментарий `chains.yaml`)

## 6. Самопроверка (Verification)
```bash
make check                                            # lint + валидация + тесты
php -r 'require "vendor/autoload.php"; $c=Symfony\Component\Yaml\Yaml::parseFile("config/chains.yaml"); var_export($c["command_profiles"]["qwen_max_medium"]); echo PHP_EOL, $c["roles"]["code_reviewer_backend_puaro"]["command_profile"], PHP_EOL, $c["roles"]["backend_developer_tony"]["commands_by_complexity"]["C0"], PHP_EOL;'
grep -n -i "ollama\|huihui" config/chains.yaml || echo "OK: упоминаний нет"
```

## 7. Риски и зависимости (Risks and Dependencies)
- `medium` у qwen4-max-preview не оценён AA (известен только xhigh = 42,9) — фактическое качество подтверждается живым ревью Пуаро в этом конвейере.
- Недоступность Alibaba-эндпоинта остановит шаги ревью/C0 — откат решается revert PR; прямой и прокси-доступ проверены 2026-11-23.
- Поинтовый расход плана Alibaba: ревью на `medium` дешевле `xhigh`, лимиты не публикуются в дашборде (TODO владельца).

## 8. Источники (Sources)
- [ ] Дашборд `~/MyProjects/dashboards/models/index.html` (AA v5.0.1, срез 2026-11-22): Qwen4 Max xhigh = 42,9, Kimi 46,5, GLM-Flash 27,8, локальный ориентир 20,1
- [ ] `~/.pi/agent/models.json`: провайдер `qwen-token-plan-individual`, `thinkingLevelMap` qwen4-max-preview
- [ ] Пробы pi JSON-режим 2026-11-23: `--thinking medium` применяется, прямой и прокси-доступ ОК

## 9. Комментарии (Comments)

- Уровень `xhigh` для Пуаро отклонён владельцем (2026-11-23): «дорого» по поинтам плана.
- Уровень Тони C0 поднят владельцем с `low` до `medium` (2026-11-23).
- Единственный оставшийся разрыв (вне scope): на C5 Левша пишет на Kimi K3.1 Pro (46,5), ревью — qwen `medium` (< 42,9); митигируется детерминированными quality gates (phpunit/psalm/deptrac).

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-23 04:23:42 (1795407822) | Тимлид Алекс (pi) | Создание задачи |
