---
type: chore
created: 2026-09-24 01:32:10 (1790213530)
due: 
started: 2026-09-24 01:32:11 (1790213531)
completed: 2026-09-24 01:32:48 (1790213568)
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Бэкендер Тони (pi)
assignee: Бэкендер Тони (pi)
branch: task/codex-gpt6-profiles
pr: https://github.com/prikotov/task-orchestrator/pull/408
status: done
---

# TASK-chore-zai-glm-thinking: Уровни рассуждения для профилей zai glm и flash для Гермионы

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Четыре роли на `pi + zai` (`glm-5.3`) запускаются без уровня рассуждения — раннер выбирает умолчание провайдера; доступная лёгкая модель `glm-5.3-flash` не используется ни для одной роли.

### Варианты или путь решения (Solution Sketch)
- Прописать `--thinking` по назначению роли (как уже сделано для codex-профилей в TASK-chore-codex-gpt6-profiles): глубокий — реализации и ревью, средний — QA и документации; документацию перевести на быстрый `glm-5.3-flash`.

### Ожидаемый результат (Expected Result)
- Все профили `pi + zai` имеют явный уровень рассуждения; технический писатель работает на быстрой flash-модели; конфигурация проходит проверки проекта.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как владелец оркестратора цепочек, я хочу, чтобы роли на `pi + zai` имели явные уровни рассуждения по назначению роли и использовали быструю flash-модель для документации, чтобы балансировать качество, скорость и стоимость запусков.

### Цель по SMART (Goal)
- В `config/chains.yaml` для четырёх активных `pi + zai`-команд:
  - `backend_developer_levsha` → `glm-5.3`, `--thinking high`;
  - `code_reviewer_backend_puaro` → `glm-5.3`, `--thinking high`;
  - `qa_backend_house` → `glm-5.3`, `--thinking medium`;
  - `technical_writer_hermione` → `glm-5.3-flash`, `--thinking medium`.
- PHPUnit и Psalm — зелёные.

## 2. Контекст и Границы (Context and Scope)
* **Где делаем:** `config/chains.yaml` (только активные pi+zai-команды четырёх ролей).
* **Границы (Out of Scope):** закомментированные альтернативы; codex-профили (закрыты задачей TASK-chore-codex-gpt6-profiles); профиль `system_analyst_sherlock` (`pi + openai-codex`); цепочки (`chains:`) и документация.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] `backend_developer_levsha`: добавлен `--thinking high`.
- [x] `code_reviewer_backend_puaro`: добавлен `--thinking high`.
- [x] `qa_backend_house`: добавлен `--thinking medium`.
- [x] `technical_writer_hermione`: модель `glm-5.3` → `glm-5.3-flash`, добавлен `--thinking medium`.
- [x] Закомментированные блоки и прочие профили не изменены.
- [x] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные.
### ⚫ Won't Have (Не будем делать)
- Изменение codex-профилей, цепочек, прокси-настроек, документации; перевод других ролей на `glm-5.3-highspeed`.

## 4. План реализации (Implementation Plan)
1. [x] Подтверждение доступности моделей: `pi --list-models "glm-5.3" --provider zai` (glm-5.3, glm-5.3-flash, glm-5.3-highspeed — все с поддержкой рассуждения).
2. [x] Точечная правка четырёх активных `pi + zai`-команд в `config/chains.yaml` (флаг `--thinking` по образцу активной команды `system_analyst_sherlock`).
3. [x] `vendor/bin/phpunit`, `vendor/bin/psalm`.
4. [x] Пуш в ветку PR #408 по регламенту.

## 5. Критерии приёмки (Definition of Done)
- [x] Четыре профиля `pi + zai` — с явными уровнями рассуждения; Гермиона — на `glm-5.3-flash` (8 добавленных строк в `config/chains.yaml`, 1 изменённая).
- [x] PHPUnit: OK (1572 теста, 4448 утверждений; 1 предсуществующее устаревание вне зоны правки, 2 skipped).
- [x] Psalm: ошибок нет.
- [x] Задача в `done`, изменения в ветке PR #408.

## 6. Самопроверка (Verification)
```bash
grep -n -A 1 "glm-5.3\|--thinking" config/chains.yaml
php vendor/bin/todo-md validate todo/TASK-chore-zai-glm-thinking.todo.md
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Риски и зависимости (Risks и Dependencies)
- Выбор уровней — конфигурационное решение (продолжение подхода TASK-chore-codex-gpt6-profiles), не утверждение о превосходстве моделей; откат — точечная правка YAML.
- `glm-5.3-flash` подтверждён в реестре моделей провайдера zai (`pi --list-models`), поддержка рассуждения — да; живой запуск ролей — вне рамок задачи.

## 8. Источники (Sources)

## 9. Комментарии (Comments)
- `technical_writer_hermione` не участвует ни в одной активной цепочке (`implement`, `task-implement`, `analyze`, `hotfix`), поэтому перевод на flash не влияет на текущие цепочки.
- Флаг `--thinking` размещён по образцу активной команды `system_analyst_sherlock` (после `--model`, до `--system-prompt`).

## История изменений (Change History)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-09-24 01:32:10 (1790213530) | Бэкендер Тони (pi) | Создание задачи |
| 2026-09-24 01:33:00 (1790213580) | Бэкендер Тони (pi) | Старт задачи, заполнение постановки |
| 2026-09-24 01:40:00 (1790214000) | Бэкендер Тони (pi) | Правка четырёх профилей, phpunit+psalm зелёные |
| 2026-09-24 01:45:00 (1790214300) | Бэкендер Тони (pi) | Изменения в PR #408, CI ожидается, задача закрыта |
