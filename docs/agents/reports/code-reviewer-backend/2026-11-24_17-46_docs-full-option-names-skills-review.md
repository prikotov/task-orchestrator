# Ревью: уход от кратких ключей CLI в документации скиллов (docs-full-option-names-skills)

**Роль:** Ревьювер Бэка Пуаро (code_reviewer_backend_puaro)
**Дата:** 2026-11-24
**Объект:** незакоммиченные изменения ветки `task/docs-full-option-names-skills` — `docs/agents/skills/run-subagent/SKILL.md`, `docs/agents/skills/run-subagent/README.md`, `docs/agents/skills/task-via-subagents/SKILL.md`, `docs/agents/skills/epic-via-subagents/SKILL.md` плюс 2 санкционированных фикса ссылок (`docs/agents/reports/code-reviewer-backend/2026-11-24_11-59_docs-subagent-timeout-calibration-review.md`, `docs/agents/team-retro/RETRO-ROADMAP.md`), diff 54+/54−; а также закоммиченные `95fd434d` (профиль Гермионы в `config/chains.yaml`) и `1974a57a` (поправка постановки)
**Задача:** [TASK-docs-full-option-names-skills](../../../../todo/done/TASK-docs-full-option-names-skills.todo.md)
**Тип прохода:** RV — проверка связей и актуальности для docs-задачи (соответствие постановке, факты, ссылки, границы scope)

---

## Вердикт

**APPROVED.** Блокирующих замечаний нет. Дифф точно соответствует постановке, границы scope соблюдены. Два неблокирующих наблюдения по предсуществующему долгу — ниже, отдельной задачей.

## Проверки

### 1. Актуальность полных имён опций — ✅

Сверено с исходником `docs/agents/skills/run-subagent/scripts/watch-subagent.sh` (парсер аргументов, строки 147–151):

| Имя в документации | Строка парсера скрипта | Совпадение |
|---|---|---|
| `--soft-timeout` | 147: `-s\|--soft-timeout)` | ✅ |
| `--hard-timeout` | 148: `-m\|--hard-timeout)` | ✅ |
| `--stall-timeout` | 149: `-t\|--stall-timeout)` | ✅ |
| `--output` | 150: `-o\|--output)` | ✅ |
| `--role-file` | 151: `-r\|--role-file)` | ✅ |

Скрипт не менялся: `git diff HEAD -- …/watch-subagent.sh` пуст, последние коммиты скрипта (`2a816d55` и ранее) не входят в эту ветку.Won't Have соблюдён.

### 2. Связность: отсутствие кратких ключей — ✅

Контрольный grep из раздела 6 постановки по четырём целевым файлам:

```bash
grep -rnE '(^|[^[:alnum:]-])-(s|m|t|o|r)([^[:alnum:]-]|$)' \
  docs/agents/skills/run-subagent/SKILL.md docs/agents/skills/run-subagent/README.md \
  docs/agents/skills/task-via-subagents/SKILL.md docs/agents/skills/epic-via-subagents/SKILL.md
# вывод пуст, exit 1
```

Дополнительный grep по `` `-s` ``/`` `-m` ``/`` `-t` ``/`` `-o` ``/`` `-r` `` и ` -s ` — тоже пусто.

Отдельно оценена замена в `run-subagent/README.md` (механизм stall): было `while IFS= read -r -t "$STALL_TIMEOUT"`, стало «в теле цикла чтения с опцией таймаута `read`». Это ключи встроенной команды bash `read`, а не CLI скрипта, — ложное срабатывание grep-паттерна (предвидено риском 1 постановки). Переформулировка сохраняет смысл механизма (stall через опцию таймаута `read`) и необходима для пустого grep. Признаю обоснованным отклонением от «дословности» Must-критерия; точность описания не потеряна.

### 3. Согласованность смежных документов и фиксы ссылок — ✅

- Оба фикса ведут на существующий `todo/done/TASK-docs-subagent-timeout-calibration.todo.md`; относительные пути (`../../../../todo/done/…` из отчёта, `../../../todo/done/…` из RETRO-ROADMAP) резолвятся в корень проекта корректно.
- В фиксах изменён только путь ссылки; исторический нарратив RETRO-ROADMAP (включая упоминания `-s 1800`, `-s 600` как факты прошлых запусков) не тронут — правильно, это история, а не инструкция.
- `make md-links` — ✅ «All internal links valid», все проверки зелёные.
- Взаимные ссылки SKILL.md ↔ README.md run-subagent смысл не потеряли: таблица SKILL (3 колонки) и полная справка README (3 колонки) согласованы по именам, значениям и обязательности (`--soft-timeout`, `--role-file` обязательны; `--task-id`/`--complexity` — один обязателен).

