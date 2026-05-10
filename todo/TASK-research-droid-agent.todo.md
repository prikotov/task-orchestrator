---
# Metadata (Метаданные)
type: research
created: 2026-05-09
value: V3
complexity: C2
priority: P3
depends_on:
epic: EPIC-research-coding-agents-comparison
author: Аналитик (Шерлок)
assignee: Аналитик (Шерлок)
branch: task/research-droid-agent
pr: "PR #182"
status: done
---

# TASK-research-droid-agent: Droid

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
> **Job Story:** Когда мне нужно подключить CLI-агент кодинга как сабагента к роли команды, я хочу знать его возможности по кастомизации (системный промпт, скиллы, AGENTS.md, запуск в JSON-режиме), чтобы определить, подходит ли он для работы с нашей системой ролей и скиллов.

### Goal (Цель по SMART)
Исследовать Droid (Droid coding agent) по 10 критериям. Создать отчёт в `docs/research/coding-agents/droid-agent-comparison.md` со сводкой по каждому критерию. Вердикт: подходит / частично подходит / не подходит для использования как сабагент с ролями.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/research/coding-agents/droid-agent-comparison.md`
*   **Текущее поведение:** Агент ещё не подключён к нашей системе
*   **Границы (Out of Scope):** написание кода интеграции, глубокий code review исходников, бенчмарки

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [x] **Критерий 1. Системный промпт:** Можно ли полностью заменить системный промпт? Дополнить? Передать файл роли? Механизмы (CLI-аргумент, конфигурационный файл, env). Сравнение с pi (`--system-prompt`, `--append-system-prompt`) и codex (`model_instructions_file`).
- [x] **Критерий 2. Промпт агента / роль:** Инъекция контекста роли (файл роли) в сессию через CLI-аргумент, env, файл конфигурации.
- [x] **Критерий 3. Скиллы:** Встроенная поддержка скиллов. Подключение файлов/каталогов скиллов к сессии. Можно ли задавать разные скиллы разным ролям.
- [x] **Критерий 4. AGENTS.md:** Автообнаружение `AGENTS.md` из корня проекта. Можно ли отключить. Альтернативные форматы инструкций проекта.
- [x] **Критерий 5. Стандартная папка `.agents/skills/`:** Поддержка стандартной директории `.agents/skills/`. Автосканирование из коробки или нужно явное подключение.
- [x] **Критерий 6. Запуск как сабагент:** JSON-режим / programmatic API / pipe-управление. Контроль таймаутов. Структурированный результат. Non-interactive / ephemeral режим.
- [x] **Критерий 7. Токены и стоимость:** Отслеживание потребления токенов (input/output) за сессию. Расчёт стоимости. Какие метрики доступны и как их получить.
- [x] **Критерий 8. Free tier:** Наличие бесплатного тарифа. Какие модели доступны бесплатно. Ограничения (лимиты токенов, RPM, число запросов).
- [x] **Критерий 1. Системный промпт:** ⚠️ Только через конфигурацию (.factory/) и Custom Droids, нет CLI-флагов.
- [x] **Критерий 2. Промпт агента / роль:** ⚠️ Custom Droids (frontmatter: model, disallowedTools), нет CLI-инъекции для exec.
- [x] **Критерий 3. Скиллы:** ⚠️ Agent Skills standard, автосканирование, плагины, нет CLI-управления для exec.
- [x] **Критерий 4. AGENTS.md:** ✅ Автообнаружение AGENTS.md из коробки.
- [x] **Критерий 5. Стандартная папка `.agents/skills/`:** ⚠️ Поддерживается через конфигурацию и плагины, не подтверждено автосканирование из CWD.
- [x] **Критерий 6. Запуск как сабагент:** ✅ `droid exec --output-format json/stream-jsonrpc`, JSON-RPC протокол, GitHub Actions.
- [x] **Критерий 7. Токены и стоимость:** ⚠️ Токены в session files (input/output/cache/thinking), нет стоимости в $, нет вывода в stdout при exec.
- [x] **Критерий 8. Free tier:** ❌ Проприетарный, $20+/мес, нет free tier, BYOK с подпиской.
- [x] **Критерий 9. Провайдеры и модели:** ⚠️ 10 BYOK провайдеров (OpenAI, Anthropic, Google, Ollama, OpenRouter, Fireworks, Groq и др.), mixed models.
- [x] **Критерий 10. Лицензия:** ❌ Проприетарная (unfree, закрытый код).
- [x] Вердикт: ⚠️ Частично подходит (6/10) — подробное обоснование в отчёте

### 🟡 Should Have (Желательно)
- [x] Практические примеры запуска в JSON-режиме (если поддерживается)
- [x] Сравнение с pi и codex по ключевым критериям

### 🟢 Could Have (Опционально)
- [ ] Mermaid-диаграмма потока данных при запуске как сабагент

### ⚫ Won't Have (Не будем делать)
- [ ] Код интеграции
- [ ] Бенчмарки производительности

## 4. Implementation Plan (План реализации)
1. [x] Найти официальный репозиторий и документацию агента
2. [x] Изучить CLI-параметры и конфигурацию
3. [x] Оценить каждый из 10 критериев с примерами
4. [x] Создать отчёт в `docs/research/coding-agents/droid-agent-comparison.md`
5. [x] Добавить колонку в `docs/research/coding-agents-summary.md`

## 5. Definition of Done (Критерии приёмки)
- [x] Отчёт создан в `docs/research/coding-agents/droid-agent-comparison.md`
- [x] Каждый из 10 критериев оценён с примерами CLI-команд или конфигурации
- [x] Вердикт: подходит / частично подходит / не подходит — с обоснованием
- [x] Колонка агента добавлена в `docs/research/coding-agents-summary.md`

## 6. Verification (Самопроверка)
```bash
ls docs/research/coding-agents/droid-agent-comparison.md
grep "Droid" docs/research/coding-agents-summary.md
```

## 7. Risks and Dependencies (Риски и зависимости)
- Агент может быть проприетарным — анализ только по документации
- Агент может активно развиваться — информация может устареть

## 8. Sources (Источники)
- [x] [Factory AI Docs — sitemap](https://docs.factory.ai/sitemap.xml)
- [x] [droid-acp (GitHub)](https://github.com/kingsword09/droid-acp)
- [x] [factoryai-droid-docker (GitHub)](https://github.com/Wuodan/factoryai-droid-docker)
- [x] [oh-my-droid (GitHub)](https://github.com/MeroZemory/oh-my-droid)
- [x] [factory-cli-nix (GitHub)](https://github.com/GutMutCode/factory-cli-nix)

## 9. Comments (Комментарии)
Droid — CLI-агент кодинга от Factory AI (https://factory.ai). Проприетарный, v0.25.1, $20+/мес.

## Инструкции для сабагента

**Ветка:** task/research-droid-agent (уже создана и активна)
**PR:** [PR #182](https://github.com/prikotov/task-orchestrator/pull/182)

### Порядок действий
1. Переключись в ветку `task/research-droid-agent`: `git checkout task/research-droid-agent`
2. Реализуй задачу согласно описанию.
3. Делай промежуточные коммиты после каждого логического этапа.
4. Сделай `git push`.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-05-09 | Аналитик (Шерлок) | Создание задачи |
| 2026-05-10 | Аналитик (Шерлок) | Исследование завершено, отчёт создан, задача переведена в done |
