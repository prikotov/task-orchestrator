<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Infrastructure\Service\Chain;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TaskOrchestrator\Common\Module\ChainDefinition\Domain\Service\Chain\ResolveYamlDynamicPromptsServiceInterface;
use TaskOrchestrator\Common\Module\ChainDefinition\Infrastructure\Service\Chain\ResolveYamlDynamicPromptsService;

/**
 * Unit-проверка резолва prompt-конфигурации dynamic-цепочки
 * ({@see ResolveYamlDynamicPromptsService}).
 *
 * Покрывает: инлайн-тексты, чтение файлов относительно каталога YAML,
 * trim содержимого файла, fail-fast на отсутствующих/пустых ключах и
 * ошибке чтения prompt-файла (file_exists проходит, file_get_contents
 * возвращает false — ветка readFile() с RuntimeException).
 */
#[CoversClass(ResolveYamlDynamicPromptsService::class)]
final class ResolveYamlDynamicPromptsServiceTest extends TestCase
{
    /** Схема потокового wrapper'а «существующего, но нечитаемого» файла */
    private const string UNREADABLE_SCHEME = 'tor-unreadable-prompt';
    private string $tmpDir;
    private string $yamlPath;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tor-prompts-resolver-' . getmypid() . '-' . uniqid();
        mkdir($this->tmpDir . '/nested', 0777, true);
        $this->yamlPath = $this->tmpDir . '/chains.yaml';
    }

    protected function tearDown(): void
    {
        $rm = static function (string $dir) use (&$rm): void {
            foreach (glob($dir . '/*') ?: [] as $item) {
                is_dir($item) ? $rm($item) : unlink($item);
            }
            rmdir($dir);
        };
        $rm($this->tmpDir);
    }

    #[Test]
    public function implementsDomainContract(): void
    {
        // Контракт DI: реализация обязана реализовывать доменный интерфейс
        // (внедрение в YamlChainLoaderService и alias в services.yaml).
        self::assertInstanceOf(
            ResolveYamlDynamicPromptsServiceInterface::class,
            new ResolveYamlDynamicPromptsService(),
        );
    }

    #[Test]
    public function resolvesInlineTextsAsIs(): void
    {
        $resolved = (new ResolveYamlDynamicPromptsService())->resolve('demo', $this->yamlPath, [
            'brainstorm_system' => 'Think hard.',
            'facilitator_append' => '  Appendix. ',
            'facilitator_start' => 'Start.',
            'facilitator_continue' => 'Continue.',
            'facilitator_finalize' => 'Finalize.',
            'participant_append' => 'Participate.',
            'participant_user' => 'User turn.',
        ]);

        // Инлайн-текст используется как есть, включая пробелы (trim — только для файлов).
        self::assertSame('Think hard.', $resolved['brainstorm_system']);
        self::assertSame('  Appendix. ', $resolved['facilitator_append']);
        self::assertCount(7, $resolved);
    }

    #[Test]
    public function readsPromptFilesRelativeToYamlDirAndTrims(): void
    {
        file_put_contents($this->tmpDir . '/brainstorm.md', "  Brainstorm.\n\n");
        file_put_contents($this->tmpDir . '/nested/continue.md', 'Continue from file.');

        $resolved = (new ResolveYamlDynamicPromptsService())->resolve('demo', $this->yamlPath, [
            'brainstorm_system' => 'brainstorm.md',
            'facilitator_append' => 'inline',
            'facilitator_start' => 'inline',
            'facilitator_continue' => 'nested/continue.md',
            'facilitator_finalize' => 'inline',
            'participant_append' => 'inline',
            'participant_user' => 'inline',
        ]);

        self::assertSame('Brainstorm.', $resolved['brainstorm_system']);
        self::assertSame('Continue from file.', $resolved['facilitator_continue']);
    }

    #[Test]
    public function throwsOnMissingRequiredKey(): void
    {
        $resolver = new ResolveYamlDynamicPromptsService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dynamic chain "demo" must specify prompts.facilitator_start.');

        $resolver->resolve('demo', $this->yamlPath, [
            'brainstorm_system' => 'x',
            'facilitator_append' => 'x',
            // facilitator_start отсутствует
            'facilitator_continue' => 'x',
            'facilitator_finalize' => 'x',
            'participant_append' => 'x',
            'participant_user' => 'x',
        ]);
    }

    #[Test]
    public function throwsOnEmptyRequiredKey(): void
    {
        $resolver = new ResolveYamlDynamicPromptsService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dynamic chain "demo" must specify prompts.participant_user.');

        $resolver->resolve('demo', $this->yamlPath, [
            'brainstorm_system' => 'x',
            'facilitator_append' => 'x',
            'facilitator_start' => 'x',
            'facilitator_continue' => 'x',
            'facilitator_finalize' => 'x',
            'participant_append' => 'x',
            'participant_user' => '',
        ]);
    }

    #[Test]
    public function throwsWhenPromptFileCannotBeRead(): void
    {
        // Каталог или файл без прав дают разное поведение file_get_contents()
        // на разных версиях PHP (8.4: false, 8.5: ''), поэтому ветка readFile()
        // детерминированно эмулируется wrapper'ом: url_stat() сообщает «файл
        // существует» (file_exists → true), stream_open() падает
        // (file_get_contents → false).
        self::assertTrue(stream_wrapper_register(self::UNREADABLE_SCHEME, UnreadablePromptFileStream::class));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                sprintf('Failed to read prompt file: %s://dir/prompt.md', self::UNREADABLE_SCHEME),
            );

            // file_get_contents() перед возвратом false порождает E_WARNING о
            // неудачном открытии потока — это ожидаемый вход проверяемой ветки,
            // глушим на время вызова (оператор @ запрещён PHPMD).
            set_error_handler(static fn (): bool => true);

            try {
                (new ResolveYamlDynamicPromptsService())->resolve(
                    'demo',
                    sprintf('%s://dir/chains.yaml', self::UNREADABLE_SCHEME),
                    [
                        'brainstorm_system' => 'x',
                        'facilitator_append' => 'x',
                        'facilitator_start' => 'prompt.md',
                        'facilitator_continue' => 'x',
                        'facilitator_finalize' => 'x',
                        'participant_append' => 'x',
                        'participant_user' => 'x',
                    ],
                );
            } finally {
                restore_error_handler();
            }
        } finally {
            stream_wrapper_unregister(self::UNREADABLE_SCHEME);
        }
    }
}

/**
 * Потоковый wrapper «существующего, но нечитаемого» файла для теста ветки
 * readFile(): stat рапортует обычный файл только для пути …/prompt.md,
 * открытие потока всегда падает — file_exists() → true, file_get_contents() → false.
 */
final class UnreadablePromptFileStream
{
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return false;
    }

    public function url_stat(string $path, int $flags): array|false
    {
        if (!str_ends_with($path, '/prompt.md')) {
            return false;
        }

        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0100777,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 5,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => 4096,
            'blocks' => 1,
        ];
    }
}
