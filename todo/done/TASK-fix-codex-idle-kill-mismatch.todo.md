---
# Metadata (Метаданные)
type: fix
created: 2026-08-17
value: V2
complexity: C3
priority: P2
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Бэкендер Левша (pi)
branch: task/fix-codex-idle-kill-mismatch
pr: https://github.com/prikotov/task-orchestrator/pull/352
status: done
---

# TASK-fix-codex-idle-kill-mismatch: Согласовать общие пороги простоя AI-сабагентов со штатным ожиданием и восстановлением моделей

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Автоматические пути запуска AI-сабагентов (`watch-subagent.sh` и PHP-раннер `agent:run`) могут убить процесс раньше штатного ожидания или восстановления модели. Модели любых провайдеров способны молчать до ~5 минут в таких фазах, а прежние общие пороги считали это сбоем: watch-subagent убивал по stall через 180s, PHP-раннер по idle через 60s. В результате живые сессии терминируются, а ретраи `RetryingAgentRunner` стартуют всё с нуля. Кроме того, watch-subagent запускал codex с `--ephemeral`, поэтому rollout-журналы не сохранялись для посмертного разбора.

### Варианты или путь решения (Solution Sketch)
- Поднять общие для всех раннеров пороги убийства по простою: stall_timeout по умолчанию 360s в watch-subagent и idle-порог PHP `AGENT_RUNNER_IDLE_TIMEOUT_SEC` 330s; явный `-t` остаётся пользовательским override (переопределением).
- Убрать `--ephemeral` из запуска codex в watch-subagent (опционально вернуть через env-переключатель) — сохранить rollout-журналы для разбора падений.
- Добавить codex флаг `-o/--output-last-message` — финальный ответ агента попадает в файл даже при смерти стрима.

### Ожидаемый результат (Expected Result)
Codex-сабагенты переживают сетевые паузы 3–5 минут своими штатными автоповторами вместо преждевременного убийства нашей инфраструктурой. При реальном падении сессии сохраняются rollout-журналы codex (`~/.codex/sessions`) и финальное сообщение агента — инцидент можно разобрать по фактам. Интерактивный TUI-опыт (где codex работает круглосуточно без таких проблем) и автоматический — выравниваются.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда сеть молчит 1–5 минут во время работы codex-сабагента, я хочу, чтобы инфраструктура запуска (watch-subagent.sh и PHP-раннер) не убивала процесс раньше его собственных автоповторов, чтобы живые сессии доживали до восстановления, а действительно погибшие — оставляли улики (rollout, финальное сообщение) для разбора.

### Goal (Цель по SMART)
Установить общие пороги простоя обоих путей запуска: stall 360s в watch-subagent и idle 330s в PHP для всех раннеров; убрать `--ephemeral` по умолчанию для codex и добавить сохранение финального сообщения. Покрыть unit-тестами общую настройку порога.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `docs/agents/skills/run-subagent/scripts/watch-subagent.sh` — `STALL_TIMEOUT=180` (строка ~75), codex-ветка `build_runner_command` с `--ephemeral` (строка ~370).
    *   `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php` — общий `AGENT_RUNNER_IDLE_TIMEOUT_SEC` (default 60) для всех раннеров.
    *   `docs/agents/skills/run-subagent/SKILL.md`, `docs/guide/codex.md` — документация таймаутов/env.
*   **Факты-основания (исходники openai/codex, версия 0.147.0):**
    *   `codex-rs/model-provider-info/src/lib.rs:25-28`: `DEFAULT_STREAM_IDLE_TIMEOUT_MS = 300_000`, `DEFAULT_STREAM_MAX_RETRIES = 5`, `DEFAULT_REQUEST_MAX_RETRIES = 4` — codex молча ждёт 5 минут тишины стрима до начала ретраев.
    *   `codex-rs/core/src/responses_retry.rs:70-95`: исчерпание websocket-ретраев → фолбэк на HTTPS-транспорт, счётчик сбрасывается; первый ретрай скрыт из вывода by design (`report_error = retry_count > 1`) — в `--json` тишина выглядит как зависание.
    *   `codex-rs/utils/cli/src/shared_options.rs:56`: `--yolo` — скрытый алиас `--dangerously-bypass-approvals-and-sandbox` (наш флаг равнозначен).
