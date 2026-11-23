<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain;

use InvalidArgumentException;
use Override;
use RuntimeException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ResolveYamlDynamicPromptsServiceInterface;

/**
 * Реализация резолва prompt-конфигурации dynamic-цепочки из raw YAML
 * ({@see ResolveYamlDynamicPromptsServiceInterface}).
 *
 * Вынесено из
 * {@see \TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\YamlChainLoaderService}:
 * загрузчик цепочек отвечает за разбор структуры цепочек, а загрузка
 * содержимого prompt-файлов (I/O) — здесь.
 *
 * Каждый элемент prompts может быть:
 * - путь к файлу (относительно каталога YAML-файла) — если файл существует,
 *   содержимое читается и обрезается по краям (trim);
 * - инлайн-текст — используется как есть.
 *
 * Обязательные ключи валидируются fail-fast: отсутствующий или пустой ключ
 * ломает загрузку конфигурации немедленно, а не при первом раунде динамической
 * цепочки.
 */
final readonly class ResolveYamlDynamicPromptsService implements ResolveYamlDynamicPromptsServiceInterface
{
    /**
     * Обязательные ключи секции prompts dynamic-цепочки (семантика ключей —
     * контракт {@see \TaskOrchestrator\Common\Module\ChainDefinition\Domain\Factory\ChainDefinitionFactory::createFromDynamic()}).
     *
     * @var list<string>
     */
    private const REQUIRED_PROMPT_KEYS = [
        'brainstorm_system',
        'facilitator_append',
        'facilitator_start',
        'facilitator_continue',
        'facilitator_finalize',
        'participant_append',
        'participant_user',
    ];

    #[Override]
    public function resolve(string $chainName, string $yamlPath, array $rawPrompts): array
    {
        $baseDir = dirname($yamlPath);

        $resolved = [];
        foreach (self::REQUIRED_PROMPT_KEYS as $key) {
            if (!isset($rawPrompts[$key]) || $rawPrompts[$key] === '') {
                throw new InvalidArgumentException(
                    sprintf('Dynamic chain "%s" must specify prompts.%s.', $chainName, $key),
                );
            }

            $value = $rawPrompts[$key];
            $filePath = $baseDir . '/' . $value;

            $resolved[$key] = file_exists($filePath)
                ? trim($this->readFile($filePath))
                : $value;
        }

        return $resolved;
    }

    /**
     * Читает содержимое файла с проверкой на ошибку чтения.
     */
    private function readFile(string $path): string
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read prompt file: %s', $path));
        }

        return $content;
    }
}
