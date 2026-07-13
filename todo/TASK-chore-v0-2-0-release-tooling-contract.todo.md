---
type: chore
created: 2026-07-14
value: V4
complexity: C3
priority: P0
depends_on: TASK-fix-v0-2-0-runtime-platform-contract, TASK-fix-v0-2-0-phar-become-role-distribution
epic:
author: system_analyst_sherlock (Шерлок)
assignee:
branch: task/chore-v0-2-0-release-tooling-contract
pr:
status: todo
---

# TASK-chore-v0-2-0-release-tooling-contract: Сделать выпуск v0.2.0 однозначным и проверяемым

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Репозиторий не имеет исполняемого release contract (контракта выпуска), совпадающего с фактическим устройством проекта:

1. Сгенерированный `docs/git-workflow/release.md` предлагает `make prepare-commit`, `make changelog`, `make tests-e2e`, `make release` и `make release-*`, но таких targets (целей Makefile) нет. Все семь команд завершаются `No rule to make target`.
2. Тот же документ использует ветку `master`, тогда как integration branch (интеграционная ветка) репозитория — `main`, а команда копирования шаблона указывает отсутствующий путь `docs/releases/templates/release-plan.template.md`.
3. Документ предлагает после push тега отдельно выполнить `gh release create`, но `.github/workflows/release-phar.yml` уже сам создаёт публичный GitHub Release через `softprops/action-gh-release`. Получаются два конкурирующих publisher (публикатора) одного релиза.
4. Опубликованный PHAR `v0.1.24` выводит `Task Orchestrator 0.1.24.0`, потому что `Kernel::resolveVersion()` использует нормализованную Composer version (версию Composer), а не pretty version (публичное представление версии). Для тега `v0.2.0` публичный CLI contract должен быть ровно `0.2.0`.
5. Tag workflow (процесс по тегу) принимает любой ref (ссылку) по glob `v*` и до публикации не сопоставляет тег с `CHANGELOG.md`, release plan (планом релиза), release notes (описанием релиза), release line (линией релиза) и версией собранного CLI. Production SemVer с leading zeros (ведущими нулями), например `v0.02.0`, сейчас отдельно не запрещён.
6. `docs/git-workflow/` исключён через `docs/.gitignore` и генерируется пакетом `prikotov/git-workflow`; локальная правка этого каталога не попадёт в PR и будет перезаписана. Следовательно, project-specific contract (контракт конкретного проекта) должен храниться в отслеживаемой документации task-orchestrator.
7. Workflow сразу публикует release и единственный asset без `.sha256`, draft verification (проверки черновика), concurrency guard (защиты от параллельного запуска) и безопасной retry policy (политики повтора). У `softprops/action-gh-release` перезапись существующих файлов разрешена по умолчанию, а отсутствие matched files (совпавших файлов) не приводит к ошибке без явной настройки.
8. `permissions: contents: write` выданы всему workflow, а `actions/checkout@v4`, `shivammathur/setup-php@v2`, `softprops/action-gh-release@v2`, Composer и Box выбираются через подвижные версии. Повторный запуск в будущем может использовать другой tooling commit (коммит инструмента).
9. Branch protection (защита ветки) `main` требует только check `test`, имеет `strict: false`; tag ruleset/tag protection (правила защиты тегов) отсутствуют. Поэтому текущая server-side policy (серверная политика) не доказывает прохождение полного `make check` и не защищает `v*` от создания/перемещения.

Без устранения этих расхождений оператор вынужден импровизировать в момент выпуска. Ошибка может создать публичный тег или GitHub Release до доказательства того, что версия, метаданные и артефакт относятся к одному commit (коммиту).

### Варианты или путь решения (Solution Sketch)

Зафиксировать один минимальный и безопасный путь:

- tracked guide (отслеживаемое руководство) task-orchestrator становится источником истины для выпуска этого репозитория;
- один read-only validator (валидатор без побочных эффектов) до сборки проверяет release metadata (метаданные релиза) и git/tag context (контекст git/тега) по явно переданной версии;
- отдельная post-build assertion (проверка после сборки) проверяет точную версию фактического PHAR; валидатор не требует ещё не созданный artifact (артефакт);
- release preparation (подготовка релиза) выполняется обычным PR из `task/release-v0-2-0` в `release/0.2`, без прямых commit/push в защищённые ветки;
- после merge (слияния) release PR и зелёных checks (проверок) пользователь отдельно подтверждает публикацию; только после этого Тимлид под GitHub App identity (идентичностью приложения) создаёт annotated tag (аннотированный тег) на точном head `origin/release/0.2` и push тега запускает публикацию;
- `.github/workflows/release-phar.yml` является единственным publisher GitHub Release, отдельно выполняет полный `make check`, использует pinned tools/actions (закреплённые инструменты/действия) и ограничивает write permission (право записи) publish job (заданием публикации);
- PHAR и его SHA-256 sidecar (файл контрольной суммы) сначала загружаются в draft release (черновик релиза), скачиваются обратно в чистый каталог и проверяются; только затем draft публикуется без перезаписи существующих assets;
- CLI выводит pretty SemVer (публичную семантическую версию), а tag workflow проверяет точное совпадение с тегом.