*   **Инциденты-основания:**
    *   `var/log/watch-subagent/20260815_171541-codex-*`: убит вотчером на `stall_gap=183s` при лимите 180s — до истечения 300s codex-idle; `live=idle` (процесс жив, ждёт сеть).
    *   `var/log/prod.log`: 16 убийств `Agent idle: no CPU/IO progress for 60 seconds` для codex, включая серию `attempt 1/3 → 3/3 → exhausted` 2026-07-08/09.
    *   `20260815_190051` / `20260815_191101`: молчаливые exit 1 — причина не установлена именно из-за `--ephemeral` (rollout стёрт).
    *   Успешные codex-запуски: максимальные паузы событий 44–84s (ниже порогов — ложных срабатываний у успеха не наблюдалось).
*   **Границы (Out of Scope):**
    *   Починка самих сетевых обрывов / прокси-инфраструктуры (`CODEX_HTTP_PROXY`, мост) — не трогаем.
    *   Расследование молчаливых exit 1 сессий 2026-08-15 — отдельная работа при повторении (эта задача только сохраняет улики).
    *   Повышение hard-cap (`AGENT_RUNNER_HARD_TIMEOUT_SEC=1800`, `--hard-timeout` 900s) — остаётся как есть.
    *   Изменение формата JSON-событий pi/codex и контракта `--output`.
    *   Изменение правил выбора модели или провайдера — не трогаем.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Блокирующие требования)
- [x] **watch-subagent.sh: общий stall-порог.** Значение по умолчанию — 360s для всех раннеров; явный `-t/--stall-timeout` пользователя применяется без клампинга.
- [x] **watch-subagent.sh: убрать `--ephemeral`** из codex-запуска по умолчанию; опциональный возврат через env (например `WATCH_CODEX_EPHEMERAL=1`). Rollout-журналы codex (`~/.codex/sessions`) сохраняются.
- [x] **watch-subagent.sh: `-o <file>` (output-last-message)** для codex-раннера — финальный ответ агента пишется в файл даже при смерти стрима посреди хода.
- [x] **PHP: общий idle-порог.** И CodexAgentRunnerService, и PiAgentRunnerService используют `AGENT_RUNNER_IDLE_TIMEOUT_SEC` с default 330 через общий `ProcessLivenessWatcher`.
- [x] Unit-тесты: общий порог 330 и env-override работают.
- [x] Обновлена документация: `docs/agents/skills/run-subagent/SKILL.md` (таймауты codex, env-переключатели, rollout), `docs/guide/codex.md` если затронуто.

### 🟡 Should Have (Важные требования)
- [ ] Интеграционная проверка: процесс любого раннера в фазе штатного ожидания (сеть молчит) не убивается раньше выбранного порога; реальный зависший процесс — убивается.
- [x] Лог-строка `run.log`/watcher фиксирует выбранный stall-порог для любого раннера (наблюдаемость).
- [x] Отметка в run-summary, если сессия завершена с сохранённым rollout (файл-путь) — для быстрого поиска улик.

### 🟢 Could Have (Желательно)
- [x] Обосновать в `SKILL.md` общие пороги: модели любых провайдеров могут молчать до ~5 минут в фазе ожидания или восстановления.

### ⚫ Won't Have (Не в этот раз)
- [ ] Настройка `stream_idle_timeout`/`stream_max_retries` на стороне codex (через `-c` overrides) — только наши пороги убийства.
- [ ] Уборка/ротация `~/.codex/sessions` — вне репозитория.
- [ ] Фолбэк раннера на другого провайдера (`TASK-feat-runner-provider-fallback` в backlog).

## 4. Implementation Plan (План реализации)
1. [x] Разведка: `watch-subagent.sh` — где применяется `STALL_TIMEOUT` (главный `read -t`-цикл ~903 и watcher ~826).
2. [x] watch-subagent.sh: общий default stall 360s без клампинга и единое логирование значения.
3. [x] watch-subagent.sh: убрать `--ephemeral` (env-опция возврата), добавить `-o "$RUN_DIR/last_message.txt"` для codex.
4. [x] PHP: общий idle-порог 330 через один `ProcessLivenessWatcher` для обоих раннеров.
5. [x] Unit- и DI-тесты общего порога (минимум 80% покрытия нового кода).
6. [x] Обновить `SKILL.md` / `docs/guide/codex.md`.
7. [x] `make check` зелёный.

## 5. Definition of Done (Критерии приёмки)
- [x] Запуск любого раннера через watch-subagent с молчанием < 360s не терминируется по stall при значении по умолчанию.
- [x] Codex-запуск пишет rollout в `~/.codex/sessions` (нет `--ephemeral` по умолчанию); опциональный env-возврат задокументирован.
- [x] Финальное сообщение codex-агента сохраняется через `-o` даже при обрыве стрима.
- [x] PHP-раннер: общий idle-порог ≥ 330s для codex и pi; unit- и DI-тесты зелёные.
- [x] Документация обновлена (`SKILL.md`, при необходимости `docs/guide/codex.md`).
- [x] `vendor/bin/phpunit` и `vendor/bin/psalm` — зелёные; `make validate-todo` — зелёный.

