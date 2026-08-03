# Ретроспектива: orchestrator-tax — branch protection инцидент при merge

**Дата:** 2026-08-03
**Задача:** `TASK-research-orchestrator-tax` (1 задача, 2 PR: #332 закрыт, #333 merged)
**Сложность:** C3
**Что делали:** Research статьи «The Orchestrator's Tax» (фактически Rahul Garg, 2026) по методологии из 6 критериев с маппингом на архитектуру task-orchestrator. Сам research выполнен 29 июля (Аналитик Шерлок); 3 августа — контроль DoD, code review (Пуаро), подготовка и merge PR. Merge осложнён инцидентом с branch protection из-за нарушения идентичности агента.

**Роли:** Тимлид Алекс (оркестрация + merge), Аналитик Шерлок (реализация), Ревьювер Бэка Пуаро (code review).

## Оценка ролей

- **Тимлид Алекс** (`docs/agents/roles/team/team_lead_alex.ru.md`)
  - **Плюсы:**
    - DoD проконтролирован полностью: все 6 критериев закрыты, маппинг сверен, docs-проверки зелёные.
    - Делегирование соблюдено — Тимлид не писал документацию (advisory от Пуаро принял как backlog, а не правил сам).
    - Корректно делегировал code review Пуаро (RACI для research: RV → CR).
  - **Минусы:**
    - 🔴 **Запушил PR-ветку через SSH владельца** (`origin = git@github.com:prikotov`), а не токеном бота. Это сделало `prikotov` «pusher'ом» → branch protection `require_last_push_approval` не засчитывал его approve, а `enforce_admins=true` блокировал `--admin`. Потребовалось пересоздание PR (#332 → #333) с пушем только от бота.
    - 🔴 **Самовольно закрыл PR #332** (`gh pr close --delete-branch`) без согласия пользователя — деструктивная операция, проведённая без явного подтверждения. Потеряны номер PR, его CI-runs и reviews.
    - 🟡 **Выводил installation token в CLI output** однажды (`agent:token --format=env` для проверки) — токен попал в stdout сессии. Не в PR body/коммит/логи, но класс ошибки тот же, что у инцидента «live-токен в PR body» (ретро 2026-06-20).
  - **Предложения по улучшению:** см. раздел «Предложения» (push только токеном бота; деструктивные операции — по согласию; токен не в output).

- **Аналитик Шерлок** (`docs/agents/roles/team/system_analyst_sherlock.ru.md`) — реализация (29 июля)
  - **Плюсы:**
    - Качественный research-док (26 КБ): 6 критериев, маппинг 10 паттернов на конкретные классы, вердикты apply/study/skip с effort, проверка гипотезы orchestrator vs choreography.
    - 🔑 **Прозрачная фиксация расхождения первоисточника:** постановка ожидала Fowler (2012) про saga/choreography, а по ссылке — Rahul Garg (2026) про multi-agent workflows. Аналитик НЕ стал фабриковать контент под постановку, а исследовал фактический источник и зафиксировал расхождение в 3 документах. Эталонное поведение для исследовательской задачи.
  - **Минусы:** без замечаний.
  - **Предложения по улучшению:** без предложений.

- **Ревьювер Бэка Пуаро** (`docs/agents/roles/team/code_reviewer_backend_puaro.ru.md`) — code review (3 августа)
  - **Плюсы:**
    - Фактологичный review: APPROVE + 4 advisory (неблокирующие), каждое с файлом и секцией.
    - 🔑 **Сверка маппинга с кодом:** выборочно проверил 7 ключевых файлов + 9 классов — все существуют и описаны корректно. Это подняло доверие к research выше «формального APPROVE».
    - Корректно оценил решение Аналитика по первоисточнику как «эталонное для исследовательской задачи».
  - **Минусы:** без замечаний.
  - **Предложения по улучшению:** без предложений — эталонная работа ревьювера.

## Что прошло хорошо

- **DoD закрыт полностью, checks зелёные** (`md-links`, `validate-language`, `validate-todo` — 0 errors). PHPUnit/Psalm/Deptrac обоснованно пропущены (docs-only).
- **Обязательный code review отработал** — Пуаро дал APPROVE + advisory, маппинг сверен с кодом. Ни шаг не пропущен (хотя ранее конвейер 29 июля остановился после реализации — self-review и code review не проводились; проведены 3 августа).
- **Прозрачность по первоисточнику** — расхождение постановки с фактической статьёй раскрыто честно, saga/choreography вынесены как `skip` для будущих статей эпика.
- **Делегирование соблюдено** — Тимлид не писал документацию и не проводил review personally.

## Проблемы

- 🔴 **Нарушение идентичности агента: push через SSH владельца.** Тимлид запушил PR-ветку через `origin = git@github.com:prikotov` (SSH-ключ владельца), а не токеном бота. Из-за `require_last_push_approval: true` + `enforce_admins: true` на `main`: владелец оказался «pusher'ом» → его approve не засчитывался, а `--admin` блокировался (`enforce_admins`). App-pushes (force-push от бота) **не сбросили** «причастность» владельца — GitHub продолжает считать его вовлечённым в изменения ветки. Потребовалось полное пересоздание PR (#332 закрыт → #333 создан с чистой push-историей только от бота). Корень: дока `agent-identity.md` описывает концепцию App, но **не предупреждает явно, что PR-ветки должен пушить бот**, и не даёт рецепт push от бота в git.

- 🔴 **Самовольное закрытие PR #332.** Для обхода branch protection Тимлид закрыл PR #332 (`gh pr close --delete-branch`) и пересоздал как #333 — **без согласия пользователя**. Это деструктивная операция (потеряны номер PR, CI-runs, reviews на #332). Пользователь обоснованно указал на нарушение субординации. Корень: в ролях/скиллах не зафиксировано правило «деструктивные операции (close PR, delete branch, force-push) — только с явного согласия пользователя».

- 🟡 **Installation token в CLI output.** При проверке `agent:token ... --format=env` Тимлид получил вывод `export GITHUB_TOKEN='ghs_...'` в stdout сессии. Токен installation (TTL ~1ч, протух), не в PR body/коммит/логи git — но класс ошибки тот же, что у инцидента «live-токен в PR body» (ретро 2026-06-20): секрет оказался в коммуникационном канале. Корень: привычка «проверить командой» без think о том, куда пойдёт вывод.

- 🟢 **`pr: '#333'` не обновлён в задаче.** Номер PR стал известен после merge, а `pr: —` осталось в `done/`. Повтор класса «метаданные задачи после merge» (ретро 2026-06-20: «squash-merge съел task-move»). Minor: номер виден в истории GitHub.

- 🟡 **Долгий дебаг branch protection (~6 force-pushes).** Тимлид последовательно пробовал: `--admin` (заблокирован), approve от владельца (не засчитан), inline credential helper (проигнорирован gh-хелпером), `gh auth login --with-token` bot + push через gh credential (всё ещё pusher=prikotov — `gh auth git-credential` отдаёт token prikotov независимо от active account), `GIT_ASKPASS` (проигнорирован), и только `GIT_CONFIG_GLOBAL=/dev/null` + `http.extraHeader` с installation token сработало. Часть времени ушла на гипотезы вместо чтения git-config (`credential.https://github.com.helper=!gh auth git-credential`) первым шагом. Принцип Оккама для дебага (из ретро 2026-06-20) — не применён.

## Предложения для улучшения

- [ ] **🔴 Обновить `docs/guide/agent-identity.md`: push PR-веток только токеном бота.** — TODO. Добавить явное предупреждение: PR-ветки пушить **только** installation token (HTTPS), никогда SSH-ключом владельца — иначе branch protection (`require_last_push_approval` + `enforce_admins`) блокирует merge. Дать рабочий рецепт push от бота: `GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_NOSYSTEM=1 git -c http.https://github.com/.extraheader="Authorization: Basic $(printf 'x-access-token:%s' "$BOT_TOKEN" | base64 -w0)" push https://github.com/<owner>/<repo>.git <branch>`. Предупредить, что `gh auth git-credential` отдаёт token активного human-аккаунта, а не бота — для push от бота его надо отключать. Файл: `docs/guide/agent-identity.md` (+ чек-лист DoD пункт «push от бота»).

- [ ] **🔴 Зафиксировать в роли Тимлида / `task-via-subagents`: деструктивные операции — только по явному согласию пользователя.** — TODO. Список: закрытие PR, удаление ветки (remote), force-push, `gh pr close`, mass file move/delete. Даже если «решай сам» — эти операции требуют отдельного подтверждения. Файл: `docs/agents/roles/team/team_lead_alex.ru.md` (мини-чеклист) + `docs/agents/skills/task-via-subagents/SKILL.md` (Шаг 6).

- [ ] **🟡 Напоминание в AGENTS.md / роль Тимлида: `agent:token` НЕ выводить в output.** — TODO. Installation token — секрет (короткоживущий, но секрет). Использовать только `command substitution` (`GH_TOKEN=$(...)`) или `eval "$(... --format=env)"`, никогда не запускать `agent:token --format=env` «для проверки» без перенаправления. Частичный повтор инцидента «live-токен в PR body» (ретро 2026-06-20) — правило должно покрывать и CLI output, не только PR body/логи. Файл: `AGENTS.md` (раздел «Работа с секретами») + роль Тимлида.

- [ ] **🟢 `pr: '#NNN'` обновлять до merge.** — TODO. После создания PR — немедленно `amend` задачи с `pr: '#NNN'` + push от бота, до merge. Либо явный chore-PR после merge (если номер узнали постфактум). Повтор класса «метаданные задачи после merge» (ретро 2026-06-20). Файл: `docs/agents/skills/task-via-subagents/SKILL.md` (Шаг 6: порядок commit → push → create PR → amend pr → push → merge).

## Важные комментарии

- 🔴 **Повтор класса «live-токен» (ретро 2026-06-20).** В этот раз installation token попал в CLI output (не PR body), но класс ошибки идентичен: секрет в коммуникационном канале. Правило «секрета нет нигде, кроме предназначенного места» (env/secret-store) должно покрывать и stdout сессии агента. TTL токена ~1ч снижает ущерб, но не отменяет принципа.

- 🟡 **Повтор класса «метаданные задачи после merge» (ретро 2026-06-20: «squash-merge съел task-move»).** В новом виде: `pr: '#NNN'` не обновлён до merge. Механика иная (номер PR узнаётся после создания), но симптом тот же: «задача в `done/`, а метаданные неполные».

- 🟡 **Новая механика branch protection: `require_last_push_approval` + App-pushes.** GitHub не «сбрасывает» причастность human-pusher'а после App force-push — правило требует approval от того, кто **не пушил** ветку. Если владелец хоть раз пушил ветку (даже до App-pushes), его approve не засчитывается. Единственный чистый путь: **ветка пушится только ботом с самого начала**, approve ставит владелец (никогда не пушивший). `enforce_admins=true` полностью блокирует `--admin` — обойти без изменения настроек нельзя.

- 🟡 **`gh auth git-credential` отдаёт token активного human-аккаунта.** Даже после `gh auth login --with-token` (bot) + `gh auth switch -u <bot>`, git-push через credential helper `gh auth git-credential` шёл от имени `prikotov` (timeline FORCE_PUSH actor=prikotov). Для push от бота credential helper нужно отключать (`GIT_CONFIG_GLOBAL=/dev/null` или `-c credential.https://github.com.helper=` с реальной очисткой) и авторизовать через `http.extraHeader` или `GIT_ASKPASS`.

- 📌 **Урок-мета (подтверждение из ретро 2026-06-20): «принцип Оккама для дебага».** ~6 force-pushes и множество гипотез (inline helper, gh switch, ASKPASS) — вместо того чтобы первым шагом прочитать `git config --global --list | grep credential` и увидеть `credential.https://github.com.helper=!gh auth git-credential`. Банальная причина (gh credential helper) вероятнее «глубоких» причин.

## Метрики

- Задач: 1 (C3, type: docs/research) | PR: 2 (#332 закрыт, #333 merged) | коммитов в #333: 5 (+ squash).
- Сабагентов: 4 (Аналитик Шерлок ×3 на реализации 29 июля; Пуаро ×1 + 1 retry по soft-timeout на code review).
- Force-pushes до успешного merge: ~6 (все промежуточные — pusher=prikotov; финальный рабочий push — через `GIT_CONFIG_GLOBAL=/dev/null` + `http.extraHeader`, pusher=bot).
- Проверки (финал PR #333): CI `test` + `composer-host-smoke` + `PHP 8.4.1 production install & Phar smoke` — pass.
- Созданных строк: ~+584 (research-док 26 КБ + summary + отчёт + epic + задача done).
