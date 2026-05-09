# Kilo Code CLI — Исследование: краткое резюме

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-10
**Объект:** Kilo Code CLI v7.1.21 (`@kilocode/cli`)
**Задача:** [TASK-research-kilocode-cli](../../../todo/TASK-research-kilocode-cli.todo.md)

---

## Результат

Создан отчёт `docs/research/coding-agents/kilocode-cli-comparison.md` с анализом по 10 критериям.

## Вердикт: ⚠️ Частично подходит (6/10)

**Ключевые ограничения:**
- 🔴 Нет CLI-флагов для инъекции системного промпта (`--system-prompt`, `--append-system-prompt`)
- 🔴 Нет CLI-управления скиллами (`--skill`, `--no-skills`)
- 🔴 Нет назначения скиллов конкретным агентам — скиллы глобальны
- 🟡 JSON-режим слабо документирован
- 🟡 Требуется создание файлов агентов `.kilo/agent/*.md` для каждой роли

**Сильные стороны:**
- ✅ Встроенная система агентов с permissions
- ✅ AGENTS.md автоматически обнаруживается
- ✅ Agent Skills standard поддерживается
- ✅ MIT-лицензия
- ✅ 500+ моделей через Kilo Cloud + BYOK
- ✅ ACP-протокол для глубокой интеграции

## Рекомендация

Не рекомендуется как основной сабагент. Pi остаётся предпочтительным выбором. Kilo Code может быть альтернативой для задач, где нужна специфическая модель из Kilo Cloud.

## Файлы

- Отчёт: `docs/research/coding-agents/kilocode-cli-comparison.md`
- Сводная таблица: `docs/research/coding-agents-summary.md` (обновлена)

## Источники

1. `kilo --help`, `kilo run --help` — CLI-параметры (v7.1.21)
2. Встроенный skill `kilo-config` — документация по конфигурации
3. [Kilo Code GitHub](https://github.com/Kilo-Org/kilocode)
4. [Kilo Docs](https://kilo.ai/docs)
5. `package.json` — метаданные, лицензия MIT