Не добавлять `make release` или другой mutating helper (помощник с побочными эффектами), который сам создаёт commit/tag/release: это скроет обязательные review (ревью) и пользовательские checkpoint (точки подтверждения).

### Ожидаемый результат (Expected Result)

Для `v0.2.0` существует один документированный и проверяемый маршрут `main -> release/0.2 -> release PR -> annotated v0.2.0 tag -> tag workflow -> verified draft -> GitHub Release`. Невалидный тег, рассинхрон метаданных, непройденный `make check`, checksum mismatch (расхождение контрольной суммы) или версия CLI `0.2.0.0` останавливают workflow до публичной публикации. Повторный ручной `gh release create` не требуется и не документируется. Контракт гарантирует проверку конкретного artifact, но не обещает bit-reproducibility (побитовую воспроизводимость повторной сборки).

## 1. Concept and Goal (Концепция и Цель)

### Story (Job Story)

> Когда команда готовит production release (производственный выпуск), я хочу иметь один read-only preflight (предварительную проверку) и один publisher, чтобы публичный тег, release notes и PHAR гарантированно относились к одной версии и одному commit.

### Goal (Цель по SMART)

До создания тега `v0.2.0` заменить несуществующий/двусмысленный release flow (процесс выпуска) task-orchestrator на отслеживаемый project-specific contract, добавить автоматическую fail-fast проверку release metadata и строгой SemVer, исправить отображение версии CLI и подключить те же инварианты к tag workflow.

## 2. Context and Scope (Контекст и Границы)

### Где делаем

- `Makefile` — единственная локальная точка запуска read-only release check;
- новый скрипт в `bin/` — детерминированная проверка release metadata без создания commit/tag/release;
- `.github/workflows/release-phar.yml` — строгая проверка tag context (контекста тега) и единственная публикация GitHub Release;
- `src/Kernel.php` — публичная версия приложения из Composer pretty version;
- unit/integration tests (модульные/интеграционные тесты) согласно [конвенции тестирования](../docs/conventions/testing/index.md);
- `docs/guide/release.md` — tracked source of truth (отслеживаемый источник истины) именно для task-orchestrator;
- `docs/releases/templates/release-plan.template.md` и `docs/releases/templates/release-notes.template.md` — отслеживаемые CLI-specific templates (шаблоны для CLI-релиза);
- `docs/index.md`, `docs/guide/index.md`, `AGENTS.md` и другие отслеживаемые ссылки на release process (процесс выпуска), найденные поиском.

### Доказанный текущий mismatch (расхождение)

- `make -n prepare-commit changelog tests-e2e release release-patch release-minor release-major` не может быть выполнен: соответствующих targets нет.
- `composer run-script --list` не содержит release/changelog scripts (скриптов выпуска/журнала изменений).
- `origin/HEAD -> origin/main`; ветки `master` нет.
- `docs/releases/templates/release-plan.template.md` отсутствует; доступные копии шаблона находятся только в игнорируемом `docs/git-workflow/`.
- `release-phar.yml` срабатывает на `v*` и `softprops/action-gh-release` публикует GitHub Release; релиз `v0.1.24` был создан именно этим workflow и содержит `task-orchestrator.phar`.
- скачанный asset `v0.1.24` возвращает `Task Orchestrator 0.1.24.0`, хотя tag — `v0.1.24`.
- production tag (производственный тег) `v0.1.24` находится на `release/0.1`; `origin/main` опережает `origin/release/0.1` на 52 commit, поэтому новая minor line (минорная линия) должна быть `release/0.2`, а не продолжение `release/0.1`.
- GitHub API возвращает для `main` единственный required check (обязательную проверку) `test` и `strict: false`; repository rulesets (наборы правил репозитория) пусты, legacy tag protection (устаревшая защита тегов) отсутствует.

### Границы (Out of Scope)

