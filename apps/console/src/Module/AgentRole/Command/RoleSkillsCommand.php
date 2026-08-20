<?php

declare(strict_types=1);

namespace TaskOrchestrator\Console\Module\AgentRole\Command;

use JsonException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TaskOrchestrator\Common\Module\AgentRole\Application\Dto\SkillDto;
use TaskOrchestrator\Common\Component\QueryBus\QueryBusComponentInterface;
use TaskOrchestrator\Common\Module\AgentRole\Application\Exception\ResolveRoleSkillsFailedException;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsQuery;
use TaskOrchestrator\Common\Module\AgentRole\Application\UseCase\Query\ResolveRoleSkills\ResolveRoleSkillsResultDto;




/**
 * CLI-команда резолвинга skills роли и вывода каталога для system prompt агента.
 *
 * Делегирует в {@see ResolveRoleSkillsQueryHandler}. Форматы вывода:
 *   - block (по умолчанию): XML-блок `<available_skills>` для вставки в
 *     system prompt (формат Agent Skills / pi);
 *   - list: построчный список `name — description`;
 *   - json: {role, skills: [{name, description, location}], catalog}.
 *
 * Используется мета-скиллом become-role для динамического объявления skills роли
 * в контексте агента (как текущей сессии, так и сабагента).
 */
#[AsCommand(
    name: 'agent:role-skills',
    description: 'Резолвинг skills роли и вывод каталога для system prompt агента',
)]
final class RoleSkillsCommand extends Command
{
    private const string ARG_ROLE = 'role';

    private const string OPT_FORMAT = 'format';

    private const string FORMAT_BLOCK = 'block';

    private const string FORMAT_LIST = 'list';

    private const string FORMAT_JSON = 'json';

    public function __construct(
        private readonly QueryBusComponentInterface $queryBus,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARG_ROLE,
                InputArgument::REQUIRED,
                'Имя роли (snake_case, как в config/chains.yaml roles.<role>)',
            )
            ->addOption(
                self::OPT_FORMAT,
                null,
                InputOption::VALUE_REQUIRED,
                'Формат вывода: block (XML-каталог), list, json',
                self::FORMAT_BLOCK,
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $roleName */
        $roleName = $input->getArgument(self::ARG_ROLE);
        /** @var string $format */
        $format = $input->getOption(self::OPT_FORMAT);

        if (!in_array($format, [self::FORMAT_BLOCK, self::FORMAT_LIST, self::FORMAT_JSON], true)) {
            $io->error(sprintf('Unknown --format "%s": expected block|list|json.', $format));

            return Command::INVALID;
        }

        try {
            /** @var ResolveRoleSkillsResultDto $result */
            $result = $this->queryBus->query(new ResolveRoleSkillsQuery($roleName));
        } catch (ResolveRoleSkillsFailedException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->render($output, $format, $roleName, $result);

        return Command::SUCCESS;
    }

    private function render(
        OutputInterface $output,
        string $format,
        string $roleName,
        ResolveRoleSkillsResultDto $result,
    ): void {
        switch ($format) {
            case self::FORMAT_JSON:
                $output->writeln($this->renderJson($roleName, $result));
                break;
            case self::FORMAT_LIST:
                $this->renderList($output, $result);
                break;
            case self::FORMAT_BLOCK:
            default:
                $output->write($result->catalogBlock);
                break;
        }
    }

    private function renderList(OutputInterface $output, ResolveRoleSkillsResultDto $result): void
    {
        if ($result->skills === []) {
            $output->writeln('<comment>Role has no skills declared.</comment>');

            return;
        }

        foreach ($result->skills as $skill) {
            $output->writeln(sprintf('%s — %s', $skill->name, $skill->description));
            $output->writeln(sprintf('  location: %s', $skill->location));
        }
    }

    private function renderJson(string $roleName, ResolveRoleSkillsResultDto $result): string
    {
        try {
            return json_encode(
                [
                    'role' => $roleName,
                    'role_file' => $result->roleFilePath,
                    'skills' => array_map(
                        static fn (SkillDto $skill): array => [
                            'name' => $skill->name,
                            'description' => $skill->description,
                            'location' => $skill->location,
                        ],
                        $result->skills,
                    ),
                    'catalog' => $result->catalogBlock,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            throw new \RuntimeException('Failed to render JSON output.', 0, $e);
        }
    }
}
