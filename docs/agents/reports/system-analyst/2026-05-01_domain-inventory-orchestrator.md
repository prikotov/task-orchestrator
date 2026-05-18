# Domain Inventory: Orchestrator — Отчёт аналитика

**Роль:** Аналитик Шерлок (system_analyst_sherlock)
**Дата:** 2026-05-01
**Объект:** `src/Module/ChainDefinition/Domain/` (66 файлов, 5 964 LOC)
**Задача:** [TASK-docs-domain-inventory](../../../../todo/done/TASK-docs-domain-inventory.todo.md)

---

## Резюме

Создан полный каталог Domain-слоя Orchestrator: `docs/releases/domain-inventory-orchestrator.md`.

### Ключевые находки

- **66 файлов, 5 964 LOC** Domain-слоя полностью каталогизированы
- **0 прямых зависимостей** между Static и Dynamic subdomain'ами — чистая граница для split
- **4 критических VO** с blast radius ≥9 потребителей: `ChainDefinitionVo` (15), `ChainRunRequestVo` (10), `ChainRunResultVo` (9), `BudgetVo` (9)
- **ROOT = 59.8% кода** — монолитный котёл без subdomain-границ, содержит все 27 VO
- **RunDynamicLoopService** — крупнейший файл (786 LOC, 13% Domain)
- **SharedChainDefinitionVo** — 0 потребителей, потенциально мёртвый код
- **72% Service-файлов** — интерфейсы (21 из 29)

### Выполненные требования

| Требование | Статус |
|---|---|
| Полный каталог Domain-файлов: путь, категория, LOC | ✅ Секция 3 |
| Карта зависимостей между Static/Dynamic/Shared | ✅ Секция 4 |
| Cross-references Static ↔ Dynamic | ✅ 0 ссылок — подтверждено |
| Группировка по кластерам для split | ✅ Секция 6 |
| VO → consumer count | ✅ Секция 5 |
| Метрики: LOC, распределение, средний размер | ✅ Summary + Секция 1-2 |

### Следующие шаги

- Обновить Roadmap: AI#13 `📋` → `✅ Done`
- Зафиксировать документ в ветке `task/docs-domain-inventory`
