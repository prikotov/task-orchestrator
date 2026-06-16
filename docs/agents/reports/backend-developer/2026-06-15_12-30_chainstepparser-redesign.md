# Реализация: редизайн `ChainStepParserHelper` → Mapper + Factory

**Роль:** Бэкендер Левша (backend_developer_levsha) — реализация; Тимлид Алекс — завершение проверок, коммиты и отчёт (сабагент таймаутил на этапе проверок).
**Дата:** 2026-06-15
**Контекст:** PR #261 (`epic refactor/phpmd-baseline-elimination`), ветка `refactor/phpmd-baseline-elimination`.
**Дизайн:** [`docs/agents/reports/system-architect/2026-06-15_11-55_chainstepparser-redesign.md`](../system-architect/2026-06-15_11-55_chainstepparser-redesign.md) (Архитектор Локи).
**Аудит проблемы:** [`docs/agents/reports/code-reviewer-backend/2026-06-15_11-06_chainstepparserhelper-convention-audit.md`](../code-reviewer-backend/2026-06-15_11-06_chainstepparserhelper-convention-audit.md) (Ревьювер Пуаро).

---

## Постановка

`ChainStepParserHelper` (создан в коммите `c8f2789` как часть эпика PHPMD-baseline) нарушал конвенцию `helper.md` (3 FAIL из аудита Пуаро: SRP — два публичных контракта; бизнес-логика — guessed-дефолты + merge + семантический флаг; отсутствие прямых unit-тестов при ветвлениях). По факту поведения это фабрика, замаскированная под helper. Реализовать редизайн строго по мини-ADR Локи с **ZERO behavioral change**.

## Что реализовано (строго по дизайну Локи)

### Создано (3 класса)

| Файл | NLOC | Слой | Ответственность |
|---|---|---|---|
| `src/Module/ChainDefinition/Domain/Factory/ChainStepFactory.php` | 161 | Domain\Factory | Авторитетная граница создания `ChainStepVo`: guard-инварианты (role/command/label), object-level дефолты через `ChainStepVo::DEFAULT_*`, merge `step ?? chain` на typed inputs (`?bool` для отличия `false` от отсутствия), перенос `runnerExplicit` без повторного вычисления |
| `src/Module/ChainDefinition/Infrastructure/Mapper/Chain/YamlChainStepMapper.php` | 191 | Infrastructure\Mapper | Техническая граница YAML array shape → вызовы фабрики: type-dispatch по `ChainStepTypeEnum`, извлечение `runnerExplicit` через `array_key_exists('runner', $step)`, `extractNoContextFiles(): ?bool`, shape PHPDoc |
| `src/Module/ChainDefinition/Infrastructure/Mapper/Chain/YamlRetryPolicyMapper.php` | 31 | Infrastructure\Mapper | Изолирует null/empty handling retry-политики, делегирует в `ChainRetryPolicyVo::createFromArray()` |

### Удалено

- `src/Module/ChainDefinition/Infrastructure/Service/Chain/Helper/ChainStepParserHelper.php` — после миграции 4 call sites в `YamlChainLoaderService`. Ноль ссылок осталось в `src/` и `tests/`.

### Изменено

- `ChainStepVo` — добавлены `public const string DEFAULT_RUNNER = 'pi'` и `public const int DEFAULT_TIMEOUT_SECONDS = 120`; constructor/static factory defaults переключены на `self::DEFAULT_*`. **Поведение не изменилось.**
- `YamlChainLoaderService` — конструктор получил 2 DI-параметра (`YamlChainStepMapper`, `YamlRetryPolicyMapper`); 4 static-вызова (стр. 133, 137, 187, 191) заменены на `$this->...->mapTo...()`.
- `phpmd.xml` — записей про `ChainStepParserHelper` не было (подтверждено), изменений не требуется.

### Тесты (3 новых класса по списку дизайна)

| Файл | NLOC | Кейсов |
|---|---|---|
| `tests/Unit/Domain/Factory/ChainStepFactoryTest.php` | 308 | defaults (pi/120), explicit-флаг, merge retry/no_context_files (включая `false !== null`), guard-сообщения |
| `tests/Unit/Infrastructure/Mapper/Chain/YamlChainStepMapperTest.php` | 151 | type-dispatch, `runnerExplicit` по key presence (включая backward-compat `runner: null`), missing/unknown type |
| `tests/Unit/Infrastructure/Mapper/Chain/YamlRetryPolicyMapperTest.php` | 54 | null/пусто/полный/частичный |

