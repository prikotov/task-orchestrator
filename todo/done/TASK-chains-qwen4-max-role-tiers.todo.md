---
type: chore
created: 2026-11-27 12:36:14 (1795782974)
due:
started: 2026-11-27 12:36:35 (1795782995)
completed: 2026-11-27 12:47:57 (1795783677)
cancelled:
value: V2
complexity: C1
priority: P1
cost_plan:
cost_fact:
depends_on:
epic:
author: Тимлид Алекс (pi@qwen4-max~medium)
assignee: Бэкендер Тони (pi@glm-5.3-flash~high)
consultants: []
reviewer: Ревьювер Бэка Пуаро (pi@deepseek-v4.1-flash-1017~max)
approver:
informed: []
branch: task/qwen4-max-switch-role-tiers
pr: https://github.com/prikotov/task-orchestrator/pull/519
status: done
---

# TASK-chains-qwen4-max-role-tiers: qwen4-max вместо preview и тир-схемы Пуаро/Тони

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Модель `qwen4-max-preview` убрана из подписки Alibaba Cloud (Token Plan) — команды ролей в `config/chains.yaml`, ссылающиеся на неё, перестанут работать при ближайшем запуске (сейчас это ревьювер Пуаро на всех уровнях и Тони на C0). Кроме того, распределение моделей по ролям несбалансировано: Пуаро расходует самую дорогую квоту плана (у `qwen4-max` нет скидки, сброс квоты 10-го числа) на ревью любой сложности, а у Тони качество инвертировано относительно сложности — простейшие задачи (C0–C1) получают сильнейшую модель (II 42,9), сложнейшие (C4–C5) — слабейшую (GLM-5.3 Flash, II 27,8).

### Варианты или путь решения (Solution Sketch)
По метрикам AA Intelligence Index v5.0.1 и кодинг-агентных бенчмарков (срез 2026-11-22, дашборд `~/MyProjects/dashboards/models/`) построить тир-схемы: Пуаро — дешёвые профили на тривиальных и средних ревью (`glm_flash_high`, новый `deepseek_flash_1017_max`), Qwen4 только на тяжёлых (C4 medium, C5 xhigh); Тони — `glm_flash_high` на базе и C0, Kimi K3.1 Pro (`kimi_high`) на C4–C5. Заменить `qwen4-max-preview` на `qwen4-max` (модель уже закреплена в локальном pi-конфиге).

### Ожидаемый результат (Expected Result)
Все роли работают на моделях, доступных в действующих подписках; самая дорогая квота (Qwen4 Token Plan) тратится только на тяжёлые ревью; сложные задачи разработки получают сильную модель (Kimi, II 46,5); конфиг валиден, проверки зелёные.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как владелец проекта, я хочу, чтобы командные профили ролей ссылались на актуальные модели подписки Alibaba Cloud и распределяли дорогую квоту по сложности задач, чтобы оркестрация не падала из-за убранной preview-модели и не сжигала квоту плана на тривиальных шагах.

### Цель по SMART (Goal)
В `config/chains.yaml`: заменить модель профиля `qwen_max_medium` на `qwen4-max`; добавить профили `qwen_max_xhigh` (qwen4-max, thinking xhigh) и `deepseek_flash_1017_max` (deepseek-v4.1-flash-1017, thinking max); ревьюверу Пуаро задать тир-схему C0 `glm_flash_high` / база `deepseek_flash_1017_max` / C4 `qwen_max_medium` / C5 `qwen_max_xhigh`; Тони — база `glm_flash_max` (неявный уровень C3), тиры C0 `glm_flash_high`, C2 `glm_flash_high`, C4 `glm_flash_max`, C5 `kimi_high`. Проверка: `todo-md validate`, `lint:yaml`, `agent:orchestrate --validate-config`, PHPUnit, Psalm — зелёные.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `config/chains.yaml` (секции `command_profiles`, `roles.code_reviewer_backend_puaro`, `roles.backend_developer_tony`)
* **Текущее поведение:** `qwen_max_medium` → `qwen4-max-preview`; Пуаро — `qwen_max_medium` на всех уровнях; Тони — C0 `qwen_max_medium`, C5 `glm_flash_max`
* **Прецеденты для переиспользования:** тир-схемы ролей Левши и Тони в том же файле; механика выбора уровней — `docs/guide/roles.md` (база = C3, точный уровень или ближайший настроенный выше)
* **Критичные конвенции:** слоистая архитектура не затрагивается; правка ограничена конфигурацией цепей
* **Границы (Out of Scope):** остальные роли и профили; исторические записи (ретро, research); pi-конфиг провайдера (`~/.pi/agent/models.json` — `qwen4-max` уже закреплён); дашборд моделей (другой репозиторий)

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Профиль `qwen_max_medium`: модель `qwen4-max-preview` → `qwen4-max`
- [x] Новый профиль `qwen_max_xhigh`: qwen4-max, `--thinking xhigh`
- [x] Новый профиль `deepseek_flash_1017_max`: провайдер `qwen-token-plan-individual`, модель `deepseek-v4.1-flash-1017`, `--thinking max`
- [x] Пуаро: база `deepseek_flash_1017_max`, тиры C0 `glm_flash_high`, C4 `qwen_max_medium`, C5 `qwen_max_xhigh`
- [x] Тони: база `glm_flash_max` (неявный C3), тиры C0 `glm_flash_high`, C2 `glm_flash_high`, C4 `glm_flash_max`, C5 `kimi_high` (резолвинг C0–C2 → glm_flash_high, C3–C4 → glm_flash_max, C5 → kimi_high)
- [x] PHPUnit и Psalm зелёные
### ⚫ Won't Have (Не будем делать)
- Изменение остальных ролей, промптов и базовых профилей
- Правка локального pi-конфига провайдера и исторических документов
- Смена runner'ов (все профили остаются на pi)

