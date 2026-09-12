# Аналитический отчёт: постановка техдолга по PHAR runtime layout и кешу

**Роль:** Аналитик Шерлок
**Дата:** 2026-09-12
**Объект:** QA-3, FR-2/FR-4 и задача `TASK-fix-phar-runtime-layout-cache`
**Задача:** [`todo/backlog/TASK-fix-phar-runtime-layout-cache.todo.md`](../../../../todo/backlog/TASK-fix-phar-runtime-layout-cache.todo.md)

---

## Результат

Создана отдельная backlog-задача типа `fix` с приоритетом `P2`, зависимостью от `TASK-feat-phar-full-become-role-install` и будущим исполнителем `Бэкендер Левша (codex-cli)`. Текущая задача дополнена только follow-up-ссылкой в разделах рисков и комментариев; её выполненные критерии и scope не изменялись.

## Зафиксированный объём

- устранение заморозки `roles_dir`, `skills_dir` и `chains_yaml` в PHAR-контейнерном кеше;
- автоматическая безопасная очистка доказанно устаревших PHAR cache roots (корней кеша);
- защита текущих, активных, неизвестных и симлинковых каталогов;
- integration- и PHAR smoke-проверки исходного QA-3 и сценария перемещения A → B;
- документирование выбранной модели runtime layout и жизненного цикла кеша.

## Оценка

- `value: V2`: исправление повышает надёжность существующего вторичного PHAR-канала, но не блокирует основной канал дистрибуции.
- `complexity: C3`: решение затрагивает Symfony-контейнер, динамический выбор файловых путей, безопасное удаление, конкурентность и проверку реально собранного PHAR.

## Проверка

- `php vendor/bin/todo-md validate todo/backlog/TASK-fix-phar-runtime-layout-cache.todo.md` — успешно, 0 ошибок, 0 предупреждений.
- `php vendor/bin/todo-md validate todo/TASK-feat-phar-full-become-role-install.todo.md` — успешно, 0 ошибок, 0 предупреждений.
- `git diff --check` для двух задач — успешно.