- Не создавать в этой задаче `release/0.2`, release PR, `v0.2.0` tag или GitHub Release — это scope `TASK-release-v0-2-0-preparation` и отдельного пользовательского подтверждения публикации.
- Не изменять `CHANGELOG.md` под `v0.2.0` и не создавать финальные `docs/releases/v0.2.0/*` — их наполняет release preparation task (задача подготовки релиза).
- Не добавлять команду, автоматически создающую commit, tag, push или GitHub Release.
- Не менять историю, не перемещать и не удалять опубликованные tags/releases `v0.1.x`.
- Не редактировать игнорируемый `docs/git-workflow/` как решение: это производная копия dependency package (пакета-зависимости), а не отслеживаемый источник истины.
- Не обновлять пакет `prikotov/git-workflow` и не исправлять его generic contract (общий контракт) в стороннем репозитории.
- Не регистрировать пакет на Packagist и не делать его зависимостью/блокером: пользователь уже перенёс `TASK-chore-packagist-register` в backlog и после этого явно подтвердил выпуск `v0.2.0`. Текущий release channel (канал выпуска) — GitHub Release/PHAR; Source/Composer поддерживается через VCS/path install (установку из VCS/пути), Packagist availability (доступность) не обещается.
- Не синхронизировать в этой задаче публичные compatibility/installation texts (тексты совместимости/установки) в README и skills: это scope уже одобренной `TASK-docs-v0-2-0-compatibility-and-operations`. Здесь меняется только operator release guide (операторское руководство выпуска) и его ссылки.
- Не добавлять prerelease tags (`alpha`, `beta`, `rc`) и build metadata (метаданные сборки): контракт этой задачи ограничен production tag формата `vX.Y.Z`.
- Не добавлять GPG signing, SBOM, self-update, Windows release jobs или Docker distribution: они остаются вне контракта `v0.2.0` по действующему RFC.
- Не называть `.sha256` цифровой подписью или доказательством подлинности: это только проверка целостности при передаче.
- Не настраивать GitHub tag ruleset/tag protection в рамках PR. Их отсутствие фиксируется как residual risk (остаточный риск); guide не должен заявлять, что неизменяемость тегов обеспечена сервером.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)

#### A. Read-only release validator (валидатор без побочных эффектов)

- [ ] Добавлен один явно именованный `Makefile` target `release-check`, принимающий обязательный `VERSION=vX.Y.Z`; при отсутствии `VERSION` команда завершается ненулевым кодом и не подставляет guessed default (предполагаемое значение).
- [ ] `release-check` вызывает один скрипт из `bin/`; скрипт не создаёт/не меняет commit, branch, tag, release, `CHANGELOG.md` или release documents (документы релиза).
- [ ] Валидатор принимает только production SemVer без leading zeros по контракту `^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$`; `v0.02.0`, `v01.2.3`, prerelease/build suffixes и любой другой формат отклоняются.
- [ ] Из уже провалидированного тега валидатор без догадок вычисляет numeric version (числовую версию) и release line `release/X.Y`, печатает проверяемый контекст и возвращает `0` только при полном успехе.
- [ ] Для `VERSION=v0.2.0` валидатор требует точный heading (заголовок) версии в `CHANGELOG.md`, `docs/releases/v0.2.0/release-plan.md` и `docs/releases/v0.2.0/release-notes.md`.
- [ ] Валидатор подтверждает, что release plan явно содержит `v0.2.0` и `release/0.2`, release notes не пусты, а обязательные файлы не содержат известных template placeholders (заполнителей шаблона) `vX.Y.Z`, `release/x.y`, `TBD`, `TODO` и маркеров, объявленных в добавленных шаблонах.
- [ ] Pre-build metadata mode (режим метаданных до сборки) не требует наличия PHAR и одинаково запускается в release-preparation PR и tag workflow.
- [ ] Добавлен явный tag mode (режим тега), который помимо metadata проверяет: workflow действительно запущен для tag ref, tag является annotated tag, tag указывает на текущий `HEAD`, а текущий `HEAD` равен явно загруженному `origin/release/X.Y`.
- [ ] Post-build version assertion (проверка версии после сборки) является отдельным шагом и принимает явные expected version (ожидаемую версию) и path (путь) к собранному PHAR; она не смешивается с pre-build validator.
- [ ] Ошибка каждого инварианта имеет отдельное детерминированное сообщение и происходит до шага публикации.

#### B. Единственный publication path (путь публикации)

