# Аналитический отчёт: доработка TRIZ research по Change Requests Локи

**Роль:** Аналитик Шерлок
**Дата:** 2026-08-12
**Объект:** `docs/research/methods/triz-method-research.md`, `todo/done/TASK-research-triz-method.todo.md`
**Задача:** [TASK-research-triz-method](../../../../todo/done/TASK-research-triz-method.todo.md)

---

## 1. Контекст

Архитектор Локи прислал Change Requests (запросы изменений) к research по реализации TRIZ-метода в `task-orchestrator`. Цель доработки — убрать преждевременные архитектурные утверждения и точнее отделить ближайший MVP от будущих feat-доработок.

Работа выполнена в текущей ветке `task/research-triz-method`; commit (коммит) и push (публикация ветки) не выполнялись.

## 2. Проверенные Change Requests

| CR | Решение | Где исправлено |
| --- | --- | --- |
| `tool` как ложный готовый gate | Убрана предпосылка, что `tool` уже подходит как conditional gate. MVP gate оставлен через `quality_gate`; условия по `tool` stdout / structured output вынесены в draft feat. | `docs/research/methods/triz-method-research.md` §6, §7, §8, §9, §10 |
| Phase 0 skill binding | Уточнено: одного каталога skill недостаточно. Нужна привязка `triz` в frontmatter целевых ролей (`skills:`) либо режим manual explicit invocation (ручной явный вызов). | §7, §8, §11, §12 |
| DynamicLoop и TRIZ-фазность | Снята предпосылка, что `max_rounds` гарантирует TRIZ-стадии. Добавлены TRIZ prompts и вариант feat `phase`/`round_goal`; без этого фазность остаётся static/conditional. | §6, §8, §10, §11 |
| Effort гибрида | Разделены три способа: ручные последовательные запуски S/M, wrapper command M, nested DSL L. | §7, §8 |
| Альтернативы и вердикт | Добавлены skill-first+eval, static-only, dynamic-only, external wrapper. Вердикт обновлён: implement Phase 0–1 now; full hybrid defer до eval/composition decision. | §7.1, §7.2, §12 |
| Evaluation | Разделено на harness skeleton M и behavioral blind A/B campaign L. | §8, §9 |
| Gates для Domain | Усилены условия старта Domain-модуля: invariants, stable I/O contract, audit model/API, правила, невыразимые chain/skill. | §7, §8, §11, §12 |

## 3. Re-review вывод

После доработки research больше не выдаёт будущие возможности за текущие primitives (примитивы):

- conditional chain в MVP использует `quality_gate` как pass/fail gate;
- `tool` может быть полезен как отдельный шаг, но условия по его stdout требуют отдельного дизайна;
- `DynamicLoop` рассматривается как генератор/ревизор концепций, а не как автомат стадий TRIZ;
- ближайшее решение ограничено Phase 0–1, а полный гибрид отложен до оценки качества и решения по composition.

## 4. Остаточные риски

- Skill-first подход не даст единого audit trail до появления YAML-chain или wrapper command.
- Static-only chain может быть слишком линейной для сильной дивергенции концепций.
- Nested DSL остаётся крупной архитектурной доработкой и не должен попадать в MVP.
- Native Domain-модуль без stable I/O contract и поведенческой оценки создаст риск overengineering (переусложнения).

## 5. Проверки

Проверки для docs-only доработки выполнены:

```bash
make md-links
make validate-todo
git diff --check
```

Результаты:

- `make md-links`: PASS, все внутренние ссылки валидны.
- `make validate-todo`: PASS, `todo/done/TASK-research-triz-method.todo.md` валиден, 0 ошибок, 0 предупреждений.
- `git diff --check`: PASS, whitespace (пробельных) ошибок нет.

PHPUnit и Psalm не запускались: изменения docs-only (только документация), код, конфигурация и скрипты не менялись.
