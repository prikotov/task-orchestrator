# Контракты DDD-модуля GitIdentity

**Роль:** Архитектор Гэндальф  
**Дата:** 2026-06-20  
**Объект:** новый модуль `GitIdentity` для `task-orchestrator`  
**Задача:** дизайн контрактов для получения короткоживущего GitHub App installation token

---

## Классификация

- 🧩 сложность запроса: 8/10 — новый bounded context с внешним API GitHub, секретами, cache и CLI-точкой.
- 🗂️ уровень контекста: 9/10 — требования детальные; уточнение потребовалось только по фактическому namespace проекта.
- 🛡️ риск ошибки: 7/10 — высокие риски вокруг JWT clock-skew, секретов, cache races и GitHub API semantics.

## Допущения

1. `Common\Module\GitIdentity\...` в постановке — сокращение; фактический PSR-4 namespace проекта: `TaskOrchestrator\Common\Module\GitIdentity\...`.
2. `GitIdentity` — самостоятельный модуль `src/Module/GitIdentity`, без межмодульных зависимостей.
3. `AgentTokenCommand` живёт в Presentation-слое CLI: `apps/console/src/Module/GitIdentity/Command/AgentTokenCommand.php`.
4. Если `installation_id` отсутствует в cache, GitHub требует App JWT для `GET /repos/{owner}/{repo}/installation`; поэтому JWT может понадобиться до проверки token-cache.
5. По умолчанию installation token запрашивается scoped to repository через `repository_names: [repo]`, если `scope_to_repository=true`.

## A. Список контрактов

### Domain ValueObject

| Путь | Назначение |
|---|---|
| `src/Module/GitIdentity/Domain/ValueObject/AppIdVo.php` | Идентификатор GitHub App. |
| `src/Module/GitIdentity/Domain/ValueObject/PrivateKeyVo.php` | PEM private key с redaction в debug. |
| `src/Module/GitIdentity/Domain/ValueObject/RepoSlugVo.php` | Repository slug `owner/repo`. |
| `src/Module/GitIdentity/Domain/ValueObject/JwtTokenVo.php` | Подписанный App JWT, secret value. |
| `src/Module/GitIdentity/Domain/ValueObject/InstallationIdVo.php` | GitHub App installation id. |
| `src/Module/GitIdentity/Domain/ValueObject/InstallationTokenVo.php` | Installation token + expiry + installation id. |
| `src/Module/GitIdentity/Domain/ValueObject/GitIdentityConfigVo.php` | Нормализованная конфигурация GitIdentity. |

### Domain Exception

| Путь | Назначение |
|---|---|
| `src/Module/GitIdentity/Domain/Exception/GitIdentityException.php` | Базовое исключение модуля. |
| `src/Module/GitIdentity/Domain/Exception/InvalidConfigurationException.php` | Ошибка конфигурации/локальной подготовки. |
| `src/Module/GitIdentity/Domain/Exception/GitHubApiException.php` | Ошибка GitHub API без утечки secret values. |

### Domain Service Interfaces

| Путь | Назначение |
|---|---|
| `src/Module/GitIdentity/Domain/Service/LoadGitIdentityConfigServiceInterface.php` | Загрузить нормализованную конфигурацию. |
| `src/Module/GitIdentity/Domain/Service/TokenCacheInterface.php` | Cache installation id и installation token. |
| `src/Module/GitIdentity/Domain/Service/SignJwtTokenServiceInterface.php` | Подписать App JWT через private key. |
| `src/Module/GitIdentity/Domain/Service/ResolveInstallationIdServiceInterface.php` | Получить installation id для repository. |
| `src/Module/GitIdentity/Domain/Service/RequestInstallationTokenServiceInterface.php` | Получить installation access token. |
| `src/Module/GitIdentity/Domain/Service/ClockServiceInterface.php` | Детерминированный источник времени. |

### Application

