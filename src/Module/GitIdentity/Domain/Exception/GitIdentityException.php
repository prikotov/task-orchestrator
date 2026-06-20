<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception;

/**
 * Базовое исключение модуля GitIdentity.
 *
 * Контракт (раздел C): все публичные ошибки модуля являются потомками
 * {@see GitIdentityException}, что позволяет ловить единый базовый класс.
 *
 * Сообщения исключений НЕ должны содержать секретные значения
 * (PEM private key, JWT, installation token, raw Authorization header).
 *
 * Базовый класс не объявляется final — от него наследуют специфичные исключения:
 * @see InvalidConfigurationException
 * @see GitHubApiException
 */
class GitIdentityException extends \RuntimeException
{
}
