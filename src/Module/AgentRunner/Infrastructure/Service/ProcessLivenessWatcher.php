<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service;

use Symfony\Component\Process\Process;
use TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Component\ProcessLiveness\{
    Dto\ProcessLivenessActiveProbeResultDto,
    Dto\ProcessLivenessUnknownProbeResultDto,
    ProcessLivenessClockComponentInterface,
    ProcessLivenessProbeComponentInterface,
    ProcessLivenessSleeperComponentInterface,
};

/**
 * Политика liveness-adaptive ожидания процесса.
 *
 * На Linux с полной читаемой procfs-телеметрией подтверждённый простой основного
 * PID и его непосредственных детей дольше порога приводит к stop(). На других
 * платформах и после первой недостоверной выборки текущий waitFor() необратимо
 * переходит в hard-cap-only: idle-kill отключён, но Process::checkTimeout()
 * продолжает вызываться на каждой итерации.
 *
 * Ошибки имеют три режима: UNKNOWN безопасно отключает только idle-kill
 * (fail-open); лишь сопоставимый INACTIVE разрешает остановку (fail-closed);
 * неожиданные Throwable компонента не перехватываются и распространяются
 * наружу (fail-fast). Pi/Codex runners синхронно очищают живой agent process
 * в своём finally.
 *
 * Имя env-переменной и порог по умолчанию задаются для каждого runner
 * через DI. Общий сервис сохраняет прежний контракт:
 * AGENT_RUNNER_IDLE_TIMEOUT_SEC с default 60.
 *
 * Hard cap задаёт runner через Process::setTimeout() до вызова waitFor().
 */
final readonly class ProcessLivenessWatcher
{
    /** @var int Интервал опроса liveness-метрик (микросекунды). */
    private const int POLL_INTERVAL_MICROSECONDS = 500_000;

    public function __construct(
        private ProcessLivenessProbeComponentInterface $probe,
        private ProcessLivenessClockComponentInterface $clock,
        private ProcessLivenessSleeperComponentInterface $sleeper,
        private string $idleThresholdEnvName = 'AGENT_RUNNER_IDLE_TIMEOUT_SEC',
        private int $defaultIdleThreshold = 60,
    ) {
    }

    /**
     * Ожидает естественное завершение либо останавливает подтверждённо idle-процесс.
     *
     * @param Process $process Уже запущенный процесс с установленным hard timeout.
     *
     * @return bool true при естественном завершении; false после idle-stop.
     */
    public function waitFor(Process $process): bool
    {
        $idleThreshold = (float) $this->getIdleThreshold();
        $lastActivity = $this->clock->now();
        $previousSnapshot = null;
        $adaptiveIdleEnabled = true;

        while ($process->isRunning()) {
            $process->checkTimeout();

            if ($adaptiveIdleEnabled) {
                $processId = $process->getPid();
                if ($processId === null && !$process->isRunning()) {
                    return true;
                }

                if ($processId === null) {
                    $adaptiveIdleEnabled = false;
                    $previousSnapshot = null;
                }

                if ($processId !== null) {
                    $result = $this->probe->probe($processId, $previousSnapshot);
                    if (!$process->isRunning()) {
                        return true;
                    }

                    if ($result instanceof ProcessLivenessUnknownProbeResultDto) {
                        $adaptiveIdleEnabled = false;
                        $previousSnapshot = null;
                    }

                    if (!$result instanceof ProcessLivenessUnknownProbeResultDto) {
                        $previousSnapshot = $result->snapshot;
                        if ($result instanceof ProcessLivenessActiveProbeResultDto) {
                            $lastActivity = $this->clock->now();
                        }
                    }
                }
            }

            if ($adaptiveIdleEnabled && $this->clock->now() - $lastActivity > $idleThreshold) {
                if (!$process->isRunning()) {
                    return true;
                }

                $process->stop(2);

                return false;
            }

            $this->sleeper->sleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        return true;
    }

    /**
     * Порог простоя в секундах для runner-сообщений об ошибке.
     */
    public function getIdleThreshold(): int
    {
        return $this->envInt($this->idleThresholdEnvName, $this->defaultIdleThreshold);
    }

    /**
     * Абсолютный hard cap шага: максимум request-timeout и env-потолка.
     */
    public function resolveHardCap(?int $requestTimeout): int
    {
        return max($requestTimeout ?? 0, $this->envInt('AGENT_RUNNER_HARD_TIMEOUT_SEC', 1800));
    }

    /**
     * Читает int из env с дефолтом; нечисловые/пустые значения дают default.
     */
    private function envInt(string $name, int $default): int
    {
        $value = getenv($name);
        if ($value === false || $value === '' || !is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }
}
