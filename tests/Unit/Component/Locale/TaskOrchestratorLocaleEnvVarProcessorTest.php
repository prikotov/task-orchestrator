<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Unit\Component\Locale;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use TaskOrchestrator\Common\Component\Locale\TaskOrchestratorLocaleEnvVarProcessor;

/**
 * Unit-тест контракта env TASK_ORCHESTRATOR_LOCALE (нормализация локали
 * AI-ролей) в {@see TaskOrchestratorLocaleEnvVarProcessor}.
 *
 * Покрывает default `en` при незаданной/пустой переменной, trim и нормализацию
 * регистра. Отдельный regression-контракт: процессор НЕ читает APP_LOCALE —
 * fallback на локаль host-проекта отсутствует по дизайну.
 */
#[CoversClass(TaskOrchestratorLocaleEnvVarProcessor::class)]
final class TaskOrchestratorLocaleEnvVarProcessorTest extends TestCase
{
    private TaskOrchestratorLocaleEnvVarProcessor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->processor = new TaskOrchestratorLocaleEnvVarProcessor();
    }

    #[Test]
    public function missingEnvYieldsNeutralDefaultEn(): void
    {
        // Переменная не задана → нейтральный default библиотеки `en`.
        self::assertSame(
            'en',
            $this->processor->getEnv(
                TaskOrchestratorLocaleEnvVarProcessor::ENV_PREFIX,
                TaskOrchestratorLocaleEnvVarProcessor::ENV_NAME,
                static fn (string $name): string => throw new EnvNotFoundException($name),
            ),
        );
    }

    #[Test]
    public function blankEnvYieldsNeutralDefaultEn(): void
    {
        // Пустая строка и строка из пробелов эквивалентны незаданной переменной.
        self::assertSame('en', $this->resolveRaw(''));
        self::assertSame('en', $this->resolveRaw('   '));
    }

    #[Test]
    public function supportedValueIsTrimmedAndLowercased(): void
    {
        self::assertSame('ru', $this->resolveRaw('ru'));
        self::assertSame('ru', $this->resolveRaw('RU'));
        self::assertSame('ru', $this->resolveRaw(' Ru '));
        self::assertSame('en', $this->resolveRaw('EN'));
    }

    #[Test]
    public function nonStringValueYieldsNeutralDefaultEn(): void
    {
        // Строгость к неожидаемому типу: локаль ролей — всегда строка `en`
        // вместо неявного приведения типов.
        self::assertSame('en', $this->resolveRaw(null));
        self::assertSame('en', $this->resolveRaw(true));
    }

    #[Test]
    public function providedTypesDeclareProcessorPrefix(): void
    {
        self::assertSame(
            ['task_orchestrator_locale' => 'string'],
            TaskOrchestratorLocaleEnvVarProcessor::getProvidedTypes(),
        );
    }

    /**
     * Прогоняет процессор с getEnv-замыканием, отдающим фиксированное сырое значение.
     */
    private function resolveRaw(mixed $raw): string
    {
        $resolved = $this->processor->getEnv(
            TaskOrchestratorLocaleEnvVarProcessor::ENV_PREFIX,
            TaskOrchestratorLocaleEnvVarProcessor::ENV_NAME,
            static fn (): mixed => $raw,
        );

        self::assertIsString($resolved);

        return $resolved;
    }
}
