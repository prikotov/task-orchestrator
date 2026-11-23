<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\PharRuntime;

/**
 * Единственная в src/ точка обращения к static API класса \Phar.
 *
 * Изолирует static-вызов `Phar::running(false)` (физический путь запущенного
 * PHAR-архива; пустая строка — исполнение вне архива) в отдельный класс,
 * чтобы потребители (Kernel и другие) не несли StaticAccess-нарушение PHPMD:
 * static API `\Phar` санкционирован точечным inline-подавлением на единственном
 * методе этого класса (см. `docs/conventions/ops/phpmd-suppressions-guidelines.md`).
 * Отсутствие расширения phar трактуется как «вне архива» (null).
 *
 * @see PharRuntimePathsProviderInterface контракт путей запущенного кода
 */
final class PharRunningPathResolver
{
    /**
     * \Phar::running() — единственный способ получить путь PHAR (static API вендора,
     * без инстансной альтернативы); постоянное санкционированное подавление, не техдолг.
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function runningPath(): ?string
    {
        if (!class_exists(\Phar::class)) {
            return null;
        }

        $running = \Phar::running(false);

        return $running === '' ? null : $running;
    }
}
