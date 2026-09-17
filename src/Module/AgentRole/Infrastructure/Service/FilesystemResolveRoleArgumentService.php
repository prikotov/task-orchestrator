<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Infrastructure\Service;

use Override;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Exception\RoleArgumentNotFoundException;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\LocateRoleFileServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\Service\ResolveRoleArgumentServiceInterface;
use TaskOrchestrator\Common\Module\AgentRole\Domain\ValueObject\RoleNameVo;

/**
 * Резолвинг аргумента «имя роли | путь к файлу роли» на файловой системе.
 *
 * Имя роли (без «/» и суффикса `.md`) делегируется в
 * {@see FilesystemLocateRoleFileService} (каталог ролей + локаль).
 *
 * Путь проверяется по базисам: абсолютный — как есть; относительный — от
 * каждого базиса с лексическим сворачиванием «..» по строке базиса
 * (семантика `cd -L` bash). Это критично для базисов-симлинков: физический
 * realpath раскрыл бы симлинк-компонент ДО сворачивания «..» и промахнулся бы
 * мимо файла, который агент адресует от логического местоположения
 * (например, `.agents/skills/become-role` → `../../../docs/…`).
 */
final readonly class FilesystemResolveRoleArgumentService implements ResolveRoleArgumentServiceInterface
{
    public function __construct(
        private LocateRoleFileServiceInterface $roleFileLocator,
    ) {
    }

    #[Override]
    public function resolve(string $argument, array $bases): string
    {
        if (!$this->looksLikeFilePath($argument)) {
            return $this->roleFileLocator->locate(RoleNameVo::createFromName($argument));
        }

        foreach ($this->pathCandidates($argument, $bases) as $candidate) {
            if (is_file($candidate)) {
                // Кандидат уже лексически каноничен; realpath переводит в
                // физический вид для единообразия вывода (файл существует).
                $realPath = realpath($candidate);

                return $realPath !== false ? $realPath : $candidate;
            }
        }

        throw new RoleArgumentNotFoundException($argument, $bases);
    }

    /**
     * Аргумент похож на путь к файлу роли, а не на имя роли (snake_case)?
     */
    private function looksLikeFilePath(string $argument): bool
    {
        return str_contains($argument, '/') || str_ends_with($argument, '.md');
    }

    /**
     * Кандидаты проверки по порядку: абсолютный путь сам по себе, затем
     * относительный аргумент от каждого базиса.
     *
     * @param list<string> $bases
     *
     * @return list<string>
     */
    private function pathCandidates(string $argument, array $bases): array
    {
        if (str_starts_with($argument, '/')) {
            return [$argument];
        }

        $candidates = [];

        foreach ($bases as $base) {
            if ($base === '') {
                continue;
            }

            $candidates[] = $this->lexicalResolve($base, $argument);
        }

        return $candidates;
    }

    /**
     * Лексическое разрешение относительного пути от базиса (семантика cd -L):
     * компоненты «..» выталкивают предыдущий компонент СТРОКОВО, без раскрытия
     * симлинков; «.» пропускается; «..» от корня игнорируется (не выше корня).
     */
    private function lexicalResolve(string $base, string $relative): string
    {
        $stack = [];

        foreach (explode('/', rtrim($base, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            $stack[] = $segment;
        }

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($stack);

                continue;
            }

            $stack[] = $segment;
        }

        return '/' . implode('/', $stack);
    }
}
