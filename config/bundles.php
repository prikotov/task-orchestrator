<?php

declare(strict_types=1);

/**
 * Реестр Symfony bundles TaskOrchestrator.
 *
 * Формат соответствует конвенции docs/conventions/symfony-folder-structure.md:
 * класс bundle => массив окружений, в которых он активен. Ключ 'all' => true
 * включает bundle во всех окружениях.
 *
 * Возвращается MicroKernelTrait::registerBundles() ядра TaskOrchestrator\Common\Kernel.
 *
 * @return array<string, array<string, bool>>
 */
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
];
