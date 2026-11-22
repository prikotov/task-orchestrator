---
type: fix
created: 2026-11-22 14:05:00 (1795333500)
due:
started:
completed:
cancelled:
value: V2
complexity: C1
priority: P2
cost_plan:
cost_fact:
depends_on:
epic:
author: Лид Арагорн (pi, host: TasK)
assignee:
consultants: []
reviewer:
approver:
informed: []
branch:
pr:
status: todo
---

# TASK-feat-fix-brainstorm-system-prompt-placeholders: brainstorm_system.txt — мёртвые плейсхолдеры ${toolsList}/${guidelines} уходят в модель литерально

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- `config/prompts/brainstorm/brainstorm_system.txt` (vendor) содержит `${toolsList}` и `${guidelines}`, но подстановки НЕ существует ни на одном уровне:
  - `YamlChainLoaderService::resolvePrompts()` читает файл и отдаёт содержимое строкой (замена только если файла нет — тогда значение становится inline-промптом);
  - в `src/` нет ни одного обработчика `${...}` (grep — 0 вхождений); `PromptFormatterService` подставляет только `%s` через sprintf для facilitator-промптов;
  - pi CLI `--system-prompt` принимает строку буквально, собственного шаблонизатора у pi нет.
- Итог: КАЖДЫЙ агент сессии (фасилитатор и все участники) получает системным промптом литеральный текст «Available tools: ${toolsList} … Guidelines: ${guidelines}». Сессия при этом работает (реальные инструкции приходят user-промптами и role-file, реальные инструменты инжектирует сам pi) — дефект деградации качества промпта, не блокер.
- Доказательство (host TasK, сессия 2026-11-20_15-11-26): `step_001_round_001_team_lead_aragorn_1_system.md` и `step_029_round_028_ui_ux_designer_alice_1_system.md` начинаются с литерального `${toolsList}`.

### Варианты или путь решения (Solution Sketch)
- Заменить файл на чистый session-agnostic preamble без плейсхолдеров (нейтральный для фасилитатора и участников: «инструкции приходят user-промптами, формат ответа — строго по промпту; факты верифицировать инструментами; роль — отдельно role-file»).
- Либо реализовать подстановку (сборка списка инструментов/гайдлайн на стороне оркестратора) — дороже, ценность сомнительна: список инструментов pi и так инжектирует harness'ом.
- Host TasK уже исправил локальную копию 22.11.2026 (config/prompts/brainstorm/brainstorm_system.txt — намеренный drift до фикса вендора).

## 1. Concept and Goal (Концепция и Цель)
- System-промпт агентов сессии не содержит мёртвых плейсхолдеров и мусорных инструкций.

## 2. Context and Scope (Контекст и Границы)
- In: `config/prompts/brainstorm/brainstorm_system.txt`.
- Out: остальные 6 промптов цепочки (плейсхолдеров не содержат; их `%s` подставляются корректно).

## 3. Requirements (Требования, MoSCoW)

### 🔴 Must Have (Обязательно)
- brainstorm_system.txt без `${...}` плейсхолдеров; текст — session-agnostic preamble, нейтральный для фасилитатора и участников.

### 🟡 Should Have (Желательно)
- Валидатор/линт: предупреждение на `${...}` в prompt-файлах цепочек (ловит класс «мёртвых» плейсхолдеров).

### ⚫ Won't Have (Не будем делать)
- Механизм подстановки переменных в промптах (если не понадобится явно).

## 4. Implementation Plan (План реализации)
1. Заменить текст файла.
2. Прогнать smoke-сессию brainstorm, убедиться в чистоте system-промпта (step_*_1_system.md).

## 5. Definition of Done (Критерии приёмки)
- [ ] Файл без `${...}`; smoke-сессия — system-промпт чистый.

## 6. Verification (Самопроверка)
- grep `config/prompts/brainstorm` на `${`; инспекция step-файлов сессии.

## 7. Risks and Dependencies (Риски и зависимости)
- Host-копии (TasK) уже diverged — при фиксе вендора сверить формулировки (или принять host-версию как канон).

## 8. Sources (Источники)
- Host TasK, сессия `var/sessions/brainstorm/2026-11-20_15-11-26` (step-файлы — литеральные плейсхолдеры); вопрос владельца 22.11.2026 «подставятся ли на уровне pi» — ответ: нет.

## 9. Comments (Комментарии)
- Связана: TASK-feat-unified-profile-command-contract (общий контекст качества consumption промптов).
