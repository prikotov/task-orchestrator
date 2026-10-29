---
type: chore
created: 2026-10-28
due:
started:
completed: 2026-10-29 08:38:49 (1793263129)
cancelled:
value: V2
complexity: C1
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
consultants: []
reviewer:
approver:
informed: []
branch: task/chains-thinking-levels-tuning
pr: https://github.com/prikotov/task-orchestrator/pull/439
status: done
started: 2026-10-28
---

# TASK-chains-thinking-levels-tuning: синхронизация профилей делегирования с принятыми раскладками (включая MiMo)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
Дефолтные профили делегирования `config/chains.yaml` пакета разошлись с принятыми раскладками ролей в проектах stocks2 (PR #405) и TasK (PR #3144): в них нет MiMo-профилей для координации/архитектуры/требований/документации, исполнитель работает на старшей `glm-5.3`, а режимы размышлений не соответствуют классам задач ролей.

### Варианты или путь решения (Solution Sketch)
Синхронизировать профили пакета с принятыми раскладками один в один: MiMo-V2.6-Pro для координации/архитектуры/требований/документации, GLM-5.3-Flash для исполнителей, локальная Ollama для ревью; режимы размышлений — по принятым значениям.

### Ожидаемый результат (Expected Result)
Профили делегирования пакета идентичны принятым раскладкам проектов; роли используют те же модели и режимы размышлений, что уже согласованы и слиты в stocks2/TasK.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)
> **Job Story:** Когда раскладки ролей в проектах обновлены и приняты, я хочу те же профили в дефолтной конфигурации пакета, чтобы делегирование в task-orchestrator работало на согласованных моделях и режимах.

### Цель по SMART (Goal)
В `config/chains.yaml` повторить принятые раскладки: `team_lead_alex` и `technical_writer_hermione` — `mimo-v2.6-pro` `off`; `system_analyst_sherlock`, `system_architect_gandalf` — `mimo-v2.6-pro` `high`; `system_architect_loki`, `backend_developer_levsha` — `glm-5.3-flash` `max`; `backend_developer_tony`, `qa_backend_house`, `technical_writer_ostap` — `glm-5.3-flash` `high`; `code_reviewer_backend_puaro` — Ollama без изменений; `make check` зелёный.

## 2. Контекст и Границы (Context and Scope)
*   **Где делаем:** `config/chains.yaml`.
*   **Текущее поведение:** Алекс/Шерлок/Локи/Тони/Остап — `glm-5.3-flash` `max`, Гэндальф — `glm-5.3` `high`, Левша — `glm-5.3-flash` `max`, Хаус/Гермиона — `glm-5.3-flash` `high`; MiMo-профилей нет.
*   **Границы (Out of Scope):** не меняем локального ревьювера Пуаро, закомментированные профили (qwen CLI, openrouter), `chains.yaml.example`, README-примеры, `max_rounds` цепочек (решение владельца 2026-10-28).

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] MiMo-V2.6-Pro: Алекс и Гермиона — `off`, Шерлок и Гэндальф — `high` (как в TasK/stocks2).
- [x] GLM-5.3-Flash: Локи и Левша — `max`; Тони, Хаус, Остап — `high`.
- [x] Значение `'off'` в YAML закавычено (иначе парсится как `false`).
- [x] У всех команд сохранена полная форма аргументов (`--system-prompt`, `--append-system-prompt`).
### 🟡 Желательно (Should Have)
- [x] Комментарий конфига ссылается на принятые раскладки (stocks2 PR #405, TasK PR #3144).
### 🟢 Опционально (Could Have)
- [ ] Синхронизировать `chains.yaml.example` — отдельной задачей.
### ⚫ Не будем делать (Won't Have)
- [x] Изменение `max_rounds` цепочек (решение владельца: остаются прежними).

## 4. План реализации (Implementation Plan)
1. [x] Сопоставить роли пакета с ролями принятых раскладок.
2. [x] Обновить `config/chains.yaml` (модели, режимы, комментарий-обоснование).
3. [ ] Прогнать `make check`, оформить PR.

## 5. Критерии приёмки (Definition of Done)
- [x] Нет регрессий (`make check` зелёный).
- [x] Обновлена документация конфигурации (комментарий в `config/chains.yaml`).

## 6. Самопроверка (Verification)
```bash
make check                                            # полный набор проверок проекта
php vendor/bin/todo-md validate todo/TASK-chains-thinking-levels-tuning.todo.md
```

## 7. Риски и зависимости (Risks and Dependencies)
- Значение `off` в аргументах команды обязано быть строкой — YAML парсит `off` как булев `false`; проверено разбором конфига.

## 8. Источники (Sources)
- [x] Принятые раскладки: stocks2 `config/chains.yaml` (PR #405), TasK `config/chains.yaml` (PR #3144).

## 9. Комментарии (Comments)
Решение владельца 2026-10-29: «подобное» для task-orchestrator — это те же профили, что приняты в проектах, **включая MiMo-роли**. Работа ведётся в отдельном `git worktree`, чтобы не мешать параллельной сессии в ветке `task/phar-runtime-layout-cache`.
