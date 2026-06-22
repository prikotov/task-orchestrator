<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\Exception;

/**
 * Infrastructure-исключение транспортного слоя GitHub API.
 *
 * Выбрасывается HTTP-компонентом GitHub при сетевых ошибках, не-2xx HTTP-ответах
 * или повреждённом JSON. Живёт в слое Infrastructure (Component): по конвенции
 * exception.md «наружу выбрасываются только исключения своего слоя» — компонент
 * не должен кидать Domain-исключения.
 *
 * Граница с Domain проходит в Infrastructure-сервисах модуля: они перехватывают
 * это исключение и оборачивают его в доменное исключение модуля, сохраняя
 * HTTP-статус (чтобы Application-слой детектировал 404 детерминированно).
 *
 * Сообщения НЕ содержат секретов (JWT, токенов, Authorization-заголовков) —
 * компонент логирует только метод и URL.
 */
final class GitHubHttpException extends \RuntimeException
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
     * При оборачивании в доменное исключение статус переносится, поэтому
     * классификация 404 сохраняется на уровне сервисов и Application.
     */
    public function isNotFound(): bool
    {
        return $this->httpStatus === 404;
    }
}
