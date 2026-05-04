<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Domain\Service\Chain\Hook;

use TaskOrchestrator\Common\Module\ChainExecution\Domain\ValueObject\HookResultVo;

/**
 * Interface для выполнения post_step hook (shell-скрипт после шага цепочки).
 *
 * Реализация — Infrastructure (Symfony Process).
 * Hook failure = warning (не прерывает цепочку).
 */
interface HookExecutorInterface
{
    /**
     * Выполняет hook-скрипт с переданным контекстом.
     *
     * @param string $scriptPath путь к shell-скрипту
     * @param array{
     *     chain_name?: string,
     *     step_name?: string|null,
     *     runner?: string,
     *     exit_code?: int,
     *     duration?: float,
     *     role?: string,
     * } $context контекст выполнения шага (передаётся как env vars)
     */
    public function execute(string $scriptPath, array $context = []): HookResultVo;
}