| Путь | Назначение |
|---|---|
| `src/Module/GitIdentity/Application/UseCase/Command/ObtainToken/ObtainTokenCommand.php` | Вход UseCase: repository slug. |
| `src/Module/GitIdentity/Application/UseCase/Command/ObtainToken/ObtainTokenResultDto.php` | Выход UseCase: token, expiresAt, installationId. |
| `src/Module/GitIdentity/Application/UseCase/Command/ObtainToken/ObtainTokenCommandHandler.php` | Единственная точка оркестрации получения token. |

### Infrastructure

| Путь | Назначение |
|---|---|
| `src/Module/GitIdentity/Infrastructure/Service/LoadGitIdentityConfigService.php` | Читает параметры, PEM-файл, проверяет chmod `0600`. |
| `src/Module/GitIdentity/Infrastructure/Service/FilesystemTokenCacheService.php` | File-based cache с lock/atomic write. |
| `src/Module/GitIdentity/Infrastructure/Service/OpenSslSignJwtTokenService.php` | RS256 JWT signer через `ext-openssl`. |
| `src/Module/GitIdentity/Infrastructure/Service/GitHubResolveInstallationIdService.php` | `GET /repos/{owner}/{repo}/installation`. |
| `src/Module/GitIdentity/Infrastructure/Service/GitHubRequestInstallationTokenService.php` | `POST /app/installations/{id}/access_tokens`. |
| `src/Module/GitIdentity/Infrastructure/Service/SystemClockService.php` | Возвращает текущее время. |

### Presentation CLI

| Путь | Назначение |
|---|---|
| `apps/console/src/Module/GitIdentity/Command/AgentTokenCommand.php` | CLI `agent:token`, делегирует в `ObtainTokenCommandHandler`. |

## B/C. Сигнатуры и контракты

### `AppIdVo`

```php
final readonly class AppIdVo
{
    public function __construct(private int $value);
    public function getValue(): int;
    public function equals(self $other): bool;
}
```

- Input: positive integer App ID.
- Output: typed app id.
- Throws: `InvalidConfigurationException` if `<= 0`.

### `PrivateKeyVo`

```php
final readonly class PrivateKeyVo
{
    public function __construct(private string $content);
    public function getContent(): string;
    public function fingerprint(): string;
    public function __debugInfo(): array;
}
```

- Input: PEM content.
- Output: secret key for signing only via `getContent()`.
- Throws: `InvalidConfigurationException` if empty/not PEM-like.
- Security: no `__toString()`, debug returns `[redacted]`, exceptions never include content.

### `RepoSlugVo`

```php
final readonly class RepoSlugVo
{
    public function __construct(private string $owner, private string $repo);
    public static function fromString(string $slug): self;
    public function getOwner(): string;
    public function getRepo(): string;
    public function toString(): string;
    public function cacheKey(): string;
}
```

- Input: `owner/repo`.
- Output: normalized owner/repo.
- Throws: `InvalidConfigurationException` for invalid format.

### `JwtTokenVo`

```php
final readonly class JwtTokenVo
{
    public function __construct(private string $value, private DateTimeImmutable $expiresAt);
    public function getValue(): string;
    public function getExpiresAt(): DateTimeImmutable;
    public function isExpiredAt(DateTimeImmutable $now): bool;
    public function __debugInfo(): array;
}
```

- Input: signed JWT and expiry.
- Output: bearer value for GitHub App auth.
- Throws: `InvalidConfigurationException` if empty/expired at construction.
- Security: no `__toString()`, debug redacted.

### `InstallationIdVo`

```php
final readonly class InstallationIdVo
{
    public function __construct(private int $value);
    public function getValue(): int;
    public function cacheKey(): string;
    public function equals(self $other): bool;
}
```

- Input: positive GitHub installation id.
- Throws: `GitHubApiException` or `InvalidConfigurationException` if invalid source value.

### `InstallationTokenVo`

```php
final readonly class InstallationTokenVo
{
    public function __construct(
        private string $token,
        private DateTimeImmutable $expiresAt,
        private InstallationIdVo $installationId,
    );
    public function getToken(): string;
    public function getExpiresAt(): DateTimeImmutable;
    public function getInstallationId(): InstallationIdVo;
    public function isUsableAt(DateTimeImmutable $now, int $safetyMarginSeconds): bool;
    public function cacheTtlSeconds(DateTimeImmutable $now, int $safetyMarginSeconds): int;
    public function __debugInfo(): array;
}
```

