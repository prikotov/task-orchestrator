<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\SecurityPolicy\Infrastructure\Persistence;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Entity\ExecRule;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\PatternTypeEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleActionEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleSeverityEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\RuleTargetEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\ExecRuleIdVo;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\RulePatternVo;

/**
 * Repository: загрузка ExecRule[] из YAML-файла (exec policy file).
 *
 * Парсит config/security_policy.yaml и создаёт ExecRule entities.
 * Если файл не найден — возвращает пустой массив (fallback на default rules).
 * Некорректные правила пропускаются с warning-логированием.
 *
 * Формат YAML:
 *   default_policy: allow | deny
 *   rules:
 *     - target: command
 *       pattern: "rm -rf *"
 *       type: glob
 *       action: deny
 *       severity: block
 *       priority: 100
 *       description: "..."
 *
 * @see ExecRule
 * @see RulePatternVo
 */
final class YamlExecRuleRepository
{
    private readonly LoggerInterface $logger;

    /**
     * @param string $yamlPath абсолютный путь к security_policy.yaml
     * @param LoggerInterface|null $logger опциональный логгер для warning при некорректных правилах
     */
    public function __construct(
        private readonly string $yamlPath,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Загружает ExecRule[] из YAML-файла.
     *
     * Если файл не найден — возвращает [] (fallback на default rules).
     * Некорректные правила пропускаются с warning в лог.
     *
     * @return list<ExecRule>
     */
    public function loadRules(): array
    {
        if (!file_exists($this->yamlPath)) {
            $this->logger->info('Security policy file not found, using default rules.', [
                'path' => $this->yamlPath,
            ]);

            return [];
        }

        try {
            $yaml = Yaml::parseFile($this->yamlPath);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to parse security policy YAML, using default rules.', [
                'path' => $this->yamlPath,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (!\is_array($yaml) || !isset($yaml['rules']) || !\is_array($yaml['rules'])) {
            $this->logger->warning('Security policy YAML has invalid structure, using default rules.', [
                'path' => $this->yamlPath,
            ]);

            return [];
        }

        return $this->parseRules(array_values($yaml['rules']));
    }

    /**
     * Определяет default policy из YAML (allow или deny).
     *
     * Используется для PermissionSetVo default policy при отсутствии совпадений.
     */
    public function loadDefaultPolicy(): string
    {
        if (!file_exists($this->yamlPath)) {
            return 'allow';
        }

        try {
            $yaml = Yaml::parseFile($this->yamlPath);
        } catch (\Throwable) {
            return 'allow';
        }

        if (!\is_array($yaml)) {
            return 'allow';
        }

        return ($yaml['default_policy'] ?? 'allow') === 'deny' ? 'deny' : 'allow';
    }

    /**
     * Парсит массив правил из YAML в ExecRule[].
     *
     * Некорректные правила пропускаются с warning.
     *
     * @param list<mixed> $rawRules
     *
     * @return list<ExecRule>
     */
    private function parseRules(array $rawRules): array
    {
        $rules = [];
        $index = 0;

        foreach ($rawRules as $raw) {
            if (!\is_array($raw)) {
                $this->logger->warning('Skipping non-array security rule at index {index}.', [
                    'index' => $index,
                ]);
                ++$index;
                continue;
            }

            $rule = $this->parseRule($raw, $index);

            if ($rule !== null) {
                $rules[] = $rule;
            }

            ++$index;
        }

        return $rules;
    }

    /**
     * Парсит одно правило из YAML.
     *
     * Возвращает null и логирует warning при ошибке.
     *
     * @param array<array-key, mixed> $raw
     */
    private function parseRule(array $raw, int $index): ?ExecRule
    {
        try {
            $id = isset($raw['id']) && $raw['id'] !== null
                ? ExecRuleIdVo::createFromString((string) $raw['id'])
                : ExecRuleIdVo::createFromString(sprintf('yaml-rule-%d', $index));

            $target = RuleTargetEnum::tryFrom((string) ($raw['target'] ?? ''));
            if ($target === null) {
                $this->logger->warning('Skipping rule {index}: invalid target "{target}".', [
                    'index' => $index,
                    'target' => $raw['target'] ?? '(missing)',
                ]);

                return null;
            }

            $patternType = PatternTypeEnum::tryFrom((string) ($raw['type'] ?? 'exact'));
            if ($patternType === null) {
                $this->logger->warning('Skipping rule {index}: invalid pattern type "{type}".', [
                    'index' => $index,
                    'type' => $raw['type'] ?? '(missing)',
                ]);

                return null;
            }

            $patternString = (string) ($raw['pattern'] ?? '');
            if ($patternString === '') {
                $this->logger->warning('Skipping rule {index}: empty pattern.', [
                    'index' => $index,
                ]);

                return null;
            }

            $pattern = RulePatternVo::createFromType($patternType, $patternString);

            $action = RuleActionEnum::tryFrom((string) ($raw['action'] ?? ''));
            if ($action === null) {
                $this->logger->warning('Skipping rule {index}: invalid action "{action}".', [
                    'index' => $index,
                    'action' => $raw['action'] ?? '(missing)',
                ]);

                return null;
            }

            $severity = RuleSeverityEnum::tryFrom((string) ($raw['severity'] ?? 'block')) ?? RuleSeverityEnum::block;
            $priority = (int) ($raw['priority'] ?? 0);
            $description = (string) ($raw['description'] ?? '');

            return new ExecRule(
                id: $id,
                target: $target,
                pattern: $pattern,
                action: $action,
                severity: $severity,
                priority: $priority,
                description: $description,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Skipping rule {index}: parse error "{error}".', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
