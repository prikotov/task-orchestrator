<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken;

use DateTimeImmutable;

/**
 * Выход UseCase получения installation token.
 *
 * Имена полей для JSON-сериализации (контракт C): token, expires_at, installation_id.
 * Преобразование в snake_case выполняется на слое Presentation.
 */
final readonly class ObtainTokenResultDto
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public int $installationId,
    ) {
    }
}