- Input: GitHub response token/expires_at/installation id.
- Output: token value and TTL helper.
- Throws: `GitHubApiException` if empty or already expired.
- Security: no `__toString()`, debug redacted.

### `GitIdentityConfigVo`

```php
final readonly class GitIdentityConfigVo
{
    public function __construct(
        private AppIdVo $appId,
        private PrivateKeyVo $privateKey,
        private string $apiBaseUri,
        private string $githubApiVersion,
        private string $userAgent,
        private int $jwtTtlSeconds,
        private int $jwtClockSkewSeconds,
        private int $tokenExpirySafetyMarginSeconds,
        private ?int $installationIdCacheTtlSeconds,
        private bool $scopeToRepository,
        private int $requestTimeoutSeconds,
    );
    public function getAppId(): AppIdVo;
    public function getPrivateKey(): PrivateKeyVo;
    public function getApiBaseUri(): string;
    public function getGitHubApiVersion(): string;
    public function getUserAgent(): string;
    public function getJwtTtlSeconds(): int;
    public function getJwtClockSkewSeconds(): int;
    public function getTokenExpirySafetyMarginSeconds(): int;
    public function getInstallationIdCacheTtlSeconds(): ?int;
    public function shouldScopeToRepository(): bool;
    public function getRequestTimeoutSeconds(): int;
}
```

- Input: normalized config values.
- Throws: `InvalidConfigurationException` for invalid ranges/URIs.

### Exceptions

```php
class GitIdentityException extends RuntimeException {}
final class InvalidConfigurationException extends GitIdentityException {}
final class GitHubApiException extends GitIdentityException {}
```

- Contract: all public module failures are `GitIdentityException` descendants.
- Security: messages must never include PEM/JWT/token/raw Authorization header/raw response with token.

### `LoadGitIdentityConfigServiceInterface`

```php
interface LoadGitIdentityConfigServiceInterface
{
    public function load(): GitIdentityConfigVo;
}
```

- Throws: `InvalidConfigurationException`.

### `TokenCacheInterface`

```php
interface TokenCacheInterface
{
    public function readInstallationToken(InstallationIdVo $installationId): ?InstallationTokenVo;
    public function writeInstallationToken(InstallationTokenVo $token, int $ttlSeconds): void;
    public function invalidateInstallationToken(InstallationIdVo $installationId): void;

    public function readInstallationId(RepoSlugVo $repoSlug): ?InstallationIdVo;
    public function writeInstallationId(RepoSlugVo $repoSlug, InstallationIdVo $installationId, ?int $ttlSeconds): void;
    public function invalidateInstallationId(RepoSlugVo $repoSlug): void;
}
```

- Keying: token by installation id; installation id by `owner/repo` hash.
- TTL: token write receives `expires_at - now - 60s` equivalent from Application.
- Throws: `InvalidConfigurationException` for unwritable cache dir; `GitIdentityException` for runtime cache I/O failure.

### `SignJwtTokenServiceInterface`

```php
interface SignJwtTokenServiceInterface
{
    public function sign(GitIdentityConfigVo $config, DateTimeImmutable $now): JwtTokenVo;
}
```

- Claims: `iat = now - clock_skew`, `exp <= iat + jwt_ttl`, `iss = app_id`.
- Throws: `InvalidConfigurationException`.

### `ResolveInstallationIdServiceInterface`

```php
interface ResolveInstallationIdServiceInterface
{
    public function resolve(RepoSlugVo $repoSlug, JwtTokenVo $jwtToken, GitIdentityConfigVo $config): InstallationIdVo;
}
```

- External call: `GET {apiBaseUri}/repos/{owner}/{repo}/installation`.
- Throws: `GitHubApiException`.

### `RequestInstallationTokenServiceInterface`

