# Self-review отчёт: TRIZ research

**Роль:** Аналитик Шерлок
**Дата:** 2026-08-12
**Объект:** незакоммиченный diff ветки `task/research-triz-method`: `todo/done/TASK-research-triz-method.todo.md`, `docs/research/methods/triz-method-research.md`, `docs/agents/reports/system-analyst/2026-08-12_16-18_triz-method-research.md`
**Задача:** [TASK-research-triz-method](../../../../todo/done/TASK-research-triz-method.todo.md)

---

## Цель self-review

Проверить, что research-изменения по TRIZ соответствуют задаче, не содержат преждевременных архитектурных утверждений и опираются на реально существующие примитивы `task-orchestrator`.

## Проверенный scope

- Весь текущий незакоммиченный diff: `git diff` по `todo/done/TASK-research-triz-method.todo.md`.
- Новые файлы:
  - `docs/research/methods/triz-method-research.md`;
  - `docs/agents/reports/system-analyst/2026-08-12_16-18_triz-method-research.md`.
- Конвенция документации: [docs/conventions/doc-writing-rules.md](../../../conventions/doc-writing-rules.md).
- Реальные примитивы проекта:
  - `ChainTypeEnum` (`static`, `dynamic`, `conditional`);
  - `ChainStepTypeEnum` (`agent`, `quality_gate`, `tool`);
  - `ConditionalExecutionStrategyService` и `when:`-выражения;
  - `DynamicExecutionStrategy` / `DynamicLoop`;
  - `todo/done/EPIC-sprint-8-conditional-branching.md`.

## Результат проверки Must/Should/DoD

- Must 1–5 закрыты: суть TRIZ, архитектура `snow-ghost/triz`, маппинг, варианты реализации, вердикт.
- Should закрыты: фазированный план, draft feat-задач, интеграция с ролями и существующими возможностями.
- Could закрыт: Mermaid-диаграмма добавлена.
- Won't соблюдён: код реализации, бенчмарки и полный code review Python-исходников не выполнялись.
- DoD закрыт: research-отчёт создан, маппинг и сравнение вариантов есть, вердикт `implement` с фазированием зафиксирован.

## Найденные замечания и исправления

### 1. Уточнить маппинг условного ветвления

**Замечание:** исходная формулировка могла читаться так, будто текущий `task-orchestrator` уже поддерживает полноценный route selector (выбор маршрута) по смысловому выводу агента и вложенный `DynamicLoop` внутри YAML-chain.

**Факт по проекту:** текущий conditional-примитив — это шаговые `when:`-условия по `passed`, `exitCode`, `status` предыдущего именованного шага; операторы — `==` и `!=`. Текущие типы цепочек (`static`, `conditional`, `dynamic`) раздельны.

**Исправлено:** в `docs/research/methods/triz-method-research.md` явно указано, что:

- смысловой gate по тексту ответа агента не является готовым примитивом;
- после Change Requests Архитектора Локи формулировка уточнена: для MVP нужен deterministic gate (детерминированная проверка) через `quality_gate`; `tool`/output conditions требуют отдельной feat-доработки;
- гибрид chain + `DynamicLoop` означает композицию запусков или будущую доработку DSL, а не уже существующий вложенный dynamic step (динамический шаг).

### 2. Реалистичность фаз S/M/L

**Замечание:** фазы могли недооценивать работу по композиции static/conditional и dynamic частей.

**Исправлено:** в фазах 1–2 добавлены prerequisites (предпосылки):

- стабильный deterministic gate или структурированный routing output;
- решение о способе композиции: внешняя orchestration-команда, последовательные chain-запуски или новая feat-доработка DSL;
- текущая гранулярность условного ветвления — только шаговые `when:`-условия.

### 3. Русский язык и термины

**Замечание:** часть табличных полей была англоязычной без русской ведущей формулировки (`Default branch`, `Snapshot`, `Tag`).

**Исправлено:** поля заменены на русские с английским термином в скобках: «Основная ветка (default branch)», «Снимок (snapshot)», «Метка версии (tag)», «Дата коммита (commit date)».

### 4. Трассировка изменений в задаче

**Замечание:** после self-review нужно отразить уточнения в истории задачи.

**Исправлено:** в `todo/done/TASK-research-triz-method.todo.md` добавлена запись Change History о self-review и уточнении маппинга conditional/DynamicLoop.

## Проверка фактов и ссылок

- Commit snapshot `snow-ghost/triz` сверялся с сохранёнными GitHub API артефактами текущего исследования:
  - `tmp/research-triz/commit-main.json`;
  - `tmp/research-triz/tags.json`.
- Ключевые утверждения по `snow-ghost/triz` сверены с локально загруженным snapshot `tmp/research-triz/triz-main/triz-main/`.
- Прямой повторный `git ls-remote https://github.com/snow-ghost/triz.git` не удался из-за SSL-сбоя окружения, поэтому отчёт не утверждает, что snapshot актуален после даты обращения 2026-08-12.
- Внутренние ссылки проверены командой `make md-links`.

## Проверки

```bash
make md-links
make validate-todo
git diff --check
```

Результат:

- `make md-links`: зелёный, все внутренние ссылки валидны.
- `make validate-todo`: зелёный, `TASK-research-triz-method` валиден, 0 ошибок, 0 предупреждений.
- `git diff --check`: без замечаний.

Дополнительно запускался `make validate-language`: целевые файлы не попали в предупреждения; есть существующее предупреждение в несвязанном файле `docs/agents/team-retro/2026-08-03_20-20-orchestrator-tax-branch-protection-incident.md`.

PHPUnit и Psalm не запускались: изменения docs-only (только документация), код, конфигурация и скрипты не менялись.

## Итог

После self-review критических или блокирующих замечаний не осталось. Позже Архитектор Локи выдал дополнительные Change Requests; они закрыты отдельным re-review отчётом: [2026-08-12_16-53_triz-loki-cr-rereview.md](2026-08-12_16-53_triz-loki-cr-rereview.md). Актуальная позиция: `quality_gate` — единственный MVP-gate, `tool`/output conditions и полный hybrid composition — отдельные решения.
