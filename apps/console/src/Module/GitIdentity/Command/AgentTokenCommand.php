<?php

declare(strict_types=1);

namespace TaskOrchestrator\Console\Module\GitIdentity\Command;

use JsonException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TaskOrchestrator\Common\Module\GitIdentity\Application\Exception\ObtainTokenFailedException;
use TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken\ObtainTokenCommand;
use TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken\ObtainTokenCommandHandler;
use TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken\ObtainTokenResultDto;

/**
 * CLI-команда получения GitHub App installation token для repository.
 *
 * Делегирует в {@see ObtainTokenCommandHandler}. Форматы вывода:
 *   - plain (по умолчанию): только токен (для pipe);
 *   - json: {token, expires_at, installation_id};
 *   - env: `export GITHUB_TOKEN='<token>'` (для eval).
 *
 * Безопасность: ошибки выводятся в stderr без секретов; токен печатается
 * только в stdout в выбранном формате.
 */
#[AsCommand(
    name: 'agent:token',
    description: 'Получить GitHub App installation token для repository',
)]
final class AgentTokenCommand extends Command
{
    private const string ARG_REPO = 'repo';

    private const string OPT_FORMAT = 'format';

    private const string FORMAT_PLAIN = 'plain';

    private const string FORMAT_JSON = 'json';

    private const string FORMAT_ENV = 'env';

    /**
     * Допустимые символы токена GitHub (ghs_*, gh*_*): буквенно-цифровые и _.
     * Используется для fail-fast при неожиданных символах перед shell-escape.
     */
    private const string TOKEN_SAFE_PATTERN = '/^[A-Za-z0-9_]+$/';

    public function __construct(
        private readonly ObtainTokenCommandHandler $handler,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARG_REPO,
                InputArgument::REQUIRED,
                'Repository slug в формате <owner>/<repo>',
            )
            ->addOption(
                self::OPT_FORMAT,
                null,
                InputOption::VALUE_REQUIRED,
                'Формат вывода: plain (только токен), json, env (export GITHUB_TOKEN)',
                self::FORMAT_PLAIN,
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $repoSlug */
        $repoSlug = $input->getArgument(self::ARG_REPO);
        /** @var string $format */
        $format = $input->getOption(self::OPT_FORMAT);

        if (!in_array($format, [self::FORMAT_PLAIN, self::FORMAT_JSON, self::FORMAT_ENV], true)) {
            $io->error(sprintf('Unknown --format "%s": expected plain|json|env.', $format));

            return Command::INVALID;
        }

        try {
            /** @var ObtainTokenResultDto $result */
            $result = ($this->handler)(new ObtainTokenCommand($repoSlug));
        } catch (ObtainTokenFailedException $e) {
            // Application-level boundary error; сообщение очищено от секретов (контракт C).
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (preg_match(self::TOKEN_SAFE_PATTERN, $result->token) !== 1) {
            $io->error('Token contains unexpected characters — aborting for safety.');

            return Command::FAILURE;
        }

        $this->render($output, $format, $result);

        return Command::SUCCESS;
    }

    private function render(OutputInterface $output, string $format, ObtainTokenResultDto $result): void
    {
        switch ($format) {
            case self::FORMAT_JSON:
                $output->writeln($this->renderJson($result));
                break;
            case self::FORMAT_ENV:
                $output->writeln(sprintf("export GITHUB_TOKEN='%s'", $result->token));
                break;
            case self::FORMAT_PLAIN:
            default:
                $output->writeln($result->token);
                break;
        }
    }

    private function renderJson(ObtainTokenResultDto $result): string
    {
        try {
            return json_encode(
                [
                    'token' => $result->token,
                    'expires_at' => $result->expiresAt->format(\DateTimeImmutable::ATOM),
                    'installation_id' => $result->installationId,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            // Не должно случиться для скалярных данных; оборачиваем в ошибку выполнения.
            throw new \RuntimeException('Failed to render JSON output.', 0, $e);
        }
    }
}
