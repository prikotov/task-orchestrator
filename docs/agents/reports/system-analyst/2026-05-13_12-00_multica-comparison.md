# Исследование: Multica — Task Management Platform для Human + Agent Teams

**Роль:** Аналитик (Шерлок)
**Дата:** 2026-05-13
**Объект:** Multica (github.com/multica-ai/multica, ~28K звёзд, Go + TypeScript/Next.js)
**Задача:** [TASK-research-multica](../../../todo/TASK-research-multica.todo.md)

---

## Результат

Отчёт создан: `docs/research/framework-comparisons/multica-comparison.md`
Строка в сводной таблице: #24 Multica (уже присутствовала)

## Ключевые находки

### 1. Разные уровни абстракции
Multica — task management платформа (Linear для AI-агентов), не chain orchestrator. Работает на уровне «кому дать задачу», task-orchestrator — «как выполнить последовательность шагов». Нулевое пересечение проблемных пространств.

### 2. Poisoned session detection — уникальный паттерн
Multica классифицирует agent output/error и исключает «poisoned» sessions из resume lookup:
- `iteration_limit` — агент достиг лимита
- `agent_fallback_message` — fallback вместо результата
- `api_invalid_request` — API 400 (conversation history испорчен)

Ни один другой из 23+ исследованных фреймворков не имеет этого механизма.

### 3. Production-grade daemon architecture
110K+ LOC Go, dual heartbeat path (WS + HTTP fallback), runtime gone recovery с coalescing, orphan recovery при restart, graceful shutdown с 30s drain.

### 4. Вердикт: 🟡 заимствовать отдельные паттерны
- P2: poisoned output detection, session resume с fresh fallback, error classification для retry policy
- P3: task-level GC, per-agent model override, admission gate

### 5. Рекомендации
Quick wins (P2): poisoned output detection для fix_iterations — уникальный паттерн, подтверждённый production-кодом Multica (~100 LOC в poisoned.go).
