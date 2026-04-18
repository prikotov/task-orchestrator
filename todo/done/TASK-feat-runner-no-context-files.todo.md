---
type: feat
created: 2026-04-16
value: V3
complexity: C2
priority: P1
depends_on:
epic:
author: Бэкендер (Левша)
assignee: Бэкендер
branch: task/feat-runner-no-context-files-v2
pr: 'https://github.com/prikotov/task-orchestrator/pull/21'
status: done
---

# TASK-feat-runner-no-context-files: Поддержка -no-context-files флага в pi-runner

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда я запускаю агента через pi-runner, я хочу иметь возможность отключить автоматическую загрузку AGENTS.md / CLAUDE.md через флаг, чтобы агент работал как чистый LLM — без контекста проекта. Это позволяет использовать оркестратор для произвольных задач (генерация текстов, анализ данных, brainstorming) на любом проекте без необходимости писать/удалять context-файлы.

### Goal (Цель по SMART)
Добавить поддержку флага `-no-context-files` (`-nc`) в pi-runner (и опцию в цепочках), чтобы при запуске агента можно было отключить автоматическую загрузку контекстных файлов проекта. Фича доступна в pi-mono >= v0.67.4.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
  - `src/Module/Orchestrator/Infrastructure/Service/AgentRunner/` — pi-runner команда
  - `src/Module/Orchestrator/Domain/ValueObject/ChainStepVo.php` — модель шага
  - `config/chains.yaml.example` — пример конфигурации
  - `apps/console/src/Module/Orchestrator/Command/RunCommand.php` — CLI
*   **Текущее поведение:** pi-runner всегда загружает AGENTS.md / CLAUDE.md из рабочего директории
*   **Границы (Out of Scope):** Не трогаем другие runner'ы (codex и т.д.)

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Pi-runner передаёт `-no-context-files` (`-nc`) флаг в команду pi, когда опция включена
- [ ] Опция `no_context_files: true` поддерживается на уровне шага в chains.yaml
- [ ] Опция `--no-context-files` добавлена в `app:agent:run` CLI-команду
### 🟡 Should Have (Желательно)
- [ ] Глобальная опция `no_context_files` на уровне цепочки (наследуется шагами)
- [ ] Опция `--no-context-files` в `app:agent:orchestrate` CLI-команде
### 🟢 Could Have (Опционально)
- [ ] Per-role `no_context_files` в секции `roles` chains.yaml
### ⚫ Won't Have (Не будем делать)
- [ ] Изменение поведения других runner'ов

## 4. Implementation Plan (План реализации)
1. [ ] Добавить поле `noContextFiles` в ChainStepVo (default: false)
2. [ ] Обновить YamlChainLoader — парсинг `no_context_files` для шага и цепочки
3. [ ] Передавать `-nc` флаг в PiAgentRunner (или subprocess-команду) когда noContextFiles = true
4. [ ] Добавить `--no-context-files` в RunCommand и OrchestrateCommand
5. [ ] Обновить config/chains.yaml.example
6. [ ] Unit-тесты

## 5. Definition of Done (Критерии приёмки)
- [ ] `php bin/console app:agent:run -r system_analyst -t "task" --no-context-files` передаёт `-nc` в pi
- [ ] `no_context_files: true` в chains.yaml отключает контекстные файлы для шага
- [ ] Обратная совместимость: без флага поведение не меняется
- [ ] Unit-тесты покрывают новый функционал

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
php bin/console app:agent:run --help
```

## 7. Risks and Dependencies (Риски и зависимости)
- Требуется pi-mono >= v0.67.4 (флаг `-no-context-files` добавлен в этом релизе)
- Если pi более старой версии — флаг будет проигнорирован или вызовет ошибку

## 8. Sources (Источники)
- https://github.com/badlogic/pi-mono/releases/tag/v0.67.4

## 9. Comments (Комментарии)
Это ключевая фича для универсальности оркестратора: без неё агент привязан к контексту конкретного проекта. С ней можно запускать чистых агентов для любых задач — от генерации документации до анализа данных.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-16 | Бэкендер (Левша) | Создание задачи |
