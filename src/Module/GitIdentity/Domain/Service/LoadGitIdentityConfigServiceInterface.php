<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\Service;

use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\InvalidConfigurationException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;

/**
 * Загружает и нормализует конфигурацию GitIdentity из источников
 * (параметры контейнера, PEM-файл, env).
 */
interface LoadGitIdentityConfigServiceInterface
{
    /**
     * @throws InvalidConfigurationException если конфигурация неполная/некорректна.
     */
    public function load(): GitIdentityConfigVo;
}