Существующие `YamlChainLoaderTest` / `YamlChainLoaderToolStepTest` оставлены как end-to-end smoke; `CoversClass(ChainStepParserHelper)` удалён.

### Адаптация wiring в integration-тестах

Изменены 5 integration-тестов + `OrchestrateCommandTest`: конструктор `YamlChainLoaderService` получил 2 новых DI-параметра, ручные `new YamlChainLoaderService(...)` обновлены. **Ассерты не тронуты** — это wiring, не behavioral change.

---

## ZERO behavioral change — подтверждение

| Поведение | Статус | Где сохранено |
|---|---|---|
| `runner='pi'` при отсутствии ключа | ✅ | `ChainStepFactory::createAgent`: `runner ?? ChainStepVo::DEFAULT_RUNNER` |
| `timeout=120` при отсутствии ключа | ✅ | `ChainStepFactory::createTool/createQualityGate`: `timeoutSeconds ?? ChainStepVo::DEFAULT_TIMEOUT_SECONDS` |
| `runnerExplicit` по **key presence** (не по значению) | ✅ | `YamlChainStepMapper::mapAgentStep`: `array_key_exists('runner', $step)` |
| `runner: pi` → explicit=true | ✅ | key presence (backward compat) |
| `runner: null` → explicit=true, runner=pi | ✅ | key presence (backward compat, отмечен как странность в дизайне) |
| Наследование retry_policy (`step ?? chain`) | ✅ | `ChainStepFactory::createAgent`: `stepRetryPolicy ?? chainRetryPolicy` |
| Наследование no_context_files (`step ?? chain`, `false ≠ отсутствие`) | ✅ | `extractNoContextFiles(): ?bool` + `stepNoContextFiles ?? chainNoContextFiles` |
| Сообщения исключений **byte-to-byte** | ✅ | сверено с оригиналом `c8f2789`: все 6 сообщений (`Step "type" is required...`, `Agent step "role" is required...`, `Tool/quality_gate step must have "command"/"label"...`) идентичны |
| `config/chains.yaml` | ✅ | не тронут, inline agent-шаги без runner работают |

---

## Результаты проверок (Тимлид, после таймаута сабагента)

| Инструмент | Результат |
|---|---|
| PHPUnit | **OK: 1017 tests, 2848 assertions** (было 981/2770 → +36 тестов, +78 assertions) |
| Psalm | **0 errors** (171 info-level — норма проекта) |
| PHPMD | **No violations** (baseline неизменен: 3 записи — bridge.php + DynamicLoopExecution ×2) |
| Deptrac | **0 violations**, 0 warnings, 0 errors |
| PHPCS (sniff-tests) | пропущено — pre-existing окружение (отсутствует `vendor/prikotov/coding-standard/vendor/autoload.php`), не связано с изменениями |

---

## Соответствие конвенциям

- `factory.md`: `ChainStepFactory` — `final`, в слое создаваемого VO (`Domain\Factory`), централизует инварианты и сложность сборки, принимает typed primitives/VO (не raw arrays).
- `mapper.md`: `YamlChainStepMapper` / `YamlRetryPolicyMapper` — `final readonly`, через DI, shape PHPDoc для `array<string,mixed>`, только техпреобразование внешнего формата.
- `helper.md`: `ChainStepParserHelper` **удалён** — чистый helper не оставлен (микрометоды ушли в private methods mapper'а, как предусмотрено дизайном).
- `value-object.md`: `ChainStepVo` остаётся источником object-level defaults через named constants.

## Совместимость с существующим `ChainDefinitionFactory`

Фабрики не конкурируют: `ChainStepFactory` создаёт один `ChainStepVo`; `ChainDefinitionFactory` создаёт chain-definition VO и проверяет cross-step invariant `fix_iterations` через `FixIterationsReferenceIntegritySpecification`. Обе фабрики появились в этом PR как замена двух helper'ов с бизнес-логикой из коммита `c8f2789`.

## Тайминг

Сабагент Левша завершил всю реализацию (3 класса + 3 теста + миграция + constants), но таймаутил (soft 900s / hard 1300s) на этапе прогона проверок. Тимлид Алекс выполнил проверки, ревью кода (включая сверку сообщений byte-to-byte с оригиналом `c8f2789`), данный отчёт и коммиты.
