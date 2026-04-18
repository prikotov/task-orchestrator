<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use LogicException;
use Override;

use function array_filter;
use function implode;
use function mb_strlen;
use function mb_substr;
use function sprintf;

/**
 * Создание DynamicChainContextVo из ChainDefinitionVo и параметров запуска.
 */
final readonly class BuildDynamicContextService implements BuildDynamicContextServiceInterface
{
    /**
     * Собирает DTO контекста dynamic-цепочки из ChainDefinitionVo и параметров CLI.
     *
     * @param list<string> $participants
     */
    #[Override]
    public function buildContext(
        ChainDefinitionVo $chain,
        string $facilitatorRole,
        array $participants,
        int $maxRounds,
        string $topic,
        string $runnerName,
        ?string $model,
        ?string $workingDir,
        int $timeout,
    ): DynamicChainContextVo {
        $brainstormSystemPrompt = $chain->getBrainstormSystemPrompt();
        $facilitatorAppendPrompt = $chain->getFacilitatorAppendPrompt();
        $facilitatorStartPrompt = $chain->getFacilitatorStartPrompt();
        $facilitatorContinuePrompt = $chain->getFacilitatorContinuePrompt();
        $facilitatorFinalizePrompt = $chain->getFacilitatorFinalizePrompt();
        $participantAppendPrompt = $chain->getParticipantAppendPrompt();
        $participantUserPrompt = $chain->getParticipantUserPrompt();

        if (
            $brainstormSystemPrompt === null
            || $facilitatorAppendPrompt === null
            || $facilitatorStartPrompt === null
            || $facilitatorContinuePrompt === null
            || $facilitatorFinalizePrompt === null
            || $participantAppendPrompt === null
            || $participantUserPrompt === null
        ) {
            throw new LogicException(
                sprintf('Dynamic chain "%s" is missing required prompts.', $chain->getName()),
            );
        }

        $facilitatorAppendPrompt = $this->formatAppendPrompt($facilitatorAppendPrompt, $participants);

        return new DynamicChainContextVo(
            facilitatorRole: $facilitatorRole,
            participants: $participants,
            maxRounds: $maxRounds,
            topic: $topic,
            runnerName: $runnerName,
            brainstormSystemPrompt: $brainstormSystemPrompt,
            facilitatorAppendPrompt: $facilitatorAppendPrompt,
            facilitatorStartPrompt: $facilitatorStartPrompt,
            facilitatorContinuePrompt: $facilitatorContinuePrompt,
            facilitatorFinalizePrompt: $facilitatorFinalizePrompt,
            participantAppendPrompt: $participantAppendPrompt,
            participantUserPrompt: $participantUserPrompt,
            model: $model,
            workingDir: $workingDir,
            timeout: $timeout,
        );
    }

    /**
     * Формирует invocation-массив для записи в session.json.
     *
     * @param list<string> $effectiveParticipants
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function buildInvocation(
        ChainDefinitionVo $chain,
        string $task,
        ?string $model,
        int $timeout,
        ?string $workingDir,
        ?string $resumeDir,
        string $effectiveFacilitator,
        array $effectiveParticipants,
        int $effectiveMaxRounds,
        string $effectiveTopic,
    ): array {
        $invocation = [
            'command' => 'bin/console app:agent:orchestrate',
            'chain' => $chain->getName(),
            'task' => $this->maskText($task),
            'topic' => $this->maskText($effectiveTopic),
            'facilitator' => $effectiveFacilitator,
            'participants' => $effectiveParticipants,
            'max_rounds' => $effectiveMaxRounds,
            'model' => $model,
            'timeout' => $timeout,
            'working_dir' => $workingDir,
            'resume_dir' => $resumeDir,
        ];

        return array_filter(
            $invocation,
            static fn(mixed $value): bool => $value !== null,
        );
    }

    private function formatAppendPrompt(string $template, array $participants): string
    {
        return sprintf($template, implode(', ', $participants));
    }

    private function maskText(string $text, int $maxLen = 60): string
    {
        $len = mb_strlen($text);

        if ($len <= $maxLen) {
            return $text;
        }

        $head = mb_substr($text, 0, 40);

        return sprintf('...%s...[%d chars]', $head, $len);
    }
}
