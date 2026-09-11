# Исследование T3 Code как мета-оркестратора агент-харнесов

**Роль:** Аналитик (Шерлок)  
**Дата:** 2026-09-11  
**Объект:** `pingdotgg/t3code`, ветка `main`, commit `0a37240a87bb2fed8f48cf20dc8312ee3b94eba6`, release `v0.0.40`  
**Задача:** [TASK-research-t3-code](../../../../todo/TASK-research-t3-code.todo.md), эпик [EPIC-research-orchestration-articles](../../../../todo/EPIC-research-orchestration-articles.todo.md)

---

## Резюме

T3 Code — контрольная поверхность над Claude Code, Codex, Cursor, Grok Build, OpenCode и Google Antigravity, а не собственный агент. Сервер оставляет процессы, рабочую область, Git и учётные данные на машине-владельце; web-, desktop- и mobile-клиенты управляют окружением через аутентифицированный RPC-контракт (контракт удалённых вызовов).

Исследование выполнено по шести критериям эпика. Оценка — **18/18**. Итоговый вердикт не монолитный:

- `apply` (применить): нормализованная граница харнеса с явными возможностями; BYO subscription (использование существующей подписки) как продуктовый инвариант;
- `study` (изучить): worktree на конкурентное исполнение, долговечное управление потоком/сессией, уровни совместимости продолжения, удалённое управление и нормализованные режимы разрешений;
- `skip` (не переносить): one-button PR (единая кнопка commit/push/PR) как часть текущего движка без пользовательского интерфейса.

## Ключевые находки

1. **Контракт T3 Code шире нашего.** `ProviderAdapter` нормализует старт/остановку сессии, ход, события, подтверждения, вопросы, чтение/откат истории и capabilities (возможности). Наш `AgentRunnerInterface` нормализует однократный `run()` и итоговый `AgentResultVo`.
2. **Поток и сессия разделены.** Долговечный thread (поток) переживает выход процесса provider; session (сессия) является подключаемым runtime (исполняемой средой).
3. **Намерение фиксируется до эффекта.** Команда сериализуется, чистый `decider` формирует событие, а побочный эффект выполняется после транзакционной фиксации. Command receipt (квитанция команды) обеспечивает идемпотентность.
4. **Branch не равен изоляции.** Маркетинг обещает ветку на поток, но документация допускает основной checkout и отдельный worktree. Для параллельных пишущих сабагентов нужен именно отдельный worktree и контракт владения.
5. **Смена харнеса не универсальна.** T3 Code поддерживает смену модели по capability и совместимый экземпляр того же driver (вида интеграции), но явно запрещает смену на другой driver после начала потока. Наш fallback передаёт текстовый `previousContext`, а не нативную историю.
6. **Режим автономии не заменяет правила проекта.** T3 Code применяет четыре режима с провайдерскими различиями. В task-orchestrator обязательное подтверждение merge и ограничения Git должны оставаться неизменяемым верхним пределом.

## Маппинг паттернов

| Паттерн | Компоненты task-orchestrator | Вердикт | Effort |
|---|---|---|---|
| Нормализация харнеса | `AgentRunnerInterface`, request/result VO, registry, Pi/Codex parsers, `config/chains.yaml` | `apply` | Medium |
| Branch/worktree на поток | `AgentRunRequestVo.workingDir`, `RunStaticChainService`, `run-subagent`, Git workflow | `study` | High |
| Единая операция PR | `GitIdentity`, `agent-identity.md`, `pull-request.md`, `AGENTS.md` | `skip` для движка | High |
| Управление потоком/сессией | `DynamicLoopSessionStateVo`, `ChainSessionWriter`, `OrchestrateChainCommandHandler` | `study` | High |
| Смена runner/model | `ChainStepVo`, `RoleConfigVo`, `ExecutionFallbackConfigVo`, `ResolveChainRunnerService` | `study` | Medium/High |
| Удалённое управление | `apps/console/`, события оркестрации, сессии `DynamicLoop` | `study` | High |
| Использование своей подписки | `roles.<role>.command`, `run-subagent`, локальные Pi/Codex runner | `apply` | Low |
| Режимы разрешений | флаги в `config/chains.yaml`, правила `AGENTS.md`, `AgentRunRequestVo.tools` | `study` | Medium/High |

## Проверка гипотез тимлида

| # | Результат | Вывод |
|---:|---|---|
| 1 | **Подтверждена** | T3 Code — мета-оркестратор; нормализованный контракт, capabilities и разделение driver/instance релевантны нам. |
| 2 | **Частично подтверждена** | Изоляция применима, но параллельным сабагентам нужен worktree, а не только branch; единая кнопка PR относится к Presentation. |
| 3 | **Частично опровергнута** | Смена модели и совместимого аккаунта возможна, кросс-харнесная смена driver в начатом потоке запрещена; наш fallback не сохраняет нативную сессию. |
| 4 | **Подтверждена** | Remote control (удалённое управление) — отдельный будущий продуктовый и security-трек. |
| 5 | **Подтверждена** | Оба продукта используют авторизацию и подписку локального runner, не вводя собственный модельный биллинг. |
| 6 | **Подтверждена с оговоркой** | Нормализация разрешений полезна, но выбираемый режим не может ослабить правила проекта и человеческий барьер merge. |

Итого: четыре гипотезы подтверждены, одна подтверждена частично, одна частично опровергнута; полностью опровергнутых нет.

## Артефакты

- `docs/research/orchestration-articles/t3-code-research.md` — полный отчёт по шести критериям, восьми паттернам и гипотезам.
- `docs/research/orchestration-articles-summary.md` — строка #2 в обеих таблицах, счётчик 2 / N и предварительные тренды.
- `docs/agents/reports/system-analyst/2026-09-11_t3-code-research.md` — данный самодостаточный аналитический отчёт.
- `todo/TASK-research-t3-code.todo.md` — прогресс, фактическая стоимость и история изменений.

## Проверки

- `php vendor/bin/todo-md validate todo/TASK-research-t3-code.todo.md` — успешно, 0 ошибок; два предупреждения о формате исходных полей `author` и `assignee`.
- `php vendor/bin/todo-md validate todo/EPIC-research-orchestration-articles.todo.md` — успешно, 0 ошибок и 0 предупреждений.
- `composer validate-docs` — успешно, все проверки конвенций документации пройдены.
- `make validate-language` — завершено в режиме предупреждений; новые файлы порог не нарушают, десять предупреждений относятся к существующим release-документам.
- `vendor/bin/validate-md-links` — успешно, все внутренние ссылки в 408 Markdown-файлах валидны. Composer-обёртка этой команды указывает на устаревший путь, поэтому использован установленный исполняемый файл.
- Дополнительная локальная проверка относительных Markdown-ссылок трёх изменённых документов — успешно.
- `git diff --check` — успешно.
- PHPUnit не запускался: изменения ограничены `docs/` и `todo/`, код, конфигурация и скрипты не затронуты.
- Psalm не запускался по той же причине.
- Commit и push не выполнялись по условиям задачи.

## Источники

- https://github.com/pingdotgg/t3code/blob/main/README.md
- https://github.com/pingdotgg/t3code/tree/main/docs/user
- https://github.com/pingdotgg/t3code/blob/main/docs/internals/overview.md
- https://github.com/pingdotgg/t3code/blob/main/docs/internals/providers.md
- https://t3.codes/
