---
# Metadata (Метаданные)
type: feat
created: 2026-04-24
value: V2
complexity: C2
priority: P2
depends_on: EPIC-feat-standalone-cli
epic: EPIC-feat-standalone-cli
author: Тимлид (Алекс)
assignee: Бэкендер (Левша)
branch: task/feat-cli-config-option
pr: #72
status: done
---

# TASK-feat-cli-config-option: CLI-опция --config для указания chains.yaml

## 1. Concept and Goal (Концепция и Цель)
### Story (User Story или Job Story)
> **Job Story:** Когда я использую task-orchestrator как standalone-продукт (composer global require / Phar), я хочу указать путь к своему `chains.yaml` через CLI-опцию `--config`, чтобы оркестрировать цепочки в任意 проекте без Symfony-конфигурации.

### Goal (Цель по SMART)
Добавить CLI-опцию `--config` в `app:agent:orchestrate`, позволяющую переопределить путь к `chains.yaml` без изменения Symfony-параметров. Опция работает и с `--validate-config`.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:**
    *   `apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php` — новая опция
    *   `src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php` — override пути
    *   `src/Module/Orchestrator/Application/UseCase/Command/OrchestrateChain/OrchestrateChainCommand.php` — новый параметр (если нужно)
    *   `docs/agents/skills/task-orchestrator/SKILL.md` — описать опцию
    *   `docs/agents/skills/task-orchestrator/README.md` — описать для standalone
    *   `tests/` — unit + integration
*   **Текущее поведение:** Путь к `chains.yaml` жёстко задан через Symfony-параметр `task_orchestrator.chains_yaml` → `YamlChainLoader::$yamlPath`. Переопределить его можно только через bundle-конфиг `config/packages/task_orchestrator.yaml`.
*   **Границы (Out of Scope):** Не меняем формат YAML, не добавляем поддержку нескольких конфигов одновременно.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] CLI-опция `--config` (VALUE_OPTIONAL, путь к файлу)
- [ ] Без `--config` — используется путь по умолчанию из bundle-конфига
- [ ] `--validate-config` учитывает `--config`
- [ ] Unit-тесты на override пути
- [ ] Integration-тест на полный цикл с кастомным конфигом

### 🟡 Should Have (Желательно)
- [ ] Обновить SKILL.md и README.md

### 🟢 Could Have (Опционально)
- [ ] Валидация существования файла по указанному пути с понятным сообщением об ошибке

### ⚫ Won't Have (Не будем делать)
- [ ] Поддержка нескольких конфигов одновременно
- [ ] Изменение формата chains.yaml

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*

## 5. Definition of Done (Критерии приёмки)
- [ ] `--config=/path/to/chains.yaml` загружает указанный файл
- [ ] Без `--config` поведение не меняется (backward compatible)
- [ ] `--validate-config --config=...` валидирует указанный файл
- [ ] Несуществующий файл → понятная ошибка + exit code 5
- [ ] Unit/Integration тесты покрывают новый функционал
- [ ] Psalm и PHPUnit зелёные
- [ ] Документация обновлена

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit
vendor/bin/psalm
php bin/task-orchestrator app:agent:orchestrate --config=custom-chains.yaml --dry-run "test"
php bin/task-orchestrator app:agent:orchestrate --config=custom-chains.yaml --validate-config "check"
```

## 7. Risks and Dependencies (Риски и зависимости)
- Проброс пути через Presentation → Application → Infrastructure без нарушения слоёв
- `YamlChainLoader` получает путь через конструктор ($yamlPath) — нужен механизм override без изменения DI
- Зависит от завершения EPIC-feat-standalone-cli (merge PR #68)

## 8. Sources (Источники)
- [ ] [YamlChainLoader](../../src/Module/Orchestrator/Infrastructure/Service/Chain/YamlChainLoader.php)
- [ ] [OrchestrateCommand](../../apps/console/src/Module/Orchestrator/Command/OrchestrateCommand.php)
- [ ] [Configuration.php](../../src/DependencyInjection/Configuration.php)

## 9. Comments (Комментарии)
Архитектурный вопрос: как передать override-путь без нарушения DDD. Варианты:
1. Новый Application-сервис (ChainLoaderInterface уже в Domain, можно добавить метод `loadWithPath(name, path)`)
2. Проброс через OrchestrateChainCommand DTO
3. Override в Presentation перед вызовом handler

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-24 | Тимлид (Алекс) | Создание задачи |
