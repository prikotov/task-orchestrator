---
# Metadata (Метаданные)
type: feat
created: 2026-04-23
value: V3
complexity: C2
priority: P1
depends_on:
epic: EPIC-feat-standalone-cli
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/feat-validate-config
pr:
status: in_progress
---

# TASK-feat-validate-config: Флаг --validate-config для проверки конфигурации цепочки

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story)
> Как AI-агент, я хочу проверить конфигурацию цепочки до запуска, чтобы не тратить бюджет на оркестрацию с невалидным конфигом.

### Goal (Цель по SMART)
Добавить флаг `--validate-config` к CLI-команде. При вызове с флагом — валидирует chains.yaml и выводит результат без запуска оркестрации. Exit code 0 = ок, exit code 5 = INVALID_CONFIG.

## 2. Context and Scope (Контекст и Границы)
* **Где делаем:** `apps/console/` (Command) + `src/` (Validator)
* **Текущее поведение:** ошибки конфигурации обнаруживаются только при запуске цепочки
* **Границы:**
  - Runtime-валидация (не JSON Schema)
  - Проверяем: структура YAML, обязательные поля, типы значений
  - Не проверяем: существование файлов ролей, доступность API

## 3. Requirements (Требования)
### 🔴 Must Have
- [ ] Флаг `--validate-config` в `app:agent:orchestrate`
- [ ] `ChainConfigValidator` — сервис валидации конфигурации
- [ ] Вывод: ок / список ошибок с описанием
- [ ] Exit code: 0 (ок) или 5 (INVALID_CONFIG из OrchestrateExitCode)
- [ ] Unit-тесты

### 🟡 Should Have
- [ ] Поддержка `--validate-config --chain=<name>` — валидация конкретной цепочки

## 4. Implementation Plan
1. Создать `ChainConfigValidator` в Application или Domain слое
2. Добавить опцию `--validate-config` в `OrchestrateCommand`
3. При флаге — загрузить конфиг, провалидировать, вывести результат, выйти
4. Unit-тесты

## 5. Definition of Done
- [ ] `--validate-config` работает
- [ ] Невалидный конфиг → exit code 5 + список ошибок
- [ ] Валидный конфиг → exit code 0 + «Config is valid»
- [ ] PHPUnit и Psalm зелёные

## 6. Verification
```bash
bin/task-orchestrator app:agent:orchestrate --validate-config
echo $?  # 0 или 5
vendor/bin/phpunit
vendor/bin/psalm
```

## 7. Risks
- Валидация может не покрывать все edge cases — начать с обязательных полей

## 8. Sources
- [RFC: cli-distribution-rfc.md](../docs/research/cli-distribution-rfc.md) — Brainstorm #2
- [chains.yaml](../config/chains.yaml) — структура конфигурации

## Инструкции для сабагента

**Твоя роль:** docs/agents/roles/team/backend_developer_levsha.ru.md
**Ветка:** task/feat-validate-config (уже создана и активна)
**PR:** уже создан (draft) из task/feat-validate-config в epic/feat-standalone-cli — [PR #65](https://github.com/prikotov/task-orchestrator/pull/65)

### Порядок действий
1. Переключись в ветку `task/feat-validate-config`: `git checkout task/feat-validate-config`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `vendor/bin/phpunit` и `vendor/bin/psalm`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-23 | Тимлид (Алекс) | Создание задачи из RFC brainstorm |
