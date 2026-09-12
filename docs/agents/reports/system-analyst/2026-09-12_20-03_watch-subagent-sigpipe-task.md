# Постановка задачи по `watch-subagent` exit `141`

**Роль:** Аналитик Шерлок
**Дата:** 2026-09-12
**Объект:** `watch-subagent.sh`, его integration-тесты, текущая PHAR-задача и реестр ретроспектив
**Задача:** [`TASK-fix-watch-subagent-sigpipe-exit-141`](../../../../todo/backlog/TASK-fix-watch-subagent-sigpipe-exit-141.todo.md)

---

## Подтверждённая проблема

`watch-subagent.sh` работает с `set -euo pipefail`. После штатного завершения сабагента `run.log` фиксирует `exit_code=0 reason=success_agent_end`, однако вычисление `max_gap` в `emit_run_summary()` использует `awk ... | sort -rn | head -1`. На большом `gaps.tsv` команда `head` досрочно закрывает канал, `sort` получает `SIGPIPE`, а обработчик `EXIT` обрывается и подменяет успешный внешний код на `141`. Итоговая сводка не достигает маркера `=== END SUMMARY ===`.

## Принятые решения

- Создана отдельная backlog-задача типа `fix` с приоритетом `P1`, ценностью `V3`, сложностью `C2` и исполнителем `Бэкендер Левша (codex-cli)`.
- Предпочтительное исправление — однопроходное вычисление максимума одним `awk` либо эквивалент без early-closing pipeline (конвейера с ранним закрытием).
- Проверка соседних диагностических конвейеров ограничена `EXIT`/cleanup-путями; исправлять разрешено только воспроизводимо опасные места.
- Регрессионный тест обязан использовать поддельный раннер, большой поток событий и проверять внутренний успех, внешний код `0`, полный маркер сводки и сохранённый вывод `text,files`.
- Таймауты, признаки успеха раннеров и общий watcher не входят в scope (границы работ).
- [`TASK-feat-phar-full-become-role-install`](../../../../todo/TASK-feat-phar-full-become-role-install.todo.md) указана только как источник наблюдения и follow-up (последующая работа), без функциональной зависимости.
- Запись реестра переведена из `watch` в `escalated`; счётчик сохранён равным `4`, новая ретроспектива не создавалась.

## Изменённые документы

- [`todo/backlog/TASK-fix-watch-subagent-sigpipe-exit-141.todo.md`](../../../../todo/backlog/TASK-fix-watch-subagent-sigpipe-exit-141.todo.md)
- [`todo/TASK-feat-phar-full-become-role-install.todo.md`](../../../../todo/TASK-feat-phar-full-become-role-install.todo.md)
- [`docs/agents/team-retro/RETRO-ROADMAP.md`](../../team-retro/RETRO-ROADMAP.md)

Реализация, ретроспектива, commit (фиксация изменений) и push (отправка изменений) не выполнялись.