- [ ] `.github/workflows/release-phar.yml` до build/upload запускает tag mode валидатора с `github.ref_name`; workflow не принимает `vfoo`, branch ref, SemVer с leading zeros или tag, не соответствующий production `vX.Y.Z`.
- [ ] Для tag-scoped concurrency (конкурентности в рамках тега) задана группа, включающая `github.ref_name`, с `cancel-in-progress: false`: два запуска одного tag не публикуют assets параллельно и более новый запуск не обрывает уже начавшуюся проверку/публикацию.
- [ ] Отдельный pre-publication quality job (задание проверки качества до публикации) устанавливает dev dependencies и выполняет полный `make check`; required check `test` из branch protection не считается его заменой.
- [ ] Tag workflow использует exact production/PHAR gate (точную проверку production/PHAR) из `TASK-fix-v0-2-0-runtime-platform-contract` и проверку distribution contract (контракта дистрибутива) из `TASK-fix-v0-2-0-phar-become-role-distribution`; эти проверки не дублируются более слабым альтернативным путём.
- [ ] После успешной строгой проверки tag из него вычисляется `COMPOSER_ROOT_VERSION` без `v`; переменная задаётся только install/build steps (шагам установки/сборки). Непроверенный `github.ref_name`, guessed version или hardcoded `0.2.0` не передаются Composer.
- [ ] Composer закреплён на `2.10.1`, Box — на `4.7.0`; workflow проверяет фактический `composer --version`/`box --version` и fail-fast при расхождении. Смена версии инструмента выполняется отдельным reviewed diff (проверяемым изменением), а не автоматически.
- [ ] Все внешние GitHub Actions в release workflow закреплены полным immutable commit SHA (не `@v2`/`@v4`/branch) с human-readable version comment (комментарием версии); выбранные SHA сверены с официальными upstream releases (выпусками исходных проектов).
- [ ] До `softprops/action-gh-release` workflow запускает собранный PHAR и проверяет точную строку/версию `0.2.0`; `0.2.0.0`, `dev-main`, другая версия или пустой вывод блокируют публикацию.
- [ ] После успешной post-build version assertion создаётся `task-orchestrator.phar.sha256` в стандартном формате `sha256sum`; `sha256sum -c` локально подтверждает `task-orchestrator.phar` до upload (загрузки).
- [ ] Build/quality jobs (задания сборки/качества) имеют только `contents: read`; `contents: write` задан исключительно publish job, который зависит от всех metadata, quality, production, Composer-host и PHAR gates.
- [ ] Publish job использует draft-first flow (сначала черновик): `softprops/action-gh-release` создаёт/сохраняет `draft: true`, получает committed body из `docs/releases/${tag}/release-notes.md` и два точных файла `task-orchestrator.phar` + `task-orchestrator.phar.sha256`.
- [ ] Для upload явно заданы `fail_on_unmatched_files: true` и `overwrite_files: false`; workflow не полагается на небезопасные defaults action и не перезаписывает существующий asset с тем же именем.
- [ ] До публикации draft оба assets скачиваются через GitHub API в новый чистый временный каталог, проверяется точный набор имён и выполняется `sha256sum -c task-orchestrator.phar.sha256`. `.sha256` называется checksum/integrity check (контрольной суммой/проверкой целостности), а не подписью или доказательством подлинности.
- [ ] Draft переводится в published state (опубликованное состояние) только после remote download verification; `softprops/action-gh-release`/финализатор остаётся единственным publisher, ручной второй `gh release create` из project-specific guide удалён.
- [ ] Retry state machine проверяет release state, tag, target SHA, name/body и assets до мутации: повтор разрешён только для неизменного draft того же tag/commit/body с отсутствующими assets либо уже совпадающими checksum; любое конфликтующее содержимое приводит к fail-fast без overwrite/delete.
- [ ] Если release уже published, workflow завершается fail-fast как недопустимый retry и не изменяет status/body/assets; повторная публикация или «починка» опубликованного release тем же tag запрещена.
- [ ] Workflow публикует только PHAR и checksum, собранные и проверенные на том же tag commit; пути assets, release name и target SHA детерминированы.
- [ ] Документировано, что явное подтверждение пользователя требуется **до** push тега, а push тега является publication checkpoint: после него GitHub Actions автоматически создаёт публичный GitHub Release.
- [ ] Annotated tag создаёт и push выполняет только GitHub App `prikotov-agent[bot]` с installation token (токеном установки) по `docs/guide/agent-identity.md`; личная owner identity (идентичность владельца) для tag/push не используется.
- [ ] Документирована recovery policy (политика восстановления): transient CI failure можно rerun только для неизменных tag + target SHA + draft; при дефекте commit/metadata или несовпадающем asset tag/draft не двигают и не переиспользуют — исправление идёт через новый patch release.

#### C. Публичная CLI version (версия CLI)

