# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
