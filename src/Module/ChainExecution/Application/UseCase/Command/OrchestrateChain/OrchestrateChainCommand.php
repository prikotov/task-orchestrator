<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainExecution\Application\UseCase\Command\OrchestrateChain;

/**
 * DTO команды оркестрации цепочки AI-агентов.
 */
final readonly class OrchestrateChainCommand
{
    public function __construct(
        public string $chainName,
        public string $task,
        public ?string $workingDir = null,
        public ?int $timeout = null,
        public ?string $topic = null,
        public ?int $maxRounds = null,
        public ?string $facilitator = null,
        /**
         * @var list<string>|null
         */
        public ?array $participants = null,
        public ?string $resumeDir = null,
        public bool $noAuditLog = false,
        public bool $noContextFiles = false,
        public ?int $maxTime = null,
        /**
         * Сложность задачи уровнем 0–5 (C0–C5) или null, если CLI не задал её явно.
         *
         * Для новых сессий CLI всегда передаёт resolved int (--complexity > поле complexity
         * файла --task-id > C3, см. OrchestrateCommand::resolveComplexity()).
         * null допустим только при resume: DynamicExecutionStrategy::resume() берёт сложность
         * из session.json (поле complexity, DynamicLoopSessionStateVo::getComplexity());
         * legacy-сессия без поля трактуется как C3. Явная --complexity приоритетнее session.json.
         *
         * Диапазон валидирует CLI-граница; повторный guard — в
         * RoleConfigVo::resolveCommandByComplexity(). Применяется к отклонениям
         * commands_by_complexity при выборе команд ролей на ACL-границе
         * ChainExecution/DynamicLoop, до запуска любого агента.
         */
        public ?int $complexity = null,
    ) {
    }
}