- [ ] `Kernel::resolveVersion()` использует Composer package/root pretty version для публичного вывода; если обе metadata отсутствуют, код завершается fail-fast вместо возврата хардкодной исторической версии `0.1.0`. Version для release tag не хардкодится.
- [ ] Регрессия нормализованной версии зафиксирована тестом: Composer data с `version=0.2.0.0` и `pretty_version=v0.2.0` даёт CLI version `0.2.0`.
- [ ] Source checkout (исходный checkout) продолжает честно показывать dev version (версию разработки), а tagged distribution (дистрибутив по тегу) — точный SemVer; тесты не подменяют production version константой.

#### D. Отслеживаемая документация и шаблоны

- [ ] Создан `docs/guide/release.md`, который явно объявлен project-specific source of truth и не ссылается на несуществующие `make release*`, `make changelog`, `make tests-e2e` или ветку `master`.
- [ ] Guide описывает полный маршрут `main -> release/X.Y -> task/release-vX-Y-Z PR -> merge в release/X.Y -> green branch checks -> отдельное подтверждение пользователя -> GitHub App annotated tag/push -> validated draft -> remote checksum verification -> publish`.
- [ ] Guide запрещает прямой commit/push в `main` и `release/*`, личную owner identity для tag/push, перемещение опубликованного tag, overwrite/delete published assets и ручное создание конкурирующего GitHub Release.
- [ ] Guide перечисляет реальные обязательные команды/проверки: полный `make check`, Composer-host smoke, PHAR smoke/exact-runtime gate и `make release-check VERSION=vX.Y.Z`; несуществующих команд нет.
- [ ] Guide объясняет `.sha256` как integrity check без cryptographic authenticity (криптографического подтверждения происхождения), draft-first publication, safe retry boundaries (границы безопасного повтора) и patch-only recovery (восстановление только новым патч-релизом) при содержательном дефекте.
- [ ] Guide явно фиксирует product exception (продуктовое исключение): `v0.2.0` публикуется через GitHub Release/PHAR; Source/Composer проверяется через VCS/path, Packagist не является блокером и его доступность не обещается.
- [ ] Добавлены отслеживаемые CLI-specific templates для release plan и release notes. Release plan не содержит нерелевантный Web/Workers deploy contract (контракт выкладки веб-приложения/воркеров), а фиксирует tag, release line, included PR/tasks, compatibility, distribution gates, post-publication verification и patch recovery.
- [ ] `docs/index.md`, `docs/guide/index.md` и `AGENTS.md` ведут оператора на новый tracked guide; generic `docs/git-workflow/` остаётся справочником общих правил, но не источником исполняемых project-specific команд.
- [ ] README/skills/compatibility tables (таблицы совместимости) не переписываются в этом PR; task spec и guide дают `TASK-docs-v0-2-0-compatibility-and-operations` однозначный фактический контракт для последующей синхронизации публичных текстов.
- [ ] Поиск по `make release`, `make changelog`, `make tests-e2e`, `master`, `gh release create`, `release-plan.template` подтверждает отсутствие устаревших исполняемых инструкций во всех **отслеживаемых** project-specific документах.

### 🟡 Should Have (Желательно)

- [ ] Валидатор печатает короткий summary (итог): tag, numeric version, release line, commit SHA и проверенные paths (пути), не выводя secrets (секреты) или содержимое release notes целиком.
- [ ] Тесты валидатора используют временный fixture repository (репозиторий-фикстуру) и не создают tags/branches в рабочем репозитории.

### 🟢 Could Have (Опционально)

- [ ] Нет: задача release-blocking, дополнительная автоматизация намеренно исключена.

### ⚫ Won't Have (Не будем делать)

- [ ] `make release`, `make release-minor`, `make release-patch` или иной helper, который автоматически mutates git/GitHub state (изменяет состояние git/GitHub).
- [ ] Автоматический version bump (подъём версии) или генерация `CHANGELOG.md` без review.
- [ ] Packagist registration/publication.
- [ ] Обещание bit-reproducible build: checksum идентифицирует проверенный artifact одного запуска, но не доказывает равенство двух независимых сборок.
- [ ] Изменение repository ruleset/tag protection; отсутствие server-side защиты тегов остаётся явно документированным residual risk.
- [ ] Переписывание generic package `prikotov/git-workflow` в этом репозитории.
- [ ] Изменение уже опубликованных `v0.1.x` tags/releases или исторических release plans.
- [ ] Фактическая публикация `v0.2.0` в рамках этого PR.

## 4. Implementation Plan (План реализации)

