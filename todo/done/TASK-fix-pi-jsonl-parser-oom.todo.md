---
# Metadata (Метаданные)
type: fix
created: 2026-06-30
value: V3
complexity: C4
priority: P2
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер Левша (backend_developer_levsha)
branch: task/fix-pi-jsonl-parser-oom
pr: '#285'
status: done
---

# TASK-fix-pi-jsonl-parser-oom: корневой streaming-fix JSONL runner output OOM

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
`PiAgentRunnerService` и `CodexAgentRunnerService` запускали процесс через `Process::run()`, затем брали весь stdout через `Process::getOutput()` и отдавали одну огромную строку в JSONL-парсер. На длинных dynamic-сессиях это приводило к OOM: stdout целиком жил в памяти/буферах, затем дополнительно разбирался построчно.

Подтверждённый root cause: streaming-deltas составляют большую часть вывода runner'ов, но для результата не нужны. Нужны только финальные события:
- pi: `agent_end` для текста и `message_end` для usage;
- codex: `item.completed` для текста и `turn.completed` для usage.

Streaming нужен только как liveness-сигнал: пришла строка — агент жив.

### Варианты или путь решения (Solution Sketch)
Паллиатив `JsonlLineReader` + `memory_limit=1G` отменён. Корневой фикс — читать stdout процесса по мере поступления:

`Process::start(callback)` → буферизация чанков до `\n` → `parser->feed($line)` → `Process::wait()` → flush последней строки без `\n` → `parser->result()`.

Парсеры становятся stateful streaming-парсерами и хранят только итоговый shape результата.

### Ожидаемый результат (Expected Result)
Длинный JSONL-stdout не материализуется одной строкой через `getOutput()`, streaming-deltas не накапливаются как результат, memory_limit-паллиатив удалён. Пустой output не крашит runner; DynamicLoop часть B продолжает нормализовать пустой успешный participant-output в ошибку и писать `_4_error.md`.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> Когда dynamic-сессия накапливает длинный JSONL-вывод runner'а, я хочу, чтобы приложение читало поток инкрементально и извлекало только финальный результат, чтобы длинные цепочки доходили до синтеза без `OutOfMemoryError`.

### Goal (Цель по SMART)
Устранить OOM в `pi/codex` JSONL runner output корневым streaming-fix за одну задачу: убрать чтение всего stdout через `getOutput()`, заменить whole-string парсинг на `feed()/result()`, удалить паллиативы (`JsonlLineReader`, `memory_limit=1G`) и покрыть edge cases тестами. Проверка: `make check`.

## Root Cause Analysis — Часть A (OOM / stdout)
- `Process::run()` + `Process::getOutput()` материализуют весь stdout процесса перед разбором.
- JSONL-парсеры раньше проходили по whole-string output ради финальных событий.
- Streaming-deltas (`message_update`, `toolcall_delta`, `thinking_delta`, `text_delta` у pi; incremental-события у codex) не нужны для итогового результата и должны игнорироваться или использоваться только как ограниченный fallback.
- Корневой фикс должен работать на уровне runner'а: читать stdout чанками и кормить parser строками.

## Root Cause Analysis — Часть B (пустые ответы)
Часть B уже реализована предыдущей правкой и не входит в текущий streaming-scope:
- `ExecuteDynamicTurnService` нормализует пустой успешный participant-output в ошибку;
- `ChainSessionWriter::logRound()` пишет error artifact `_4_error.md`;
- текущая задача не меняет эту логику, чтобы не смешивать streaming-fix и поведение dynamic loop.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `src/Module/AgentRunner/Infrastructure/Service/Pi/PiJsonlParser.php`
    *   `src/Module/AgentRunner/Infrastructure/Service/Codex/CodexJsonlParser.php`
    *   `src/Module/AgentRunner/Infrastructure/Service/Pi/PiAgentRunnerService.php`
    *   `src/Module/AgentRunner/Infrastructure/Service/Codex/CodexAgentRunnerService.php`
    *   `src/Module/AgentRunner/Resource/config/services.yaml`
    *   `bin/console`, `bin/task-orchestrator`
*   **Текущее поведение:** runner'ы читают process output целиком, парсеры принимают whole string.
*   **Границы (Out of Scope):**
    *   DynamicLoop часть B — не менять, если не мешает streaming.
    *   `HttpsProxyBridge` и его тесты — не менять.
    *   `ModuleInterface.php`, `Makefile`, `psalm.xml`, окруженческие skip/workaround — не менять.
    *   Изменение Domain-контрактов AgentRunner — не требуется.

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- [x] `PiJsonlParser` имеет `feed(string $line): void` и `result(): array`; shape результата сохранён: `outputText`, `inputTokens`, `outputTokens`, `cacheReadTokens`, `cacheWriteTokens`, `cost`, `model`, `turns`.
- [x] `PiJsonlParser` извлекает текст из `agent_end`, usage из `message_end`; `text_delta` используется только как fallback при отсутствии `agent_end` (через `php://temp` stream, закрывается при `agent_end`).
- [x] `CodexJsonlParser` имеет `feed(string $line): void` и `result(): array`; shape результата сохранён, включая `reasoningOutputTokens`.
- [x] `CodexJsonlParser` извлекает текст из `item.completed`, usage из `turn.completed`; старый `turn.items` формат допускается как fallback из финального события.
- [x] `PiAgentRunnerService` и `CodexAgentRunnerService` не вызывают `Process::run()` + `getOutput()` для результата; stdout читается потоково через `Process::start()` callback + `clearOutput()`.
- [x] Чанки stdout буферизуются по `\n`; последняя строка без завершающего `\n` flush'ится после `wait()`.
- [x] CRLF (`\r\n`) корректно обрабатывается.
- [x] Empty stdout (0 строк) возвращает empty output без crash.
- [x] Non-zero exit / killed-before-final-event не крашит runner; возвращается `AgentResultVo::createError()` (`ProcessSignaledException` обработан).
- [x] `JsonlLineReader` удалён, DI-регистрация удалена.
- [x] `ini_set('memory_limit', '1G')` удалён из `bin/console` и `bin/task-orchestrator`.
- [x] Unit-тесты покрывают parser `feed()/result()` и большой объём ignored/incremental deltas без накопления в PHP-строке.
- [x] Runner-тесты покрывают chunk boundary, CRLF, final line without newline, empty output, process error before final event.

