# Актуализация §3: Bernstein Comparison Document

**Роль:** Архитектор Локи
**Дата:** 2026-05-08
**Объект:** `docs/research/framework-comparisons/agent-bernstein-comparison.md`
**Задача:** Улучшение §3, исправление фактических ошибок

---

## Проблема

§3 первоначального документа (от 2026-04-05) анализировал код, **не существующий** в текущем репозитории Bernstein. Все фрагменты кода, ссылки на файлы и большинство «ограничений» относились к прототипу, который был полностью переписан.

## Ключевые находки

### Фантомный код
- `bernstein/circuit_breaker.py` — не существует. Реальный circuit breaker: 3 отдельных модуля (`provider_circuit_breaker.py`, `circuit_breaker.py` в observability, `evolution/circuit.py`)
- `bernstein/governance.py` — не существует. Budget/governance разнесены по `core/cost/`, `core/security/`, `evolution/governance.py`
- `bernstein/quality_gates.py` — не существует. Актуальный: `core/quality/quality_gates.py` с plugin system
- Все анализируемые «баги» (half-open → open transition error) относились к коду, которого нет в репозитории

### Инвалидированные критические замечания
| Утверждение в старом §3 | Реальность |
|---|---|
| «Фиксированный набор gates, не расширяемый» | Plugin registry с filesystem + entry_points discovery |
| «Без backoff» | Exponential backoff: `min(base * 2^retry, 300s)` |
| «Единственный верификатор» | VotingProtocol: MAJORITY, QUORUM, WEIGHTED, UNANIMOUS |
| «naive datetime без timezone» | `datetime.now(tz=UTC)` — timezone-aware |
| «Нет гарантий целостности лога» | HMAC-SHA256 chain с tamper-evidence |
| «Без потокобезопасности» | `threading.Lock` на всех мутациях состояния |
| «Захардкоженный верификационный промпт» | Параметризованный шаблон с 5 критериями |
| «Нет классификации ошибок в retry» | `_TRANSIENT_MARKERS` / `_FATAL_MARKERS` с dynamic retry limits |

### Новые подлинные ограничения (в обновлённом §3)
1. Provider breaker — in-process state, no cross-process coordination
2. Intent verification fallback на «yes» маскирует систематические сбои верификатора
3. Plugin discovery — code execution vector (загрузка .py файлов)
4. Budget multiplier doubling на retry — exponential cost growth
5. Keyword-based error classification в retry — fragile heuristic
6. Voting не интегрирован с бюджетным контроллером
7. Audit trail — single-writer, archive прерывает chain verify

## Объём изменений

- §1: Обновлена таблица ключевых файлов (14 актуальных файлов вместо 5 несуществующих)
- §2: Обновлена таблица обзора (14 строк, все основаны на текущем коде)
- §3: Полная переработка (6 подразделов, ~1300 слов → ~2200 слов, все факты верифицированы)
- §4: Обновлены описания с учётом task server, auto-decomposition, adaptive governance
- §5: Обновлена сводная таблица (12 строк, зрелость пересмотрена)
- §6: Обновлены все ссылки (22 актуальных пути вместо 5 несуществующих)

## Источники верификации

Все утверждения верифицированы по исходному коду Bernstein (commit `13ac00e`, 2026-05-08).
