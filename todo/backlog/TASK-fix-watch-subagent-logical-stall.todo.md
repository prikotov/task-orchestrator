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

# TASK-fix-watch-subagent-logical-stall: Logical-stall детектор для паттерна зависания "молчание pi"

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

`watch-subagent.sh` имеет **два паттерна** зависания pi:

**(A) Молчание после tool-result** — pi отправляет tool-result в LLM-провайдера
(zai/glm и др.) и ждёт ответа, который не приходит (обрыв прокси / timeout
провайдера). Pi замолкает. Дешёвый по токенам (~20K на зависание), но долгий
по времени. `stall-timeout` (180с) должен ловить, но `read -t` на pipe с
pi **блокирует бесконечно, не таймаутит** (микро-тест с subshell-writer
работает, с реальным pi — нет). Без external bash-kill pi висел бы вечно.

**(B) Бесконечная активность** — pi крутит turn'ы без лимита (78 turn'ов за
22 мин в одном зависании, 7M total tokens, ~166K billable), не "решая"
остановиться — модель дотошно перепроверяет/дорабатывает. **Уже закрыт
`soft-timeout` в PR #266: soft теперь убивает (доказано smoke-тестом — 22с
вместо 180с, 3 turn'а вместо десятков).**

Эта задача — про паттерн A. Паттерн B уже решён.

### Варианты или путь решения (Solution Sketch)
Нужен polling-loop, который работает несмотря на блокировку `read -t` на pipe
с pi. Попытка через `while IFS= read -r -t POLL_INTERVAL line` НЕ удалась
(реализовано и откачено в PR #266): `read -t` игнорирует timeout, когда pi
молчит. Возможные подходы:

1. **`coproc` / фоновый reader-процесс**: отдельный подпроцесс читает pipe
   построчно и пишет в файл/переменную; основной цикл `sleep N && проверка
   таймаутов`. Обходит блокировку `read`.
2. **`timeout` команда как обёртка над read**: `timeout POLL_INTERVAL bash -c
   'read line' < pipe` (нужна обёртка, т.к. `read` — builtin). Порождает
   subprocess на каждой итерации, но проверено работоспособно.
3. **Исследование поведения `read -t` с pi**: возможно, pi шлёт TCP-keepalive-
   байты без newline, и `read` не таймаутит. Перевод pi в небуферизованный
   режим может решить.

### Ожидаемый результат (Expected Result)
Зависание pi паттерна A ("молчание после tool-result") ловится за N сек
(настраиваемое, default ~60 сек), с `reason=logical_stall` в run-log и архивом
улик. Существующее поведение `soft-timeout` (паттерн B) и `stall-timeout`
сохраняются.

## 1. Context and Scope (Контекст и Границы)

### Блокер, обнаруженный при реализации (2026-06-15/16)

Polling-loop реализован (`read -t POLL_INTERVAL` 10 сек + проверки на каждой
итерации), но **не работает для паттерна A**: после того как pi замолкает
(после `tool_execution_end`), `read -t 10` **блокирует бесконечно**, игнорируя
`-t`. Debug-логирование показало: poll-итерации идут на активной части (1-8
сек), потом полностью прекращаются до bash-kill.

Микро-тест `read -t 3` на FIFO с subshell-writer `( exec 3>pipe; sleep 10 )`
работает корректно (выходит через 3 сек). Значит проблема специфична для
**pi (node-процесс) как writer**: вероятно pi шлёт TCP-keepalive-байты в pipe
без newline, либо держит fd иначе, и bash `read` не таймаутит.

### Scope (Границы)
* **Где делаем:** `docs/agents/skills/run-subagent/scripts/watch-subagent.sh`.
* **В скопе:** реализация logical-stall для паттерна A, работающая с
  pi/codex runner'ами; smoke-тест на заведомо зависающей read-tool задаче.
* **Out of scope:** изменение поведения soft/stall/hard-timeout; изменение
  CLI-контракта (`# CONTRACT: v1`); правка pi-процесса (внешний инструмент).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [ ] Logical-stall детектор срабатывает на паттерне A (молчание pi после
      tool-result) за N сек (default 60, env `WATCH_LOGICAL_STALL`).
- [ ] `reason=logical_stall` фиксируется в run-log.
- [ ] Существующий soft-timeout (паттерн B) сохранён.
- [ ] Smoke-тест: запуск read-tool-задачи с `WATCH_LOGICAL_STALL=20` → kill с
      `reason=logical_stall` за ~20-30 сек (а не висение до external kill).

### 🟡 Should Have (Желательно)
- [ ] Объяснение в комментарии/документации, ПОЧЕМУ простой `read -t
      POLL_INTERVAL` не работает с pi (ссылка на debug-лог и микро-тест).
- [ ] Опция отключения: `WATCH_LOGICAL_STALL=0`.

## 4. Verification (Самопроверка)
```bash
# Паттерн A (молчание): должен убить за ~25 сек с reason=logical_stall:
WATCH_LOGICAL_STALL=20 docs/agents/skills/run-subagent/scripts/watch-subagent.sh \
  -s 200 -m 300 -o text \
  -r docs/agents/roles/team/backend_developer_levsha.ru.md <<'P'
Прочитай файл docs/agents/skills/run-subagent/SKILL.md (используй read) и ответь одним предложением.
P
grep reason= var/log/watch-subagent/*.log | tail -1   # → reason=logical_stall
```

## 5. Risks and Dependencies (Риски и зависимости)
- **Главный риск:** повторное обнаружение, что polling-loop блокируется на
  pipe с pi. Нужен РЕАЛЬНО рабочий обход (coproc/timeout-обёртка), а не
  `read -t`.
- **Back-compat:** нельзя ломать contract v1, нельзя убивать здоровые прогоны
  (Gandalf думал 132-183 сек — logical-stall default должен быть ≥ 200 сек,
  если не переопределён).
- **Тестирование:** сложно воспроизвести зависание детерминированно (зависит
  от провайдера). Smoke-тест на read-tool задаче воспроизводим на zai/glm,
  но может не воспроизводиться на других провайдерах.

## 6. Sources (Источники)
- PR #266: наблюдаемость + soft-timeout kill (паттерн B) — `fix/watch-subagent-hang-observability`.
- Ретроспектива: `docs/agents/team-retro/2026-06-15_23-17-post-roadmap-cleanup.md`.
- Debug-лог polling-loop: показал прекращение poll-итераций после молчания pi.
- Микро-тест `read -t` на FIFO с subshell-writer: работает (выход через 3 сек)
  — значит блокировка специфична для pi как writer.
- Улики зависаний (только паттерн B найден в /tmp): 78 turn'ов / 7M токенов.

## 7. Comments (Комментарии)
Задача — прямой follow-up к PR #266. В PR #266 закрыт паттерн B (бесконечная
активность, самый дорогой по токенам — 7M на зависание). Эта задача закрывает
паттерн A (молчание, дешёвый по токенам ~20K, но долгий по времени и портит
пользовательский опыт ожиданием).

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-16 | Тимлид (Алекс) | Создание задачи как follow-up к PR наблюдаемости watch-subagent |
| 2026-06-16 | Тимлид (Алекс) | Уточнение: разделение на паттерн A (эта задача) и паттерн B (уже закрыт soft-timeout в PR #266) |