```php
interface RequestInstallationTokenServiceInterface
{
    public function request(
        InstallationIdVo $installationId,
        JwtTokenVo $jwtToken,
        GitIdentityConfigVo $config,
        RepoSlugVo $repoSlug,
    ): InstallationTokenVo;
}
```

- External call: `POST {apiBaseUri}/app/installations/{installation_id}/access_tokens`.
- Throws: `GitHubApiException`.

### `ClockServiceInterface`

```php
interface ClockServiceInterface
{
    public function now(): DateTimeImmutable;
}
```

- Used for deterministic tests and expiry logic.

### `ObtainTokenCommand`

```php
final readonly class ObtainTokenCommand
{
    public function __construct(public string $repoSlug);
}
```

- Input: `owner/repo` only.

### `ObtainTokenResultDto`

```php
final readonly class ObtainTokenResultDto
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public int $installationId,
    );
}
```

- Output serialization field names: `token`, `expires_at`, `installation_id`.

### `ObtainTokenCommandHandler`

```php
final readonly class ObtainTokenCommandHandler
{
    public function __construct(
        private LoadGitIdentityConfigServiceInterface $configLoader,
        private TokenCacheInterface $cache,
        private SignJwtTokenServiceInterface $jwtSigner,
        private ResolveInstallationIdServiceInterface $installationResolver,
        private RequestInstallationTokenServiceInterface $tokenRequester,
        private ClockServiceInterface $clock,
    );

    public function __invoke(ObtainTokenCommand $command): ObtainTokenResultDto;
}
```

- Orchestration:
  1. load config;
  2. parse `RepoSlugVo`;
  3. resolve installation id from cache, or sign JWT + GitHub lookup + cache write;
  4. read token cache by installation id;
  5. if token usable with safety margin, return DTO;
  6. sign fresh JWT if needed;
  7. request token;
  8. write token cache with `expires_at - now - safety_margin`;
  9. return DTO.
- Throws: `GitIdentityException` descendants only.

### Infrastructure implementations

- `LoadGitIdentityConfigService::__construct(?string $appId, ?string $privateKeyPath, ?string $privateKey, string $apiBaseUri, string $githubApiVersion, string $userAgent, int $jwtTtlSeconds, int $jwtClockSkewSeconds, int $tokenExpirySafetyMarginSeconds, ?int $installationIdCacheTtlSeconds, bool $scopeToRepository, int $requestTimeoutSeconds)`; `load(): GitIdentityConfigVo`.
- `FilesystemTokenCacheService::__construct(string $cacheDir)`; implements `TokenCacheInterface`; uses `flock`, temp file + `rename`, `0600` files, `0700` dir.
- `OpenSslSignJwtTokenService::sign(GitIdentityConfigVo $config, DateTimeImmutable $now): JwtTokenVo`; uses `openssl_sign(..., OPENSSL_ALGO_SHA256)` and base64url.
- `GitHubResolveInstallationIdService::resolve(...)`; uses `file_get_contents` + stream context; maps non-2xx/network/json failures to `GitHubApiException`.
- `GitHubRequestInstallationTokenService::request(...)`; uses `file_get_contents` POST; maps failures to `GitHubApiException`.
- `SystemClockService::now(): DateTimeImmutable`.

### `AgentTokenCommand`

```php
#[AsCommand(name: 'agent:token', description: 'Получить GitHub App installation token для repository')]
final class AgentTokenCommand extends Command
{
    public function __construct(private readonly ObtainTokenCommandHandler $handler);
    protected function configure(): void;
    protected function execute(InputInterface $input, OutputInterface $output): int;
}
```

- Argument: `repo` required, format `owner/repo`.
- Option: `--format=plain|json|env`, default `plain`.
- Delegation: `($handler)(new ObtainTokenCommand($repo))`.
- Output:
  - `plain`: token only;
  - `json`: `token`, `expires_at`, `installation_id`;
  - `env`: `GITHUB_TOKEN=<shell-escaped-token>`.
- Errors: catch `GitIdentityException`, print sanitized message, return `Command::FAILURE`.

