<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection;

use Override;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Регистрирует Twig-пути модуля в контейнере.
 *
 * Добавляет базовый путь с собственным Twig-namespace и дополнительные пары
 * путь => namespace в twig.loader.native_filesystem, а также подключает их
 * через prepend-extension-config ключа «twig». Один pass регистрируется на
 * каждый модуль, реализующий TwigInterface, в ModuleKernelTrait.
 *
 * @see \TaskOrchestrator\Common\Component\ModuleSystem\Extension\TwigInterface
 * @see \TaskOrchestrator\Common\Component\ModuleSystem\ModuleKernelTrait
 */
final class TwigCompilerPass implements CompilerPassInterface
{
    /**
     * @param array<string, string> $additionalPathMap пары путь => namespace
     */
    public function __construct(
        private readonly string $defaultTemplatePath,
        private readonly string $defaultTwigNamespace,
        private readonly array $additionalPathMap = [],
    ) {
    }

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (array_key_exists($this->defaultTemplatePath, $this->additionalPathMap)) {
            throw new RuntimeException('Duplicate template path');
        }

        if (in_array($this->defaultTwigNamespace, $this->additionalPathMap, true)) {
            throw new RuntimeException('Duplicate template namespace');
        }

        $container->prependExtensionConfig(
            'twig',
            ['paths' => [...$this->additionalPathMap, $this->defaultTemplatePath => $this->defaultTwigNamespace]],
        );

        $twigFilesystemLoaderDefinition = $container->getDefinition('twig.loader.native_filesystem');
        $twigFilesystemLoaderDefinition->addMethodCall(
            'addPath',
            [$this->defaultTemplatePath, $this->defaultTwigNamespace],
        );
        foreach ($this->additionalPathMap as $templatePath => $namespace) {
            $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$templatePath, $namespace]);
        }
    }
}
