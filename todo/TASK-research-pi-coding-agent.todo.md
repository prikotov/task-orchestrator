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
branch: task/research-pi-coding-agent
pr:
status: in_progress
---

# TASK-research-pi-coding-agent: Pi Coding Agent

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно подключить CLI-агент кодинга как сабагента к роли команды, я хочу знать его возможности по кастомизации (системный промпт, скиллы, AGENTS.md, запуск в JSON-режиме), чтобы определить, подходит ли он для работы с нашей системой ролей и скиллов.

### Goal (Цель по SMART)
Исследовать Pi Coding Agent (@earendil-works/pi-coding-agent, Node.js/TypeScript) по 10 критериям. Создать отчёт в `docs/research/coding-agents/pi-coding-agent-comparison.md` со сводкой по каждому критерию. Вердикт: подходит / частично подходит / не подходит для использования как сабагент с ролями. Pi уже подключён — его отчёт служит референс-точкой и бенчмарком для остальных агентов.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/coding-agents/pi-coding-agent-comparison.md`
*   **Текущее поведение:** Pi уже используется как сабагент через `watch-subagent.sh` с упрощённым системным промптом и файлами ролей
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] **Критерий 1. Системный промпт:** Полная замена (`--system-prompt`), дополнение (`--append-system-prompt`), передача файла роли. Примеры CLI-команд.
- [ ] **Критерий 2. Промпт агента / роль:** Инъекция контекста роли через `--append-system-prompt "Возьми на себя роль из файла: role.md"`. Альтернативные подходы.
- [ ] **Критерий 3. Скиллы:** `--skill path/to/skill`, `--no-skills`, автосканирование. Разные скиллы разным ролям.
- [ ] **Критерий 4. AGENTS.md:** Автообнаружение `AGENTS.md` + `CLAUDE.md`. Отключение через `--no-context-files`.
- [ ] **Критерий 5. Стандартная папка `.agents/skills/`:** Автосканирование `.agents/skills/` из коробки или нужна явная настройка.
- [ ] **Критерий 6. Запуск как сабагент:** `--mode json` (JSONL-стрим), `--no-session` (ephemeral), pipe-управление, контроль таймаутов через внешний скрипт.
- [ ] **Критерий 7. Токены и стоимость:** Метрики: `used-tokens`, `total-input-tokens`, `total-output-tokens`, `context-remaining`. Доступность через JSONL-события.
- [ ] **Критерий 8. Free tier:** Бесплатный тариф. Какие модели бесплатно. Ограничения.
- [ ] **Критерий 9. Провайдеры и модели:** Список поддерживаемых провайдеров (OpenAI, Anthropic, Google, Mistral, Ollama и т.д.). Конкретные модели. BYOK. Локальные модели (ollama, lmstudio). Переключение: `--provider google --model gemini-2.5-pro`.
- [ ] **Критерий 10. Лицензия:** Open source / проприетарный, тип лицензии, условия использования.
- [ ] Вердикт: подходит / частично подходит / не подходит — с обоснованием

### 🟡 Should Have (Желательно)
- [ ] Практические примеры запуска в JSON-режиме
- [ ] Примеры конфигурации для разных ролей

### 🟢 Could Have (Опционально)
- [ ] Mermaid-диаграмма потока данных при запуске как сабагент

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции
- [ ] Бенчмарки производительности

## 4. Implementation Plan (План реализации)
1. [ ] Изучить `pi --help` и CLI-параметры
2. [ ] Изучить официальный репозиторий и документацию
3. [ ] Оценить каждый из 10 критериев с примерами
4. [ ] Создать отчёт в `docs/research/coding-agents/pi-coding-agent-comparison.md`
5. [ ] Добавить строку в `docs/research/coding-agents-summary.md`

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт создан в `docs/research/coding-agents/pi-coding-agent-comparison.md`
- [ ] Каждый из 10 критериев оценён с примерами CLI-команд или конфигурации
- [ ] Вердикт: подходит / частично подходит / не подходит — с обоснованием
- [ ] Строка агента добавлена в `docs/research/coding-agents-summary.md`

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/pi-coding-agent-comparison.md
grep "Pi Coding Agent" docs/research/coding-agents-summary.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Pi — наш основной агент, информация наиболее актуальна
- Fork @earendil-works — может отличаться от upstream @mariozechner

## 8. Sources (Источники)
- [ ] [Pi CLI — помощь](cli --help)
- [ ] [Официальный репозиторий](https://github.com/mariozechner/pi-coding-agent)
- [ ] [Pi документация](README.md и docs/)

## 9. Comments (Комментарии)
Pi уже подключён как сабагент — его исследование должно быть наиболее полным и служить бенчмарком для остальных 13 агентов.

## Инструкции для сабагента

**Ветка:** task/research-pi-coding-agent (уже создана и активна)
**PR:** уже создан (draft) из task/research-pi-coding-agent в epic/research-coding-agents-comparison — будет указан после создания

### Порядок действий
1. Переключись в ветку `task/research-pi-coding-agent`: `git checkout task/research-pi-coding-agent`
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