## D. Dependency graph

```text
AgentTokenCommand
  -> ObtainTokenCommandHandler

ObtainTokenCommandHandler
  -> LoadGitIdentityConfigServiceInterface
       -> LoadGitIdentityConfigService
  -> TokenCacheInterface
       -> FilesystemTokenCacheService
  -> SignJwtTokenServiceInterface
       -> OpenSslSignJwtTokenService
  -> ResolveInstallationIdServiceInterface
       -> GitHubResolveInstallationIdService
  -> RequestInstallationTokenServiceInterface
       -> GitHubRequestInstallationTokenService
  -> ClockServiceInterface
       -> SystemClockService
```

Infrastructure services may import Domain VO/exceptions required by method signatures, but must inject only Domain interfaces/config primitives; no Application/Presentation dependencies.

## E. DI registration

`config/services.yaml`:

```yaml
parameters:
  task_orchestrator.git_identity.app_id: null
  task_orchestrator.git_identity.private_key_path: null
  task_orchestrator.git_identity.private_key: null
  task_orchestrator.git_identity.api_base_uri: 'https://api.github.com'
  task_orchestrator.git_identity.github_api_version: '2026-03-10'
  task_orchestrator.git_identity.user_agent: 'task-orchestrator-git-identity'
  task_orchestrator.git_identity.cache_dir: '%task_orchestrator.base_path%/var/cache/task-orchestrator/git-identity'
  task_orchestrator.git_identity.jwt_ttl_seconds: 540
  task_orchestrator.git_identity.jwt_clock_skew_seconds: 60
  task_orchestrator.git_identity.token_expiry_safety_margin_seconds: 60
  task_orchestrator.git_identity.installation_id_cache_ttl_seconds: 86400
  task_orchestrator.git_identity.scope_to_repository: true
  task_orchestrator.git_identity.request_timeout_seconds: 30

services:
  TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\LoadGitIdentityConfigServiceInterface:
    alias: TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\LoadGitIdentityConfigService
  TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\TokenCacheInterface:
    alias: TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\FilesystemTokenCacheService
  TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\SignJwtTokenServiceInterface:
    alias: TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\OpenSslSignJwtTokenService
  TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\ResolveInstallationIdServiceInterface:
    alias: TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\GitHubResolveInstallationIdService
  TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\RequestInstallationTokenServiceInterface:
    alias: TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\GitHubRequestInstallationTokenService
  TaskOrchestrator\Common\Module\GitIdentity\Domain\Service\ClockServiceInterface:
    alias: TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\SystemClockService

  TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\LoadGitIdentityConfigService:
    arguments:
      $appId: '%task_orchestrator.git_identity.app_id%'
      $privateKeyPath: '%task_orchestrator.git_identity.private_key_path%'
      $privateKey: '%task_orchestrator.git_identity.private_key%'
      $apiBaseUri: '%task_orchestrator.git_identity.api_base_uri%'
      $githubApiVersion: '%task_orchestrator.git_identity.github_api_version%'
      $userAgent: '%task_orchestrator.git_identity.user_agent%'
      $jwtTtlSeconds: '%task_orchestrator.git_identity.jwt_ttl_seconds%'
      $jwtClockSkewSeconds: '%task_orchestrator.git_identity.jwt_clock_skew_seconds%'
      $tokenExpirySafetyMarginSeconds: '%task_orchestrator.git_identity.token_expiry_safety_margin_seconds%'
      $installationIdCacheTtlSeconds: '%task_orchestrator.git_identity.installation_id_cache_ttl_seconds%'
      $scopeToRepository: '%task_orchestrator.git_identity.scope_to_repository%'
      $requestTimeoutSeconds: '%task_orchestrator.git_identity.request_timeout_seconds%'

  TaskOrchestrator\Common\Module\GitIdentity\Infrastructure\Service\FilesystemTokenCacheService:
    arguments:
      $cacheDir: '%task_orchestrator.git_identity.cache_dir%'
```

`config/console_services.yaml`:

