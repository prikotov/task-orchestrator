<?php

declare(strict_types=1);

namespace TaskOrchestrator\Tests\Integration\Bin;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Интеграционная проверка входной валидации bin/phar-smoke.
 *
 * phar-smoke требует явную ожидаемую версию (PHAR_EXPECTED_VERSION) точного
 * SemVer-формата (или non-release marker `dev`): smoke обязан падать на
 * отсутствии ожидания и на неверном формате ДО сборки PHAR, чтобы дефект вроде
 * нормализованного Composer-значения `1.0.0.0` (PHAR v0.2.0) не прошёл
 * ложнозелёным.
 *
 * Валидация входа выполнена в скрипте до проверки наличия Box, поэтому фазу
 * required/format можно покрыть тестом без локальной установки Box
 * (проверяемость вне CI).
 *
 * Точное сравнение фактического вывода PHAR `--version` с ожиданием проверяется
 * через внутренний режим самого `bin/phar-smoke`: mismatch/happy-path сценарии
 * не требуют сборки PHAR, а полная сборка остаётся в CI release-phar.
 */
#[CoversNothing]
final class PharSmokeScriptTest extends TestCase
{
    private string $projectRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
    }

    #[Test]
    public function failsWhenExpectedVersionIsMissing(): void
    {
        // Arrange / Act: PHAR_EXPECTED_VERSION не передан.
        $process = $this->runPharSmoke([]);

        // Assert: required-expectation — exit 2 до любой попытки сборки.
        self::assertSame(2, $process->getExitCode());
        $output = $process->getOutput() . $process->getErrorOutput();
        self::assertStringContainsString('PHAR_EXPECTED_VERSION must be set', $output);
    }

    #[Test]
    public function rejectsNormalizedComposerVersionAsExpectation(): void
    {
        // Arrange: дефект PHAR v0.2.0 — нормализованное Composer-значение
        // `1.0.0.0` не должно приниматься как ожидание: smoke обязан падать на нём.

        // Act
        $process = $this->runPharSmoke(['PHAR_EXPECTED_VERSION' => '1.0.0.0']);

        // Assert
        self::assertSame(2, $process->getExitCode());
        $output = $process->getOutput() . $process->getErrorOutput();
        self::assertStringContainsString('must be an exact SemVer', $output);
        self::assertStringContainsString('1.0.0.0', $output);
    }

    #[Test]
    public function rejectsNonSemVerExpectation(): void
    {
        // Arrange / Act
        $process = $this->runPharSmoke(['PHAR_EXPECTED_VERSION' => 'not-a-version']);

        // Assert
        self::assertSame(2, $process->getExitCode());
        $output = $process->getOutput() . $process->getErrorOutput();
        self::assertStringContainsString('must be an exact SemVer', $output);
    }

    #[Test]
    public function acceptsExactSemVerAndReachesBuildPhase(): void
    {
        // Arrange: валидный SemVer проходит входную валидацию и достигает
        // build-фазы. Без локального Box build-фаза падает с 127; при наличии
        // Box тест skip'ается, чтобы не запускать дорогую сборку PHAR на уровне
        // интеграционных тестов (full build проверяется в CI release-phar).

        // Act
        if ($this->boxAvailable()) {
            self::markTestSkipped('Box is installed locally; full PHAR build is covered in CI release-phar.');
        }

        $process = $this->runPharSmoke(['PHAR_EXPECTED_VERSION' => '0.2.1']);

        // Assert: вход прошёл, упёрлись в отсутствие Box.
        self::assertSame(127, $process->getExitCode());
        $output = $process->getOutput() . $process->getErrorOutput();
        self::assertStringContainsString('Box is required', $output);
    }

    #[Test]
    public function acceptsDevMarkerAndReachesBuildPhase(): void
    {
        // Arrange / Act
        if ($this->boxAvailable()) {
            self::markTestSkipped('Box is installed locally; full PHAR build is covered in CI release-phar.');
        }

        $process = $this->runPharSmoke(['PHAR_EXPECTED_VERSION' => 'dev']);

        // Assert: non-release marker `dev` принимается входной валидацией.
        self::assertSame(127, $process->getExitCode());
        $output = $process->getOutput() . $process->getErrorOutput();
        self::assertStringContainsString('Box is required', $output);
    }

    #[Test]
    public function versionCheckFailsWhenActualVersionDoesNotMatch(): void
    {
        $process = $this->runVersionCheck('0.2.1', "Task Orchestrator 1.0.0.0\n");

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('expected: Task Orchestrator 0.2.1', $process->getErrorOutput());
        self::assertStringContainsString('actual:   Task Orchestrator 1.0.0.0', $process->getErrorOutput());
    }

    #[Test]
    public function versionCheckPassesOnlyOnExactOutput(): void
    {
        $process = $this->runVersionCheck('0.2.1', "Task Orchestrator 0.2.1\n");

        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('Task Orchestrator 0.2.1', $process->getOutput());
    }

    #[Test]
    #[DataProvider('invalidExpectedVersions')]
    public function versionCheckRejectsNonSemVerExpectation(string $expectedVersion): void
    {
        $process = $this->runVersionCheck($expectedVersion, 'Task Orchestrator ' . $expectedVersion);

        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('must be an exact SemVer', $process->getErrorOutput());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidExpectedVersions(): iterable
    {
        yield 'leading v is not SemVer' => ['v0.2.1'];
        yield 'uppercase V is not SemVer' => ['V0.2.1'];
        yield 'core leading zero' => ['01.2.3'];
        yield 'numeric prerelease leading zero' => ['1.2.3-01'];
        yield 'empty prerelease identifier' => ['1.2.3-alpha..1'];
        yield 'empty build identifier' => ['1.2.3+build..1'];
        yield 'composer no-version marker' => ['1.0.0+no-version-set'];
        yield 'prerelease with composer no-version marker' => ['1.0.0-rc.1+no-version-set'];
    }

    #[Test]
    #[DataProvider('inexactActualOutputs')]
    public function versionCheckRejectsOutputThatIsNotExactlyOneLine(string $actualOutput): void
    {
        $process = $this->runVersionCheck('0.2.1', $actualOutput);

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('PHAR --version mismatch', $process->getErrorOutput());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function inexactActualOutputs(): iterable
    {
        yield 'missing newline' => ['Task Orchestrator 0.2.1'];
        yield 'extra newline' => ["Task Orchestrator 0.2.1\n\n"];
        yield 'extra output' => ["Task Orchestrator 0.2.1\nunexpected\n"];
    }

    #[Test]
    public function releaseValidationRejectsDevMarker(): void
    {
        $process = new Process(
            ['bin/phar-smoke', '--internal-validate-release-version', 'dev'],
            $this->projectRoot,
        );
        $process->run();

        self::assertSame(2, $process->getExitCode());
    }

    /**
     * @param array<string, string> $env
     */
    private function runPharSmoke(array $env): Process
    {
        $process = new Process(
            ['bin/phar-smoke'],
            $this->projectRoot,
            ['PHAR_EXPECTED_VERSION' => false, ...$env],
        );
        $process->setTimeout(20);
        $process->run();

        return $process;
    }

    private function boxAvailable(): bool
    {
        return (new ExecutableFinder())->find('box') !== null;
    }

    private function runVersionCheck(string $expectedVersion, string $actualOutput): Process
    {
        $process = new Process(
            ['bin/phar-smoke', '--internal-check-version-output', $expectedVersion],
            $this->projectRoot,
        );
        $process->setInput($actualOutput);
        $process->run();

        return $process;
    }
}
