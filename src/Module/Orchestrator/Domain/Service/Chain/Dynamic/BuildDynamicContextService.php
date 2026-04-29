<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\Orchestrator\Domain\Service\Chain\Dynamic;

use Override;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\ChainDefinitionVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\DynamicChainContextVo;
use TaskOrchestrator\Common\Module\Orchestrator\Domain\ValueObject\PromptConfigurationVo;

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
        ?string $workingDir,
        int $timeout,
        ?int $maxTime = null,
    ): DynamicChainContextVo {
        $promptConfig = $chain->getPromptConfiguration();

        $facilitatorAppendPrompt = $this->formatAppendPrompt($promptConfig->getFacilitatorAppendPrompt(), $participants);

        return new DynamicChainContextVo(
            facilitatorRole: $facilitatorRole,
            participants: $participants,
            maxRounds: $maxRounds,
            topic: $topic,
            promptConfiguration: new PromptConfigurationVo(
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
