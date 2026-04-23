# Brainstorm: Дистрибуция CLI-утилиты — позиция Гэндальфа (раунд 1)

**Роль:** Архитектор Гэндальф (System Architect)
**Дата:** 2026-04-22
**Объект:** Brainstorm-сессия «Варианты дистрибуции task-orchestrator как CLI-утилиты»
**Задача:** Защита/разрушение тезиса #1: «Phar — единственный серьёзный вариант, Composer binary — костыль»

---

## Позиция

**Phar — primary distribution format, Composer binary — first-class citizen (не second-class и не костыль).**

## Ключевые аргументы

### 1. Сравнение с jq/ripgrep/terraform — ложная аналогия

`jq` (C), `ripgrep` (Rust), `terraform` (Go) — нативные бинарники, не требующие runtime. Task-orchestrator написан на PHP, требует PHP 8.4+, использует 25 Symfony-зависимостей. Phar не даёт автономности — он даёт *иллюзию* автономности. Сравнение с Go/Rust/C-инструментами некорректно: у них нет языковой экосистемы, которую можно «использовать или игнорировать» — у PHP есть, и мы уже сделали на неё ставку.

### 2. Composer binary — нулевая стоимость, ненулевая ценность

Добавление `"bin": ["bin/console"]` в `composer.json` — одна строка. Результат: Packagist-обнаруживаемость, `composer audit`, бесшовное обновление, версионирование — бесплатно. Называть это «костылем» — не понимать разницу между CAPEX и OPEX. Composer binary — нулевой OPEX.

### 3. Phar — правильная цель, но с реальными затратами

- Build pipeline: `box-project/box` + `box.json.dist` + compact-стратегия для 25 Symfony-пакетов → 3-5 дней CAPEX
- Self-update: ~550 строк кода + вечная поддержка (OPEX)
- Security: opaque binary без SBOM, нужен GPG-ключ + rotation
- Windows: Phar + PowerShell Execution Policy = documented pain

### 4. Phar и Composer — не конкурирующие каналы, а источник и производное

Архитектурно: `composer.json` → CI (`box compile`) → Phar (GitHub Release). Composer — источник истины, Phar — производный артефакт. Один CI-пайплайн, нулевая дополнительная стоимость поддержки. Тезис #3 («утроение багов») содержит логическую ошибку: это не три канала, это один артефакт + один производный.

### 5. Target-аудитория определяет primary, но не исключает complementary

- PHP-аудитория → Composer binary (primary, бесплатно)
- Non-PHP-аудитория → Phar (primary, инвестиция)
- Выбор «одно из двух» — архитектурная близорукость

## Scorecard

| Критерий | Composer binary | Phar |
|----------|-----------------|------|
| CAPEX (внедрение) | ✅ 1 строка | ❌ 3-5 дней |
| OPEX (поддержка) | ✅ Нулевой | ⚠️ Self-update + signing |
| Security audit | ✅ `composer audit` | ❌ GPG + manual |
| Обнаруживаемость | ✅ Packagist | ❌ GitHub Releases only |
| CI/CD UX | ⚠️ Требует composer | ✅ wget + chmod |
| Изоляция зависимостей | ⚠️ `bamarni/composer-bin-plugin` | ✅ Встроенная |
| Runtime зависимость | PHP 8.4+ | PHP 8.4+ (одинаково!) |
| Windows | ✅ Native | ⚠️ Execution Policy issues |

## Резюме

Тезис #1 **разрушен**: Composer binary — не костыль, а first-class citizen с нулевой стоимостью поддержки. Phar — primary distribution format, но производный от Composer-пакета, а не заменяющий его. Правильная архитектура: Composer → (CI) → Phar, один пайплайн.
