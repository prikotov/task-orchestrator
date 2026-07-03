---
type: fix
created: 2026-07-03
value: V2
complexity: C2
priority: P1
depends_on:
epic:
author: Тимлид Алекс
assignee: Тимлид Алекс
branch: task/fix-watch-subagent-tmp-gc
pr:
status: in_progress
---

# TASK-fix-watch-subagent-tmp-gc: очистка осиротевших TMPDIR и сам-уборка watcher'а

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
`watch-subagent.sh` теряет TMPDIR при убийстве `SIGKILL`/OOM/закрытии терминала:
trap cleanup не отрабатывает в этих случаях. В `/tmp` накопилось 4.5 ГБ осиротевших
каталогов от 26 мёртвых прогонов.

### Варианты или путь решения (Solution Sketch)
Три слоя: (1) startup-GC удаляет осиротевшие `/tmp/tmp.*/` старше 6ч без держателя;
(2) фоновый watcher сам прибирает TMPDIR, если родитель убит; (3) `tmpdir=` в run.log
для диагностики.

### Ожидаемый результат (Expected Result)
TMPDIR больше не копится в `/tmp` после убийства процессов. Startup-GC покрыт
интеграционным тестом, `make check` зелёный.

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)
Когда запуск watch-subagent убит `SIGKILL`/OOM/закрытием терминала (trap cleanup не отрабатывает),
я хочу, чтобы осиротевшие TMPDIR в `/tmp` удалялись автоматически — чтобы `/tmp` не разрастался
до гигабайт (зафиксировано 4.5 ГБ мусора от 26 мёртвых прогонов).

### Goal (Цель по SMART)
Добавить в `watch-subagent.sh`: (1) startup-GC — удаление осиротевших TMPDIR старше 6ч без живого
держателя; (2) сам-уборку watcher'а, если родитель убит; (3) диагностику `tmpdir=` в run.log.
Покрыть startup-GC интеграционным тестом; проверки `phpunit`/`psalm`/`make check` зелёные.

## 2. Context and Scope (Контекст и Границы)

- **Где делаем:** `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`, `SKILL.md` того же скилла,
  `tests/Integration/Docs/Agents/Skills/RunSubagent/WatchSubagentScriptTest.php`.
- **Текущее поведение:** trap cleanup не срабатывает на SIGKILL/OOM → TMPDIR утекает; накоплено 4.5 ГБ.
- **Границы (Out of Scope):** stall-timeout (логическое зависание «молчание») — отдельная задача
  (см. `cancelled/TASK-fix-watch-subagent-logical-stall`), здесь не трогаем.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] `gc_tmpdir()`: при старте удаляет `/tmp/tmp.*/` с маркером `events.pipe`/`events.ndjson`,
      старше `WATCH_TMP_GC_MIN_AGE_MIN` (360) и без держателя (`lsof`).
- [ ] `_gc_tmpdir_has_holder()` + watcher orphan self-cleanup (`kill -0 $$` → rm TMPDIR).
- [ ] Документация env `WATCH_TMP_GC`/`WATCH_TMP_GC_MIN_AGE_MIN` в шапке скрипта и `SKILL.md`.
- [ ] Интеграционный тест `gcTmpdirRemovesOrphanedTmpdirsButKeepsFresh`.

### ⚫ Won't Have (Не будем делать)
- Не добавляем детектор логического зависания (Pattern A «молчание») — отдельная задача.

## 4. Implementation Plan (План реализации)
1. [x] Реализовать `gc_tmpdir` + `_gc_tmpdir_has_holder` + вызов перед `mktemp -d`.
2. [x] Watcher orphan self-cleanup + `log_run "tmpdir=$TMPDIR"`.
3. [x] Фикс регрессии: `shopt -p nullglob` под `set -e` → `shopt -q` (exit-статус query).
4. [x] Документация + интеграционный тест.

## 5. Definition of Done (Критерии приёмки)
- [x] Startup-GC удаляет осиротевший TMPDIR, не трогает свежий и с-держателем.
- [x] `bash -n` OK, `make check` зелёный.
- [x] Регрессия `shopt -p` покрыта тестом.

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit --filter gcTmpdir tests/Integration/Docs/Agents/Skills/RunSubagent/
make check
```

## 7. Risks and Dependencies (Риски и зависимости)
- Orphan-watcher self-cleanup покрыт ручной сверкой + `bash -n` (end-to-end требует убийства реального прогона).
- `shopt -p NAME` под `set -e` возвращает exit-статус query (1 если off) — типичная bash-ловушка.

## 8. Sources (Источники)
- `docs/agents/skills/run-subagent/scripts/watch-subagent.sh` (trap cleanup, watcher).
- bash manual: `shopt` exit status.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-03 | Тимлид Алекс | Создание задачи после очистки 4.5 ГБ осиротевших TMPDIR и обнаружения корня (trap ≠ SIGKILL). |
