<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\Service;

use DateTimeImmutable;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;

/**
 * Подписывает App JWT (RS256) для GitHub App authentication.
 *
 * Claims (контракт C):
 *   - iat = now - clock_skew (бэкдейтинг против NTP drift);
 *   - exp = iat + jwt_ttl;
 *   - iss = app_id.
 */
interface SignJwtTokenServiceInterface
{
    /**
     * @throws InvalidConfigurationException если ключ невалиден или подпись не удалась.
     */
    public function sign(GitIdentityConfigVo $config, DateTimeImmutable $now): JwtTokenVo;
}