## 4. План реализации (Implementation Plan)
1. [x] Анализ метрик дашборда моделей и баланса квот подписок (Тимлид, решение согласовано с владельцем)
2. [x] Правка `config/chains.yaml` (Бэкендер Тони, сабагент)
3. [x] PHPUnit + Psalm (Бэкендер Тони, повторено Тимлидом)
4. [x] Ревью (Ревьювер Бэка Пуаро, сабагент) — APPROVE
5. [x] Доработка замечаний ревью S1–S2: актуализация комментариев про thinking-уровни (Бэкендер Тони, сабагент)
6. [x] Повторные проверки, коммит, push токеном бота, PR

## 5. Критерии приёмки (Definition of Done)
- [x] `config/chains.yaml` не содержит `qwen4-max-preview`; новые профили резолвятся для уровней C0–C5 обеих ролей
- [x] `php vendor/bin/todo-md validate` — без ошибок
- [x] PHPUnit: OK; Psalm: без ошибок
- [x] Ревью Пуаро: APPROVE, замечания S1–S2 устранены
- [ ] PR создан с меткой агента, CI зелёный

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
vendor/bin/phpunit
vendor/bin/psalm
php bin/console lint:yaml config/chains.yaml
php bin/console agent:orchestrate --validate-config
```

## 7. Риски и зависимости (Risks and Dependencies)
- Локальный pi-override (~/.pi/agent/models.json) закрепляет `medium` для qwen4-max; в upstream-каталоге pi `medium → null` — на средах без override уровень клампнется в `high` (запуск не ломается, расход квоты выше); на целевой среде конфиг совпадает с запланированным
- Нет smoke-запуска C4/C5 профилей на целевой среде до PR (проверка — первый реальный запуск, метрики снять в audit.jsonl)

## 8. Источники (Sources)
- [ ] Дашборд моделей AA v5.0.1: `~/MyProjects/dashboards/models/index.html` (срез 2026-11-22)
- [ ] `docs/guide/roles.md` — семантика `commands_by_complexity`
- [ ] `docs/guide/agent-identity.md` — рецепт push PR-веток токеном бота

## 9. Комментарии (Comments)
- Обоснование выбора моделей (без ca-индекса — он измерен в связке с чужими упряжками): II v5 Kimi 46,5 > Qwen4 42,9 > DeepSeek 1017 32,6 > GLM-5.3 Flash 27,8; ca в аргументации не используется
- Nit ревью N1 (имя `deepseek_1017_max` vs `deepseek_flash_1017_max`): принят — профиль переименован в `deepseek_flash_1017_max` по прямому указанию владельца (семейство flash vs pro должно читаться из имени); конвенции именования профилей — кандидат в отдельную задачу техдолга
- Nit ревью N2 (избыточный пин C0 у Тони) отклонён: явный пин — часть согласованной постановки

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-27 12:36:14 (1795782974) | Тимлид Алекс (pi@qwen4-max~medium) | Создание задачи |
| 2026-11-27 12:36:35 (1795782995) | Тимлид Алекс (pi@qwen4-max~medium) | Переход в in_progress, назначение исполнителя и ревьювера |
| 2026-11-27 20:02:08 (1795784528) | Тимлид Алекс (pi@qwen4-max~medium) | Переименование профиля deepseek_1017_max → deepseek_flash_1017_max по указанию владельца |
| 2026-11-27 20:59:32 (1795787972) | Тимлид Алекс (pi@qwen4-max~medium) | Тир-схема Тони: C3–C4 подняты до glm_flash_max по указанию владельца |
