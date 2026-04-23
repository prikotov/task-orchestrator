# RFC: Дистрибуция task-orchestrator как CLI-утилиты

> **Дата:** 2026-04-22 — Brainstorm #1 (21 раунд)
> **Дата:** 2026-04-23 — Brainstorm #2 (20 раундов, новая вводная: AI-агенты как установщики)
> **Участники:** Архитектор Гэндальф, Архитектор Локи, Бэкендер Тони
> **Фасилитатор:** Тимлид Алекс

---

## Краткое резюме для владельца проекта

### Кто будет использовать task-orchestrator

1. **PHP-разработчики** — ставят через Composer, как phpstan/psalm/deptrac
2. **AI-агенты** (Pi, Claude Code, Cursor и др.) — ставят и настраивают САМИ по инструкции, когда непрограммист просит: «настрой мне task-orchestrатор»

### Как будет распространяться

| Канал | Для кого | Статус | Усилия |
|---|---|---|---|
| **Composer/Packagist** | PHP-разработчики + AI-агенты | Primary, полная поддержка | ~1.5 ч |
| **Phar (GitHub Releases)** | CI/CD pipelines | Secondary, best-effort | ~45 мин |
| **Docker** | — | Отложен до появления спроса | — |
| **Инструкция для AI-агентов** | AI-ассистенты непрограммистов | Новый канал, P1 | ~2 ч |

### Что нужно сделать (~4–5 часов)

1. Поменять `type: "project"` → `"library"` в composer.json + добавить `"bin"`
2. Зарегистрировать пакет на Packagist
3. Настроить сборку Phar при релизе (box.json.dist + CI step)
4. Написать инструкцию по установке для AI-агентов (пошаговая, без assumptions)
5. Добавить `--validate-config` флаг для проверки конфигурации
6. Добавить typed exit codes (вместо одного catch-all `Command::FAILURE`)

### Решения, которые принялconsensus

- ✅ Composer — основной канал
- ✅ Phar — для CI, без self-update
- ✅ Docker — не наш случай (работаем с файлами и процессами на хосте)
- ✅ Инструкция для AI-агентов — новый канал дистрибуции
- ✅ CLI — стратегический актив, не техдолг (AI-агенты работают через shell)

### Решения владельца проекта

1. **SBOM/GPG** → отложено до v1.0. Денег не нужно (open-source инструменты), но время на настройку есть. Не приоритет.
2. **Programmatic API** → не сейчас. Архитектура позволяет добавить за ~4 часа, когда появится реальный запрос. Записать как возможное будущее.
3. **SKILL для AI-агентов** → создать в `docs/agents/skills/install-task-orchestrator/SKILL.md` по формату `SKILL-CREATION.md`. В README — краткая инструкция установки для людей. SKILL.md — пошаговая инструкция для AI-агента.

---

## Brainstorm #1 (2026-04-22, 21 раунд) — Полный протокол

**Дата:** 2026-04-22
**Участники:** Архитектор Гэндальф, Архитектор Локи, Бэкендер Тони

---

### ПРИНЯТЫЕ РЕШЕНИЯ

#### Решение 1: Два канала дистрибуции с первого дня (v0.1.0)
- **Composer/Packagist** — primary-канал (обнаружение + установка для PHP-экосистемы)
- **Phar (GitHub Releases)** — secondary/best-effort канал (convenience для пользователей вне Composer-проектов)
- Docker — отложен до появления измеримого спроса от non-PHP аудитории
- Стоимость: ~3.5 часа одноразово
- **Консенсус:** все трое (Тони сдал Phar-only в R7, признал Composer primary в R10)

#### Решение 2: `type: "project"` → `type: "library"` в composer.json
- One-line change, проект в v0.1.0 — лучший момент для смены
- **Владелец:** Бэкендер Тони (первый коммит в ветке task/)

#### Решение 3: Investment tier — Composer full, Phar best-effort
На основе 5 архитектурных решений Локи (принято всеми):

| Решение | Composer (full) | Phar (best-effort) |
|---|---|---|
| CI gate | Packagist publish failure = release blocked | `box compile` failure = warning, release continues |
| Тесты | Integration test: `composer require` в чистом окружении | Smoke test: `php task-orchestrator.phar --version` на Linux/macOS |
| Source of truth | `composer.json` — canonical | `box.json.dist` — производный |
| Error handling | Полная диагностика, version conflict detection | `@techdebt` — минимальные сообщения до v1.0 |
| Support boundary | Баг —priority: high | «Known limitation, используйте Composer» |

#### Решение 4: Phar-ограничения на v0.x — осознанный техдолг
- Без GPG-подписи (до v1.0)
- Без Windows CI (до v1.0)
- Без self-update (до v1.0)
- Маркировка: `@techdebt` с описанием и условиями снятия

---

### ПЛАН ДЕЙСТВИЙ