### 4. Соответствие постановке (MoSCoW) — ✅

- **Must:** колонка «Сокращение» удалена из таблиц `run-subagent/SKILL.md` и `README.md`; все краткие ключи в инструкциях/примерах четырёх файлов заменены на полные; README синхронизирован; термины («soft-timeout», «hard-timeout», «stall») не искажены.
- **Must (профиль):** коммит `95fd434d` — ровно одна строка `config/chains.yaml`: `technical_writer_hermione.command_profile: mimo_pro_off → mimo_pro_high`; профиль `mimo_pro_high` существует (chains.yaml:34) и уже используется другими ролями. Коммит `1974a57a` фиксирует решение владельца в постановке (Must-пункт + Change History) — расширение scope санкционировано текстом задачи.
- **Should (900):** обоснованно — `run-subagent/SKILL.md`: два примера базового запуска 600 → 900 и пример ревью `--complexity C1` 300 → 900 (таблица сложности: C0–C2 = 900 после PR #498); `epic-via-subagents/SKILL.md`: пример ревью 600 → 900 при `--stall-timeout 300` (таблица «Ревью = 300 сек» сохранена). Значения в таблицах сложности не менялись (граница scope соблюдена).
- **Won't:** скрипт, роли, конвенции, таблицы сложности, конфигурации вне chains.yaml — не тронуты (подтверждено диффом).
- Валидаторы: `make md-links` ✅; `validate-language` — exit 0 (warning mode), ни один из изменённых файлов в списке предупреждений; `php vendor/bin/todo-md validate TASK-docs-full-option-names-skills` — 0 ошибок, 0 предупреждений.

### 5. Качество таблиц и кодовых блоков — ✅

- `run-subagent/SKILL.md` и `README.md`: 3 колонки, разделители согласованы, все 12 строк параметров целы.
- `epic-via-subagents/SKILL.md`: таблица soft/hard — 3 колонки, таблица stall — 2 колонки, разделители корректны.
- Все heredoc-блоки (`<<'PROMPT' … PROMPT`) и bash-блоки закрыты, переносы строк `\` в многострочных примерах сохранены.

## Неблокирующие наблюдения (предсуществующий долг, вне scope этой задачи)

1. **`docs/agents/skills/epic-via-subagents/SKILL.md:218`** — «По умолчанию 120 сек» про stall-timeout противоречит фактическому дефолту скрипта 360 сек (`watch-subagent.sh:91`, `STALL_TIMEOUT=360`) и таблицам run-subagent (360). Строка присутствует в HEAD до этой ветки — данным диффом не привнесена и не усугублена. Рекомендую отдельную техдолг-задачу на калибровку текста раздела stall в epic-via-subagents (заодно выровнять рекомендацию «Реализация 120» с дефолтом 360).
2. **Колонка «Сокращение» в других скиллах** — `docs/agents/skills/brainstorm/SKILL.md:63`, `docs/agents/skills/task-orchestrator/SKILL.md:32`, `docs/agents/skills/SKILL-CREATION.md:179`. Это CLI других инструментов, не `watch-subagent.sh`; постановка явно ограничивает scope четырьмя файлами. Если решение владельца «уйти от сокращений» распространяется на все скиллы — нужна отдельная задача; в этом PR не трогать.

## Допущения и границы прохода

- PHPUnit/Psalm в этом проходе не запускались: незакоммиченный diff строго документационный (исключение 1 AGENTS.md). Закоммиченная правка `chains.yaml` — замена значения на существующий профиль; полный барьер `make check` перед PR остаётся в ответственности тимлида (раздел 9 постановки).
- Grep-критерий проверяет краткие ключи как отдельные токены; вхождения внутри исторического нарратива RETRO-ROADMAP вне четырёх целевых файлов — намеренно сохранены.

## Итог

APPROVED — изменения соответствуют постановке, факты сверены с исходником скрипта, ссылки и валидаторы зелёные, scope соблюдён. Наблюдения 1–2 — кандидаты на отдельные задачи, к этому диффу отношения не имеют.
