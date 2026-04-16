# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
