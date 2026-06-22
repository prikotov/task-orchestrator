<?php

declare(strict_types=1);

namespace TaskOrchestrator\Common\Module\GitIdentity\Application\UseCase\Command\ObtainToken;

use TaskOrchestrator\Common\Module\GitIdentity\Application\Exception\ObtainTokenFailedException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitHubApiException;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Exception\GitIdentityException;
use Psr\Clock\ClockInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\LoadGitIdentityConfigServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\RequestInstallationTokenServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\ResolveInstallationIdServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\SignJwtTokenServiceInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\TokenCacheInterface;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\GitIdentityConfigVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationIdVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\InstallationTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\JwtTokenVo;
use TaskOrchestrator\Common\Module\GitIdentity\Domain\ValueObject\RepoSlugVo;

/**
 * Единственная точка оркестрации получения GitHub App installation token.
 *
 * Контракт (раздел B):
 *   1. load config;
 *   2. parse RepoSlugVo;
 *   3. resolve installation id from cache, or sign JWT + GitHub lookup + cache write;
 *   4. read token cache by installation id;
 *   5. if token usable with safety margin, return DTO;
 *   6. sign fresh JWT if needed;
 *   7. request token;
 *   8. write token cache with expires_at - now - safety_margin;
 *   9. return DTO.
 *
 * Сеть используется ТОЛЬКО при cache-miss: сначала проверяется кеш
 * installation_id (без сети), затем кеш токена (без сети), и только при
 * промахе подписывается JWT и выполняются запросы к GitHub.
 *
 * Self-healing кеша installation_id: если на шаге 7 request() падает с 404
 * (installation удалена/переустановлена), а installation_id был взят из кеша,
 * устаревшая запись инвалидируется, installation_id перевычисляется (resolve) и
 * request повторяется ровно один раз. Если installation_id получен свежим
 * resolve'ом на шаге 3 (cache-miss), повтор resolve при 404 лишён смысла.
 *
 * Boundary-контракт (исключения): наружу выбрасываются только
 * {@see ObtainTokenFailedException} (Application-слой). Любое Domain-исключение
 * ({@see GitIdentityException}) оборачивается, чтобы Presentation не зависел от
 * Domain (конвенция исключений: «наружу — только исключения слоя»).
 */
final readonly class ObtainTokenCommandHandler
{
    public function __construct(
        private LoadGitIdentityConfigServiceInterface $configLoader,
        private TokenCacheInterface $cache,
        private SignJwtTokenServiceInterface $jwtSigner,
        private ResolveInstallationIdServiceInterface $installationResolver,
        private RequestInstallationTokenServiceInterface $tokenRequester,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ObtainTokenFailedException при любой ошибке получения токена.
     */
    public function __invoke(ObtainTokenCommand $command): ObtainTokenResultDto
    {
        try {
            return $this->handle($command);
        } catch (GitIdentityException $e) {
            // Boundary: оборачиваем Domain-исключение в Application-исключение.
            // Сообщение сохраняется (контракт C: без секретов), полный trace — в previous.
            throw new ObtainTokenFailedException($e->getMessage(), 0, $e);
        }
    }

    private function handle(ObtainTokenCommand $command): ObtainTokenResultDto
    {
        $config = $this->configLoader->load();
        $repoSlug = RepoSlugVo::fromString($command->repoSlug);
        $safetyMargin = $config->getTokenExpirySafetyMarginSeconds();
        $installationIdCacheTtl = $config->getInstallationIdCacheTtlSeconds();

        // 3. installation id из кеша (без сети), либо resolve через JWT + GitHub.
        $jwt = null;
        $installationIdFromCache = false;
        $installationId = $this->cache->readInstallationId($repoSlug);
        if ($installationId === null) {
            $jwt = $this->jwtSigner->sign($config, $this->clock->now());
            $installationId = $this->installationResolver->resolve($repoSlug, $jwt, $config);
            $this->cache->writeInstallationId($repoSlug, $installationId, $installationIdCacheTtl);
        } else {
            $installationIdFromCache = true;
        }

        // 4-5. token cache (без сети); если пригоден с safety margin — сразу DTO.
        $cachedToken = $this->cache->readInstallationToken($installationId);
        if ($cachedToken !== null && $cachedToken->isUsableAt($this->clock->now(), $safetyMargin)) {
            return $this->toDto($cachedToken);
        }

        // 6. подписать свежий JWT, если ещё не подписан для resolve.
        if ($jwt === null) {
            $jwt = $this->jwtSigner->sign($config, $this->clock->now());
        }

        // 7. запросить токен. При 404 и cache-hit installation_id — self-healing:
        //    устаревший installation_id инвалидируется, resolve/request повторяются 1 раз.
        try {
            $token = $this->tokenRequester->request($installationId, $jwt, $config, $repoSlug);
        } catch (GitHubApiException $e) {
            if (!$installationIdFromCache || !$e->isNotFound()) {
                throw $e;
            }

            $installationId = $this->reinstallAndRetry($repoSlug, $jwt, $config, $installationIdCacheTtl);
            $token = $this->tokenRequester->request($installationId, $jwt, $config, $repoSlug);
        }

        // 8. записать в кеш с TTL = expires_at - now - safety_margin.
        $this->cache->writeInstallationToken($token, $token->cacheTtlSeconds($this->clock->now(), $safetyMargin));

        // 9. DTO.
        return $this->toDto($token);
    }

    /**
     * Self-healing: инвалидация устаревшего installation_id и повторный resolve.
     *
     * Возвращает актуальный installation_id после переустановки App.
     */
    private function reinstallAndRetry(
        RepoSlugVo $repoSlug,
        JwtTokenVo $jwt,
        GitIdentityConfigVo $config,
        ?int $installationIdCacheTtl,
    ): InstallationIdVo {
        $this->cache->invalidateInstallationId($repoSlug);
        $installationId = $this->installationResolver->resolve($repoSlug, $jwt, $config);
        $this->cache->writeInstallationId($repoSlug, $installationId, $installationIdCacheTtl);

        return $installationId;
    }

    private function toDto(InstallationTokenVo $token): ObtainTokenResultDto
    {
        return new ObtainTokenResultDto(
            token: $token->getToken(),
            expiresAt: $token->getExpiresAt(),
            installationId: $token->getInstallationId()->getValue(),
        );
    }
}
