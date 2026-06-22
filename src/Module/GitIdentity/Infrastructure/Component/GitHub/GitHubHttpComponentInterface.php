<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub;

use TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Component\GitHub\Exception\GitHubHttpException;

/**
 * HTTP-компонент для вызовов GitHub API.
 *
 * Инкапсулирует транспортную логику взаимодействия с GitHub (HTTP-запрос,
 * разбор статуса, декодирование JSON, обёртка исключений) по конвенции
 * {@link https://github.com/.../docs/conventions/core_patterns/external-service.md External Service}.
 * Поверхностные сервисы ({@see \TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\GitHubResolveInstallationIdService}
 * и {@see \TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\GitHubRequestInstallationTokenService})
 * делегируют выполнение запроса этому компоненту, формируя URL и парся результат.
 *
 * Безопасность: Bearer-значение (JWT/installation token) передаётся только в
 * HTTP-заголовок Authorization и никогда не попадает в логи или сообщения
 * исключений — компонент логирует только метод и URL.
 */
interface GitHubHttpComponentInterface
{
    /**
     * Выполняет HTTP-запрос к GitHub API и возвращает декодированный JSON-ответ.
     *
     * @param string $method HTTP-метод (GET/POST).
     * @param string $url Полный URL запроса.
     * @param string $bearer Bearer-значение для заголовка Authorization (НЕ логируется).
     * @param string|null $body Тело запроса для POST (JSON-строка) или null.
     *
     * @return array<string, mixed> Декодированный JSON-ответ.
     *
     * @throws GitHubHttpException при сетевой ошибке, не-2xx ответе или невалидном JSON.
     */
    public function request(string $method, string $url, string $bearer, ?string $body = null): array;
}
