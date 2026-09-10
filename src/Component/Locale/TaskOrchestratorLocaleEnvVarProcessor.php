<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Component\Locale;

use Override;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;

/**
 * Env-процессор локали AI-ролей task-orchestrator (prefix `task_orchestrator_locale`).
 *
 * Единая точка контракта env TASK_ORCHESTRATOR_LOCALE — локали AI-ролей
 * (role files, каталог skills):
 *  - переменная не задана или пустая → пустая строка (автоматический поиск
 *    доступного role file). Язык каталога skills при этом использует default
 *    `en`. Скрытый fallback (резервный переход) на APP_LOCALE отсутствует
 *    намеренно: APP_LOCALE не является контрактом task-orchestrator;
 *  - любое другое значение → trim + lower-case (нормализация регистра локали).
 *
 * Процессор вызывается контейнером в runtime (динамический параметр
 * `task_orchestrator.locale`, см. Kernel::getKernelParameters()): значение НЕ
 * запекается в скомпилированный контейнер, поэтому смена локали применяется
 * при следующем запуске без очистки кеша при неизменном корне кеша.
 */
final class TaskOrchestratorLocaleEnvVarProcessor implements EnvVarProcessorInterface
{
    /** Prefix плейсхолдера `%env(task_orchestrator_locale:TASK_ORCHESTRATOR_LOCALE)%`. */
    public const string ENV_PREFIX = 'task_orchestrator_locale';

    /** Имя переменной окружения — публичный контракт локали AI-ролей. */
    public const string ENV_NAME = 'TASK_ORCHESTRATOR_LOCALE';

    #[Override]
    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        try {
            $raw = $getEnv($name);
        } catch (EnvNotFoundException) {
            return '';
        }

        $locale = is_string($raw) ? trim($raw) : '';

        return strtolower($locale);
    }

    /**
     * @return array<string, string> префикс процессора → PHP-тип результата
     */
    #[Override]
    public static function getProvidedTypes(): array
    {
        return [self::ENV_PREFIX => 'string'];
    }
}
