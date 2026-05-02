<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum;

/**
 * Цель правила безопасности: к чему применяется правило.
 *
 * command — shell-команда (exec policy для runner'ов и quality gates).
 * runner  — имя runner'а (openai, anthropic, local-shell).
 * tool    — инструмент, доступный runner'у (read, grep, test).
 * model   — модель LLM (gpt-4, claude-3.5).
 * chain   — имя цепочки оркестрации (chain-level authorization).
 */
enum RuleTargetEnum: string
{
    case command = 'command';
    case runner = 'runner';
    case tool = 'tool';
    case model = 'model';
    case chain = 'chain';
}
