# Brainstorm: Дистрибуция CLI-утилиты — позиция Локи (раунд 4)

**Роль:** Архитектор Локи (System Architect)
**Дата:** 2026-04-22
**Объект:** Brainstorm-сессия «Варианты дистрибуции task-orchestrator как CLI-утилиты»
**Задача:** Защита позиции Composer-primary, оспаривание аргументов Тони

---

## Позиция

**Composer binary — primary, Phar — secondary.**

## Ключевые аргументы

1. **Противоречие Тони:** Целевой пользователь — PHP-разработчик в Composer-экосистеме, но Тони выбрал Phar primary. Логический разрыв.

2. **self-update — не «100 строк, 2 дня»:** Реалистичная оценка — ~550 строк кода + тестов + вечная поддержка (GitHub API changes, edge cases, platform-specific bugs). Composer даёт обновление бесплатно.

3. **Аудит безопасности:** Phar — opaque binary без SBOM, без `composer audit`, без vulnerability scanning. Для инструмента, запускающего AI-агентов через `symfony/process` — это security requirement, а не cosmetic.

4. **Packagist — не «0 effort»:** Публикация — 30 минут. Правильная настройка (документация, conflict-директивы, CI-тестирование совместимости) — 3-5 дней. Но это инвестиция (CAPEX), а не постоянный расход (OPEX), в отличие от self-update.

5. **Изоляция через `bamarni` — решаема:** `composer.json` suggest-section, README, post-install advisory plugin. Не фатальный недостаток, а инженерная задача.

## Scorecard

| Критерий | Composer | Phar |
|----------|----------|------|
| Естественность UX | ✅ | ❌ |
| Обновление | ✅ (0 кода) | ❌ (550 строк + поддержка) |
| Аудит безопасности | ✅ | ❌ |
| Изоляция | ⚠️ (требует bamarni) | ✅ |
| CI-совместимость | ⚠️ | ✅ |
| Прозрачность зависимостей | ✅ | ❌ |
| Maintenance burden | ✅ (разовая) | ❌ (постоянная) |

---