1. [ ] Реализовать read-only `bin/` validator и `make release-check VERSION=...`; отделить pre-build metadata/tag modes от post-build PHAR version assertion без guessed defaults.
2. [ ] Покрыть validator изолированными positive/negative fixtures: leading-zero/malformed version, missing changelog/plan/notes, placeholders, wrong release line, lightweight/wrong-target tag и PHAR version mismatch.
3. [ ] Исправить `Kernel::resolveVersion()` на Composer pretty version, добавить регрессионный тест `0.2.0.0 -> 0.2.0` и передавать `COMPOSER_ROOT_VERSION` только из провалидированного tag.
4. [ ] Перестроить `release-phar.yml` на read-only metadata, full `make check`, Composer-host, exact production/PHAR build и write-only publish jobs; добавить tag-scoped concurrency и закрепить Actions/Composer/Box.
5. [ ] После build сформировать/проверить `.sha256`; реализовать draft-first upload с `fail_on_unmatched_files=true`, `overwrite_files=false`, remote download verification и финализацией draft.
6. [ ] Реализовать и протестировать safe retry state machine для отсутствующего release, совпадающего draft и уже published release без мутации/перезаписи assets.
7. [ ] Создать tracked project guide и CLI-specific release templates; описать App-only tag/push, checksum semantics, draft/retry/recovery, Packagist exception и residual tag-protection risk; обновить tracked indexes/AGENTS references.
8. [ ] Поиском доказать отсутствие устаревших project-specific executable instructions и floating action/tool versions (подвижных версий actions/tools).
9. [ ] Выполнить целевые тесты, полный `make check`, оба distribution smoke, `todo-md-validate` и `git diff --check`.

## 5. Definition of Done (Критерии приёмки)

- [ ] Positive `release-check` проверяется только на корректных fixtures и возвращает `0`; каждый рассинхрон версии/файлов/line/placeholders возвращает ненулевой код до любой мутации.
- [ ] На реальном checkout этой tooling task (задачи инструментария) `make release-check VERSION=v0.2.0` ожидаемо возвращает ненулевой код, потому что финальные `CHANGELOG.md` и `docs/releases/v0.2.0/*` создаются позже в `TASK-release-v0-2-0-preparation`; этот expected failure не маскируется фальшивыми release documents.
- [ ] `make release-check` без `VERSION` и с `VERSION=vfoo` завершается fail-fast и не создаёт git/GitHub objects (объекты).
- [ ] Validator отклоняет `v0.02.0`/`v01.2.3`; tag-mode test отклоняет lightweight tag, tag не на `HEAD` и `HEAD` не из требуемой `origin/release/0.2`.
- [ ] Смоделированный корректный tag context `v0.2.0` проходит validator; отдельный distribution-version assert (проверка версии дистрибутива) принимает `0.2.0` и отклоняет `0.2.0.0`.
- [ ] `release-phar.yml` имеет отдельный успешно пройденный full `make check` job и не доходит до write-enabled publish job при ошибке validator/quality/Composer-host/build/smoke/version/checksum assertion.
- [ ] Два параллельных запуска fixture workflow для одного tag сериализуются одной concurrency group и не отменяют друг друга (`cancel-in-progress: false`).
- [ ] Все release Actions используют full commit SHA; Composer фактически `2.10.1`, Box фактически `4.7.0`; floating refs `@v*`, непинованные `composer`/`box` и несовпадающие tool versions отсутствуют.
- [ ] Build/quality jobs имеют `contents: read`, только publish job имеет `contents: write` и зависит от всех gates.
- [ ] Локальные `sha256sum -c` и проверка после скачивания draft assets из чистого каталога успешны; изменение хотя бы одного байта PHAR/sidecar блокирует публикацию.
- [ ] Upload использует `draft: true`, `fail_on_unmatched_files: true`, `overwrite_files: false`; опубликованы ровно `task-orchestrator.phar` и `task-orchestrator.phar.sha256`.
- [ ] Retry tests доказывают: неизменный draft/tag/SHA/body безопасно продолжается; конфликтующий draft metadata/asset отклоняется без overwrite/delete; published release вызывает fail-fast и не мутируется.
- [ ] Workflow читает release body из `docs/releases/v0.2.0/release-notes.md`, публикует draft только после remote checksum verification, а tracked guide не предлагает повторный `gh release create`.
- [ ] `php bin/task-orchestrator --version` в dev остаётся честной dev version; tagged PHAR fixture для `v0.2.0` показывает ровно `Task Orchestrator 0.2.0`.
- [ ] `COMPOSER_ROOT_VERSION=0.2.0` выводится только из уже принятого validator тега `v0.2.0`; malformed/unvalidated ref не достигает Composer install/build.
- [ ] `docs/guide/release.md` содержит только существующие команды, использует `main`/`release/0.2`, требует GitHub App identity для tag/push и явно отделяет release preparation от подтверждённой пользователем publication.
- [ ] Guide не называет checksum подписью, не обещает bit-reproducibility/Packagist и явно фиксирует отсутствие server-side tag protection как residual risk.
- [ ] `make check`, Composer-host smoke, exact-runtime PHAR smoke, `todo-md-validate` и `git diff --check` проходят.

