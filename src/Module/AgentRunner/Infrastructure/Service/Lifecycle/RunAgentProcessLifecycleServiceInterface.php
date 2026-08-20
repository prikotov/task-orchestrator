<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Lifecycle;

use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentResultVo;
use TaskOrchestrator\Common\Module\AgentRunner\Domain\ValueObject\AgentRunRequestVo;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service\Codex\HttpsProxyBridge;

/**
 * Внутренний контракт общего жизненного цикла процесса AI-агента.
 *
 * Инкапсулирует runtime-часть раннера Codex/Pi, одинаковую для обоих CLI:
 * создание Symfony Process с hard-cap настройкой и рабочей директорией,
 * подготовку прокси-окружения (CODEX_HTTP_PROXY + HttpsProxyBridge),
 * буферизацию stdout в JSONL-парсер, tail stderr, liveness-adaptive ожидание
 * с idle/hard-cap/signal маппингом в AgentResultVo и гарантированную остановку
 * процесса и моста.
 *
 * Раннер-специфика передаётся callbacks:
 * - `buildCommand` — CLI-семантика codex/pi принципиально разная;
 * - `resetParser`/`feedParserLine` — у Codex/Pi разные JSONL-парсеры без общего
 *   контракта (result() имеет разный array-shape);
 * - `buildResult` — интерпретация завершившегося процесса остаётся hook'ом
 *   раннера (Pi дополнительно обрабатывает isError/errorMessage из JSONL).
 *
 * Интерфейс размещён в Infrastructure (а не Domain), потому что компонент
 * используется только инфраструктурными раннерами; это локальная техническая
 * граница, не доменный контракт.
 */
interface RunAgentProcessLifecycleServiceInterface
{
    /**
     * Прогоняет полный жизненный цикл процесса агента и возвращает результат.
     *
     * @param callable(AgentRunRequestVo): list<string> $buildCommand строит CLI-команду раннера
     * @param callable(): void $resetParser сбрасывает JSONL-парсер раннера перед потоком
     * @param callable(string): void $feedParserLine отдаёт парсеру одну JSONL-строку
     * @param callable(Process, string): AgentResultVo $buildResult собирает AgentResultVo
     *        по завершившемуся процессу и stderr-tail (runner-specific hook)
     */
    public function run(
        AgentRunRequestVo $request,
        string $runnerName,
        callable $buildCommand,
        callable $resetParser,
        callable $feedParserLine,
        callable $buildResult,
    ): AgentResultVo;

    /**
     * Формирует env-переменные для Symfony Process с учётом HTTP-прокси.
     *
     * @param array<string, string> $currentEnv текущее окружение (например, из getenv())
     *
     * @return array<string, string> окружение с подменённым HTTPS_PROXY
     */
    public function buildProcessEnv(array $currentEnv): array;

    /**
     * Создаёт HttpsProxyBridge если CODEX_HTTP_PROXY содержит https:// схему.
     */
    public function createBridgeIfNeeded(): ?HttpsProxyBridge;

    /**
     * Формирует пользовательский промпт (previous context + task).
     */
    public function buildUserPrompt(AgentRunRequestVo $request): string;

    /**
     * Определяет путь к файлу system-prompt, если он задан существующим файлом.
     */
    public function resolveSystemPromptPath(AgentRunRequestVo $request): ?string;
}