```yaml
services:
  TaskOrchestrator\Console\Module\GitIdentity\Command\:
    resource: '%task_orchestrator.package_dir%/apps/console/src/Module/GitIdentity/Command/*'
    public: true
    tags: ['console.command']
```

## F. Configuration.php

New section `task_orchestrator.git_identity`:

| Field | Required | Default | Notes |
|---|---:|---|---|
| `enabled` | no | `false` | If `true`, validate required credentials at container config level. |
| `app_id` | conditionally | `null` | Required when command/use case is used. |
| `private_key_path` | conditionally | `null` | Preferred; infra checks readable regular file and chmod `0600`. |
| `private_key` | conditionally | `null` | Alternative for env-provided PEM; no chmod check possible. |
| `api_base_uri` | no | `https://api.github.com` | Allow GitHub Enterprise. |
| `github_api_version` | no | `2026-03-10` | Overridable for GHES compatibility. |
| `user_agent` | no | `task-orchestrator-git-identity` | Required by GitHub best practice. |
| `cache_dir` | no | `%kernel.project_dir%/var/cache/task-orchestrator/git-identity` | In standalone entrypoint use `base_path`. |
| `jwt_ttl_seconds` | no | `540` | Must be `1..600`. |
| `jwt_clock_skew_seconds` | no | `60` | Backdate `iat`. |
| `token_expiry_safety_margin_seconds` | no | `60` | Cache TTL safety margin. |
| `installation_id_cache_ttl_seconds` | no | `86400` | `null` means no expiry. |
| `scope_to_repository` | no | `true` | Request token scoped to command repo. |
| `request_timeout_seconds` | no | `30` | Stream context timeout. |

## H. Test plan

### Unit

- VO validation: app id, repo slug, token expiry, redaction via `__debugInfo()`.
- `OpenSslSignJwtTokenService`: RS256 shape, `iat/exp/iss`, no secret in exception messages; use generated test key fixture.
- `ObtainTokenCommandHandler`: cache hit, token expired with 60s margin, installation id cache miss, GitHub API exceptions, config errors.
- `FilesystemTokenCacheService`: read/write/invalidate, TTL expiry, corrupt JSON, atomic write, no token in thrown messages.
- `LoadGitIdentityConfigService`: missing app id/key, both key sources, chmod not `0600`, unreadable file.

### Integration

- `AgentTokenCommand` with Symfony `CommandTester`: plain/json/env output, invalid repo slug, handler failure.
- Container wiring: all aliases resolve; command registered in `console.application`.
- Filesystem cache integration in temp dir: two command invocations reuse token cache.
- No real GitHub network: replace interfaces with fake services in test container.

## I. Risks / blind spots

1. Phar packaging: PEM path must stay external; reading from Phar breaks chmod semantics.
2. Clock skew: GitHub recommends backdating `iat`; NTP drift can still fail JWT auth.
3. Cache races: concurrent agents may request duplicate tokens; use `flock` around cache key.
4. Cache secrecy: token files must be `0600`, dir `0700`; avoid shared workspace leakage.
5. GitHub Enterprise: API base URI/version may differ; keep configurable.
6. Token scope: `repository_names` fails if installation repository selection/permissions do not include repo.
7. Branch protection: App approval flow depends on GitHub branch protection rules and App permissions, not only token identity.
8. Revocation/uninstall: cached installation id may become stale; invalidate on 401/404 or use finite TTL.
9. `file_get_contents`: HTTP errors/warnings need robust suppression + `$http_response_header` parsing.
10. Secrets in logs: CLI `--format=json/env` intentionally prints token; errors/debug must not.

## Sources

- GitHub: Generating a JSON Web Token for a GitHub App — https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/generating-a-json-web-token-jwt-for-a-github-app
- GitHub: Authenticating as a GitHub App installation — https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/authenticating-as-a-github-app-installation
- GitHub REST: Apps endpoints, repository installation — https://docs.github.com/en/rest/apps/apps?apiVersion=2026-03-10
- GitHub: Managing private keys for GitHub Apps — https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/managing-private-keys-for-github-apps
