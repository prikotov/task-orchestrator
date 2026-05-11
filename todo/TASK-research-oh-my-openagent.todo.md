---
# Metadata (Метаданные)
type: research
created: 2026-05-11
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch:
pr:
status: todo
---

# TASK-research-oh-my-openagent: Oh My OpenAgent (OmO)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно подключить CLI-агент кодинга как сабагента к роли команды, я хочу знать его возможности по кастомизации (системный промпт, скиллы, AGENTS.md, запуск в JSON-режиме), чтобы определить, подходит ли он для работы с нашей системой ролей и скиллов.

### Goal (Цель по SMART)
Исследовать Oh My OpenAgent (OmO, форк OpenCode, TypeScript) по 10 критериям. Создать отчёт в `docs/research/coding-agents/oh-my-openagent-comparison.md` со сводкой по каждому критерию. Вердикт: подходит / частично подходит / не подходит для использования как сабагент с ролями.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/coding-agents/oh-my-openagent-comparison.md`
*   **Текущее поведение:** Агент ещё не подключён к нашей системе
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] **Критерий 1. Системный промпт:** Можно ли полностью заменить системный промпт? Дополнить? Передать файл роли? Механизмы (CLI-аргумент, конфигурационный файл, env). Сравнение с pi (`--system-prompt`, `--append-system-prompt`) и codex (`model_instructions_file`).
- [ ] **Критерий 2. Промпт агента / роль:** Инъекция контекста роли (файл роли) в сессию через CLI-аргумент, env, файл конфигурации.
- [ ] **Критерий 3. Скиллы:** Встроенная поддержка скиллов. Подключение файлов/каталогов скиллов к сессии. Можно ли задавать разные скиллы разным ролям.
- [ ] **Критерий 4. AGENTS.md:** Автообнаружение `AGENTS.md` из корня проекта. Можно ли отключить. Альтернативные форматы инструкций проекта. Поддержка `/init-deep` для генерации иерархических AGENTS.md.
- [ ] **Критерий 5. Стандартная папка `.agents/skills/`:** Поддержка стандартной директории `.agents/skills/`. Автосканирование из коробки или нужно явное подключение. Skill-Embedded MCPs.
- [ ] **Критерий 6. Запуск как сабагент:** JSON-режим / programmatic API / pipe-управление. Контроль таймаутов. Структурированный результат. Non-interactive / ephemeral режим. Team Mode как механизм параллельного запуска.
- [ ] **Критерий 7. Токены и стоимость:** Отслеживание потребления токенов (input/output) за сессию. Расчёт стоимости. Какие метрики доступны и как их получить.
- [ ] **Критерий 8. Free tier:** Наличие бесплатного тарифа. Какие модели доступны бесплатно. Ограничения (лимиты токенов, RPM, число запросов).
- [ ] **Критерий 9. Провайдеры и модели:** Конкретный список поддерживаемых LLM-провайдеров (OpenAI, Anthropic, Google, Mistral, Ollama и т.д.). Конкретные модели из коробки. BYOK — можно ли подключить свой API-ключ. Локальные модели (ollama, lmstudio). Легкость переключения между провайдерами.
- [ ] **Критерий 10. Лицензия:** Open source / проприетарный. Тип лицензии (MIT, Apache, proprietary). Условия использования. Лицензия SUL-1.0 — что это.
- [ ] Вердикт: подходит / частично подходит / не подходит — с обоснованием

### 🟡 Should Have (Желательно)
- [ ] Практические примеры запуска в JSON-режиме (если поддерживается)
- [ ] Сравнение с pi и codex по ключевым критериям
- [ ] Оценка Team Mode и Discipline Agents (Sisyphus, Hephaestus, Prometheus) как потенциальных паттернов оркестрации

### 🟢 Could Have (Опционально)
- [ ] Mermaid-диаграмма потока данных при запуске как сабагент

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции
- [ ] Бенчмарки производительности

## 4. Implementation Plan (План реализации)
1. [ ] Найти официальный репозиторий и документацию агента
2. [ ] Изучить CLI-параметры и конфигурацию
3. [ ] Оценить каждый из 10 критериев с примерами
4. [ ] Создать отчёт в `docs/research/coding-agents/oh-my-openagent-comparison.md`
5. [ ] Добавить строку в `docs/research/coding-agents-summary.md`

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт создан в `docs/research/coding-agents/oh-my-openagent-comparison.md`
- [ ] Каждый из 10 критериев оценён с примерами CLI-команд или конфигурации
- [ ] Вердикт: подходит / частично подходит / не подходит — с обоснованием
- [ ] Строка агента добавлена в `docs/research/coding-agents-summary.md`

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/oh-my-openagent-comparison.md
grep "Oh My OpenAgent" docs/research/coding-agents-summary.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Проект активно развивается (57k+ stars, частые релизы) — информация может устареть
- Форк OpenCode — нужно разделять фичи OmO от фич базового OpenCode
- Лицензия SUL-1.0 — нестандартная, может ограничивать коммерческое использование
- npm-пакет всё ещё называется `oh-my-opencode` — возможна путаница

## 8. Sources (Источники)
- [Oh My OpenAgent — GitHub](https://github.com/code-yeongyu/oh-my-openagent)
- NPM: `oh-my-opencode` / `oh-my-openagent`

## 9. Comments (Комментарии)
OmO — форк OpenCode (уже исследован: `docs/research/coding-agents/opencode-cli-comparison.md`) с добавлением Team Mode, Discipline Agents, `ultrawork`, IntentGate, Hash-Anchored Edit Tool и других фич. Важно оценить, что нового поверх OpenCode и насколько это полезно для нашей архитектуры сабагентов.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-11 | Тимлид (Алекс) | Создание задачи |
