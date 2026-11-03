---
type: chore
created: 2026-11-02 15:25:21 (1793607921)
due: 
started: 2026-11-02 15:25:21 (1793607921)
completed: 2026-11-03 03:51:41 (1793677901)
cancelled: 
value: V2
complexity: C1
priority: P2
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: Тимлид Алекс (pi)
branch: task/role-model-redistribution
pr: https://github.com/prikotov/task-orchestrator/pull/449
status: done
---

# TASK-config-role-model-redistribution: Перераспределение моделей по ролям и дефолт pi (2026-11-02)

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

Профили ролей пакета (`config/chains.yaml`) синхронизированы с раскладом 2026-10-29, но с тех пор изменилась модельная панель владельца: подключена подписка Kimi Coding (лучшая агентная модель K3.1-Pro), поменяны решения по хост-проектам (TasK 523f23ecc: Арагорн → GLM-5.3-Flash `high`, Гэндальф → Kimi `high`, Локи → MiMo-Pro `high`, Пуаро → Huihui `medium`). В пакетном раскладе оркестратор (Алекс) сидит на MiMo-Pro `off` — слабое рассуждение у координатора делегирования; архитектор Гэндальф — на MiMo-Pro, хотя в хост-проектах его перевели на Kimi; ревью кода (Пуаро) — на локальной qwen, которую владелец заменил на Huihui. Плюс интерактивный дефолт проекта (`.pi/settings.json`) — MiMo-Flash `max`, что нарушает собственное правило «MiMo-V2.6-Flash не назначается». К чему приводит: рассинхрон пакета с хост-проектами, качество координации/архитектуры ниже доступного, расходы квот не оптимальны.

### Варианты или путь решения (Solution Sketch)

Решение задано владельцем (2026-11-02): зеркалировать approved-расклад TasK — Алекс и интерактивный дефолт → Z.AI GLM-5.3-Flash `high`; Гэндальф → Kimi K3.1-Pro `high`; Локи → MiMo-V2.6-Pro `high` (вендорное разнообразие пары Гэндальф/Локи); Пуаро → локальная Huihui `medium`.

### Ожидаемый результат (Expected Result)

Пакетные роли работают на моделях, согласованных владельцем 2026-11-02 и синхронных с TasK; интерактивные сессии проекта стартуют на GLM-5.3-Flash `high`; нарушенное правило «MiMo-Flash не назначается» больше не действует ни в одном конфиге проекта.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)
> **User Story:** Как владелец пакета, я хочу, чтобы профили ролей task-orchestrator использовали согласованные модели и уровни рассуждения, чтобы делегирование шло на оптимальных моделях без перерасхода подписочных квот.

### Цель по SMART (Goal)

(S) Обновить `config/chains.yaml` (Алекс → Z.AI GLM-5.3-Flash `high`; Гэндальф → Kimi K3.1-Pro `high`; Локи → MiMo-V2.6-Pro `high`; Пуаро → Ollama Huihui `medium`) и `.pi/settings.json` (Z.AI GLM-5.3-Flash `high`); (M) расклад команд совпадает с approved-списком, `make check` зелёный, валидация YAML проходит; (A) модели присутствуют в конфигах провайдеров pi (`models-store.json`/`models.json`); (R) зеркалирует TasK 523f23ecc (решение владельца 2026-11-02); (T) один PR.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `config/chains.yaml`, `.pi/settings.json`, задача в `todo/`
*   **Текущее поведение:** см. «Problem»; команды ролей определены YAML-якорями в `config/chains.yaml`
*   **Границы (Out of Scope):** прочие роли без изменений (Левша `max`, Тони/Хаус/Шерлок/Гермиона), changelog/версия пакета, код оркестратора

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)
- [x] team_lead_alex → `zai/glm-5.3-flash --thinking high`
- [x] system_architect_gandalf → `kimi-coding/k3.1-pro --thinking high`
- [x] system_architect_loki → `xiaomi-token-plan-sgp/mimo-v2.6-pro --thinking high`
- [x] code_reviewer_backend_puaro → `ollama/huihui-ai:pi-100k --thinking medium`
- [x] `.pi/settings.json` → `zai/glm-5.3-flash`, thinking `high`
- [x] YAML валиден, все алиасы якорей разрешаются
### 🟡 Желательно (Should Have)
- [x] Заголовочный комментарий `chains.yaml` дополнен решением с датой
### 🟢 Опционально (Could Have)
- [ ] Проверочный запуск роли через `agent:role-skills`
### ⚫ Не будем делать (Won't Have)
- [ ] Изменения прочих ролей и кода пакета
- [ ] Слияние PR без подтверждения владельца

## 4. План реализации (Implementation Plan)
1. [x] Ветка `task/role-model-redistribution` от `main`
2. [x] Правки `config/chains.yaml` и `.pi/settings.json`
3. [x] `make check`, коммит, push
4. [x] PR через prikotov-bot (label `pi`), задача → `done`

## 5. Критерии приёмки (Definition of Done)
- [x] Распечатка команд ролей совпадает с Must Have
- [x] `make check` проходит (включая validate-todo, validate-roles)
- [x] Нет регрессий в смежных модулях
- [x] Обновлена техническая документация (заголовочный комментарий `chains.yaml`)

## 6. Самопроверка (Verification)
```bash
make check                                            # lint + валидация + тесты
php -r 'require "vendor/autoload.php"; print_r(Symfony\Component\Yaml\Yaml::parseFile("config/chains.yaml"));'  # парсинг и команды ролей
```

## 7. Риски и зависимости (Risks and Dependencies)
- Huihui `medium` — `thinkingLevelMap` не задан в `models.json`; уровень может кламповаться — проверить фактическим запуском роли
- Kimi — месячное ведро в перерасходе темпа (2.75×): сессии Гэндальфа в пакете редкие, но бюджет держать в уме

## 8. Источники (Sources)
- [ ] `config/chains.yaml` (расклад 2026-10-29, PR #405)
- [ ] TasK `config/chains.yaml`, коммит 523f23ecc (2026-11-02)
- [ ] Artificial Analysis, снапшот рейтингов 2026-11-02

## 9. Комментарии (Comments)

- Дефолт интерактивных сессий выровнен с глобальным (`~/.pi/agent/settings.json`: Z.AI GLM-5.3-Flash `high`, решение владельца 2026-11-02)
