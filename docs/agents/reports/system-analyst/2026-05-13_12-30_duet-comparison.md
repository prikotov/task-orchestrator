# Исследование Duet (Aomni) — Business Agent SaaS

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-13
**Объект:** Duet (duet.so, github.com/aomni-com)
**Задача:** [TASK-research-duet](../../../../todo/TASK-research-duet.todo.md)

---

## Резюме

Duet (by Aomni, $4.4M funding) — проприетарный SaaS-продукт, позиционируемый как «always-on AI agent built for teams». Автоматизирует GTM, product и ops workflows через skill-driven orchestration: intent → skill selection → multi-phase autonomous execution.

### Вердикт: 🟡 Заимствовать отдельные паттерны

Duet — не framework, не SDK, не workflow engine — это **business-agent SaaS** на уровне целых бизнес-процессов. Dependency невозможна (проприетарный, cloud-only). Наибольшая ценность — в паттернах prompt engineering и cron-triggered execution.

### Ключевые находки

1. **Skill-driven orchestration** — уникальная модель, не встреченная в других 23 проектах. Не DAG, не agent loop, не graph — multi-phase workflows, описанные в SKILL.md
2. **Prompt-driven error handling** — полностью через «Gotchas» секции в SKILL.md, без retry/CB/fallback. Примитивно, но показательно как подход
3. **SKILL.md + UseCase separation** — strict TypeScript-enforced запрет на behavioral overrides. Архитектурный урок для Domain/Presentation
4. **Cron tool** — scheduled pipelines по расписанию. Практичный паттерн для recurring chain execution
5. **31 skill** (19 default + 12 industry) — наиболее развитый публичный skill registry среди исследованных

### Паттерны для заимствования

| Приоритет | Паттерн | Обоснование |
| --- | --- | --- |
| P2 | Cron-trigger для chains | Расширение применимости chains за пределы ручного запуска |
| P2 | Idempotency guidance в prompts | Для fix_iterations: обогатить промпт runner'а |
| P2 | Baseline → Diff pattern | Для recurring chains с state comparison |
| P3 | SKILL.md-формат | Де-факто стандарт (16/24 проектов) |
| P3 | Gotchas в system prompt | Anti-pattern guidance для runner'ов |

### Отчёт

Полный отчёт: [duet-comparison.md](../../../../docs/research/framework-comparisons/duet-comparison.md)

Сводная таблица обновлена: строка #24 добавлена, тренды пересчитаны (23→24).
