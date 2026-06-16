---
type: fix
created: 2026-06-16
value: V2
complexity: C3
priority: P3
depends_on: []
epic:
author: Тимлид (Алекс)
assignee:
branch:
pr:
status: backlog
---

# TASK-fix-watch-subagent-logical-stall: Logical-stall детектор зависаний pi после tool-result

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
`watch-subagent.sh` использует `stall-timeout` (180 сек без событий) как единственный детектор зависания агента. Но pi (через zai/glm и др. провайдеров) регулярно зависает **после `tool_execution_end`**: отправляет tool-result в LLM-провайдера и ждёт ответа, который не приходит (обрыв прокси / timeout провайдера). В этом состоянии pi **молчит** — `stall-timeout` ловит это, но это ~3 минуты мёртвого ожидания на каждом зависании. Наблюдаемость (run-log + архив TMPDIR) добавлена в PR #<TODO>, но сама задержка не устранена.

Хотелся бы «logical-stall»: убивать по отсутствию **значимых** событий (`message_end`/`tool_*`/`turn_*`/`agent_end`, не инкрементальных `message_update`) за N сек (например 30-60 сек), чтобы зависание после tool-result ловилось за минуту, а не за три.

### Варианты или путь решения (Solution Sketch)
Нужен polling-loop: `read` с малым интервалом опроса (POLL_INTERVAL, ~10 сек) + проверка всех таймаутов на каждой итерации. Реализован и откачен в этом PR — упёрся в блокер (см. ниже).

### Ожидаемый результат (Expected Result)
Зависание pi после `tool_execution_end` ловится за N сек (настраиваемое, default ~60 сек), с `reason=logical_stall` в run-log и архивом улик. Существующее поведение `stall-timeout` (полное молчание) сохраняется как fallback.

## 1. Context and Scope (Контекст и Границы)

### Блокер, обнаруженный при реализации (2026-06-15)

Polling-loop реализован (`read -t POLL_INTERVAL` 10 сек + проверки на каждой итерации), но **не работает**: после того как pi замолкает (после `tool_execution_end`), `read -t 10` **блокирует бесконечно**, игнорируя `-t`. Debug-логирование показало: poll-итерации идут на активной части (1-5 сек), потом полностью прекращаются до bash-kill.

Микро-тест `read -t 3` на FIFO с subshell-writer `( exec 3>pipe; sleep 10 )` работает корректно (выходит через 3 сек). Значит проблема специфична для **pi (node-процесс) как writer**: вероятно pi шлёт TCP-keepalive-байты в pipe без newline, либо держит fd иначе, и bash `read` не таймаутит.

### Возможные подходы (на оценку исполнителю)
1. **`coproc` / фоновый reader-процесс**: отдельный подпроцесс читает pipe построчно и пишет в файл/переменную; основной цикл `sleep N && проверка таймаутов`. Сложнее, но обходит блокировку `read`.
2. **`timeout` команда как обёртка над read**: `timeout POLL_INTERVAL read line < pipe`. Порождает подпроссы на каждой итерации, но проверено работоспособно.
3. **Исследование `read -t` поведения с pi**: возможно, проблема в buffering или в том, как pi пишет в stderr/stdout. Может, перевод pi в небуферизованный режим решает.
4. **Внешний watchdog-процесс**: отдельный таймер-демон, убивающий основной скрипт по logical-stall.

## 2. Scope (Границы)
* **Где делаем:** `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`.
* **В скопе:** реализация logical-stall, работающая с pi/codex runner'ами; smoke-тест на заведомо зависающей задаче (read-tool).
* **Out of scope:** изменение поведения stall/hard/soft-timeout; изменение CLI-контракта (`# CONTRACT: v1`); правка pi-процесса (внешний инструмент).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Logical-stall детектор срабатывает на зависании pi после `tool_execution_end` за N сек (default 60 сек, env `WATCH_LOGICAL_STALL`).
- [ ] `reason=logical_stall` фиксируется в run-log.
- [ ] Существующий stall-timeout (полное молчание) сохранён как fallback.
- [ ] Smoke-тест: запуск read-tool-задачи (заведомо зависает) с `WATCH_LOGICAL_STALL=20` → kill с `reason=logical_stall` за ~20-30 сек.

### 🟡 Should Have (Желательно)
- [ ] Объяснение в комментарии/документации, ПОЧЕМУ простой `read -t POLL_INTERVAL` не работает с pi (ссылка на debug-лог).
- [ ] Опция отключения: `WATCH_LOGICAL_STALL=0`.

## 4. Verification (Самопроверка)
```bash
# Должен убить за ~25 сек с reason=logical_stall (не external_signal!):
WATCH_LOGICAL_STALL=20 docs/agents/skills/run-subagent/scripts/watch-subagent.sh -s 200 -m 300 -o text \
  -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'P'
Прочитай файл docs/agents/skills/run-subagent/SKILL.md (используй read) и ответь одним предложением.
P
# Проверка run-log:
grep reason= var/log/watch-subagent/*.log | tail -1   # → reason=logical_stall
```

## 5. Risks and Dependencies (Риски и зависимости)
- **Главный риск:** повторное обнаружение, что polling-loop блокируется на pipe с pi. Нужен РЕАЛЬНО рабочий обход (coproc/timeout-обёртка), а не `read -t`.
- **Back-compat:** нельзя ломать contract v1, нельзя убивать здоровые прогоны (Gandalf думал 132-183 сек — logical-stall default должен быть ≥ 200 сек, если не переопределён).
- **Тестирование:** сложно воспроизвести зависание детерминированно (зависит от провайдера). Smoke-тест на read-tool задаче воспроизводим на zai/glm, но может не воспроизводиться на других провайдерах.

## 6. Sources (Источники)
- Наблюдаемость (PR #<TODO>): run-log + gaps.tsv + архив TMPDIR — добавлены в этом PR.
- Ретроспектива: `docs/agents/team-retro/2026-06-15_23-17-post-roadmap-cleanup.md` (как приоритет инфраструктуры делегирования).
- Debug-лог polling-loop: показал прекращение poll-итераций после молчания pi.
- Микро-тест `read -t` на FIFO с subshell-writer: работает (выход через 3 сек) — значит блокировка специфична для pi как writer.

## 7. Comments (Комментарии)
Задача заведена как прямой follow-up к PR наблюдаемости (`fix/watch-subagent-hang-observability`). Наблюдаемость РЕШАЕТ «понимать что происходит»; logical-stall РЕШИТ «не ждать 3 минуты». Без наблюдаемости (раньше) зависания происходили вслепую; с наблюдаемостью (сейчас) они диагностируемы; с logical-stall (эта задача) будут быстро убиваться.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-16 | Тимлид (Алекс) | Создание задачи как follow-up к PR наблюдаемости watch-subagent |
