# Approaches — сводная таблица сравнения

**Дата создания:** 2026-07-27  
**Дата обновления:** 2026-07-27 (1 исследование)  
**Эпик:** [EPIC-research-approaches-comparison](../../todo/EPIC-research-approaches-comparison.md)  
**Автор:** Аналитик (Шерлок)

---

## Легенда оценок

| Символ | Значение | Балл |
|--------|----------|------|
| ✅ | Сильная применимость / совпадение с процессом task-orchestrator | 3 |
| ⚠️ | Частичная применимость, требуется `adapt` (адаптация) | 2 |
| ❌ | Низкая применимость или противоречие процессу | 1 |

## Часть 1. Ранжирование подходов

Место определяется суммой баллов по 8 критериям методологии эпика (максимум 24) и качественным вердиктом для развития role-based workflow (процесса по ролям) task-orchestrator.

| # | Подход | Категория | К1 Тезис | К2 Процесс | К3 Автономия | К4 Качество | К5 Контекст | К6 Артефакты | К7 Маппинг | К8 Вердикт | ∑ | Итог |
|---|--------|-----------|----------|------------|--------------|-------------|-------------|--------------|------------|------------|---|------|
| 1 | [Why Software Factories Fail](approaches/why-software-factories-fail-comparison.md) | `lit factory` / `front-loaded alignment` | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ | **23** | **adapt** — валидирует процесс, добавить `program design` |

## Часть 2. Детальная сводная таблица

| # | Подход | Главный тезис | Process model (модель процесса) | Autonomy model (модель автономии) | Quality mechanism (механизм качества) | Context engineering (управление контекстом) | Главный gap у нас | Рекомендация | Effort |
|---|--------|---------------|----------------------------------|-----------------------------------|---------------------------------------|---------------------------------------------|----------------------|--------------|--------|
| 1 | [Why Software Factories Fail](approaches/why-software-factories-fail-comparison.md) | `Harness engineering is not enough`: без human ownership (владения человеком) AI-фабрика теряет поддерживаемость. | `product review → system architecture → program design → vertical slicing → implementation → human review` | `lit factory`: agent ускоряет, человек выравнивает и ревьюит; `dark factory` отвергается. | Тесты и мониторинг нужны, но не заменяют review; поддерживаемость защищают конвенции, архитектура и человек. | `research / plan / implement`, intentional compaction (намеренное сжатие), subagents как контроль контекста. | Нет формального `program design` в task template (типы, сигнатуры, графы вызовов). | Добавить отдельной задачей `Program Design` для C3+ code tasks и уточнить positioning (позиционирование) как `lit-factory orchestration`. | Medium |

## Часть 3. Тренды и предварительные выводы

Пока исследован 1 / N подходов, поэтому тренды предварительные:

1. **Скорость без владения кодом — риск, а не цель.** Первый подход усиливает нашу ставку на `review gates` и человеческое подтверждение `merge` (слияния).
2. **Harness нужен, но как часть lit factory.** task-orchestrator должен позиционироваться не как «автопилот без инженеров», а как orchestration (оркестрация) ролей, артефактов и quality gates.
3. **Самый конкретный gap — `program design`.** Требуется не новый runtime (среда выполнения), а методологическое уточнение шаблона задач.

## Часть 4. Backlog рекомендаций по итогам исследований

| # | Рекомендация | Источник | Статус | Effort |
|---|--------------|----------|--------|--------|
| 1 | Добавить в task template секцию `Program Design` для C3+ code tasks: affected classes/interfaces, public method signatures, DTO/VO/Enum contracts, call graph, phase checkpoints. | [Why Software Factories Fail](approaches/why-software-factories-fail-comparison.md#проверка-гипотезы-тимлида-про-program-design) | Рекомендация, задача не создана | Medium |
| 2 | В product/docs positioning использовать формулу `lit-factory orchestration`: harness + roles + artifacts + human gates. | [Why Software Factories Fail](approaches/why-software-factories-fail-comparison.md#влияние-на-позиционирование-task-orchestrator) | Рекомендация, задача не создана | Low/Medium |
