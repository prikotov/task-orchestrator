<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Component\Frontmatter;

use InvalidArgumentException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Парсер YAML-frontmatter markdown-файла (формат Agent Skills / role-file).
 *
 * Разделяет файл на YAML-блок (между первыми двумя линиями `---`) и тело.
 * Возвращает распарсенный ассоциативный массив frontmatter либо пустой массив,
 * если frontmatter отсутствует. Невалидный YAML выбрасывает исключение.
 */
final readonly class FrontmatterYamlParser
{
    private const string FRONTMATTER_PATTERN = '/^---\r?\n(.*?)\r?\n---\r?\n/s';

    /**
     * @return array<non-empty-string, mixed> распарсенный frontmatter (пустой массив, если frontmatter отсутствует)
     *
     * @throws InvalidArgumentException если frontmatter присутствует, но YAML невалиден
     */
    public function parse(string $content): array
    {
        if (preg_match(self::FRONTMATTER_PATTERN, $content, $matches) !== 1) {
            return [];
        }

        try {
            $data = Yaml::parse($matches[1]) ?? [];
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('Invalid frontmatter YAML: %s', $e->getMessage()), 0, $e);
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Frontmatter must be a YAML mapping at the top level.');
        }

        return $data;
    }
}
