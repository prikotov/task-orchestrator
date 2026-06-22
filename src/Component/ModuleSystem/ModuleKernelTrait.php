<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\ModuleSystem;

use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\ModuleCompilerPass;
use TaskOrchestrator\Common\Component\ModuleSystem\DependencyInjection\TwigCompilerPass;
use TaskOrchestrator\Common\Component\ModuleSystem\Extension\TranslationInterface;
use TaskOrchestrator\Common\Component\ModuleSystem\Extension\TwigInterface;

/**
 * Поддержка модульной системы в Symfony Kernel.
 *
 * registerModules() перебирает реестр модулей (config/modules.php) и для
 * каждого модуля, активного в текущем окружении и реализующего ModuleInterface,
 * регистрирует ModuleCompilerPass (подгрузка Resource/config/services.yaml).
 * Если модуль реализует TwigInterface — добавляется TwigCompilerPass с путями
 * шаблонов; если TranslationInterface — пути переводов подключаются через
 * prepend-extension-config ключа «framework».
 *
 * Trait не знает о конкретных модулях и не зависит от Doctrine (проект не
 * использует БД). Используется классом Kernel.
 *
 * Требует от использующего класса метод isEnvironmentIncluded(array $envs): bool.
 *
 * @see \TaskOrchestrator\Common\Kernel
 *
 * @method bool isEnvironmentIncluded(array $envs)
 */
trait ModuleKernelTrait
{
    /**
     * @param array<string, array<string, bool>> $modules реестр модулей (класс => окружения)
     *
     * @throws RuntimeException если класс модуля не существует или не реализует ModuleInterface
     */
    private function registerModules(ContainerBuilder $container, array $modules): void
    {
        foreach ($modules as $class => $envs) {
            if (!$this->isEnvironmentIncluded($envs)) {
                continue;
            }

            if (!class_exists($class)) {
                throw new RuntimeException(sprintf('Class %s does not exist', $class));
            }

            $module = new $class();
            if (!$module instanceof ModuleInterface) {
                throw new RuntimeException(
                    sprintf('Module must implement %s interface', ModuleInterface::class),
                );
            }

            $container->addCompilerPass(
                new ModuleCompilerPass($module->getModuleConfigPath(), $this->environment),
                PassConfig::TYPE_BEFORE_OPTIMIZATION,
                10000,
            );

            if ($module instanceof TwigInterface) {
                $container->addCompilerPass(
                    new TwigCompilerPass(
                        defaultTemplatePath: $module->getBaseTemplatesPath(),
                        defaultTwigNamespace: $module->getBaseTwigNamespace(),
                        additionalPathMap: $module->getAdditionalTemplatesPaths(),
                    ),
                );
            }

            if ($module instanceof TranslationInterface) {
                $container->prependExtensionConfig('framework', [
                    'translator' => ['paths' => [$module->getBaseTranslationsPath()]],
                ]);

                foreach ($module->getAdditionalTranslationsPaths() as $path) {
                    $container->prependExtensionConfig('framework', [
                        'translator' => ['paths' => [$path]],
                    ]);
                }
            }
        }
    }
}