## 6. Verification (Самопроверка)

```bash
php vendor/prikotov/todo-md/bin/todo-md-validate todo/TASK-chore-v0-2-0-release-tooling-contract.todo.md
# Positive validator/tag/version/checksum/retry cases run against temporary fixtures:
vendor/bin/phpunit --filter 'Release|Version'

# Real v0.2.0 metadata does not exist until TASK-release-v0-2-0-preparation:
if make release-check VERSION=v0.2.0; then
  echo 'ERROR: release-check unexpectedly passed without v0.2.0 release documents' >&2
  exit 1
else
  echo 'Expected failure: v0.2.0 release documents are not prepared yet.'
fi

vendor/bin/phpunit
vendor/bin/psalm
make check
make composer-host-smoke
make phar-smoke
git diff --check
rg -n "make (prepare-commit|changelog|tests-e2e|release)|git switch master|gh release create|release-plan\.template" \
  AGENTS.md README.md README.en.md README.zh.md docs/guide docs/index.md docs/releases
if rg -n "uses: [^#[:space:]]+@(v[0-9]+|main|master)|tools:.*(composer|box)([ ,]|$)" .github/workflows/release-phar.yml; then
  echo 'ERROR: release workflow still contains floating action/tool versions' >&2
  exit 1
fi
```

Проверка `rg` должна не находить floating action refs (подвижные ссылки actions) и unversioned Composer/Box (инструменты без версии). Для negative tests запрещено создавать временные tags/branches/releases в текущем repository; используются subprocess и временный fixture repository. Draft/retry tests работают с fake/local API boundary (поддельной/локальной границей API), а не публикуют тестовый GitHub Release.

## 7. Risks and Dependencies (Риски и зависимости)

- Задача блокирует `v0.2.0`: без неё единственная доступная инструкция предлагает несуществующие команды, а tag workflow может опубликовать release с неверной публичной версией.
- Зависимость `TASK-fix-v0-2-0-runtime-platform-contract`: release workflow должен переиспользовать уже установленный exact PHP 8.4.1 production/PHAR gate, а не вернуть более слабый `php-version: 8.4` contract.
- Зависимость `TASK-fix-v0-2-0-phar-become-role-distribution`: release gate должен включать фактические Composer-host и PHAR distribution checks, принятые для `v0.2.0`.
- Обе зависимости находятся в review/open PR `#306`/`#307`. Implementation этой задачи начинается только после их merge; дополнительное подтверждение пользователя для старта не требуется, потому что release-blocking план уже одобрен.
- `softprops/action-gh-release` создаёт публичный Release после push тега. Поэтому push тега нельзя выполнять как побочный эффект проверки; это отдельное high-risk действие после явного подтверждения пользователя.
- Tag workflow начинается уже после появления public ref. Server-side tag protection отсутствует, поэтому техническая неизменяемость тега GitHub ruleset сейчас не обеспечена. Mitigation этой задачи — App-only identity, точная проверка target SHA и запрет move/reuse; остаточный риск документируется, а при дефекте выпускается `v0.2.1`.
- Default `overwrite_files: true` у release action способен незаметно заменить asset при retry. Явный `false` недостаточен без проверки draft/published state и remote checksum, поэтому состояние release проверяется до action и после download.
- SHA-256 обнаруживает повреждение/подмену относительно опубликованного sidecar, но не подтверждает автора: оба файла может заменить субъект с write-доступом до публикации. GPG/Sigstore не входят в `v0.2.0`, поэтому documentation обязана честно ограничить гарантию целостностью.
- Draft-first flow уменьшает риск публичного некорректного asset, но draft уже является GitHub-side mutable state (изменяемым состоянием). Retry разрешён только при совпадении tag, target SHA и checksum; cleanup/overwrite конфликтующего draft автоматически не выполняется.
- Full `make check` использует dev toolchain, тогда как production/PHAR gate выполняется на PHP 8.4.1 без dev dependencies. Эти jobs нельзя объединять ценой `--ignore-platform-reqs` или ослабления exact production contract.
- GitHub App installation token и permissions `contents: write` нужны до tag push. Отсутствие/истечение токена — fail-fast blocker конкретной операции публикации, не основание перейти на личную owner identity.
- Immutable action SHA и exact Composer/Box versions требуют осознанного обслуживания; автоматическое обновление вне reviewed PR запрещено, иначе проверенная release environment снова станет плавающей.
- Проверка только `Kernel::resolveVersion()` недостаточна: Composer может хранить одновременно normalized и pretty versions. Нужен distribution-level assert на фактический вывод собранного CLI.
- `docs/git-workflow/` игнорируется и может быть перегенерирован dependency package. Любая реализация, меняющая только этот каталог, не закрывает задачу.
- Packagist сейчас не содержит `prikotov/task-orchestrator`, но по явному решению пользователя это не blocker `v0.2.0`. GitHub Release/PHAR — текущий release channel; Composer-host smoke проверяет VCS/path package composition, а не публикацию на Packagist.

