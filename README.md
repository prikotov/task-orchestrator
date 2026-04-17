# TasK Orchestrator

[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4.svg)](https://php.net)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-10.5-green.svg)](https://phpunit.de)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

> Chain-based AI agent orchestration with retry, circuit breaker, quality gates and dynamic loops.

TasK Orchestrator is a PHP library for orchestrating AI agents into deterministic and dynamic workflows. Define agent chains in YAML, run them with built-in resilience patterns (retry, circuit breaker, fallback), and get full audit trails of every execution.

Built with **DDD principles**, minimal dependencies in the domain layer (only `psr/log`), and shipped as a **Symfony Bundle** for easy integration.

---

## Overview

### Key features

| Feature | Description |
|---|---|
| **Static chains** | Fixed sequence of agent steps with retry groups, fix iterations, and quality gates |
| **Dynamic loops** | Facilitator-driven multi-agent discussions with budget control and round tracking |
| **Retry** | Exponential backoff with configurable policy (max retries, delays, multiplier) |
| **Circuit breaker** | Stateful runner protection (closed → open → half-open) with configurable thresholds |
| **Fallback runners** | Alternative commands when the primary agent runner is unavailable |
| **Quality gates** | Cross-model validation — one agent verifies another agent's output |
| **Budget control** | Per-chain and per-step cost limits |
| **Audit trail** | JSONL-based logging of every chain step, turn, and result |
| **Prompt management** | Role-based prompts loaded from `.md` files with variable substitution |

### Architecture

```
┌─────────────────────────────────────────────────┐
│                  Application                     │
│  Use Cases: OrchestrateChain, RunAgent,          │
│  GenerateReport, GetRunners                      │
│  Events: RoundCompleted, SessionCompleted         │
├─────────────────────────────────────────────────┤
│                    Domain                        │
│  Entities, Value Objects, Enums,                 │
│  Service Interfaces (ports)                      │
├─────────────────────────────────────────────────┤
│                Infrastructure                    │
│  PiAgentRunner, YamlChainLoader,                 │
│  Audit Logger, QualityGateRunner, etc.           │
│  Symfony Bundle + DependencyInjection             │
└─────────────────────────────────────────────────┘
```

- **Domain** — pure business logic. Only external dependency: `psr/log` for observability. Entities, value objects, enums, service interfaces.
- **Application** — use case handlers, DTOs, events, mappers. Depends only on Domain.
- **Infrastructure** — concrete implementations (agent runners, chain loaders, loggers). Symfony Bundle for DI.

---

## Installation

### Requirements

- PHP >= 8.4
- Symfony >= 7.3 (for Bundle integration)

### Install via Composer

```bash
composer require prikotov/task-orchestrator
```

### Register the Bundle

Add to your `config/bundles.php`:

```php
return [
    // ...
    TaskOrchestrator\Infrastructure\Symfony\TaskOrchestratorBundle::class => ['all' => true],
];
```

---

## Configuration

### Bundle parameters

Create `config/packages/task_orchestrator.yaml` in your Symfony project:

```yaml
task_orchestrator:
    # Path to role prompt .md files
    roles_dir: '%kernel.project_dir%/docs/agents/roles/team'

    # Path to chains YAML configuration
    chains_yaml: '%kernel.project_dir%/apps/console/config/agent_chains.yaml'

    # Path to JSONL audit log file
    audit_log_path: '%kernel.project_dir%/var/log/agent_audit.jsonl'

    # Path to chains session directory (runtime state)
    chains_session_dir: '%kernel.project_dir%/var/agent/chains'

    # Project root for path relativization in logs
    base_path: '%kernel.project_dir%'
```

All five parameters are **required**.

### Chains YAML

Define your agent roles and chains in a YAML file:

```yaml
roles:
  analyst:
    prompt_file: prompts/analyst.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --model
      - gpt-4o
      - --system-prompt
      - "@system-prompt"   # resolved to the path of prompt_file

  developer:
    prompt_file: prompts/developer.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --model
      - gpt-4o
      - --system-prompt
      - "@system-prompt"   # resolved to the path of prompt_file

  reviewer:
    prompt_file: prompts/reviewer.md
    command:
      - pi
      - --mode
      - json
      - -p
      - --no-session
      - --model
      - gpt-4o
      - --system-prompt
      - "@system-prompt"   # resolved to the path of prompt_file

chains:
  implement:
    description: "Analyze → Implement → Review cycle"
    retry_policy:
      max_retries: 3
      initial_delay_ms: 1000
      max_delay_ms: 30000
      multiplier: 2.0
    steps:
      - type: agent
        role: analyst
        name: analyze
      - type: agent
        role: developer
        name: implement
      - type: quality_gate            # Shell command validation (pass/fail)
        role: reviewer
        name: review
    fix_iterations:
      - group: dev-review
        steps: [implement, review]
        max_iterations: 3

  # Dynamic loop — facilitator picks who speaks each round
  brainstorm:
    type: dynamic
    description: "Facilitated brainstorm with dynamic routing"
    facilitator: analyst
    participants: [developer, reviewer]
    max_rounds: 10
    prompts:
      facilitator_append: prompts/facilitator_append.txt
      participant_append: prompts/participant_append.txt
```

#### Step types

| Type | YAML value | Description |
|---|---|---|
| **Agent** | `agent` | AI agent execution in a specific role |
| **Quality gate** | `quality_gate` | Shell command validation (pass/fail) |

#### Chain types

| Type | Key | Description |
|---|---|---|
| **Static chain** | `steps` | Fixed sequence of agent steps, executed in order |
| **Dynamic loop** | `dynamic` | Facilitator selects which agent speaks in each round |

#### Resilience options

```yaml
# Retry policy — per-chain or per-step
retry_policy:
    max_retries: 3
    initial_delay_ms: 1000
    max_delay_ms: 30000
    multiplier: 2.0

# Budget — cost limits
budget:
    max_cost_total: 5.0          # Max $5 for the entire chain
    max_cost_per_step: 1.5       # Max $1.50 per step

# Fallback runner — alternative command when primary fails
roles:
  developer:
    prompt_file: prompts/developer.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]
    fallback:
      command:
        - codex
        - --model
        - gpt-4o
        - --full-auto
        - --system-prompt
        - "@system-prompt"   # resolved to the path of prompt_file
```

---

## Usage

All handlers are registered as services by the Bundle. Inject them via Symfony DI:

```php
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\OrchestrateChain\OrchestrateChainCommandHandler;

// $handler is injected via Symfony DI (autowired)
$command = new OrchestrateChainCommand(
    chainName: 'implement',
    task: 'Create a REST API endpoint for user registration',
);

$result = $handler->__invoke($command);

echo $result->totalCost;        // Total cost in USD
foreach ($result->stepResults as $step) {
    echo $step->role;             // Agent role name
    echo $step->outputText;       // Agent output
}
```

### Run a single agent

```php
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\RunAgent\RunAgentCommand;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Command\RunAgent\RunAgentCommandHandler;

$command = new RunAgentCommand(
    role: 'analyst',
    task: 'Analyze this codebase for performance issues',
);

$result = $handler->__invoke($command);

echo $result->outputText;
echo $result->cost;
```

### Generate a report

```php
use TaskOrchestrator\Common\Module\Orchestrator\Application\Enum\ReportFormatEnum;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportQuery;
use TaskOrchestrator\Common\Module\Orchestrator\Application\UseCase\Query\GenerateReport\GenerateReportQueryHandler;

$query = new GenerateReportQuery(
    result: $chainResult,              // OrchestrateChainResultDto from chain execution
    chainName: 'implement',
    task: 'Create a REST API endpoint',
    format: ReportFormatEnum::text,    // ReportFormatEnum::text | ReportFormatEnum::json
);

$result = $handler->__invoke($query);
echo $result->content;
```

---

## Architecture

### Layers and dependency direction

```
Presentation (your app)
    │
    ▼
Application (Use Cases, DTOs, Events)
    │
    ▼
Domain (Entities, VOs, Interfaces)
    ▲
    │
Infrastructure (Implementations, Bundle)
```

- **Domain** has minimal external dependencies — only `psr/log` (`LoggerInterface`) for observability. No framework imports.
- **Application** depends on Domain only.
- **Infrastructure** implements Domain interfaces and provides the Symfony Bundle.
- **Presentation** (your CLI, controllers, etc.) calls Application use cases.

### Key interfaces (ports)

| Interface | Layer | Purpose |
|---|---|---|
| `AgentRunnerInterface` | Domain | Run an AI agent and return results |
| `ChainLoaderInterface` | Domain | Load chain definitions from storage |
| `PromptProviderInterface` | Domain | Provide role prompts from `.md` files |
| `AuditLoggerInterface` | Domain | Log chain execution steps |
| `QualityGateRunnerInterface` | Domain | Validate step output via cross-model check |
| `FacilitatorResponseParserInterface` | Domain | Parse facilitator decisions in dynamic loops |
| `CheckDynamicBudgetServiceInterface` | Domain | Check budget constraints for dynamic loops |

### Events

| Event | When |
|---|---|
| `OrchestrateRoundCompletedEvent` | After each dynamic loop round |
| `OrchestrateSessionCompletedEvent` | After chain session finishes |

Subscribe to these events in your Symfony project for custom notifications or logging.

---

## Contributing

This library is part of the [TasK](https://github.com/prikotov/TasK) project. Contributions are welcome via pull requests.

## License

[MIT](https://opensource.org/licenses/MIT)
