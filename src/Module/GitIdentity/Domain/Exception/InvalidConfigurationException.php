<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception;

/**
 * Ошибка конфигурации или локальной подготовки GitIdentity.
 *
 * Выбрасывается при некорректных входных данных, нарушении инвариантов Value Object,
 * недоступности PEM-файла, нарушении требований к правам доступа (chmod),
 * некорректных URI/диапазонах конфигурации.
 *
 * Сообщения не содержат секретных значений (PEM/JWT/token).
 */
final class InvalidConfigurationException extends GitIdentityException
{
}