## 8. Sources (Источники)

- [Project release guide, текущее генерируемое состояние](../docs/git-workflow/release.md) — доказательство заявленных, но отсутствующих команд; файл игнорируется и не является подходящим project-specific source.
- [Release checklist](../docs/git-workflow/release-checklists.md).
- [RFC: дистрибуция task-orchestrator как CLI-утилиты](../docs/research/rfc/cli-distribution-rfc.md) — Composer full, PHAR best-effort; GPG/SBOM/self-update/Windows отложены.
- [Конвенция тестирования](../docs/conventions/testing/index.md).
- [Операционные smoke commands](../docs/conventions/ops/smoke-commands.md).
- `.github/workflows/release-phar.yml`, `Makefile`, `composer.json`, `src/Kernel.php`, `CHANGELOG.md`.
- `docs/releases/v0.1.24/release-plan.md` и [опубликованный GitHub Release v0.1.24](https://github.com/prikotov/task-orchestrator/releases/tag/v0.1.24) — последний фактический release path.
- [Packagist registration task](backlog/TASK-chore-packagist-register.todo.md) — отдельный невыполненный внешний scope.
- [Packagist metadata endpoint для `prikotov/task-orchestrator`](https://repo.packagist.org/p2/prikotov/task-orchestrator.json) — на 2026-07-14 отвечает `404`.
- [softprops/action-gh-release inputs](https://github.com/softprops/action-gh-release#inputs) — `draft`, `overwrite_files` (default `true`), `fail_on_unmatched_files`, permissions.
- [Composer root package version detection](https://getcomposer.org/doc/articles/troubleshooting.md#root-package-version-detection) — `COMPOSER_ROOT_VERSION` для CI.
- [Box 4.7.0 release](https://github.com/box-project/box/releases/tag/4.7.0).

## 9. Comments (Комментарии)

### Почему не реализуем отсутствующий `make release`

Его слепое добавление не решает расхождение и создаёт новый риск: команда, которая сама делает commit/tag, конфликтует с обязательными PR, review и пользовательским подтверждением публикации. Для `v0.2.0` нужен не release generator (генератор релиза), а проверяемый read-only contract плюс явный high-risk checkpoint.

Implementation не назначен и не стартует до merge зависимостей `#306` и `#307`. После их merge Тимлид запускает уже одобренную задачу без повторного запроса подтверждения пользователя.

### Фактическая цепочка публикации после выполнения задачи

```text
main
  -> release/0.2 (selected commit)
  -> task/release-v0-2-0 (CHANGELOG + plan + notes)
  -> PR в release/0.2
  -> review + full make check + distribution gates + merge
  -> явное подтверждение пользователя на публикацию
  -> GitHub App: annotated tag v0.2.0 на точном origin/release/0.2 HEAD
  -> GitHub App: push tag
  -> release workflow: metadata/tag validate -> full check -> build/smoke -> exact version
  -> local SHA-256 -> draft upload -> remote download/SHA-256 -> publish draft
```

## Change History (История изменений)

| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-14 | system_analyst_sherlock (Шерлок) | Создание release-blocking задачи по фактическому tooling contract для `v0.2.0`. |
| 2026-07-14 | system_analyst_sherlock (Шерлок) | Уточнение после архитектурного review: pre/post-build gates, strict SemVer, checksum/draft/retry, pinned tooling, least privilege, App identity и Packagist exception. |
| 2026-07-14 | system_analyst_sherlock (Шерлок) | Статус возвращён в `todo`, assignee очищен до merge зависимостей PR #306/#307; повторное пользовательское подтверждение после merge не требуется. |
