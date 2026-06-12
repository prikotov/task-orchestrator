---
type: research
created: 2026-06-12
value: V2
complexity: C4
priority: P3
depends_on: []
epic:
author: Аналитик (Шерлок)
assignee:
branch:
pr:
status: backlog
---

# TASK-arch-agent-memory-context-store: Память/контекстное хранилище для результатов research/ретро/решений

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда агент выполняет research/ретро/решения, я хочу персистентное хранилище результатов с vector + keyword retrieval, compression и pruning, чтобы reuse знания и избежать повторной работы.

### Goal (Цель по SMART)
Спроектировать и реализовать Agent Memory / Context Store: vector + keyword retrieval для результатов research/ретро/решений, persistence, compression, pruning. Определить entity/VO, repository, и интегрировать с нашей DDD/Clean Architecture.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `src/Module/MemoryStore/` (новый модуль) (Application + Domain + Infrastructure layers). Возможные новые сущности: `MemoryEntry`, `MemoryId`, `MemoryType`, `MemoryVector`, `MemoryMetadata`.
*   **Текущее поведение:** task-orchestrator не имеет memory store. Результаты research/ретро/решений не сохраняются между запусками.
*   **Границы (Out of Scope):** Не реализовывать vector retrieval для skills (только для memory entries). Не интегрировать с ChromaDB или другими хранилищами — только определить абстракции.

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] Определить entity/VO для Memory Entry: `MemoryId`, `MemoryType` (research/retro/decision), `MemoryContent` (text), `MemoryVector` (embedding vector), `MemoryMetadata` (tags, timestamp, source chain)
- [ ] Определить repository interface: `MemoryRepositoryInterface` (Domain layer) с методами store, retrieveById, searchByVector, searchByKeyword, prune
- [ ] Реализовать storage abstraction (Infrastructure layer): file-based JSON + vector index (можно использовать плоский JSON + поиск по text, вектор optional)
- [ ] Определить use case handlers: `StoreMemoryCommand/Handler`, `RetrieveMemoryQuery/Handler`, `SearchMemoryQuery/Handler` (Application layer)
- [ ] Реализовать compression strategy: `MemoryCompressionStrategyInterface` (Domain layer) + implementation (Infrastructure layer) — LLM-based summarization или keyword extraction
- [ ] Реализовать pruning strategy: `MemoryPruningStrategyInterface` (Domain layer) + implementation (Infrastructure layer) — remove old/unused entries, dedup, supersede
- [ ] Указать ссылки на конвенции: [`Entity`](../../docs/conventions/layers/domain/entity.md), [`VO`](../../docs/conventions/core_patterns/value-object.md), [`Repository`](../../docs/conventions/layers/domain/repository.md), [`Use Case`](../../docs/conventions/layers/application/use_case.md), [`Service`](../../docs/conventions/core_patterns/service.md)

### 🟡 Should Have (Желательно)
- [ ] Реализовать keyword retrieval: text search with basic ranking (tf-idf or simple substring match)
- [ ] Реализовать vector retrieval: optional embedding (can be external service or local model), cosine similarity search
- [ ] Реализовать memory tags for categorization
- [ ] Реализовать memory expiration: TTL for old entries

### 🟢 Could Have (Опционально)
- [ ] Рассмотреть integration с vector database (Qdrant, ChromaDB) — отдельная задача/эпик
- [ ] Рассмотреть LLM-based memory extraction from chain execution logs
- [ ] Рассмотреть memory sharing between chains (global vs scoped memory)

### ⚫ Won't Have (Не будем делать)
- [ ] Не интегрировать с vector database (Qdrant, ChromaDB) в этой задаче — только определить абстракции
- [ ] Не реализовывать LLM-based memory extraction — только manual storage via use cases
- [ ] Не реализовывать memory sharing between different workspaces — только per-workspace isolation

## 4. Implementation Plan (План реализации)
*План заполняется исполнителем перед стартом.*
1. [ ] Определить entity/VO для Memory Entry
2. [ ] Определить repository interface и implementation (file-based JSON + vector index)
3. [ ] Реализовать compression strategy (LLM-based summarization or keyword extraction)
4. [ ] Реализовать pruning strategy (remove old/unused, dedup, supersede)
5. [ ] Определить use case handlers (StoreMemory, RetrieveMemory, SearchMemory)
6. [ ] Создать unit-тесты для repository, compression, pruning
7. [ ] Обновить документацию (архитектура, memory store examples)

## 5. Definition of Done (Критерии приёмки)
- [ ] Определены entity/VO для Memory Entry (Domain layer)
- [ ] Определен repository interface (Domain layer) и implementation (Infrastructure layer)
- [ ] Реализованы compression и pruning strategies (Domain + Infrastructure layers)
- [ ] Определены use case handlers (Application layer)
- [ ] Созданы unit-тесты для repository, compression, pruning
- [ ] Обновлена документация: архитектура memory store, examples

## 6. Verification (Самопроверка)
```bash
vendor/bin/phpunit tests/Unit/Module/MemoryStore/Domain/Entity/MemoryEntryTest.php
vendor/bin/phpunit tests/Unit/Module/MemoryStore/Infrastructure/Repository/FileMemoryRepositoryTest.php
vendor/bin/phpunit tests/Unit/Module/MemoryStore/Infrastructure/Compression/MemoryCompressionStrategyTest.php
vendor/bin/phpunit tests/Unit/Module/MemoryStore/Infrastructure/Pruning/MemoryPruningStrategyTest.php
ls data/memory/  # Проверка storage directory (если есть)
```

## 7. Risks and Dependencies (Риски и зависимости)
- **Риск:** Compression (LLM-based summarization) may be slow/expensive — need to evaluate trade-offs.
- **Риск:** Vector retrieval may require embedding service — need to define external dependency or local model.
- **Риск:** File-based storage may not scale for large memory sets — need to define migration path to vector database.

## 8. Sources (Источники)
- [odysseus-comparison.md](../../docs/research/framework-comparisons/odysseus-comparison.md) — секция "Implementation Candidates for task-orchestrator"
- [agent-frameworks-summary.md](../../docs/research/agent-frameworks-summary.md) — кластер "Контекст и memory" (12/26 проектов имеют auto-compaction/summarization)
- [OpenCode comparison](../../docs/research/framework-comparisons/opencode-comparison.md) — context compaction с structured template

## 9. Comments (Комментарии)
Цель этой задачи — basic memory store with file-based storage + compression + pruning. Vector database integration — отдельная задача/эпик.

**AGPL disclaimer:** Концепция memory store с vector + keyword retrieval взята из Odysseus/ChromaDB, но мы не копируем код. Implementation будет с нуля в нашей DDD/Clean Architecture.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-06-12 | Аналитик (Шерлок) | Создание задачи |