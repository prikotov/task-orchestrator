# Brainstorm: CLI Distribution — Раунд 19, Гэндальф

**Роль:** System Architect (Гэндальф)
**Дата:** 2026-04-23
**Объект:** Переоценка дистрибуции с учётом AI-агентов как установщиков
**Задача:** Brainstorm-сессия, var/sessions/cli-distribution/

---

## Краткое резюме

### Главный тезис
Локи понизил typed exit codes с P0 до P1 через круговое рассуждение (begging the question): аргументировал снижение приоритета P0a через функциональность (`error.type` в JSON), которая зависит от P0a. Проверка кодовой базы подтвердила: `error.type`, `error.message`, `error.availableChains` **не существуют** — grep по всему проекту вернул ноль результатов.

### Ключевые аргументы
1. **Circular reasoning Локи:** JSON с `error.type` требует typed exceptions → typed exceptions требуют typed catch → typed catch = P0a. Локи использует результат P0a как аргумент, что P0a не нужна.
2. **CI/CD — primary consumer exit codes:** Exit code — единственный контракт, который CI/CD обрабатывает автоматически. Граница между primary/secondary проводится по контракту: primary = влияет на автоматическое поведение consumer'а.
3. **«Шум 1990-х» — демагогия:** POSIX exit codes работают сегодня в GitHub Actions, Docker, Kubernetes, systemd. Это действующий стандарт, не legacy.
4. **P0a → P0b — dependency chain:** Нельзя построить JSON error response без typed exceptions. Фундамент (P0a) должен идти до фасада (P0b).

### Итоговый ordering
- P(-1): type: library + bin + Packagist (1–1.5 ч)
- **P0a: Typed exceptions + exit codes (2–2.5 ч)** — остаётся P0
- P0b: --output=json + stderr (3–3.5 ч)
- P1a: Рефакторинг Command (2.5–3 ч)
- P1b: ChainConfigValidator (2–2.5 ч)
- **Итого: 11–13 часов**

### Контр-вызовы
- Локи: покажи конкретную строку кода, где существует `error.type` в error response
- Локи: покажи JSON error response с `error.type: "chain_not_found"` без typed exceptions
- Тони: согласен ли ты, что CI/CD — primary consumer exit codes?
