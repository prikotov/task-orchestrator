# Запуск Codex CLI

Инструкция по запуску Codex CLI в проекте task-orchestrator.

## Прокси (обязательно)

Codex CLI требует HTTPS-прокси: без него — `403 Forbidden` на `chatgpt.com` (Cloudflare IP-block по региону). Это не баг codex — нужен только прокси в окружении.

Прокси лежит в `.env.local` (переменная `CODEX_HTTP_PROXY`).

## Через watch-subagent.sh (автоматически)

`watch-subagent.sh` source-ит `.env.local` и выставляет `HTTPS_PROXY`/`HTTP_PROXY` из `CODEX_HTTP_PROXY` для codex-раннера. Ручных действий не требуется.

## Прямой запуск codex

Вне `watch-subagent.sh`:

```bash
set -a; . ./.env.local; set +a
codex exec --json --skip-git-repo-check -o /tmp/codex-last-message.txt "..."
```

или `export HTTPS_PROXY="$CODEX_HTTP_PROXY" HTTP_PROXY="$CODEX_HTTP_PROXY"` перед вызовом.

`watch-subagent.sh` по умолчанию сохраняет rollout-журналы в
`~/.codex/sessions` и последнее сообщение в `last_message.txt` каталога запуска.
Для отключения rollout-журнала явно задайте `WATCH_CODEX_EPHEMERAL=1`.
Его эффективный stall-порог не опускается ниже 360 секунд без
`WATCH_CODEX_ALLOW_SHORT_STALL=1`, чтобы не прерывать 300-секундное ожидание стрима codex.

## Шум `Reconnecting…` — это нормально

При `CODEX_HTTP_PROXY` со схемой `https://` codex пишет `Reconnecting... Proxy URL scheme not supported` и падает на WebSocket — сразу следует fallback на HTTPS-transport, и запрос проходит. Не ошибка, не воспринимать как сбой.

## Не изобретать обходы

🔴 Запрещено «изобретать» обходы (`HttpsProxyBridge` standalone, PHP-мосты, костыли) для запуска codex вне PHP-раннера. Прокси в env — единственное решение. `HttpsProxyBridge` используется только внутри `CodexAgentRunnerService` (PHP, через `bin/console`), для `watch-subagent.sh` он не нужен.
