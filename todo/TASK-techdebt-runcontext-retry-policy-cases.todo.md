---
type: test
created: 2026-11-24 03:50:21 (1795492221)
due: 
started: 
completed: 
cancelled: 
value: V1
complexity: C1
priority: P3
cost_plan: 
cost_fact: 
depends_on: 
epic: 
author: Тимлид Алекс (pi)
assignee: 
consultants: []
reviewer: 
approver: 
informed: []
branch: 
pr: 
status: todo
---

# TASK-techdebt-runcontext-retry-policy-cases: Симметричные retry-policy кейсы в контракт-тесте RunDynamicLoopAgentService

<!--
  Секция создана по шаблону docs/todo-md/templates/task.md по решению владельца 2026-11-24
  (неблокирующая рекомендация ревью PR #493). Плейсхолдеры заменены, AI-инструкции удалены.
-->

## 0. Простое описание (Human Brief)

### Проблема простыми словами (Problem)

В задаче [TASK-techdebt-runfacilitator-vo](done/TASK-techdebt-runfacilitator-vo.todo.md) (PR #493) появился контракт-тест `RunDynamicLoopAgentServiceTest`: он проверяет, что value-объекты контекста (VO — небольшие неизменяемые объекты с полями) передаются в вызовы раннеров агентов «поле в поле», без перестановок и потерь. Но такая страховка покрывает только часть сценариев: для завершающего прохода цикла (finalize — вызов `runFacilitatorFinalize`) и для прохода участника с ненулевой `retryPolicy` (policy повторов — правила, сколько раз перезапускать шаг при сбое) симметричных проверок в тесте нет. Код сейчас работает верно — это не баг; проблема в том, что при будущих правках сигнатур или полей VO регрессия именно на этих путях может проскочить незамеченной: тест будет зелёным не потому, что всё хорошо, а потому, что эти пути он не смотрит. Мешает команде: сужает защитную сетку, введённую в PR #493, ровно там, где маппинг тоже нетривиальный.

### Варианты или путь решения (Solution Sketch)

Один вариант: дополнить существующий `RunDynamicLoopAgentServiceTest` двумя-тремя кейсами по образцу уже имеющихся (заготовки стабов и хелперы в файле переиспользовать):

1. finalize-кейс: `FacilitatorFinalizeRunContextVo` со всеми заполненными полями → проверить 1:1 маппинг в вызов `runFacilitatorFinalize`;
2. participant-кейс с ненулевой `retryPolicy` → проверить, что политика повторов доезжает до раннера без перепутывания полей;
3. (опционально) facilitator-кейс с ненулевой `retryPolicy` — если окажется, что существующий start/continue-кейс уже покрывает ненулевую политику, не дублировать.

Решение не задано жёстко; исполнитель может предложить минимальный вариант в плане реализации, сохранив принцип: симметрия покрытия с существующими кейсами, без новых абстракций и без рефакторинга теста.

### Ожидаемый результат (Expected Result)

Контракт всех трёх методов раннера (`runFacilitator`, `runParticipant`, `runFacilitatorFinalize`) покрыт маппинг-тестами симметрично: любая перестановка или потеря поля VO на любом из путей роняет тест. Пользователь этого увидит так: при ревье будущих правок `DynamicLoop` исчезает класс рисков «тест не смотрит этот путь», а отчёт ревьювера перестаёт повторять эту рекомендацию.

## 1. Концепция и Цель (Concept and Goal)
### История (User Story или Job Story)

> **Job Story:** Когда я правлю сигнатуры или поля VO в `DynamicLoop` и прогоняю контракт-тест, я хочу, чтобы finalize- и participant-пути с ненулевой `retryPolicy` проверялись так же строго, как facilitator start/continue, чтобы регрессия маппинга на этих путях ловилась автоматически.

### Цель по SMART (Goal)

Дополнить `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php` кейсами: finalize с полным `FacilitatorFinalizeRunContextVo` и participant с ненулевой `retryPolicy` — каждый проверяет пофайловый 1:1 маппинг в вызов раннера, по образцу существующих кейсов файла. Критерий: временная перестановка любого смещённого поля в соответствующем VO роняет тест (мутационная проверка при реализации); полный `make check` зелёный.

## 2. Контекст и Границы (Context and Scope)

*   **Где делаем:** `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php` (только тестовый файл; продакшн-код `src/Module/DynamicLoop/` не трогается).
*   **Текущее поведение:** тест покрывает facilitator-сценарии (start/continue) — по 6 тестам / 128 assertions на 5 стабах; finalize- и retryPolicy-пути не проверяются (зафиксировано в отчёте ревью PR #493 как неблокирующая рекомендация).
*   **Прецеденты для переиспользования:** существующие кейсы и стабы того же файла — копировать их структуру (arrange-блоки, проверка порядка аргументов); сами VO — `src/Module/DynamicLoop/Domain/ValueObject/` (`FacilitatorFinalizeRunContextVo`, `ParticipantRunContextVo`).
*   **Критичные конвенции:** [Конвенции тестирования](../docs/conventions/testing/index.md); стиль кейсов — по соседним тестам файла.
*   **Границы (Out of Scope):** любые правки `src/`; рефакторинг/переименование существующих кейсов и стабов теста; повышение `complexity`/пересмотр контрактов `RunDynamicLoopAgentServiceInterface`; покрытие прочих модулей.

## 3. Требования, MoSCoW (Requirements)
### 🔴 Обязательно (Must Have)

- [ ] Кейс finalize: полный `FacilitatorFinalizeRunContextVo` → `runFacilitatorFinalize`; проверен 1:1 маппинг всех полей (по образцу существующих кейсов файла).
- [ ] Кейс participant с ненулевой `retryPolicy`: политика повторов доезжает до `runParticipant` без перепутывания полей.
- [ ] Эффективность новых кейсов подтверждена мутационной проверкой при реализации: временная перестановка смещённого поля роняет тест, после чего код возвращён как был.
- [ ] `vendor/bin/phpunit` и `vendor/bin/psalm` зелёные.

### 🟡 Желательно (Should Have)

- [ ] Если существующий facilitator-кейс уже использует ненулевую `retryPolicy` — в отчёте задачи указать это явно (дубликат не заводить).

### 🟢 Опционально (Could Have)

- [ ] Третий кейс facilitator с ненулевой `retryPolicy`, если Should-1 покажет непокрытость.

### ⚫ Не будем делать (Won't Have)

- [ ] Правки продакшн-кода `src/`.
- [ ] Рефакторинг существующих кейсов/стабов теста.
- [ ] Заведение новых задач из этого процесса.

## 4. План реализации (Implementation Plan)

1. [ ] Перечитать существующие кейсы `RunDynamicLoopAgentServiceTest.php`, отчёт ревью PR #493 (раздел с рекомендацией) и VO `FacilitatorFinalizeRunContextVo`/`ParticipantRunContextVo`.
2. [ ] Добавить кейс finalize (Must-1) по образцу существующих.
3. [ ] Добавить кейс participant с ненулевой `retryPolicy` (Must-2).
4. [ ] Мутационная проверка: переставить поле → убедиться в падении → вернуть (Must-3).
5. [ ] `vendor/bin/phpunit`, `vendor/bin/psalm`; при необходимости точечный `composer install`.
6. [ ] Обновить Change History задачи, перевести в `done`, PR.

## 5. Критерии приёмки (Definition of Done)

- [ ] Сценарий протестирован (Unit/Integration)
- [ ] Нет регрессий в смежных модулях
- [ ] Обновлена техническая документация (если требуется)
- [ ] Кейсы Must-1 и Must-2 добавлены, файл теста симметрично покрывает все три метода интерфейса.
- [ ] Мутационная проверка выполнена и зафиксирована в Change History задачи.
- [ ] `vendor/bin/phpunit` (полный) и `vendor/bin/psalm` — зелёные.
- [ ] Задача в `done`, файл в `todo/done/`, поле `pr` заполнено.

## 6. Самопроверка (Verification)

```bash
vendor/bin/phpunit                                            # все тесты
vendor/bin/phpunit tests/Unit/Common/Module/DynamicLoop/      # целевой модуль
vendor/bin/psalm                                              # статический анализ
make check                                                    # полный барьер перед PR
php vendor/bin/todo-md validate TASK-techdebt-runcontext-retry-policy-cases
```

## 7. Риски и зависимости (Risks and Dependencies)

- Зависимость: структура стабов существующего теста; если при реализации окажется, что finalize-путь недостижим через публичный контракт сервиса стабами, — остановиться и уточнить у тимлида (не выдумывать обходы).
- Риск: мутационная проверка (Must-3) требует аккуратности — мутация только во временной рабочей копии и обязательно откатывается; потеря отката = испорченное рабочее дерево (контроль `git status` до и после).

## 8. Источники (Sources)

- [ ] [Отчёт ревью PR #493 (Пуаро) — раздел с неблокирующей рекомендацией](../docs/agents/reports/code-reviewer-backend/2026-11-23_22-08_runfacilitator-vo-review.md)
- [ ] [Задача TASK-techdebt-runfacilitator-vo (done) — источник контракта](done/TASK-techdebt-runfacilitator-vo.todo.md)
- [ ] Тест `tests/Unit/Common/Module/DynamicLoop/Integration/Service/ChainExecution/RunDynamicLoopAgentServiceTest.php`
- [ ] VO: `src/Module/DynamicLoop/Domain/ValueObject/` (`FacilitatorFinalizeRunContextVo`, `ParticipantRunContextVo`)

## 9. Комментарии (Comments)

- Задача заведена по решению владельца 2026-11-24 («если нужно — оформи задачей») на базе неблокирующей рекомендации ревьюера в PR #493; приоритет P3 — отложенная страховка, не блокирует ничего.
- Тип фикса — расширение тестовой сетки; в реестре ретроспектив отдельной записи не имеет (рекомендация ревью, не ретро-находка).

## История изменений (Change History)

| Дата | Автор | Изменение |
| :--- | :--- | :--- |
| 2026-11-24 03:50:21 (1795492221) | Тимлид Алекс (pi) | Создание задачи |
