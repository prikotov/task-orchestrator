<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\AgentRole\Domain\Exception;




/**
 * Выбрасывается при обнаружении циклической зависимости между skills.
 *
 * Развёртка `depends_on` выполняется рекурсивно; цикл (skill A зависит от B,
 * а B — от A) означает некорректную конфигурацию каталога skills и не может
 * быть разрешена детерминированно.
 */
final class CircularSkillDependencyException extends AgentRoleException
{
    /**
     * @param list<string> $chain упорядоченная цепочка имён skills, образовавшая цикл
     */
    public function __construct(array $chain)
    {
        parent::__construct(sprintf('Circular skill dependency detected: %s.', implode(' -> ', $chain)));
    }
}