## 6. Verification (Самопроверка)
```bash
# 1. Общий stall: default равен 360s, явный -t применяется без клампинга
grep -E "runner=|stall_timeout" var/log/watch-subagent/<new-run>/run.log

# 2. Rollout сохраняется: после codex-запуска появился свежий файл с originator codex_exec
ls -t ~/.codex/sessions/$(date +%Y/%m/%d)/ | head -2

# 3. last_message: файл создан после завершения codex-запуска
ls var/log/watch-subagent/<new-run>/last_message.txt

# 4. PHP общий idle
vendor/bin/phpunit tests/Unit/ --filter ProcessLiveness
grep -rn "AGENT_RUNNER_IDLE_TIMEOUT_SEC" src/ tests/

# 5. Общие проверки
make check
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Реально зависшие (не ожидающие штатного восстановления) сессии теперь живут дольше** до терминирования — растёт расход wall-clock на мёртвых запусках. Митигация: hard-cap не меняется (900s/1800s); stall всё ещё срабатывает после 360s.
- **Правка конструктора/контракта `ProcessLivenessWatcher`** — проверить backward-compat (обе точки вызова: pi/codex) и Deptrac.
- **Накопление rollout-файлов** в `~/.codex/sessions` (вне репозитория, диск пользователя) — упомянуть в документации; ротация вне scope.
- **Пользовательские настройки провайдера** могут изменить внутренний бюджет ожидания; общие пороги 360/330 покрывают типичную паузу около 5 минут, а более длинный бюджет пользователь согласует с `-t` и hard-лимитом.
- `--ephemeral` был добавлен коммитом `ab851ca` (2026-05-10) без явного обоснования — возврат через env оставляет обратную совместимость.

## 8. Sources (Источники)
- Исходники codex 0.147.0 (github.com/openai/codex): `codex-rs/model-provider-info/src/lib.rs` (константы бюджетов), `codex-rs/core/src/responses_retry.rs` (механика ретраев/фолбэка), `codex-rs/utils/cli/src/shared_options.rs` (алиас `--yolo`).
- `docs/agents/skills/run-subagent/scripts/watch-subagent.sh` — общий `STALL_TIMEOUT=360`, `--ephemeral`, `apply_proxy_env_defaults`.
- `src/Module/AgentRunner/Infrastructure/Service/ProcessLivenessWatcher.php`, `CodexAgentRunnerService.php`, `PiAgentRunnerService.php`.
- Инциденты: `var/log/watch-subagent/20260815_171541-codex-*` (stall-килл на 183s), `20260815_190051/191101` (молчаливые exit 1), `var/log/prod.log` (16 idle-убийств codex, серия 2026-07-08/09).
- Коммит `ab851ca` (добавление `--ephemeral` без обоснования).

## 9. Comments (Комментарии)
Задача родилась из расследования 2026-08-16/17 (диалог с владельцем): три смерти codex-сессий 15.08. Одна — доказанное преждевременное убийство нашим stall_timeout (183s < 300s внутреннего idle-бюджета codex). Две — молчаливые exit 1, причина неустановима из-за `--ephemeral` (rollout стёрт). Websocket-ошибки (`Proxy URL scheme not supported`) исключены как причина: присутствуют в успешных запусках, это шум канала usage-обновлений с фолбэком на HTTPS. Расследование причины exit 1 — при повторении, уже с сохранёнными rollout (эта задача обеспечивает улики).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-08-17 | Тимлид (Алекс) | Создание задачи по итогам расследования смертей codex-сессий 15.08 и анализа исходников codex 0.147.0. |
| 2026-08-17 | Тимлид (Алекс) | Реализовано через конвейер: Левша (реализация + доработки по self-review CR-1..3), Пуаро (ревью: блокер KernelTestCase + 3 неблокирующих → доработка → APPROVE). PR #352. PHPUnit 1493/3992 OK, Psalm 0, make check зелёный. N-2/N-4 (поведенческий stall-тест, флаки liveness) вынесены в отдельные задачи. |
| 2026-08-18 | Владелец | Решение: не выделять codex. Stall и idle-пороги общие для всех раннеров: 360s и 330s соответственно; удалены механика отдельных порогов и соответствующая env-переменная. |
