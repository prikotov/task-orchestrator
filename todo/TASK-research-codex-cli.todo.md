---
# Metadata (Метаданные)
type: research
created: 2026-05-09
value: V3
complexity: C2
priority: P1
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Аналитик (Шерлок)
assignee: Аналитик (Шерлок)
branch: task/research-codex-cli
pr:
status: in_progress
---

# TASK-research-codex-cli: Codex CLI (OpenAI)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно подключить CLI-агент кодинга как сабагента к роли команды, я хочу знать его возможности по кастомизации (системный промпт, скиллы, AGENTS.md, запуск в JSON-режиме), чтобы определить, подходит ли он для работы с нашей системой ролей и скиллов.

### Goal (Цель по SMART)
Исследовать Codex CLI (OpenAI, Rust) по 10 критериям. Создать отчёт в `docs/research/coding-agents/codex-cli-comparison.md` со сводкой по каждому критерию. Вердикт: подходит / частично подходит / не подходит. Codex — второй агент, который мы уже используем, его исследование наряду с Pi даёт две референс-точки.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/coding-agents/codex-cli-comparison.md`
*   **Текущее поведение:** Codex используется интерактивно, `model_instructions_file` в `~/.codex/config.toml` для системного промпта, `codex exec --json` для неинтерактивного запуска
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] **Критерий 1. Системный промпт:** Полная замена через `model_instructions_file` в `~/.codex/config.toml` или проектный `.codex/`. Можно ли дополнить? Передача файла роли. Сравнение с подходом pi (`--system-prompt`).
- [ ] **Критерий 2. Промпт агента / роль:** Инъекция контекста роли через config, env, или CLI. Механизм profiles (`-p/--profile`).
- [ ] **Критерий 3. Скиллы:** Есть ли встроенная поддержка скиллов? Подключение файлов/каталогов инструкций. Можно ли задавать разные скиллы разным ролям.
- [ ] **Критерий 4. AGENTS.md:** Автообнаружение `AGENTS.md` или альтернативный формат (`codex.md`, `.codex/`). Можно ли отключить (`--ignore-rules`, `--ignore-user-config`).
- [ ] **Критерий 5. Стандартная папка `.agents/skills/`:** Автосканирование `.agents/skills/` — из коробки или нужно явное подключение.
- [ ] **Критерий 6. Запуск как сабагент:** `codex exec --json` (JSONL-стрим), `--ephemeral` (без сохранения сессии), `--output-last-message`, pipe-управление, контроль таймаутов.
- [ ] **Критерий 7. Токены и стоимость:** Метрики: `context-used`, `five-hour-limit`, `weekly-limit`, `used-tokens`, `total-input-tokens`, `total-output-tokens`. Доступность через JSONL или TUI status line.
- [ ] **Критерий 8. Free tier:** Бесплатный тариф OpenAI. Какие модели бесплатно. Ограничения (лимиты токенов, RPM, число запросов).
- [ ] **Критерий 9. Провайдеры и модели:** OpenAI (gpt-4o, gpt-4.1, o3, o4-mini и т.д.). `--oss` для open-source провайдеров. `--local-provider` (ollama, lmstudio). BYOK. Переключение: `-m model`.
- [ ] **Критерий 10. Лицензия:** Open source / проприетарный, тип лицензии, условия использования.
- [ ] Вердикт: подходит / частично подходит / не подходит — с обоснованием

### 🟡 Should Have (Желательно)
- [ ] Практические примеры запуска `codex exec --json` как сабагента
- [ ] Примеры конфигурации `config.toml` для разных ролей

### 🟢 Could Have (Опционально)
- [ ] Mermaid-диаграмма потока данных при запуске как сабагент

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции
- [ ] Бенчмарки производительности

## 4. Implementation Plan (План реализации)
1. [ ] Изучить `codex --help` и `codex exec --help`
2. [ ] Изучить конфигурацию `~/.codex/config.toml` и проектные настройки
3. [ ] Изучить официальный репозиторий и документацию
4. [ ] Оценить каждый из 10 критериев с примерами
5. [ ] Создать отчёт в `docs/research/coding-agents/codex-cli-comparison.md`
6. [ ] Добавить строку в `docs/research/coding-agents-summary.md`

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт создан в `docs/research/coding-agents/codex-cli-comparison.md`
- [ ] Каждый из 10 критериев оценён с примерами CLI-команд или конфигурации
- [ ] Вердикт: подходит / частично подходит / не подходит — с обоснованием
- [ ] Строка агента добавлена в `docs/research/coding-agents-summary.md`

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/codex-cli-comparison.md
grep "Codex CLI" docs/research/coding-agents-summary.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Codex — проприетарный продукт OpenAI, анализ по документации и CLI
- Модельный ряд может быстро меняться
- Codex Cloud — отдельный продукт, не путать с Codex CLI

## 8. Sources (Источники)
- [ ] [Codex CLI — GitHub](https://github.com/openai/codex)
- [ ] [Codex CLI — документация](https://openai.github.io/codex/)

## 9. Comments (Комментарии)
Codex уже используется — его исследование должно быть подробным, наравне с Pi. Особый интерес: подход к системному промпту через `model_instructions_file` (отличается от pi), поддержка `--oss` для локальных моделей.

## Инструкции для сабагента

**Ветка:** task/research-codex-cli (уже создана и активна)
**PR:** уже создан (draft) — [PR #173](https://github.com/prikotov/task-orchestrator/pull/173)

### Порядок действий
1. Переключись в ветку `task/research-codex-cli`: `git checkout task/research-codex-cli`
2. Реализуй задачу согласно описанию.
3. Следуй [Конвенциям](../../../../docs/conventions/index.md) проекта.
4. Делай промежуточные коммиты после каждого логического этапа.
5. После реализации запусти проверки: `make check`.
6. Сделай `git push`.
7. Переведи PR из draft в ready: `gh pr ready <PR_NUMBER>`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-09 | Аналитик (Шерлок) | Создание задачи |
