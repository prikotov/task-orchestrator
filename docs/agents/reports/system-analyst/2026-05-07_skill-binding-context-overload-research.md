# Аналитический отчёт: Привязка Skills и перегрузка контекста инструкций (доработка)

**Роль:** Аналитик Шерлок
**Дата:** 2026-05-07
**Объект:** docs/research/analytical/skill-binding-and-context-overload.md
**Задача:** Доработка исследования — фокус на SKILL.md как текстовые инструкции в system prompt, не tools/function-calling

---

## Саммари

Доработка аналитического отчёта по запросу заказчика. Предыдущая версия исследовала tool/function-calling overload (JSON-схемы) — это была неправильная тема. Исправленная версия фокусируется на instruction following degradation при длинных system prompts, содержащих текстовые SKILL.md.

### Ключевые находки

1. **System prompt Тимлида = ~19K токенов** — уже в зоне значительной деградации instruction following (порог: 4K-8K).

2. **Lost in the Middle применим к инструкциям.** Навыки в середине system prompt получают на 25-40% хуже attention, чем в начале/конце.

3. **Instruction following монотонно убывает** с ростом числа инструкций — каждая дополнительная инструкция «размывает» внимание ко всем остальным.

4. **Фреймворки решают проблему минимизацией контекста**, а не сжатием: bootstrap budget (OpenClaw), lazy loading (Hermes), fresh context per worker (Factory Missions).

5. **Рекомендуемый путь:** Quick win (bootstrap budget 8K токенов) → ленивая загрузка навыков → fresh context per sub-agent.

### Структура отчёта

- **§1 (компактный):** 5 паттернов привязки навыков к агентам — статическая привязка, ленивая загрузка, bootstrap budget, fresh context per worker, summary template.
- **§2 (полностью переписан):** перегрузка контекста инструкций — Lost in the Middle для инструкций, пороги деградации, prompt compression, подходы фреймворков.

### Полный отчёт

docs/research/analytical/skill-binding-and-context-overload.md
