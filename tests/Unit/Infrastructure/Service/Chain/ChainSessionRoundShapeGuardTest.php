<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use TaskOrchestrator\Common\Module\DynamicLoop\Domain\Service\Session\DynamicLoopSessionReaderInterface;

/**
 * Сторож канонической формы записи раунда (doc-уровень).
 *
 * Канон — docblock DynamicLoopSessionReaderInterface::getRoundFiles().
 * Все инлайн-копии полной формы в src/, apps/, tests/ обязаны быть
 * байт-в-байт равны канону: дрейф любой копии роняет тест в CI, не полагаясь
 * на ручные сверки при ревью (закрывает OBS-2 ревью PR #526 и AR-5
 * архитектурного ревью TASK-techdebt-writer-round-shape-drift).
 *
 * Канон извлекается рефлексией, а не хардкодом сигнатуры: при переносе
 * интерфейса или реформатировании docblock тест остаётся точным.
 *
 * Теловой уровень (синхронность белых списков parseRoundFiles()/
 * writeSessionJson()) этот тест не сторожит — он покрыт roundtrip-тестом
 * ChainSessionWriterRoundTripAllFieldsTest.
 *
 * Критерий выборки — начало объявления: в неё попадает любая форма,
 * начинающаяся с `system:` (включая открытую с завершающим `...` и узкую
 * закрытую с усечённым набором ключей) — такая копия уронит сторож при
 * дрейфе; объявление от иного первого ключа (потребляющая форма
 * restoreFromRoundFiles() от `round`) — вне выборки, правила AR-1/AR-2
 * не нарушаются. Сам этот докблок литерал формы не содержит, чтобы не
 * попасть в собственную выборку.
 */
final class ChainSessionRoundShapeGuardTest extends TestCase
{
    /**
     * Ожидаемое число копий полной формы в кодовой базе, включая сам канон.
     *
     * Сознательное изменение набора копий (добавление/удаление объявления)
     * требует обновления этой константы — тест фиксирует инвентарь копий.
     */
    private const EXPECTED_OCCURRENCES = 9;

    /** Каталоги скана: весь код библиотеки, консольное приложение, тесты. */
    private const SCAN_DIRS = ['src', 'apps', 'tests'];

    /**
     * Предел подъёма по каталогам при поиске корня проекта (composer.json):
     * тест лежит глубоко в tests/, предел с запасом покрывает любую разумную
     * вложенность и гарантирует завершение цикла.
     */
    private const MAX_ROOT_SEARCH_DEPTH = 8;

    #[Test]
    public function everyRoundShapeDeclarationIsByteIdenticalToCanon(): void
    {
        $root = $this->findProjectRoot();
        $canon = $this->extractCanonicalShape();

        $occurrences = [];
        foreach (self::SCAN_DIRS as $dir) {
            $occurrences = [...$occurrences, ...$this->collectShapeOccurrences($root, $root . '/' . $dir)];
        }

        self::assertSame(
            self::EXPECTED_OCCURRENCES,
            count($occurrences),
            'Набор копий формы записи раунда изменился — обнови EXPECTED_OCCURRENCES '
            . 'осознанно. Найдено: ' . $this->formatOccurrences($occurrences),
        );

        foreach ($occurrences as $occurrence) {
            self::assertSame(
                $canon,
                $occurrence['shape'],
                sprintf(
                    'Дрейф формы записи раунда в %s:%d — приведи объявление к канону '
                    . 'DynamicLoopSessionReaderInterface::getRoundFiles() байт-в-байт.',
                    $occurrence['file'],
                    $occurrence['line'],
                ),
            );
        }
    }

    /**
     * Возвращает каноническую строку формы из docblock getRoundFiles().
     */
    private function extractCanonicalShape(): string
    {
        $docComment = (new ReflectionMethod(
            DynamicLoopSessionReaderInterface::class,
            'getRoundFiles',
        ))->getDocComment();
        self::assertNotFalse(
            $docComment,
            'У DynamicLoopSessionReaderInterface::getRoundFiles() исчез docblock с канонической формой.',
        );

        $count = preg_match_all('/array\{system:.*?\}/s', $docComment, $matches);
        self::assertSame(
            1,
            $count,
            'В docblock канона должна быть ровно одна копия формы записи раунда.',
        );

        return $matches[0][0];
    }

    /**
     * Собирает все вхождения полной формы записи раунда в *.php файлах каталога.
     *
     * @return list<array{file: string, line: int, shape: string}>
     */
    private function collectShapeOccurrences(string $root, string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $occurrences = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }
            $content = (string) file_get_contents($fileInfo->getPathname());
            preg_match_all('/array\{system:.*?\}/s', $content, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as [$shape, $offset]) {
                $occurrences[] = [
                    'file' => substr($fileInfo->getPathname(), strlen($root) + 1),
                    'line' => substr_count(substr($content, 0, $offset), "\n") + 1,
                    'shape' => $shape,
                ];
            }
        }

        return $occurrences;
    }

    /**
     * Возвращает корень проекта (каталог с composer.json).
     */
    private function findProjectRoot(): string
    {
        $dir = __DIR__;
        for ($depth = 0; $depth < self::MAX_ROOT_SEARCH_DEPTH; $depth++) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        self::fail('Не найден корень проекта (каталог с composer.json).');
    }

    /**
     * @param list<array{file: string, line: int, shape: string}> $occurrences
     */
    private function formatOccurrences(array $occurrences): string
    {
        return implode(
            '; ',
            array_map(
                static fn (array $occurrence): string => $occurrence['file'] . ':' . $occurrence['line'],
                $occurrences,
            ),
        );
    }
}
