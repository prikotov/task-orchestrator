---
type: fix
created: 2026-04-17
value: V2
complexity: C2
priority: P2
depends_on:
epic:
author: Бэкендер (Левша)
assignee:
branch:
pr:
status: done
---

# TASK-fix-conventions-broken-links: Исправление битых ссылок в конвенциях (docs/conventions/)

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда разработчик (или AI-агент) переходит по ссылкам в конвенциях, он хочет попадать на существующие документы, а не в никуда — чтобы не тратить время на поиск нужного файла вручную.

### Goal (Цель по SMART)
Исправить все 37 битых внутренних ссылок в `docs/conventions/`. Каждая ссылка должна вести на существующий файл проекта либо быть удалена/заменена, если целевой файл не существует и не планируется.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `docs/conventions/` (все `.md` файлы).
*   **Контекст:** Конвенции перенесены из другого проекта. Относительные пути не обновлены. Часть ссылок указывает на файлы, которых в task-orchestrator нет и не планируется (DataFixtures, Kernel.php, Makefile, phpmd.xml, depfile.yaml и т.д.).
*   **Границы (Out of Scope):** Содержание конвенций (текст, примеры кода) — не трогаем. Только ссылки.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Исправлены все ссылки с неправильными относительными путями (замена `../../` → `../../../` и аналогичные)
- [ ] Удалены или заменены ссылки на файлы, не существующие в проекте и не планирующиеся:
  - `src/DataFixtures/*` — удалить ссылки (5 шт.)
  - `src/Kernel.php` — удалить ссылки (3 шт.)
  - `src/Module/Health/*` — удалить ссылку (1 шт.)
  - `depfile.yaml`, `Makefile`, `phpmd.xml`, `phpmd.baseline.xml`, `phpcs.xml.dist` — удалить ссылки
  - `tests/AGENTS.md` — удалить ссылку
- [ ] Исправлены ссылки с неправильными именами файлов:
  - `use_cases.md` → `use_case.md`
  - `application_layer.md` → `../application.md`
  - `events.md` → `event.md`
  - `integration.md` → `../integration.md`
  - `../infrastructure/criteria.md` → `../infrastructure/criteria-mapper.md`
- [ ] Удалены/заменены ссылки на несуществующие документы:
  - `../../guides/dto.md`, `../../guides/enum.md`, `../guides/dto.md`
  - `../../../architecture/events/transactions.md`
  - `../theme/README.md`
  - `principles/README.md`
- [ ] После исправлений автоматическая проверка всех ссылок проходит без ошибок

### 🟡 Should Have (Желательно)
- [ ] Проверка ссылок добавлена в CI или smoke-команду (отдельная задача)

### 🟢 Could Have (Опционально)
### ⚫ Won't Have (Не будем делать)
- Переписывание содержания конвенций
- Создание недостающих файлов (guides/dto.md, architecture/events/transactions.md и т.д.)

## 4. Implementation Plan (План реализации)
1. [ ] Исправить `testing/index.md` — обновить относительные пути и удалить ссылки на несуществующие файлы проекта
2. [ ] Исправить `layers/application.md` — поправить ссылки на use_case, command_handler, query_handler; удалить/заменить guides/dto.md
3. [ ] Исправить `layers/application/command_handler.md` — use_cases→use_case, application_layer→../application.md, удалить guides/dto.md, architecture/events/transactions.md
4. [ ] Исправить `layers/application/query_handler.md` — аналогично command_handler
5. [ ] Исправить `layers/application/use_case.md` — application_layer→../application.md
6. [ ] Исправить `layers/application/event.md` — удалить architecture/events/transactions.md
7. [ ] Исправить `layers/domain/repository.md` — criteria→criteria-mapper, удалить src/Module/Health/*
8. [ ] Исправить `layers/infrastructure/criteria-mapper.md` — удалить phpmd.xml
9. [ ] Исправить `layers/integration/listener.md` — events→event, use_cases→use_case, integration→../integration.md
10. [ ] Исправить `layers/presentation/view.md` — удалить theme/README.md
11. [ ] Исправить `ops/phpmd-suppressions-guidelines.md` — удалить phpmd.xml, phpmd.baseline.xml, Makefile
12. [ ] Исправить `principles/values.md` — удалить/исправить README.md
13. [ ] Исправить `symfony-applications.md` и `symfony-folder-structure.md` — удалить src/Kernel.php
14. [ ] Запустить полную проверку всех ссылок и убедиться в отсутствии ошибок

## 5. Definition of Done (Критерии приёмки)
- [x] Сформирован отчёт о битых ссылках: `docs/agents/reports/backend-developer/2026-04-20_08-26_conventions-broken-links-audit.md`
- [x] Проблемы классифицированы по разделам с рекомендациями для upstream
- [x] ~~Правки в конвенции~~ — не требуются, конвенции приходят из upstream-пакета, исправления будут вноситься там

## 6. Verification (Самопроверка)
```bash
# Скрипт проверки ссылок (повторить из терминала):
cd docs/conventions && find . -name "*.md" -print0 | while IFS= read -r -d '' file; do
  dir=$(dirname "$file")
  grep -ohP '\]\([^)]*\)' "$file" | sed 's/](\(.*\))/\1/' | while IFS= read -r link; do
    case "$link" in http*|\#*) continue ;; esac
    clean="${link%%#*}"
    [ -z "$clean" ] && continue
    resolved="$dir/$clean"
    [ ! -f "$resolved" ] && echo "❌ $file → $link"
  done
done
```

## 7. Risks and Dependencies (Риски и зависимости)
- Некоторые ссылки могут указывать на файлы, которые планируется создать в будущем — нужно внимательно оценить каждую перед удалением
- Конвенции из Presentation-слоя (controller, forms, voter и т.д.) не относятся к task-orchestrator напрямую, но оставляем как справочные

## 8. Sources (Источники)

## 9. Comments (Комментарии)
Обнаружено при аудите файлов ролей — конвенции упоминаются в 4 из 5 ролей и активно используются AI-агентами. Битые ссылки снижают эффективность работы.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-04-17 | Бэкендер (Левша) | Создание задачи |
| 2026-04-20 | Бэкендер (Левша) | Задача закрыта: сформирован аудит-отчёт, правки будут в upstream-пакете конвенций |
