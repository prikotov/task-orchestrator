---
# Metadata (Метаданные)
type: docs
created: 2026-04-27
value: V2
complexity: C1
priority: P1
depends_on:
epic: EPIC-feat-brainstorm-improvements
author: Тимлид (Алекс) (pi)
assignee: Тех. писатель Гермиона (pi)
branch: task/brainstorm-role-expertise
pr: #68
status: done
---

# TASK-feat-brainstorm-role-expertise: Зоны ответственности ролей в front matter файлов ролей

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как фасилитатор brainstorm, я хочу знать, какую экспертизу несёт каждая роль, чтобы направлять вопросы адресно: за оценкой стоимости — к Левше, за альтернативами — к Локи, за проверкой логики — к Шерлоку.

### Goal (Цель по SMART)
Добавить поле `brainstorm_expertise` (или аналогичное) в front matter каждого файла роли (`docs/agents/roles/team/*.ru.md`), содержащее краткое описание зоны ответственности роли в brainstorm. Фасилитатор читает это поле при загрузке роли.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `docs/agents/roles/team/system_architect_gandalf.ru.md` — front matter
    *   `docs/agents/roles/team/system_architect_loki.ru.md` — front matter
    *   `docs/agents/roles/team/backend_developer_levsha.ru.md` — front matter
    *   `docs/agents/roles/team/backend_developer_tony.ru.md` — front matter
    *   `docs/agents/roles/team/system_analyst_sherlock.ru.md` — front matter
    *   `docs/agents/roles/team/qa_backend_house.ru.md` — front matter
    *   `docs/agents/roles/team/code_reviewer_backend_puaro.ru.md` — front matter
    *   `docs/agents/roles/team/technical_writer_hermione.ru.md` — front matter
    *   `docs/agents/roles/team/technical_writer_ostap.ru.md` — front matter
    *   `docs/agents/roles/team/team_lead_alex.ru.md` — front matter
*   **Текущее поведение:** Файлы ролей имеют front matter с полями `name`, `description`, возможно другими. Нет поля, описывающего зону ответственности в brainstorm.
*   **Границы (Out of Scope):**
    *   Не меняем контент ролей (только front matter)
    *   Не меняем код, который загружает роли (если он не читает front matter — это не блокер, фасилитатор прочитает файл роли целиком)
    *   Не меняем промпт фасилитатора (он уже получает файл роли через participant_append.txt)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] Каждая роль имеет поле `brainstorm_expertise` в front matter с кратким описанием (1-2 предложения)
- [x] Описание сфокусировано на том, **какие вопросы** направлять роли, а не на общем описании роли
- [ ] Предлагаемый набор зон:
  - `system_architect_gandalf`: «Стратегическая архитектура, DDD-границы, bounded contexts, долгосрочные решения. Направлять вопросы: как это влияет на архитектуру в целом? какие долгосрочные последствия?»
  - `system_architect_loki`: «Альтернативные решения, поиск слепых зон, критика текущего плана. Направлять вопросы: какие альтернативы? что мы упускаем? где слабые места?»
  - `backend_developer_levsha`: «Оценка реализуемости, стоимость в часах, риски реализации, практические ограничения. Направлять вопросы: сколько это стоит? какие риски при реализации? какие есть технические ограничения?»
  - `backend_developer_tony`: «Простые решения, упрощение сложных предложений, скорость реализации. Направлять вопросы: как сделать проще? что можно выкинуть?»
  - `system_analyst_sherlock`: «Требования, контракты, критерии приёмки, проверка логики аргументов. Направлять вопросы: какие требования? есть ли дыры в логике? как проверить?»
  - `qa_backend_house`: «Тестопригодность решений, покрытие тестами, edge cases. Направлять вопросы: как это протестировать? какие edge cases?»
  - `code_reviewer_backend_puaro`: «Качество кода, безопасность, соответствие конвенциям. Направлять вопросы: соответствует ли конвенциям? есть ли риски безопасности?»
  - `technical_writer_hermione`: «Документация, контракты, описание API. Направлять вопросы: как это задокументировать?»
  - `technical_writer_ostap`: «Упрощение документации, удаление лишнего. Направлять вопросы: что можно выкинуть из документации?»
  - `team_lead_alex`: «Фасилитатор — не выступает как участник»
### 🟡 Should Have (Желательно)
- [ ] В `facilitator_append.txt` добавлена ссылка: «Используй поле brainstorm_expertise из файла роли для выбора участника»
### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- [ ] Программная маршрутизация на основе expertise (фасилитатор решает сам)

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
1. [ ] Прочитать front matter каждого файла роли
2. [ ] Добавить поле `brainstorm_expertise` в каждый файл
3. [x] При необходимости обновить `facilitator_append.txt` ссылкой на expertise

## 5. Definition of Done (Критерии приёмки)
- [x] Все 10 файлов ролей содержат `brainstorm_expertise` в front matter
- [x] Описания уникальны для каждой роли (не дублируются)
- [x] Изменения — только docs, проверки не требуются

## 6. Verification (Самопроверка)
```bash
# Проверить, что все файлы ролей содержат brainstorm_expertise
grep -l "brainstorm_expertise" docs/agents/roles/team/*.ru.md | wc -l
# Должно быть 10
```

## 7. Risks and Dependencies (Риски и зависимости)
- Если front matter файлов ролей не поддерживает произвольные поля — может потребоваться согласование с кодом, который парсит front matter. Но поскольку фасилитатор (LLM) читает файл роли целиком — поле будет видно в любом случае.

## 8. Sources (Источники)
- [ ] [Файлы ролей](../../docs/agents/roles/team/)
- [ ] [Ретроспектива — P4: Левша недоиспользован, фасилитатор не понимает зоны ответственности](../../var/sessions/brainstorm/2026-04-27_06-46-57/discussion_history.md)

## 9. Comments (Комментарии)
Задача — docs-only. Проверки PHPUnit/Psalm можно пропустить.

## Инструкции для сабагента

**Ветка:** `task/brainstorm-role-expertise` (уже создана и активна)
**PR:** уже создан (draft) из `task/brainstorm-role-expertise` в `feat/brainstorm-improvements`

### Порядок действий
1. Переключись в ветку `task/brainstorm-role-expertise`: `git checkout task/brainstorm-role-expertise`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-27 | Тимлид (Алекс) (pi) | Создание задачи |
