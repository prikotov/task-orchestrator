<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRunner\Infrastructure\Service;

use Symfony\Component\Process\Process;

/**
 * Liveness-adaptive ожидание процесса.
 *
 * Пока процесс активен (грузит CPU/IO — модель рассуждает/стримит) — даём ему
 * доработать до абсолютного hard cap (Symfony {@see Process::setTimeout()} на
 * переданном процессе, fallback через {@see Process::checkTimeout()}). При
 * простое (idle) дольше {@see \AGENT_RUNNER_IDLE_TIMEOUT_SEC} — останавливаем:
 * это зависание, быстрее вернуть transient-error и дать retry, чем ждать cap.
 *
 * Симметрично используется {@see PiAgentRunnerService} и {@see CodexAgentRunnerService}
 * (Infrastructure/Service/Pi и .../Codex), чтобы liveness-логика не дублировалась.
 *
 * Параметры (env-override):
 *   AGENT_RUNNER_IDLE_TIMEOUT_SEC — порог простоя → считать зависанием (default 60).
 *
 * Hard cap задаёт сам runner через `$process->setTimeout($hardCap)` ДО вызова
 * {@see waitFor()} — watcher только поллит и проверяет cap через checkTimeout().
 */
final class ProcessLivenessWatcher
{
    /** @var int Интервал опроса liveness-метрик (микросекунды) */
    private const int POLL_INTERVAL_US = 500_000; // 0.5с

    /**
     * Ожидает завершение процесса с liveness-проверкой.
     *
     * @param Process $process запущенный процесс (start() уже вызван); на нём
     *                         должен быть установлен setTimeout() как hard cap.
     *
     * @return bool true — процесс завершился сам; false — остановлен по idle.
     */
    public function waitFor(Process $process): bool
    {
        $idleThreshold = (float) $this->envInt('AGENT_RUNNER_IDLE_TIMEOUT_SEC', 60);

        $lastActivity = microtime(true);
        $previousCpu = null;
        $previousIo = null;

        while ($process->isRunning()) {
            // Symfony fallback: бросит ProcessTimedOutException при превышении hard cap.
            $process->checkTimeout();

            $pid = $process->getPid();
            if ($pid !== null) {
                $cpu = $this->readProcessCpuTime($pid);
                $currentIo = $this->readProcessIo($pid);
                if ($previousCpu !== null && $previousIo !== null && ($cpu > $previousCpu || $currentIo > $previousIo)) {
                    $lastActivity = microtime(true); // прогресс есть — работает
                }
                $previousCpu = $cpu;
                $previousIo = $currentIo;
            }

            if (microtime(true) - $lastActivity > $idleThreshold) {
                // Зависание: grace SIGTERM, затем SIGKILL.
                $process->stop(2);

                return false;
            }

            usleep(self::POLL_INTERVAL_US);
        }

        return true;
    }

    /**
     * Порог простоя в секундах (для runner-сообщений об ошибке).
     */
    public function getIdleThreshold(): int
    {
        return $this->envInt('AGENT_RUNNER_IDLE_TIMEOUT_SEC', 60);
    }

    /**
     * Абсолютный hard cap шага: максимум из request-timeout и env-потолка
     * AGENT_RUNNER_HARD_TIMEOUT_SEC (default 1800). Runner устанавливает его на
     * process через setTimeout() ДО вызова waitFor().
     */
    public function resolveHardCap(?int $requestTimeout): int
    {
        return max($requestTimeout ?? 0, $this->envInt('AGENT_RUNNER_HARD_TIMEOUT_SEC', 1800));
    }

    /**
     * CPU-time процесса и его direct children в секундах.
     *
     * ВАЖНО: codex/node-раннеры при tool calls spawn'ят детей (find/grep/sed),
     * пока сами ждут в ep_poll — direct PID выглядит idle. Суммируем CPU
     * direct PID + direct children, иначе liveness ложно kill'ит активный
     * процесс с tool calls (brainstorm: «читай файлы — проверяй»).
     */
    private function readProcessCpuTime(int $pid): int
    {
        $sum = $this->readSingleCpuTime($pid);
        foreach ($this->childPids($pid) as $childPid) {
            $sum += $this->readSingleCpuTime($childPid);
        }

        return $sum;
    }

    private function readSingleCpuTime(int $pid): int
    {
        $output = $this->executeShellCommandSilently(sprintf('ps -o times= -p %d 2>/dev/null', $pid));
        if ($output === null || $output === '') {
            return 0;
        }

        $trimmedOutput = trim($output);

        return is_numeric($trimmedOutput) ? (int) $trimmedOutput : 0;
    }

    /**
     * Сумма rchar+wchar из /proc/<pid>/io для процесса и его direct children.
     * Включает сетевой IO через libc, не только диск. Linux-only;
     * отсутствие /proc — 0 (liveness по CPU). Children важны для tool calls
     * (см. readProcessCpuTime).
     */
    private function readProcessIo(int $pid): int
    {
        $sum = $this->readSingleIo($pid);
        foreach ($this->childPids($pid) as $childPid) {
            $sum += $this->readSingleIo($childPid);
        }

        return $sum;
    }

    private function readSingleIo(int $pid): int
    {
        $path = sprintf('/proc/%d/io', $pid);
        if (!$this->isReadableSilently($path)) {
            return 0;
        }

        $content = $this->readFileContentsSilently($path);
        if ($content === null) {
            return 0;
        }

        $sum = 0;
        foreach (['rchar', 'wchar'] as $key) {
            if (preg_match('/^' . $key . ':\s*(\d+)/m', $content, $matches) === 1) {
                $sum += (int) $matches[1];
            }
        }

        return $sum;
    }

    /**
     * Direct children PIDs (один уровень — достаточно для codex tool calls:
     * find/grep/sed сами детей не spawn'ят).
     *
     * @return list<int>
     */
    private function childPids(int $pid): array
    {
        $output = $this->executeShellCommandSilently(sprintf('pgrep -P %d 2>/dev/null', $pid));
        if ($output === null || $output === '') {
            return [];
        }

        $parts = preg_split('/\s+/', trim($output));
        if ($parts === false) {
            return [];
        }

        $pids = [];
        foreach ($parts as $part) {
            if (is_numeric($part)) {
                $pids[] = (int) $part;
            }
        }

        return $pids;
    }

    private function executeShellCommandSilently(string $command): ?string
    {
        set_error_handler(static fn (): bool => true);
        try {
            /** @psalm-suppress ForbiddenCode */
            $output = shell_exec($command);
        } finally {
            restore_error_handler();
        }

        return is_string($output) ? $output : null;
    }

    private function isReadableSilently(string $path): bool
    {
        set_error_handler(static fn (): bool => true);
        try {
            return is_readable($path);
        } finally {
            restore_error_handler();
        }
    }

    private function readFileContentsSilently(string $path): ?string
    {
        set_error_handler(static fn (): bool => true);
        try {
            $content = file_get_contents($path);
        } finally {
            restore_error_handler();
        }

        return is_string($content) ? $content : null;
    }

    /**
     * Читает int из env с дефолтом; не-числовые/пустые значения → default.
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