### 🟡 Should Have (Желательно)
- [ ] В отчёте явно описать streaming-архитектуру и удалённые паллиативы.

### 🟢 Could Have (Опционально)
- [ ] В будущем выделить общий stream-buffer service, если появится третий JSONL runner.

### ⚫ Won't Have (Не будем делать)
- [ ] Не менять DynamicLoop часть B.
- [ ] Не добавлять environment/socket skips и другие workaround'и под локальное окружение.
- [ ] Не менять `HttpsProxyBridge` в рамках этой задачи.

## 4. Implementation Plan (План реализации)
1. [ ] Перевести `PiJsonlParser` на stateful `feed()/result()`.
2. [ ] Перевести `CodexJsonlParser` на stateful `feed()/result()`.
3. [ ] В `PiAgentRunnerService` заменить `run()+getOutput()` на `start(callback)+wait()` с line-buffer.
4. [ ] В `CodexAgentRunnerService` заменить `run()+getOutput()` на `start(callback)+wait()` с line-buffer, сохранив HTTPS-proxy bridge lifecycle.
5. [ ] Удалить `JsonlLineReader` и регистрацию в `services.yaml`.
6. [ ] Удалить `ini_set('memory_limit', '1G')` из CLI entry points.
7. [ ] Обновить unit-тесты парсеров и runner'ов на edge cases.
8. [ ] Запустить `make check` и честно зафиксировать результат.

## 5. Definition of Done (Критерии приёмки)
- [x] Runner'ы больше не материализуют stdout через `getOutput()` для успешного результата.
- [x] Parser result shape не изменён.
- [x] Edge cases из требований покрыты тестами.
- [x] `JsonlLineReader` и `memory_limit` паллиативы удалены.
- [x] `make check` зелёный (окружение проекта: 1261 тестов, 3395 assertions, 0 skipped; Psalm/PHPStan/PHPMD/Deptrac/PHPCS чисто).
- [x] Code review (Ревьювер Бэка Пуаро): Approval без блокеров.

## 6. Verification (Самопроверка)
```bash
make check
```

## 7. Risks and Dependencies (Риски и зависимости)
- Stateful parser требует обязательного `reset()` перед каждым запуском runner'а; runner'ы должны выполнять reset до `Process::start()`.
- `text_delta` fallback у pi нужен только для edge case без `agent_end`; он не должен дублировать итоговый outputText при наличии `agent_end`.
- Symfony Process всё равно имеет внутренний output buffer; callback после обработки чанка должен очищать `clearOutput()/clearErrorOutput()`, чтобы не держать весь stdout/stderr.
- Локальные socket-ограничения могут ломать существующие HTTPS-proxy bridge tests; по задаче их нельзя маскировать skip'ами.

## 8. Sources (Источники)
- `docs/conventions/index.md`
- `docs/conventions/principles/code-style.md`
- `docs/conventions/core-patterns/service.md`
- `docs/guide/architecture.md`
- `todo/AGENTS.md`

## 9. Comments (Комментарии)
Reverse briefing: задача теперь не про post-factum построчный reader и не про поднятие лимита памяти, а про streaming на границе process stdout. Часть B оставлена как уже выполненная и не расширяется.

### Архитектурное замечание (code review Пуаро) — input для будущей задачи parallel execution
Stateful-парсеры (`PiJsonlParser`, `CodexJsonlParser`) регистрируются через autowire → shared (singleton). При текущем **синхронном** execution это безопасно: `reset()` вызывается перед каждым `run()`, stale state очищается. **Риск возникнет при будущем DAG/parallel execution** (`TASK-feat-parallel-execution` / `TASK-feat-dag-orchestration`): при параллельных `run()` одного shared-сервиса в одном PHP-процессе parser'ы могут перемешать состояние. Решение на будущее: parser-per-run, factory или non-shared wiring. **Обязательно учесть в дизайне parallel execution.**

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-30 | Тимлид (Алекс) | Создание задачи по итогам brainstorm-сессии 2026-06-30 (OOM) и ретро 2026-05-13 (висящий Action Item) |
| 2026-06-30 | Бэкендер Левша (backend_developer_levsha) | Переформулировал задачу с паллиатива на корневой streaming-fix через `feed()/result()` и `Process::start()` |
