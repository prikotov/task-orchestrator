# Self-review исследования Deep Agents Code / SDK

**Роль:** Аналитик (Шерлок)  
**Дата:** 2026-08-14  
**Объект:** `docs/research/coding-agents/deepagents-comparison.md`, `docs/research/coding-agents-summary.md`, `todo/done/TASK-research-deepagents.todo.md`, `todo/done/EPIC-research-coding-agents-comparison.md`
**Задача:** `todo/done/TASK-research-deepagents.todo.md`

---

## Проверки

- Полнота отчёта: все 10 критериев присутствуют и имеют оценку; есть итоговая сумма, pass-count (количество успешных критериев) и отдельные verdict (вердикты) для CLI (инструмента командной строки) и SDK (набора программных интерфейсов).
- CLI / SDK: различение сохранено — `Deep Agents Code CLI` оценён как частично пригодный CLI-сабагент, `Deep Agents SDK` отдельно отмечен как пригодный программный harness (каркас агента).
- Источники: в отчёте используются только первичные источники — GitHub, README/source из официального репозитория, PyPI и официальная документация LangChain.
- Дата/версии: зафиксированы дата анализа, commit snapshot (срез commit) и версии PyPI `deepagents` / `deepagents-code`.
- Сводка: Deep Agents добавлен в рейтинг и в детальную строку #21; счётчики пересчитаны на 21 исследование.
- Workflow (процесс задачи): задача переведена в `review`, так как PR уже указан; перенос в `todo/done/` и статус `done` остаются после approval (подтверждения) и перед merge (слиянием).

## Найденные и исправленные замечания

1. Ссылки на README/source в `deepagents-comparison.md` вели на `main`, хотя текст фиксировал commit snapshot. Исправлено на ссылки с commit `822f7c9b02e6d99bdb46b5545bb2543783c01769`; PyPI-ссылки заменены на version-specific (версии-зависимые) страницы.
2. В `coding-agents-summary.md` текстовый счётчик `Agent Skills standard` оставался `80%`, при таблице `17/21 (81%)`. Исправлено на `81%`.
3. `todo/done/TASK-research-deepagents.todo.md` имел статус `in_progress` при заполненном PR. Исправлено на `review`; чекбоксы выполненных пунктов и источников отмечены.

## Итог

После исправлений артефакты готовы к review (проверке). Commit, push и PR не выполнялись.
