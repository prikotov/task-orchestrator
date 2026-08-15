---
type: chore
created: 2026-07-26
value: V3
complexity: C2
priority: P3
depends_on:
epic:
author: Тимлид (Алекс)
assignee: Бэкендер Тони
branch: task/validate-language-true-zero
pr: https://github.com/prikotov/task-orchestrator/pull/348
status: review
---

# TASK-chore-validate-language-true-zero: Довести validate-language до настоящего 0 over (чистка allowlist + перевод общих слов)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)
- После включения `validate-language` (PR #325 `coding-standard` 0.26 + #326 в `make check` + #327 `allowlist`/`max_ratio`/`exclude` + #328 чистка 18 файлов) формальный baseline = **0 over-threshold**.
- Но этот «0» достигнут во многом за счёт **слишком широкого `allowlist`**: при агрессивном снижении baseline (373 → 18) туда попали **общие английские слова** (`execution`, `path`, `design`, `action`, `time`, `step`, `fix`, `changes`, `draft`, `ok`, `open`, `user`, `cases`, `shared`, `free`, `tier`, `source`, `big`…), а не только термины/жаргон. Валидатор их не видит → тексты `docs/` местами остались английскими, а `validate-language` формально «зелёный».
- Это та же природа ошибки, что с переводом «God Object» → «объект-бог» (нестандартный; правильно — «Божественный объект»): погоня за метрикой в ущерб качеству.

### Варианты или путь решения (Solution Sketch)
- Почистить `allowlist` в `.coding-standard.php` — убрать общие слова, оставить только термины/жаргон/имена.
- Перевести общие слова в `docs/` (Тех. писатель Гермиона) — `execution`→«выполнение», `path`→«путь», и т.д.; термины при первом упоминании — с английским в скобках.
- Довести `validate-language` до 0 over уже на чистом тексте.

### Ожидаемый результат (Expected Result)
- `validate-language`: **настоящий** 0 over-threshold — не за счёт общих слов в `allowlist`, а за счёт переведённого текста.
- `allowlist` содержит только легитимные термины/жаргон/имена собственные.

## 1. Concept and Goal (Концепция и Цель)
### Story (Job Story)
Когда мы включили `validate-language` в `make check`, я хочу, чтобы «0 warnings» означало настоящее качество русскоязычной документации, а не формальный «0» за счёт широкого `allowlist`, чтобы валидатор реально ловил англицизмы-общие-слова в будущем.

### Goal (Цель по SMART)
Почистить `allowlist` от общих английских слов и перевести их в `docs/` до настоящего 0 over-threshold `validate-language`. Срок: до конца Q3 2026. Зависит от merge PR #326 и #328.

## 2. Context and Scope (Контекст и Границы)
*   **Где делаем:** `.coding-standard.php` (ключ `language.allowlist`), файлы `docs/` (перевод общих слов).
*   **Текущее поведение:** формально 0 over, но `allowlist` загрязнён общими словами (список в секции 6). `max_ratio` 0.08, `paths`/`exclude` настроены в PR #327.
*   **Out of Scope:** изменение `max_ratio` (0.08 — оставляем), `paths`/`exclude` (настроены). Не трогаем `todo/` (вне проверки), внешние каталоги (`docs/conventions`, `docs/todo-md`, `docs/git-workflow` — из пакетов).

## 3. Requirements (Требования, MoSCoW)
### 🔴 Must Have (Обязательно)
- [ ] `allowlist` почищен от общих английских слов; остались только термины/жаргон/имена.
- [ ] Общие слова переведены в `docs/` (с терминами в скобках при первом упоминании).
- [ ] `validate-language`: 0 over-threshold на чистом тексте.
- [ ] `make md-links` — зелёный.

### 🟡 Should Have (Желательно)
- [ ] `allowlist` разбит по категориям с комментариями (как сейчас).
- [ ] В Comments зафиксирован boundary «термин vs общее слово».

### ⚫ Won't Have (Не будем делать)
- [ ] Изменение `max_ratio` или `paths`/`exclude`.
- [ ] Перевод `todo/` и внешних каталогов пакетов.

## 4. Implementation Plan (План реализации)
1. [ ] Составить финальный список общих слов на удаление из `allowlist` (стартовая база — секция 6).
2. [ ] Убрать общие слова из `.coding-standard.php` → `allowlist`.
3. [ ] Перевести общие слова в `docs/` (Гермиона): `execution`→«выполнение», `path`→«путь», и т.д.
4. [ ] Довести `validate-language` до 0 over; `make md-links`.

## 5. Definition of Done (Критерии приёмки)
- [ ] `allowlist` содержит только термины/жаргон/имена; общие слова убраны.
- [ ] `validate-language`: 0 over-threshold на чистом тексте (не за счёт общих слов в `allowlist`).
- [ ] `make md-links` — зелёный.
- [ ] `allowlist` закомментирован по категориям.

## 6. 🔎 Замечания / термины для внимания (живая секция — пополняется по мере ревью PR #328)

Принцип: `allowlist` = только термины/жаргон/имена собственные. Общие английские слова → перевод; термин при первом упоминании — «русский (English)».

| Англицизм (как было) | Правильный перевод | Примечание / источник |
|---|---|---|
| God Object → «объект-бог» | **Божественный объект (God object)** | Устоявшийся рус. термин; [Википедия «Божественный объект»](https://ru.wikipedia.org/wiki/Божественный_объект). «Объект-бог» — нестандарт, статьи нет. (исправлено в #328) |
| `execution` (в «static-chain execution») | **выполнение** | общее слово, не термин. «выполнение `static-chain`» |
| `path` (в «dynamic-loop path», «Dynamic path») | **путь** | общее слово. «динамический путь», «поведенческий путь» |
| концепт `resume` (в «реализует resume», «Контракт resume», «поток resume») | **возобновление** | концепт/общее слово (НЕ метод `resume()`, НЕ `$resumeDir`). «реализует возобновление», «поток возобновления (resume flow)» (исправлено в #328) |
| `hook` (переведено как «перехватчик») | **хук** | калька (как «лог», «пулл»), устоявшаяся в рус. IT; короче и привычнее «перехватчика». «хук», «хуки» (исправлено в #328) |
| _пополняется по замечаниям_ | | |

### Кандидаты на удаление из allowlist (общие слова)
`execution`, `path`, `resume`, `design`, `action`, `time`, `step`, `fix`, `changes`, `draft`, `ok`, `open`, `user`, `cases`, `shared`, `free`, `tier`, `source`, `big`, `matter`, `five`, `and`, `of`, `in`, `out`.

**Оставить** (термины/жаргон/имена): `chain`, `runner`, `retry`, `fallback`, `payload`, `CircuitBreaker`, `AgentRunner`, `ChainDefinition`, `namespace`, `enum`, `byok`, `bootstrap`, `Symfony`, `PHP`, `DDD`, …

### Пометки исполнителю (на что обращать внимание)
- Не переводить **имена классов/идентификаторы**: `ExecutionStrategyInterface`, `AgentRunner`, `TASK-feat-...`, `PR #N`, имена файлов.
- Не переводить **имена компонентов/библиотек**: «Symfony Process», «Doctrine ORM» — `Process`/`ORM` здесь часть имени компонента, не «процесс Symfony» (ошибка из ревью #328, исправлено).
- Не переводить **термины-жаргон из allowlist**: `chain`, `runner`, `retry`, `fallback`, `payload`, `CircuitBreaker`, и т.д.
- **Общие английские слова** (`execution`, `path`, `design`, `action`, `time`, `step`, …) — переводить.
- Сохранять формат markdown, ссылки, якоря; после правок заголовков — `make md-links`.
- Каждое сомнительное слово — проверять: термин (→ allowlist/скобки) или общее слово (→ перевод).

## 7. Verification (Самопроверка)
```bash
vendor/bin/validate-language                                          # 0 over
vendor/bin/validate-language --json | jq '[.files[]|select(.ratio>=0.08)]|length'   # 0
make md-links
make validate-todo
```

## 8. Risks and Dependencies (Риски и зависимости)
- Удаление общих слов из `allowlist` временно поднимет baseline — переводить в той же задаче.
- Риск переусердствовать: не удалить легитимные термины (boundary — секция 6).
- Правки заголовков → битые якоря → `make md-links` поймает.
- Зависимости: PR #326 (`validate-language` в `make check`), #328 (чистка 18 файлов).

## 9. Sources (Источники)
- [Википедия: Божественный объект](https://ru.wikipedia.org/wiki/Божественный_объект) — пример корректного рус. термина.
- `docs/conventions/ops/validate-language.ru.md` (в пакете `coding-standard`) — док. валидатора.
- PR #325, #326, #327, #328 — история внедрения `validate-language`.

## 10. Comments (Комментарии)
Задача выделена из ревью PR #328 по запросу пользователя: «0 over» должен быть честным, а не allowlist-driven. Живая секция 6 пополняется по мере замечаний по PR #328.

## Change History (История изменений)
| Дата | Автор (роль) | Изменение |
| :--- | :--- | :--- |
| 2026-07-26 | Тимлид (Алекс) | Создание задачи. Контекст: validate-language формально «0 over», но за счёт широкого allowlist; цель — настоящее качество. |
