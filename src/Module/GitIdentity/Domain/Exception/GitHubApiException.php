<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception;

/**
 * Ошибка взаимодействия с GitHub API.
 *
 * Выбрасывается при сетевых ошибках, не-2xx HTTP-ответах, повреждённом JSON
 * или неожиданной структуре ответа GitHub. Сообщения НЕ содержат JWT, токенов,
 * Authorization-заголовков и тел ответов с секретами — только технические
 * детали (HTTP-статус, метод, sanitized URL).
 *
 * При наличии HTTP-статуса (заполняется транспортным слоем через
 * {@see forHttpStatus()}) доступен детерминированный способ распознать
 * конкретную ошибку — {@see isNotFound()} — без парсинга текста сообщения.
 */
final class GitHubApiException extends GitIdentityException
{
    private ?int $httpStatus = null;

    /**
     * Создаёт исключение для не-2xx HTTP-ответа, сохраняя статус для
     * детерминированной классификации (например, 404 = installation удалена).
     *
     * @param int $httpStatus Фактический HTTP-статус ответа GitHub (>= 400).
     * @param string $message Сообщение без секретов (HTTP-статус, метод, sanitized URL).
     */
    public static function forHttpStatus(int $httpStatus, string $message, ?\Throwable $previous = null): self
    {
        $instance = new self($message, 0, $previous);
        $instance->httpStatus = $httpStatus;

        return $instance;
    }

    /**
     * Возвращает HTTP-статус ответа GitHub или null, если исключение не связано
     * с конкретным статусом (сетевая ошибка, повреждённый JSON и т.п.).
     */
    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Является ли ошибка 404 Not Found.
     *
     * Используется Application для различения сценария «installation удалена/
     * переустановлена» (требует инвалидации устаревшего кеша installation_id
     * и повторного resolve→request) от прочих неустранимых ошибок API.
     */
    public function isNotFound(): bool
    {
        return $this->httpStatus === 404;
    }
}
