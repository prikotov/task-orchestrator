<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\PharCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TaskOrchestrator\Common\Component\PharCache\CacheRootOwnerMarker;

/**
 * Модульные тесты чистой логики маркера владения кеш-корнем PHAR
 * ({@see CacheRootOwnerMarker}): кодирование и разбор содержимого без
 * файловых операций.
 */
#[CoversClass(CacheRootOwnerMarker::class)]
final class CacheRootOwnerMarkerTest extends TestCase
{
    #[Test]
    public function encodeProducesJsonWithSchemaAndRootName(): void
    {
        $payload = CacheRootOwnerMarker::encode('abc123');

        self::assertSame('{"schema":1,"root":"abc123"}', trim($payload));
    }

    #[Test]
    public function decodeReturnsRootNameForValidMarker(): void
    {
        $payload = CacheRootOwnerMarker::encode('abc123');

        self::assertSame('abc123', CacheRootOwnerMarker::decode($payload));
    }

    #[Test]
    public function decodeToleratesTrailingWhitespace(): void
    {
        self::assertSame('abc123', CacheRootOwnerMarker::decode("{\"schema\":1,\"root\":\"abc123\"}\n\n"));
    }

    #[Test]
    public function decodeReturnsNullForEmptyPayload(): void
    {
        // Аварийное завершение между созданием файла и записью маркера.
        self::assertNull(CacheRootOwnerMarker::decode(''));
    }

    #[Test]
    public function decodeReturnsNullForBrokenJson(): void
    {
        self::assertNull(CacheRootOwnerMarker::decode('{"schema":1,"root":'));
    }

    #[Test]
    public function decodeReturnsNullForJsonScalar(): void
    {
        self::assertNull(CacheRootOwnerMarker::decode('"abc123"'));
    }

    #[Test]
    public function decodeReturnsNullForUnknownSchemaVersion(): void
    {
        self::assertNull(CacheRootOwnerMarker::decode('{"schema":999,"root":"abc123"}'));
    }

    #[Test]
    public function decodeReturnsNullForMissingRootField(): void
    {
        self::assertNull(CacheRootOwnerMarker::decode('{"schema":1}'));
    }

    #[Test]
    public function decodeReturnsNullForNonStringRootField(): void
    {
        self::assertNull(CacheRootOwnerMarker::decode('{"schema":1,"root":42}'));
    }

    #[Test]
    public function decodeReturnsNullForEmptyRootField(): void
    {
        self::assertNull(CacheRootOwnerMarker::decode('{"schema":1,"root":""}'));
    }
}
