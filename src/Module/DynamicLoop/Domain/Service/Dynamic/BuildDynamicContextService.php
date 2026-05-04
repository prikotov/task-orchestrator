<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Dynamic;

use Override;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopConfigVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopContextVo;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\ValueObject\DynamicLoopPromptConfigVo;

/**
 * Создание DynamicLoopContextVo из DynamicLoopConfigVo и параметров запуска.
 */
final readonly class BuildDynamicContextService implements BuildDynamicContextServiceInterface
{
    #[Override]
    public function buildContext(
        DynamicLoopConfigVo $chain,
        string $facilitatorRole,
        array $participants,
        int $maxRounds,
        string $topic,
        ?string $workingDir,
        int $timeout,
        ?int $maxTime = null,
    ): DynamicLoopContextVo {
        $promptConfig = $chain->getPromptConfiguration();

        $facilitatorAppendPrompt = $this->formatAppendPrompt($promptConfig->getFacilitatorAppendPrompt(), $participants);

        return new DynamicLoopContextVo(
            facilitatorRole: $facilitatorRole,
            participants: $participants,
            maxRounds: $maxRounds,
            topic: $topic,
            promptConfiguration: new DynamicLoopPromptConfigVo(
                brainstormSystemPrompt: $promptConfig->getBrainstormSystemPrompt(),
                facilitatorAppendPrompt: $facilitatorAppendPrompt,
                facilitatorStartPrompt: $promptConfig->getFacilitatorStartPrompt(),
                facilitatorContinuePrompt: $promptConfig->getFacilitatorContinuePrompt(),
                facilitatorFinalizePrompt: $promptConfig->getFacilitatorFinalizePrompt(),
                participantAppendPrompt: $promptConfig->getParticipantAppendPrompt(),
                participantUserPrompt: $promptConfig->getParticipantUserPrompt(),
            ),
            workingDir: $workingDir,
            timeout: $timeout,
            maxTime: $maxTime,
        );
    }

    #[Override]
    public function buildInvocation(
        DynamicLoopConfigVo $chain,
        string $task,
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
