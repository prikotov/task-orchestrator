# Hooks — перехватчики после шага (post-step)

Перехватчики после шага (post-step hooks) — shell-скрипты, выполняемые после завершения каждого шага цепочки.
Сбой перехватчика (hook failure) даёт предупреждение (warning) и не прерывает цепочку.

## YAML DSL

Добавьте `post_step` к любому шагу в `config/chains.yaml`:

```yaml
chains:
  implement:
    steps:
      - type: agent
        role: backend_developer_levsha
        name: implement
        post_step: "bin/notify.sh"    # вызовется после завершения шага
```

## Переменные окружения

Перехватчик (hook) получает контекст через переменные окружения (env vars):

| Переменная | Описание | Пример |
|-----|----------|--------|
| `HOOK_CHAIN_NAME` | Имя цепочки | `implement` |
| `HOOK_STEP_NAME` | Имя шага | `implement` |
| `HOOK_ROLE` | Роль агента | `backend_developer_levsha` |
| `HOOK_EXIT_CODE` | Код возврата (exit code) шага | `0` |
| `HOOK_DURATION` | Длительность (секунды) | `12.5` |

## Desktop-уведомления со звуком

В комплекте идёт `bin/notify.sh` — скрипт с поддержкой CESP (CESP-aware) и звуками через peon-ping.

### Установка peon-ping

```bash
curl -fsSL https://raw.githubusercontent.com/PeonPing/peon-ping/main/install.sh | bash
```

### Установка русскоязычных звуковых пакетов

```bash
# Орк-Батрак (рекомендуется) — Warcraft III
~/.local/bin/peon packs install peon_ru
~/.local/bin/peon packs use peon_ru

# Крестьянин (Warcraft III)
~/.local/bin/peon packs install peasant_ru

# Дед Борис (AI-generated)
~/.local/bin/peon packs install boris_ru

# Строитель Высших Эльфов (Warcraft III)
~/.local/bin/peon packs install high_elf_builder_ru

# Артас Рыцарь Смерти (Warcraft III)
~/.local/bin/peon packs install arthas_ru

# Все русскоязычные пакеты: https://openpeon.com/packs?lang=ru
```

### Прослушать звуки пакета

```bash
~/.local/bin/peon preview task.complete    # успех
~/.local/bin/peon preview task.error       # ошибка
~/.local/bin/peon preview --list           # все категории
```

### Fallback

Если peon-ping не установлен — звук через `canberra-gtk-play` (системный).
Если и его нет — только `notify-send` (desktop-уведомление).

### Доступные CESP-события

| Событие | Когда |
|---------|-------|
| `task.complete` | Шаг завершён успешно (exit code = 0) |
| `task.error` | Шаг завершён с ошибкой (exit code ≠ 0) |

## Свой перехватчик (hook)

Любой исполняемый скрипт:

```sh
#!/bin/sh
echo "Цепочка: $HOOK_CHAIN_NAME, шаг: $HOOK_STEP_NAME" >&2
```

Укажите путь в `post_step` — task-orchestrator вызовет его через процесс Symfony (Symfony Process) с таймаутом 30 секунд.

## Ссылки

- [OpenPeon — звуковые пакеты](https://openpeon.com/packs)
- [Спецификация CESP (CESP Spec)](https://openpeon.com/spec)
- [peon-ping на GitHub](https://github.com/PeonPing/peon-ping)
