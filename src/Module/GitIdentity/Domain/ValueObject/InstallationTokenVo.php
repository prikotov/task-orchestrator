<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject;

use DateTimeImmutable;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;

/**
 * Value Object GitHub installation access token вместе с expiry и installation id.
 *
 * Хранит секретное значение токена. Класс сознательно не реализует
 * {@see __toString()}: случайное преобразование в строку раскрыло бы секрет.
 * Безопасность: {@see __debugInfo()} возвращает redacted значение.
 *
 * Чистый детерминированный VO: конструктор не читает системные часы. Пригодность
 * к использованию определяет Application через {@see isUsableAt()} (с safety
 * margin) — время передаётся вызывающей стороной (Psr\Clock\ClockInterface), что
 * делает VO предсказуемым и тестируемым с фиксированным временем.
 *
 * Хелперы TTL/usable выносят логику safety margin из Application, оставаясь
 * чистыми детерминированными операциями над значениями VO.
 */
final readonly class InstallationTokenVo
{
    public function __construct(
        private string $token,
        private DateTimeImmutable $expiresAt,
        private InstallationIdVo $installationId,
    ) {
        if ($token === '') {
            throw new GitHubApiException('Installation token must not be empty.');
        }
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getInstallationId(): InstallationIdVo
    {
        return $this->installationId;
    }

    /**
     * Пригоден ли токен к использованию в момент $now с учётом safety margin
     * (запас времени до фактического expiry, чтобы избежать race при близком
     * истечении).
     */
    public function isUsableAt(DateTimeImmutable $now, int $safetyMarginSeconds): bool
    {
        $safeExpiry = $this->expiresAt->modify(sprintf('-%d seconds', max(0, $safetyMarginSeconds)));

        return $now < $safeExpiry;
    }

    /**
     * Рекомендуемый TTL кеша в секундах от $now: сколько секунд токен ещё
     * «безопасно» валиден с учётом safety margin. Никогда не отрицательный.
     */
    public function cacheTtlSeconds(DateTimeImmutable $now, int $safetyMarginSeconds): int
    {
        $ttl = $this->expiresAt->getTimestamp() - $now->getTimestamp() - max(0, $safetyMarginSeconds);

        return max(0, $ttl);
    }

    /**
     * @return array<string, string|int>
     */
    public function __debugInfo(): array
    {
        return [
            'token' => '[redacted]',
            'expiresAt' => $this->expiresAt->format(DateTimeImmutable::ATOM),
            'installationId' => $this->installationId->getValue(),
        ];
    }
}
