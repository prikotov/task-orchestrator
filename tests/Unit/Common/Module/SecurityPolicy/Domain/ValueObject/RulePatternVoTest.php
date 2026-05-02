<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Common\Module\SecurityPolicy\Domain\ValueObject;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\Enum\PatternTypeEnum;
use TaskOrchestrator\Common\Module\SecurityPolicy\Domain\ValueObject\RulePatternVo;

#[CoversClass(RulePatternVo::class)]
final class RulePatternVoTest extends TestCase
{
    // ─── Exact matching ─────────────────────────────────────────────

    #[Test]
    public function exactMatchReturnsTrueForSameValue(): void
    {
        $pattern = RulePatternVo::createFromExact('rm');

        self::assertTrue($pattern->matches('rm'));
    }

    #[Test]
    public function exactMatchReturnsFalseForDifferentValue(): void
    {
        $pattern = RulePatternVo::createFromExact('rm');

        self::assertFalse($pattern->matches('rm -rf'));
        self::assertFalse($pattern->matches('RM'));
    }

    // ─── Glob matching ──────────────────────────────────────────────

    #[Test]
    public function globMatchWithStar(): void
    {
        $pattern = RulePatternVo::createFromGlob('rm *');

        self::assertTrue($pattern->matches('rm -rf /'));
        self::assertTrue($pattern->matches('rm file.txt'));
        self::assertFalse($pattern->matches('ls'));
    }

    #[Test]
    public function globMatchWithPrefixStar(): void
    {
        $pattern = RulePatternVo::createFromGlob('bash*');

        self::assertTrue($pattern->matches('bash'));
        self::assertTrue($pattern->matches('bash -c echo'));
        self::assertFalse($pattern->matches('sh'));
    }

    #[Test]
    public function globMatchWithQuestionMark(): void
    {
        $pattern = RulePatternVo::createFromGlob('file?.txt');

        self::assertTrue($pattern->matches('file1.txt'));
        self::assertTrue($pattern->matches('fileA.txt'));
        self::assertFalse($pattern->matches('file.txt'));
        self::assertFalse($pattern->matches('file12.txt'));
    }

    #[Test]
    public function globMatchWithDoubleStar(): void
    {
        $pattern = RulePatternVo::createFromGlob('/usr/bin/sudo *');

        self::assertTrue($pattern->matches('/usr/bin/sudo apt install'));
        self::assertFalse($pattern->matches('/usr/bin/sudo'));
    }

    #[Test]
    public function globMatchWithStarOnly(): void
    {
        $pattern = RulePatternVo::createFromGlob('*');

        self::assertTrue($pattern->matches('anything'));
        self::assertTrue($pattern->matches(''));
    }

    // ─── Regex matching ─────────────────────────────────────────────

    #[Test]
    public function regexMatchReturnsTrueForMatchingValue(): void
    {
        $pattern = RulePatternVo::createFromRegex('/^rm\s/');

        self::assertTrue($pattern->matches('rm -rf /'));
        self::assertTrue($pattern->matches('rm file'));
        self::assertFalse($pattern->matches('ls'));
    }

    #[Test]
    public function regexMatchWithAnchors(): void
    {
        $pattern = RulePatternVo::createFromRegex('/^bash -c /');

        self::assertTrue($pattern->matches('bash -c echo hi'));
        self::assertFalse($pattern->matches('/bin/bash -c echo'));
    }

    #[Test]
    public function regexRejectsInvalidPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid regex pattern');

        RulePatternVo::createFromRegex('/[invalid/');
    }

    // ─── Empty pattern ──────────────────────────────────────────────

    #[Test]
    public function rejectsEmptyPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');

        RulePatternVo::createFromExact('');
    }

    // ─── Factory methods ────────────────────────────────────────────

    #[Test]
    public function createFromTypeSetsCorrectType(): void
    {
        $pattern = RulePatternVo::createFromType(PatternTypeEnum::glob, 'test*');

        self::assertSame(PatternTypeEnum::glob, $pattern->getType());
        self::assertSame('test*', $pattern->getPattern());
    }

    // ─── Equality ───────────────────────────────────────────────────

    #[Test]
    public function equalsReturnsTrueForSameTypeAndPattern(): void
    {
        $p1 = RulePatternVo::createFromGlob('rm *');
        $p2 = RulePatternVo::createFromGlob('rm *');

        self::assertTrue($p1->equals($p2));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentTypeOrPattern(): void
    {
        $glob = RulePatternVo::createFromGlob('rm');
        $exact = RulePatternVo::createFromExact('rm');

        self::assertFalse($glob->equals($exact));
    }

    #[Test]
    public function toStringReturnsTypeAndPattern(): void
    {
        $pattern = RulePatternVo::createFromExact('rm');

        self::assertSame('exact:rm', (string) $pattern);
    }
}
