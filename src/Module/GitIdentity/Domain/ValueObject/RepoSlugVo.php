<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject;

use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;

/**
 * Value Object идентификатора репозитория в формате `<owner>/<repo>`.
 *
 * Нормализует и валидирует GitHub repository slug. Используется как источник
 * cache-key для кеширования installation_id по паре owner/repo.
 */
final readonly class RepoSlugVo
{
    /**
     * Допустимые символы GitHub для owner/repo: буквенно-цифровые, точка,
     * подчёркивание, дефис; не начинается и не заканчивается спецсимволом.
     */
    private const string NAME_PATTERN = '/^[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?$/';

    public function __construct(private string $owner, private string $repo)
    {
        if ($owner === '' || $repo === '') {
            throw new InvalidConfigurationException('Repository owner and name must not be empty.');
        }
        if (preg_match(self::NAME_PATTERN, $owner) !== 1) {
            throw new InvalidConfigurationException(
                sprintf('Invalid repository owner "%s".', $owner),
            );
        }
        if (preg_match(self::NAME_PATTERN, $repo) !== 1) {
            throw new InvalidConfigurationException(
                sprintf('Invalid repository name "%s".', $repo),
            );
        }
    }

    /**
     * Создаёт VO из строки `<owner>/<repo>`.
     *
     * @throws InvalidConfigurationException если формат некорректен.
     */
    public static function fromString(string $slug): self
    {
        $slug = trim($slug);
        $parts = explode('/', $slug, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidConfigurationException(
                sprintf('Invalid repository slug "%s": expected "<owner>/<repo>".', $slug),
            );
        }

        return new self($parts[0], $parts[1]);
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepo(): string
    {
        return $this->repo;
    }

    public function toString(): string
    {
        return $this->owner . '/' . $this->repo;
    }

    /**
     * Возвращает безопасный (без слэшей) ключ кеша для пары owner/repo.
     */
    public function cacheKey(): string
    {
        return $this->owner . '_' . $this->repo;
    }
}
