---
type: docs
created: 2026-11-10 06:21:27 (1794291687)
due: 
started: 2026-11-10 06:22:33 (1794291753)
completed: 2026-11-10 06:29:55 (1794292195)
cancelled: 
value: V2
complexity: C2
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Аналитик Шерлок (pi)
assignee: Аналитик Шерлок (pi)
consultants: []
reviewer: 
approver: 
informed: []
branch: task/pi-native-rendering-pi-1.5.1
pr: https://github.com/prikotov/task-orchestrator/pull/476
status: done
---

# TASK-docs-pi-native-rendering-pi-1-5-1: Актуализировать скилл pi-native-rendering под pi 1.5.1

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- Скилл `pi-native-rendering` запрещал типы диаграмм, которые pi 1.5.1 уже рисует (`pie`, `gitGraph`, `mindmap`), и не упоминал новые (`timeline`) — агенты избегают полезных диаграмм или дают исходник вместо живой схемы.
- В скиллах нет правил кликабельных ссылок: агенты выдают пути обычным текстом или относительными ссылками, и терминал при клике показывает ошибку «Не удалось распознать схему URI».

### Варианты или путь решения (Solution Sketch)
- Сверить текст скилла с фактическими возможностями рендерера `lovely-mermaid@0.3.3` и OSC 8-поведением pi; переписать списки типов диаграмм и добавить блок «Кликабельность».
- Форматы ссылок проверить эмпирической клик-матрицей в ghostty, а не только по коду.

### Ожидаемый результат (Expected Result)
- Роли, подключающие скилл, свободно используют диаграммы новых типов и всегда дают кликабельные ссылки; клики по путям в ответах открывают файлы без ошибок терминала.

## 1. Концепция и Цель (Concept and Goal)

### История (User Story)
> **User Story:** Как AI-агент команды, я хочу актуальный скилл рендеринга pi, чтобы мои диаграммы отображались в терминале, а пути и ссылки в ответах открывались по клику.

### Цель по SMART (Goal)
К 2026-11-10 текст `docs/agents/skills/pi-native-rendering/SKILL.md` соответствует pi 1.5.1: перечислены 9 рисуемых типов диаграмм, запрет перенесён на 4 нерисуемых, есть блок «Кликабельность» с эталоном `file://`/`https://` и результатами клик-матрицы (6 форматов).

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `docs/agents/skills/pi-native-rendering/SKILL.md`; отчёт анализа — `docs/agents/reports/system-analyst/2026-11-10_12-52_pi-native-rendering-pi-1-5-1.md`
*   **Текущее поведение:** скилл запрещает `pie/gitGraph/mindmap` (в pi 1.5.1 они рисуются), правил кликабельности нет
*   **Прецеденты для переиспользования:** нет (пионерная актуализация этого скилла)
*   **Критичные конвенции:** [SKILL-CREATION.md](../../docs/agents/skills/SKILL-CREATION.md) — стандарт оформления навыков
*   **Границы (Out of Scope):** файлы ролей (подключают скилл по ссылке), `docs/guide/diagrams.md` (GitHub-рендеринг), код и тесты, поддержка безсхемных путей в ответах

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] Список рисуемых типов соответствует `lovely-mermaid@0.3.3`: flowchart/graph, sequence, state, class, er, pie, mindmap, timeline, gitGraph
- [x] Запрет перенесён на нерисуемые типы: gantt, journey, quadrantChart, sankey
- [x] Блок «Кликабельность»: эталон `[подпись](<file:///полный/путь>)` и `[подпись](https://…)`, отказ для голых/относительных/тильда-путей, пунктуация за закрывающей скобкой
- [x] `description` скилла отражает кликабельные ссылки (OSC 8)
- [x] Отчёт анализа сохранён в `docs/agents/reports/system-analyst/`

### ⚫ Won't Have (Не будем делать)
- Правки файлов ролей и `docs/guide/diagrams.md`
- Рекомендация безсхемных путей (`/абсолютный/путь` — работает только в ghostty, вне стандарта OSC 8)

## 4. План реализации (Implementation Plan)
1. [x] Анализ changelog pi 1.5.1 и исходников рендерера (`lovely-mermaid`, `pi-tui`)
2. [x] Клик-матрица форматов ссылок в ghostty (6 вариантов)
3. [x] Правка `SKILL.md` (типы диаграмм + кликабельность + description)
4. [x] Отчёт анализа с матрицей результатов
5. [ ] Задача, коммит, push, PR с меткой `pi`

## 5. Критерии приёмки (Definition of Done)
- [x] `php vendor/bin/todo-md validate` проходит
- [x] В скилле не осталось устаревших формулировок (grep по «gantt/pie/gitGraph/mindmap НЕ рисуются»)
- [x] Проверки кода пропущены обоснованно: изменения только в документации (исключение №1 раздела «Предварительные проверки» `AGENTS.md`)
- [ ] PR создан, CI зелёный, метка `pi` установлена

## 6. Самопроверка (Verification)
```bash
php vendor/bin/todo-md validate
grep -n "gantt" docs/agents/skills/pi-native-rendering/SKILL.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- Клик-матрица снята в ghostty: поведение безсхемных путей в других терминалах может отличаться, поэтому эталоном в скилле оставлен спецификационный `file://`, а `/абсолютный/путь` явно запрещён.

## 8. Источники (Sources)
- [x] [Отчёт анализа](../../docs/agents/reports/system-analyst/2026-11-10_12-52_pi-native-rendering-pi-1-5-1.md)
- [x] Changelog pi 1.5.1 (локальный пакет `@earendil-works/pi-coding-agent`)
- [x] [PR #8158](https://github.com/earendil-works/pi/pull/8158) — upgraded terminal Mermaid rendering

## 9. Комментарии (Comments)
- Правка инициирована выходом pi 1.5.1 («Richer Mermaid diagrams») и вопросом пользователя о нестабильной кликабельности путей/ссылок.

## История изменений (Change History)
| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-10 06:21:27 (1794291687) | Аналитик Шерлок (pi) | Создание задачи |
| 2026-11-10 06:25:00 (1794291900) | Аналитик Шерлок (pi) | Заполнение постановки: Human Brief, scope, MoSCoW, план, критерии |
