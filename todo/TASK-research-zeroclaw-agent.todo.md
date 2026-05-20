---
# Metadata (Метаданные)
type: research
created: 2026-05-20
value: V3
complexity: C2
priority: P2
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Тимлид (Алекс)
assignee: Аналитик (Шерлок)
branch: task/research-zeroclaw-agent
pr:
status: in_progress
---

# TASK-research-zeroclaw-agent: Zeroclaw (zeroclaw-labs)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно подключить CLI-агент кодинга как сабагента к роли команды, я хочу знать его возможности по кастомизации (системный промпт, скиллы, AGENTS.md, запуск в JSON-режиме), чтобы определить, подходит ли он для работы с нашей системой ролей и скиллов.

### Goal (Цель по SMART)
Исследовать Zeroclaw (zeroclaw-labs/zeroclaw, Rust, MIT/Apache-2.0) по 10 критериям. Создать отчёт в `docs/research/coding-agents/zeroclaw-agent-comparison.md` со сводкой по каждому критерию. Вердикт: подходит / частично подходит / не подходит для использования как сабагент с ролями.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/coding-agents/zeroclaw-agent-comparison.md`
*   **Текущее поведение:** Агент ещё не подключён к нашей системе. Есть отдельное исследование Zeroclaw как оркестратора в `docs/research/framework-comparisons/zeroclaw-comparison.md` (EPIC-research-agent-frameworks-comparison) — можно переиспользовать контекст, но оценка по 10 критериям кодинг-агента — новая
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] **Критерий 1. Системный промпт:** Можно ли полностью заменить системный промпт? Дополнить? Передать файл роли? Механизмы (CLI-аргумент, конфигурационный файл, env). Сравнение с pi (`--system-prompt`, `--append-system-prompt`) и codex (`model_instructions_file`).
- [ ] **Критерий 2. Промпт агента / роль:** Инъекция контекста роли (файл роли) в сессию через CLI-аргумент, env, файл конфигурации.
- [ ] **Критерий 3. Скиллы:** Встроенная поддержка скиллов. Подключение файлов/каталогов скиллов к сессии. Можно ли задавать разные скиллы разным ролям.
- [ ] **Критерий 4. AGENTS.md:** Автообнаружение `AGENTS.md` из корня проекта. Можно ли отключить. Альтернативные форматы инструкций проекта.
- [ ] **Критерий 5. Стандартная папка `.agents/skills/`:** Поддержка стандартной директории `.agents/skills/`. Автосканирование из коробки или нужно явное подключение.
- [ ] **Критерий 6. Запуск как сабагент:** JSON-режим / programmatic API / pipe-управление. Контроль таймаутов. Структурированный результат. Non-interactive / ephemeral режим.
- [ ] **Критерий 7. Токены и стоимость:** Отслеживание потребления токенов (input/output) за сессию. Расчёт стоимости. Какие метрики доступны и как их получить.
- [ ] **Критерий 8. Free tier:** Наличие бесплатного тарифа. Какие модели доступны бесплатно. Ограничения (лимиты токенов, RPM, число запросов).
- [ ] **Критерий 9. Провайдеры и модели:** Конкретный список поддерживаемых LLM-провайдеров. BYOK. Локальные модели (ollama, lmstudio). Легкость переключения между провайдерами.
- [ ] **Критерий 10. Лицензия:** Open source / проприетарный. Тип лицензии. Условия использования.
- [ ] Вердикт: подходит / частично подходит / не подходит — с обоснованием

### 🟡 Should Have (Желательно)
- [ ] Практические примеры запуска в JSON-режиме (если поддерживается)
- [ ] Сравнение с pi и codex по ключевым критериям

### 🟢 Could Have (Опционально)
- [ ] Mermaid-диаграмма потока данных при запуске как сабагент

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции
- [ ] Бенчмарки производительности

## 4. Implementation Plan (План реализации)
*Заполняется исполнителем перед стартом.*
1. [ ] Изучить репозиторий https://github.com/zeroclaw-labs/zeroclaw (CLI-параметры, TOML-конфигурация, personality system)
2. [ ] Оценить каждый из 10 критериев с примерами CLI-команд или конфигурации
3. [ ] Создать отчёт в `docs/research/coding-agents/zeroclaw-agent-comparison.md`
4. [ ] Добавить строку #16 в `docs/research/coding-agents-summary.md`

## 5. Definition of Done (Критерии приёмки)
- [ ] Отчёт создан в `docs/research/coding-agents/zeroclaw-agent-comparison.md`
- [ ] Каждый из 10 критериев оценён с примерами CLI-команд или конфигурации
- [ ] Вердикт: подходит / частично подходит / не подходит — с обоснованием
- [ ] Строка агента #16 добавлена в `docs/research/coding-agents-summary.md`

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/zeroclaw-agent-comparison.md
grep "Zeroclaw" docs/research/coding-agents-summary.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Zeroclaw — agent runtime, не специализированный coding agent: может не иметь типичных для coding-агентов функций (AGENTS.md, .agents/skills/)
- Rust-бинар: нет npm/pip-интерфейса — другая модель установки
- Проект активно развивается (v0.7.5) — информация может устареть
- Параллельное исследование в EPIC-research-agent-frameworks-comparison — нужно избегать дублирования, сфокусироваться на 10 критериях coding-агента

## 8. Sources (Источники)
- [Zeroclaw — GitHub](https://github.com/zeroclaw-labs/zeroclaw)
- [Ранее созданный отчёт об оркестрации](framework-comparisons/zeroclaw-comparison.md) — для контекста, не копировать

## 9. Comments (Комментарии)
Zeroclaw уже исследован как оркестратор (docs/research/framework-comparisons/zeroclaw-comparison.md, PR #211). Эта задача — **дополнительный ракурс**: оценка как CLI-агента кодинга по 10 критериям. Persona system (7 markdown-шаблонов) и SkillForge (SKILL.md discovery) — прямые аналоги наших ролей и скиллов, что делает Zeroclaw потенциально интересным кандидатом на роль сабагента.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-20 | Тимлид (Алекс) | Создание задачи |
