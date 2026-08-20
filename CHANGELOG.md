# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-08-20

### Added

- Added locale-aware role resolution for `become-role`, including `APP_LOCALE` support (#318, #319, #320).

### Changed

- Standardized release history in `main`; future releases are prepared and tagged from this branch (#360).

### Fixed

- Hardened Pi/Codex runner lifecycle and timeouts; `run-subagent` now uses the consumer project's role profile in Composer installations (#352, #354, #357, #359).

## [0.2.3] - 2026-07-17

### Changed

- `become-role` skill catalog and `become-role/SKILL.md` now explain how to resolve in-skill relative paths (`scripts/`, `references/`) via `<location>` — prefix with the skill directory — so agents stop looking for skill scripts in the project root in host installations (#315).

## [0.2.2] - 2026-07-17

### Fixed

- Fix `become-role` skill script path for host (Composer) installations: use a path relative to the skill directory per the Agent Skills standard so the script resolves in any context; reference the standard as the source of truth in `SKILL-CREATION` (#313).

## [0.2.1] - 2026-07-16

### Fixed

- Report the exact package version from PHAR and Composer-installed binaries instead of the fallback `1.0.0.0`; v0.2.1 now reports `Task Orchestrator 0.2.1` (#310).

## [0.2.0] - 2026-07-15

### Added

- Add universal role skill discovery and the `become-role` workflow, including `agent:init` and `agent:role-skills` support for source and Composer installations (#289, #307).
- Add GitHub App identity support for AI agents through the `agent:token` command and cached installation tokens (#275).
- Add liveness-adaptive execution for Pi and Codex runners: active processes can continue working, while idle processes are stopped without waiting for the hard timeout (#297, #298, #308).

### Changed

- Require PHP >= 8.4.1 with `ext-openssl` and `ext-zlib`; production installation and PHAR release checks now verify the exact runtime contract (#306).
- Make module registration explicit and PHAR-safe across the package root, components, modules, and console application (#282).
- Stream Pi and Codex JSONL output instead of buffering complete process output, reducing memory use on long agent runs (#285).
- Strengthen release gates with Composer-host, PHAR, and Linux process-liveness smoke tests (#306, #307, #308).

### Fixed

- Preserve static-chain system prompts across the primary and retry execution paths, including resolution of `@system-prompt` and `@append-system-prompt` file markers (#299, #300, #304).
- Recognize Pi model errors instead of reporting them as empty output, and route Pi HTTPS traffic through the configured proxy bridge (#286, #290).
- Make runner liveness probing platform-safe without depending on `ps`, `pgrep`, or procps; unavailable probes now fall back to the hard timeout (#308).
- Keep active subagents alive while enforcing wall-clock and idle timeouts, and eliminate pipe backpressure in the watcher read loop (#273, #292, #295).
- Enable transient retries for dynamic-chain steps (#293).

### Known limitations

- The PHAR distribution does not install the `become-role` skill through `agent:init`; use the Composer distribution for that workflow until full PHAR support is implemented (#307).

## [0.1.24] - 2026-06-16

### Fixed

- Stop `--timeout`/`--max-time` CLI defaults from silently overriding `chain.timeout`/`chain.max_time`: the options no longer carry a default in `addOption()`, so an unset option resolves to `null` and the execution strategy applies precedence `explicit CLI → chain.* → hard default` (#267).
- Stop token burn on runaway subagents: `watch-subagent.sh` `soft-timeout` now kills the run by default instead of only warning; `run-subagent` skill gains per-run logging and post-mortem event archives (#266).
- Restore fail-fast guard for `fix_iterations` in the deprecated `ChainDefinitionVo` and sync the validator with the specification (#262, #263).

### Changed

- Eliminate PHPMD baseline suppressions (12 → 0) through specification + factory + mapper + owned-component redesign of `ChainDefinition` and `DynamicLoopExecution` (#261).
- Move domain `ChainDefinition` contracts out of the Integration path (#249).

## [0.1.23] - 2026-06-06

### Changed

- Move ChainExecution and DynamicLoop ChainDefinition provider contracts from `Domain/Service/Integration` to `Domain/Service/ChainDefinition`, keeping Integration-layer implementations and DI aliases explicit.

## [0.1.22] - 2026-06-05

### Fixed

- Add end-to-end Phar smoke coverage before tagging releases: `make phar-smoke` and `bin/phar-smoke` validate the built `task-orchestrator.phar`, and CI runs `test` plus `phar-smoke` for `main` and `release/**` branches.
- Harden `Release Phar` workflow to use `make phar-smoke`, fail on smoke errors, and publish release assets with `permissions: contents: write`.
- Fix Symfony service discovery for Phar builds by resolving services from `%task_orchestrator.package_dir%` and excluding `src/**/Resources/` from service discovery.

## [0.1.21] - 2026-06-05

### Fixed

- Fix `Release Phar` workflow to build Phar with `box.json.dist` (`box compile --config=box.json.dist`), so release assets include bundled configuration files such as `config/services.yaml`, `config/console_services.yaml`, and `config/chains.yaml`.

## [0.1.20] - 2026-06-05

### Fixed

- Fix `Release Phar` workflow path mismatch: use generated `bin/task-orchestrator.phar` for smoke test and GitHub Release asset upload.

## [0.1.19] - 2026-06-04

### Changed

- Update `prikotov/git-workflow` from `v0.1.0` to `v0.2.0` and raise Composer constraint from `^0.1.0` to `^0.2.0`.
- Keep other direct `prikotov/*` dependencies up to date.

## [0.1.18] - 2026-06-04

### Added

- Add `validate:connectivity` CLI command to check startup of roles configured in `chains.yaml`.
- Strengthen AI agent role-file validation and support role runner profiles for subagent delegation.

### Fixed

- Stabilize static chain metrics integration test.

### Security

- Update `prikotov/*` tooling dependencies and Symfony security patches; `composer audit` reports no advisories.

## [0.1.17] - 2026-05-25

### Changed

- AGENTS.md: added documentation consistency rule to mini-checklist

## [0.1.16] - 2026-05-25

### Fixed

- Add missing `agent:orchestrate` command name in brainstorm SKILL.md, cli.md, troubleshooting.md examples

## [0.1.15] - 2026-05-25

### Changed

- Rename CLI commands: `app:agent:*` → `agent:*` (drop redundant `app:` prefix)
- Use `php vendor/bin/task-orchestrator` in all documentation examples
- Fix binary name in README code examples (`TasK-orchestrator` → `task-orchestrator`)

## [0.1.14] - 2026-05-25

### Added

- Rename CLI commands: `app:agent:*` → `agent:*` (drop redundant `app:` prefix)

## [0.1.13] - 2026-05-25

### Fixed

- Fix console services path resolution in vendor context: use `task_orchestrator.package_dir` instead of `kernel.project_dir` for locating console commands and event subscribers

## [0.1.12] - 2026-05-23

### Changed

- Sync AGENTS.md working-with-code rules from TasK: conventions check, `make check` gate, simplified merge workflow

## [0.1.11] - 2026-05-23

### Changed

- Unified CLI command in docs: `bin/console app:agent:orchestrate` → `vendor/bin/task-orchestrator`

## [0.1.10] - 2026-05-23

### Changed

- Bump `prikotov/coding-standard` ^0.19.0 (was ^0.18.0)

## [0.1.9] - 2026-05-23

### Changed

- Bump `prikotov/coding-standard` ^0.18.0 (was ^0.17.1)
- Move Domain DTOs from `Domain/Dto/` to `Domain/Service/Audit/` (new PHPCS convention)

### Fixed

- Fix flaky test `staticChainAggregatedMetricsAreAccumulated` — `assertGreaterThan` → `assertGreaterThanOrEqual`

## [0.1.8] - 2026-05-23

### Changed

- Bump `prikotov/coding-standard` ^0.17.1 (was ^0.17.0)

## [0.1.7] - 2026-05-22

### Changed

- Bump `prikotov/coding-standard` ^0.17.0 (was ^0.16.0)

## [0.1.6] - 2026-05-22

### Changed

- Rename `InMemoryMetricsCollector` → `InMemoryMetricsCollectorService`, move to `Infrastructure/Service/Metrics/` (PHPCS convention)
- Require `prikotov/coding-standard` ^0.16.0 (was ^0.14.0)

## [0.1.5] - 2026-05-22

### Changed

- Move `prikotov/coding-standard` from `require` to `require-dev`

## [0.1.4] - 2026-05-22

### Added

- PHPStan 2.1 static analysis at level 8

### Changed

- Require `prikotov/coding-standard` ^0.14.0 (was ^0.13.0)

## [0.1.3] - 2026-05-19

## [0.1.2] - 2026-05-12

## [0.1.1] - 2026-04-28

## [0.1.0] - 2026-04-16

### Added

- Initial extraction from TasK monorepo as standalone library
- **Domain layer**: entities (`DynamicLoopExecution`, `StaticChainExecution`), 25 value objects, enums, domain services and interfaces
- **Application layer**: command/query handlers (`OrchestrateChain`, `RunAgent`, `GenerateReport`, `GetRunners`), events, mappers
- **Infrastructure layer**: `PiAgentRunner` with JSONL streaming, `YamlChainLoader`, `JsonlAuditLogger`, `QualityGateRunner`, `RolePromptBuilder`
- **Symfony Bundle** (`TaskOrchestratorBundle`) for auto-configuration in Symfony projects
- **Retry** decorator for agent runners with configurable policy (exponential backoff)
- **Circuit breaker** decorator for agent runners with state tracking (closed → open → half-open)
- **Dynamic loop orchestration** with facilitator-driven multi-agent rounds and budget control
- **Static chain orchestration** with fixed step sequences, fix iteration groups, and cross-model quality gates
- **Fallback runners** — configurable alternative commands when primary runner fails
- **Audit trail** — JSONL-based logging of chain execution steps and results
- **Budget control** — per-chain and per-step cost limits
- Own marker interfaces (`CommandInterface`, `QueryInterface`) — self-contained, no external framework dependencies