| # | Задача | Время | Владелец |
|---|---|---|---|
| 1 | Изменить `type` на `"library"`, добавить `"bin": ["bin/console"]` в composer.json | 5 мин | Бэкендер Тони |
| 2 | Создать `box.json.dist` — минимальная конфигурация для Phar-сборки | 45 мин | Архитектор Локи |
| 3 | CI pipeline: `composer install` → `box compile` → smoke-test → Packagist publish | 2 ч | Архитектор Гэндальф |
| 4 | Зарегистрировать пакет на Packagist, настроить API token | 10 мин | Бэкендер Тони |
| 5 | README: секция Installation (`composer require` — primary, Phar — альтернатива) | 30 мин | Архитектор Гэндальф |
| 6 | Integration test: `composer require` в чистом окружении → `vendor/bin/task-orchestrator --version` | 30 мин | Бэкендер Тони |

**Итого: ~3 ч 40 мин** (с буфером — 3.5–4 ч)

---

### КЛЮЧЕВЫЕ АРГУМЕНТЫ, ОПРЕДЕЛИВШИЕ РЕШЕНИЕ

1. **Арифметика:** При стоимости 3.5 ч за 2 канала vs 4 ч за 1 канал — оба канала дешевле и шире (Локи, R6)
2. **Hidden OPEX:** Phar primary генерирует Windows/GPG/stale-version incidents, Composer primary — нет (Локи, R9)
3. **Вовлечённость:** Определяется ценностью инструмента, а не усилием на установку (Гэндальф, R8 — опроверг Тони)
4. **Catch-22:** Отсутствие Packagist = невидимость для PHP-экосистемы; критично к v0.3, желательно с v0.1 (Гэндальф, R5; Тони, R7)

---

### НЕРАЗРЁШЕННЫЕ ВОПРОСЫ

1. **Совместимость `apps/console/` с `bin/`**: текущая структура CLI-приложения в `apps/console/src/` — как она взаимодействует с `"bin": ["bin/console"]`? Нужен отдельный анализ.
2. **Phar stub и PHP 8.4 compatibility**: `box compile` на PHP 8.4 может иметь edge-case'ы — требуется проверка.
3. **Критерии добавления Docker**: нет количественных порогов (сколько non-PHP запросов = добавляем Docker?).
4. **Metadata tests для Composer**: Локи упоминал тест на `composer show` → assert `"bin"` populated — не включён в план.
5. **Phar corruption detection**: нет плана по обработке повреждённых Phar-файлов.

---

### ЭВОЛЮЦИЯ ПОЗИЦИЙ

| Участник | R1 | R5/R6 | Финал (R8–R10) |
|---|---|---|---|
| Гэндальф | Composer primary, Phar — derivative | Оба канала, Composer primary | Composer primary, Phar best-effort, investment tier принят |
| Локи | Phar-only → Composer staged | Конвергенция с Гэндальфом + investment tier | 5 архитектурных решений как framework |
| Тони | Phar-only | Оба канала, Phar primary | Composer primary, все аргументы за Phar primary сданы |

**Примечание:** Тони в R10 заявил «Я был первым, кто предложил оба канала одновременно» — это фактическая неточность: в R2 Тони предложил именно Phar-only («Phar. Один канал. Завтра.»), оба канала он принял только в R7 под давлением аргументов Гэндальфа и Локи.

## Metrics
- Total rounds: 21
- Total time: 1852.1s
- Total tokens: 277149 / 75563
- Total cost: $0.0000
- Completed at: 2026-04-22T16:53:45+00:00

---

## Brainstorm #2 (2026-04-23, 20 раундов) — AI-агенты как установщики

**Новая вводная:** Помимо PHP-разработчиков, есть второй класс пользователей — AI-агенты (Pi, Claude Code, Cursor), которые ставят и настраивают tool сами по инструкции. Непрограммист говорит ассистенту: «настрой мне task-orchestrator» — и агент выполняет.

### Позиции участников

| Участник | Позиция |
|---|---|
| **Локи** | CLI уже работает для AI-агентов. 16 флагов, exit codes, JSON output — это машинный контракт. Архитектура уже headless. Не нужно ничего менять, нужна инструкция. |
| **Гэндальф** | CLI — lossy-прокси над programmatic API. Один catch-all exit code, stdout — помойка. Нужно: typed exit codes, `--validate-config`, чистый JSON. Инструкция для AI-агентов — это не просто текст, а машинно-читаемый контракт. |
| **Тони** | CLI — стратегический актив. Но нужен рефакторинг: typed exit codes (enum), `--validate-config`, вынести рендеринг из Command. ~11–12 часов работы. Programmatic API — когда появится реальный запрос. |

### Принятые решения

1. **Инструкция для AI-агентов** — новый канал дистрибуции. Формат: пошаговая инструкция без assumptions (какой PHP, куда ставить, как проверить) + минимальный пример конфига.
2. **Typed exit codes** — P0. Сейчас один catch-all `Command::FAILURE` → AI-агент не понимает причину ошибки. Enum `OrchestrateExitCode` решает это.
3. **`--validate-config`** — P1. AI-агент валидирует конфиг до запуска цепочки.
4. **CLI — strategic asset** — консенсус. AI-агенты работают через shell, CLI — их естественная среда.

### Влияние на решения Brainstorm #1

Решения #1–4 подтверждены. Новое:
- Plan действий расширен: +typed exit codes, +`--validate-config`, +инструкция для AI-агентов
- Оценка трудозатрат: 4 ч → ~11–12 ч (но P0 = ~4 ч, остальное P1/P2)

### Сессия
`var/sessions/cli-distribution/2026-04-23_03-02-36/`
