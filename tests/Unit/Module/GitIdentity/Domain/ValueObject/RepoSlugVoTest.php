<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Module\GitIdentity\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

#[CoversClass(RepoSlugVo::class)]
final class RepoSlugVoTest extends TestCase
{
    #[Test]
    public function fromStringParsesOwnerAndRepo(): void
    {
        $vo = RepoSlugVo::fromString('octocat/Hello-World');

        self::assertSame('octocat', $vo->getOwner());
        self::assertSame('Hello-World', $vo->getRepo());
        self::assertSame('octocat/Hello-World', $vo->toString());
    }

    #[Test]
    public function fromStringAllowsDotsDashesAndUnderscores(): void
    {
        $vo = RepoSlugVo::fromString('my_org/my.repo_v2');

        self::assertSame('my_org', $vo->getOwner());
        self::assertSame('my.repo_v2', $vo->getRepo());
    }

    #[Test]
    public function fromStringTrimsSurroundingWhitespace(): void
    {
        $vo = RepoSlugVo::fromString('  octocat/Hello-World  ');

        self::assertSame('octocat', $vo->getOwner());
        self::assertSame('Hello-World', $vo->getRepo());
    }

    #[Test]
    public function cacheKeyReplacesSlashWithUnderscore(): void
    {
        $vo = RepoSlugVo::fromString('octocat/Hello-World');

        self::assertSame('octocat_Hello-World', $vo->cacheKey());
        self::assertStringNotContainsString('/', $vo->cacheKey());
    }

    /**
     * @param non-empty-string $slug
     */
    #[Test]
    #[DataProvider('invalidSlugs')]
    public function fromStringRejectsInvalidSlug(string $slug): void
    {
        $this->expectException(InvalidConfigurationException::class);

        RepoSlugVo::fromString($slug);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSlugs(): iterable
    {
        yield 'empty' => [''];
        yield 'missing slash' => ['octocat'];
        yield 'leading slash only' => ['/Hello-World'];
        yield 'trailing slash only' => ['octocat/'];
        yield 'extra path segment' => ['octocat/Hello/World'];
        yield 'space in owner' => ['octo cat/Hello-World'];
        yield 'space in repo' => ['octocat/Hello World'];
        yield 'leading dash owner' => ['-octocat/Hello-World'];
        yield 'trailing dash repo' => ['octocat/Hello-World-'];
        yield 'owner with slash-like char' => ['octo/cat/Hello'];
    }

    #[Test]
    public function constructorRejectsEmptyOwner(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new RepoSlugVo('', 'repo');
    }

    #[Test]
    public function constructorRejectsEmptyRepo(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new RepoSlugVo('owner', '');
    }
}
