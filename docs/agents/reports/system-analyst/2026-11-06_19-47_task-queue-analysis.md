# Сводный анализ очереди задач task-orchestrator

**Роль:** Аналитик Шерлок (system_analyst_sherlock)
**Дата:** 2026-11-06
**Объект:** `todo/EPIC-research-*.todo.md` (4 эпика), `todo/backlog/TASK-*.todo.md` (7 задач), сверка с `todo/AGENTS.md`, `CHANGELOG.md`, корневым `AGENTS.md`, кодом `src/`/`config/`
**Задача:** запрос пользователя — оценить состояние очереди и дать рекомендации по дальнейшей работе (строго анализ, без изменений)

---

## 0. Методика

- Прочитаны `todo/AGENTS.md` (Definition of Ready), все 11 файлов очереди, шаблон `docs/todo-md/templates/task.md`, `docs/todo-md/reference/TYPES.md`.
- Объективная проверка структуры: `php vendor/bin/todo-md validate` по каждому файлу очереди.
- Актуальность контрактов сверена с кодом: `src/Module/GitIdentity/`, `src/Module/ChainExecution/`, `config/chains.yaml`, `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`.
- Актуальность формулировок по локали сверена с `CHANGELOG.md` (breaking change: `APP_LOCALE` → `TASK_ORCHESTRATOR_LOCALE`, #380).
- Внешние источники не использовались.

## 1. Таблица позиций

| Файл | Суть | Готовность постановки | Приоритет (субъ.) | Рекомендация |
|---|---|---|---|---|
| `EPIC-research-agent-frameworks-comparison` | Evergreen-трек исследований агент-оркестраторов (39/39 артефактов) | Высокая; warnings: формат `author`, пустой `assignee` при `in_progress`, `pr` не URL; stale `branch: task/research-t3-code` | P2, поддерживать | Ведение по evergreen; синхронизировать метаданные; закрытие — только решением владельца |
| `EPIC-research-approaches-comparison` | Evergreen-трек процессных подходов (SDLC/PDLC), 2 исследования done | Высокая (валидатор чист); stale-ссылка Sources, Этап 2 — не TASK-ссылка, чекбоксы MoSCoW/DoD не отражают 2 done-задачи | P2 | Поправить ссылку и чекбоксы; пополнять Этап 1 по мере появления подходов |
| `EPIC-research-coding-agents-comparison` | Evergreen-трек CLI-агентов кодинга | Высокая; warnings: формат `author`/`assignee`, `pr` не URL; противоречие «21 агент» (Goal/DoD) vs фактически 22 исследования; stale-ссылка Sources | P2 | Актуализировать счётчики (21→22), ссылку; далее — evergreen |
| `EPIC-research-orchestration-articles` | Evergreen-трек статей/книг по паттернам оркестрации, 7 задач done | Средняя: статус `todo` при фактической работе; чекбоксы Must/DoD не синхронизированы со сводной таблицей; 2 done-задачи без записей Change History; мусорный символ `映射` в рисках; stale-ссылка Sources; `branch: —` | P2 | Привести статус/чекбоксы/историю в порядок; затем evergreen |
| `TASK-chore-packagist-register` | Регистрация пакета на Packagist | Низкая (валидатор: нет Human Brief/Problem/Solution Sketch/Expected Result/Won't Have; битая ссылка `../docs/research/rfc/...`); предусловие `TASK-chore-composer-library-type` уже выполнено | P3 / C2 | Уточнить у владельца, актуальна ли задача (с апреля 2026); дописать секции, починить ссылку |
| `TASK-chore-watch-subagent-log-cleanup` | CLI-очистка/диагностика архива `var/log/watch-subagent/` | Средняя: нет Human Brief/Expected Result/Won't Have; остальное детально (SMART, риски с mitigation) | P3, фактически «quick win» | Дополнить шаблонные секции → можно запускать одной из первых |
| `TASK-feat-dag-orchestration` | DAG-движок исполнения цепочек | Низкая: `priority: P4` невалиден (P0–P3); `depends_on: [ ... ]` — невалидный формат; нет Human Brief/Expected Result/Verification/Won't Have; пустой `author`; план TBD; противоречие с parallel-execution | C5 — самая сложная; не запускать | Сначала решить вопрос parallel↔DAG, декомпозировать (ориентир diff ≈600 строк) |
| `TASK-feat-docker-sandboxing` | Sandbox выполнения агентов в Docker | Низкая: `priority: P4` невалиден; нет Human Brief/Expected Result/Verification/Won't Have; пустой `author`; дрейф имени контракта (`ShellHookExecutor` → фактически `ShellHookExecutorService(/Interface)`) | C5, P3–P4 (security V3) | Уточнить scope (CI/CD-критерий в DoD расширяет границы), дизайн-анализ перед стартом |
| `TASK-feat-parallel-execution` | Параллельные группы шагов цепочки | Средняя: нет Human Brief/Expected Result/Verification/Won't Have; пустой `author`; пересечение scope с DAG (Should Have `depends_on` = Must Have DAG) | C4, P3 — ведущая фича после решения вопроса | Решить пересечение с DAG; затем — кандидат №1 среди фич |
| `TASK-feat-runner-provider-fallback` | Fallback раннера/провайдера сабагента | Хорошая по содержанию (открытые вопросы вынесены явно), но нет Human Brief/Problem/Solution Sketch/Expected Result; уточнить контракт: в задаче `roles.<role>.command`, в `chains.yaml` роли ссылаются на `command_profile` | C3, P3; высокая операционная ценность | Короткий дизайн-анализ (Локи/Гэндальф) → запуск; кандидат №2 |
| `TASK-techdebt-git-identity-hardening` | Defense-in-depth модуля GitIdentity (L1/L2/L4/INFO/CR-2) | Единственная с Human Brief; валидация — только warning по `author`; **INFO-критерий уже выполнен** (в коде `createFromString`); L1/L2/L4/CR-2 подтверждены актуальными по коду; зависимость PR #275 выполнена | C2, P3 — готова к запуску | Кандидат №1: снять INFO при старте, решить вопрос CR-2 |

## 2. Проблемы

**Устаревшие формулировки и ссылки:**
1. Ссылка `todo/done/EPIC-research-agent-frameworks-comparison.md` в Sources трёх живых эпиков (approaches:128, coding-agents:178, orchestration-articles:131) — эпик переоткрыт и живёт в `todo/EPIC-research-agent-frameworks-comparison.todo.md`.
2. `TASK-techdebt-git-identity-hardening`: INFO-критерий (`RepoSlugVo::fromString` → `createFromString`) уже выполнен в коде — снять при старте.
3. `TASK-feat-runner-provider-fallback`: контракт `roles.<role>.command` — фактически `roles.<role>.command_profile` + секция `command_profiles` в `config/chains.yaml`; имя `ShellHookExecutor` в docker-sandboxing — фактически `ShellHookExecutorService(/Interface)`.
4. stale `branch` в метаданных двух эпиков (привязаны к завершённым задачам `task/research-t3-code`, `task/research-why-software-factories-fail`).
5. `APP_LOCALE` в очереди не встречается — актуально; устаревшие упоминания есть только в архиве `todo/done/` (исторически допустимо по решению из `TASK-refactor-task-orchestrator-locale`).

**Проторечия:**
6. `TASK-feat-parallel-execution` ↔ `TASK-feat-dag-orchestration`: DAG объявлен зависимым от parallel, но описан как «общий случай»; `depends_on` между шагами фигурирует в обеих задачах (дубль scope). Порядок реализации не определён однозначно.
7. `EPIC-research-coding-agents-comparison`: Goal/DoD «21 агент» против фактических 22 исследований (deepseek #22).
8. `EPIC-research-orchestration-articles`: статус `todo` при 7 выполненных задачах; чекбоксы Must/DoD не синхронизированы (сводная таблица существует и заполнена частично).
9. `EPIC-research-approaches-comparison`: чекбоксы Must/DoD не отражают 2 выполненных исследования.

**Слабые места / ошибки валидатора (`todo-md validate`):**
10. `priority: P4` невалиден (допустимы P0–P3): dag-orchestration, docker-sandboxing.
11. `depends_on: [TASK-feat-parallel-execution]` — невалидный формат (нужен plain ID).
12. Нет секций Human Brief / Expected Result (и др.) у 6 из 7 задач backlog — формально DoR не выполнен (для backlog допустимо, но блокирует перевод в `todo`).
13. Пустой `author` в 3 задачах (dag, docker, parallel); устаревший формат `author`/`assignee` в нескольких файлах (warnings).
14. Битая относительная ссылка на RFC в packagist-register (`../docs/...` → нужен `../../docs/...`).
15. Мусорный символ `映射` (иероглиф) в тексте рисков `EPIC-research-orchestration-articles` — нарушает языковую чистоту (validate-language).
16. Этап 2 (summary-задачи) в approaches и orchestration-articles — не оформлены TASK-ссылками/файлами.
17. В `EPIC-research-orchestration-articles` отсутствуют записи Change History о завершении `agent-pipelines-textbook` и `agents-vibe-coding` (план отмечен `[x]`, файлы в `done/`).
18. `pr: "#51, #52, ..."` (список номеров) в agent-frameworks — не URL (warning валидатора).

**Дубли:** явных дублей задач нет; пересечение scope только у пары parallel↔DAG (п. 6).

## 3. Рекомендуемый порядок запуска (топ-3)

1. **`TASK-techdebt-git-identity-hardening`** — единственная с заполненным Human Brief и подтверждённой готовностью; C2, независима, закрывает хвост code review PR #275 (зависимость выполнена). Перед стартом: снять выполненное INFO, решить вопрос CR-2 (или сузить scope до L1/L2/L4).
2. **`TASK-feat-runner-provider-fallback`** — операционная устойчивость всего конвейера (инцидент 2026-06-17 уже был); закрытые open-questions делают задачу готовой к короткому дизайн-анализу. Повышает надёжность всех последующих запусков сабагентов.
3. **`TASK-chore-watch-subagent-log-cleanup`** — C1, автономна, решает реальную проблему (архив 232 МБ / 1627 каталогов и растёт), минимальный риск (dry-run по умолчанию). Перед переводом в `todo` дополнить Human Brief.

Далее — `TASK-feat-parallel-execution` (после решения вопроса parallel↔DAG), затем DAG/docker-sandboxing после декомпозиции. Из исследовательских треков активных задач нет: все висят в evergreen-поддержке до решения владельца.

## 4. Вопросы, требующие решения пользователя

1. **Parallel vs DAG:** что первично — тонкий слайс `parallel_group` (как сейчас задумано) или сразу общий DAG-движок? Не рассматривать ли их как один эпик с декомпозицией? (дубль scope `depends_on`, взаимная зависимость)
2. **CR-2 git-identity:** VO → `InvalidArgumentException` по конвенции или обновить конвенцию под доменные exception-деревья (`GitIdentityException`)? Решение архитектурное.
3. **Docker-sandboxing:** входит ли «CI/CD pipeline работает в sandbox» в DoD (это меняет CI-процесс) или только sandbox для запуска цепочек?
4. **Приоритеты P4:** вне диапазона P0–P3 — понизить до P3 или пересмотреть приоритеты при постановке в работу?
5. **packagist-register:** актуальна ли ещё (задача от 2026-04-23, предусловие выполнено) — возможно, уже выполнена вне задачи или не нужна?
6. **Evergreen-эпики:** подтверждается ли статус «все 4 трека открыты до явного решения владельца»; нужно ли оформить Этап 2 (summary) в approaches/orchestration-articles сейчас или по мере накопления?

---

*Валидация: `php vendor/bin/todo-md validate` — по 11 файлам очереди (детали в пп. 10–14). Полный прогон (272 файла) даёт 861 ошибку / 433 warning — преимущественно в архиве `todo/done/` (не входит в scope анализа). Файлы задач, код и документация не изменялись. Git-операции не выполнялись.*
