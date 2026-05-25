[![Read in Russian](https://img.shields.io/badge/Lang-Русский-blue)](README.md)
[![Read in 繁體中文](https://img.shields.io/badge/Lang-繁體中文-blue)](README.zh.md)

# TasK-orchestrator

### CLI Agent Orchestrator

Prolonged and varied work with an AI agent in a single long session overloads its context. The agent simultaneously holds process instructions, details of every step, and your casual slip-ups. The longer the session, the more noise and the worse the result. Context degrades and response quality declines.

On the other hand, you juggle several tasks in parallel: switch between agents, manually launch each next step, lose context by forgetting what you asked minutes ago, and spend time restoring it. Cognitive load increases, you grow tired, and your work quality drops too.

Moreover, different models excel at different things: Gemini produces quality text, GPT models follow instructions more precisely and work with code, Opus solves complex tasks with deep analysis, and new models from China are actively catching up. A model is only part of the stack: it operates inside a harness — a control shell like Codex, Claude Code, or pi — with a specific set of tools and its own approach to context. Codex works well with OpenAI models, Claude Code with Anthropic, and OpenCode, Kilocode, and pi support multiple providers. For each piece of work, you want to pick the right combination of console agent, model, role, and skills — but configuring, switching manually, and copying context between windows is tedious.

TasK-orchestrator solves these problems by simulating the work of a real team, where each participant has their own role, their own skills, and their own context, and tasks are passed as assignments — after all, people don't share a single brain. Four tools are provided for this:

**Roles** — a Markdown file describing the agent's personality: behavioral profile (DISC, Big Five), expertise, working style, strengths and weaknesses. Skills are attached to a role. The model receives the file as a system instruction and acts within that role.

**YAML config** — for processes where the step order is known in advance. You describe roles, attach a console agent, model, and launch parameters to each, arrange steps and branching conditions. The orchestrator walks through the chain without deviations.

**Skills** — abilities that a role possesses. A skill is described by a Markdown file with step-by-step instructions and auxiliary scripts. The role itself selects the appropriate skill and applies it as needed.

**Subagents** — a way to launch a role as an agent in a clean context. The agent receives the role, assignment, and other context as text and files. No chat history or artifacts from previous sessions.

Orchestration operates in two modes. In the first, the process is defined by a YAML config and executed by the orchestrator, sequentially launching agents for each step. In the second, the process is driven by an agent: the team lead receives a skill as an instruction and independently determines which subagent to launch, when to send work back for revision, and when to escalate a problem.

---

## Roles

A role is a Markdown file that the model receives as a system instruction. The front matter describes the personality through several behavioral models (DISC, Big Five, Adizes, Belbin, Jungian archetypes), expertise, and attached skills. The body of the file elaborates on the personality defined in the front matter: role description, personal traits, working style, principles, and behavioral rules.

A single specialization can have multiple roles with different behavioral profiles. Two architects given the same assignment but different profiles will produce different solutions — one strictly follows standards, the other looks for blind spots and alternatives. Two backend developers: one writes clean code by all the rules, the other values speed and simplicity. Such a clash of perspectives is a deliberate choice, as it brings the same benefits as in a real team. It is especially important where conflict of interest is needed: during code review, the reviewer examines the solution from a different angle — their priorities are simplicity and performance, not code cleanliness; during a brainstorm, participants with different profiles generate more ideas than a single agent that tends to agree with itself and others. The classic orchestration systems we studied don't work with behavioral profiles: they pay no attention to role "personalities."

Users create new roles as Markdown files — with a unique personality, expertise, and set of skills. Guidelines for describing roles are in [ROLE-CREATION.md](docs/agents/roles/ROLE-CREATION.md).

Role examples: [`docs/agents/roles/team/`](docs/agents/roles/team/).

---

## YAML Config

A YAML file defines a deterministic process: you describe roles, attach an AI agent, model, and launch parameters to each, arrange steps and branching conditions. The orchestrator is a PHP engine that traverses the chain step by step. Behavior is determined by the config: which steps, in what order, with what conditions.

Two chain types are supported. `static` — a fixed sequence of steps where the order is known in advance: analyst parses requirements → backend developer implements → reviewer checks. `dynamic` — the leader decides who acts and when: manages the process, assigns executors, sends work back for revision.

Chains support: retry with delay on failures, circuit breaker (temporary blocking on repeated errors), fallback executor, checks via shell commands (quality gates), limit constraints, branching via `when:`, logging of every step to JSONL.

Example — a brainstorm where four roles discuss an architectural decision:

```yaml
# config/chains.yaml
roles:
  team_lead_alex:
    prompt_file: docs/agents/roles/team/team_lead_alex.ru.md
    command: [pi, --mode, json, -p, --no-session, --provider, zai, --model, glm-5-turbo, --system-prompt, "@system-prompt"]

  system_architect_gandalf:
    prompt_file: docs/agents/roles/team/system_architect_gandalf.ru.md
    command: [codex, exec, --dangerously-bypass-approvals-and-sandbox, --json, --model, gpt-5.5]

  system_architect_loki:
    prompt_file: docs/agents/roles/team/system_architect_loki.ru.md
    command: [codex, exec, --dangerously-bypass-approvals-and-sandbox, --json, --model, gpt-5.5]

  backend_developer_tony:
    prompt_file: docs/agents/roles/team/backend_developer_tony.ru.md
    command: [pi, --mode, json, -p, --no-session, --provider, zai, --model, glm-5-turbo, --system-prompt, "@system-prompt"]

chains:
  brainstorm:
    type: dynamic
    description: "Facilitated brainstorm with constructive conflict"
    timeout: 600
    facilitator: team_lead_alex
    participants: [system_architect_gandalf, system_architect_loki, backend_developer_tony]
    max_rounds: 20
    max_time: 3600
    prompts:
      brainstorm_system: prompts/brainstorm/brainstorm_system.txt
      facilitator_append: prompts/brainstorm/facilitator_append.txt
      facilitator_start: prompts/brainstorm/facilitator_start.txt
      facilitator_continue: prompts/brainstorm/facilitator_continue.txt
      facilitator_finalize: prompts/brainstorm/facilitator_finalize.txt
      participant_append: prompts/brainstorm/participant_append.txt
      participant_user: prompts/brainstorm/participant_user.txt
```

```bash
task-orchestrator agent:orchestrate \
  "Which modules should be extracted from AgentRunner?" \
  --chain=brainstorm
```

Details: [chains documentation](docs/guide/chains.md), [reliability](docs/guide/reliability.md). Example config: [`config/chains.yaml`](config/chains.yaml).

---

## Skills

A skill is an ability that a role possesses. It is described by a Markdown file (`SKILL.md`) with step-by-step instructions and auxiliary scripts. The role itself selects and loads the appropriate skill as needed.

This enables orchestration through a skill: the role receives an instruction and decides how to act — which subagent to launch, when to send work back for revision, when to escalate a problem. For example, the team lead drives an epic from setup to merge via [`epic-via-subagents`](docs/agents/skills/epic-via-subagents/SKILL.md), and an individual task via [`task-via-subagents`](docs/agents/skills/task-via-subagents/SKILL.md). This approach is more flexible than a YAML chain but less reliable: the agent may forget a step, deviate from the instruction, or choose a suboptimal path. Therefore, for processes that can be described via YAML, it is better to use a YAML config. Skills are for agent-driven orchestration or as wrappers over YAML chains: for example, the [`brainstorm`](docs/agents/skills/brainstorm/SKILL.md) skill describes how to conduct a brainstorm, while the brainstorm itself is implemented as a YAML chain.

A skill is attached to a role in the front matter. The role receives only the skills it needs for its work.

Available skills:

| Skill | Description |
|---|---|
| [`run-subagent`](docs/agents/skills/run-subagent/SKILL.md) | Launches a subordinate agent: role + assignment + context. Timeout control, stall detection, output filtering |
| [`task-via-subagents`](docs/agents/skills/task-via-subagents/SKILL.md) | Drives a task from setup to merge: implementation → self-review → code review → revision → PR |
| [`epic-via-subagents`](docs/agents/skills/epic-via-subagents/SKILL.md) | Drives an epic of multiple tasks via subagents |
| [`brainstorm`](docs/agents/skills/brainstorm/SKILL.md) | Brainstorm: facilitator leads the discussion, participants debate, the result is a decision protocol |
| [`retrospective`](docs/agents/skills/retrospective/SKILL.md) | Retrospective after an epic: process quality analysis, improvement proposals |
| [`agent-report`](docs/agents/skills/agent-report/SKILL.md) | Saves agent report to a file for traceability |

Users can create new skills as directories with `SKILL.md` and scripts — following the pattern of existing ones. Guidelines: [SKILL-CREATION.md](docs/agents/skills/SKILL-CREATION.md). Examples: [`docs/agents/skills/`](docs/agents/skills/).

---

## Subagents

A subagent is a way to launch a role as a separate agent in a clean context. The agent receives the role, assignment, and context as text and files. No chat history — each launch starts from scratch, as if you were handing an assignment to another team member.

This solves the context degradation problem: instead of one long session where the agent accumulates noise, the task is split into assignments. Each subagent does its part and returns the result. The team lead or another designated role coordinates the results without loading the executor's context with details of previous steps and without overloading its own context with unnecessary implementation details.

Launching via the [`run-subagent`](docs/agents/skills/run-subagent/SKILL.md) skill.

---

## Comparison with Other Solutions

The AI-roles team studied 25+ AI agent orchestration frameworks — LangGraph, CrewAI, Archon, and others. Full comparison: [framework research](docs/research/agent-frameworks-summary.md) and [coding agents comparison](docs/research/coding-agents-summary.md).

Strengths of TasK-orchestrator compared to the solutions studied:

- **Process control**: deterministic step chains via YAML, retry on failures, circuit breaker, limit constraints
- **Role behavioral profiles**: deliberately opposing roles with different personalities within the same specialization
- **Delegation into clean context**: subagents without chat history, coordination through a designated role
- **Skills as instructions**: text-based processes that the agent drives itself, or wrappers over YAML chains
- **Quality checks**: linters and tests right inside the step chain

Development roadmap — see [ROADMAP](docs/releases/ROADMAP-2026-Q2-Q3.md).

---

## Quick Start

TasK-orchestrator is a CLI tool. Minimum requirements: PHP >= 8.4 and a CLI agent (e.g., [pi CLI](https://github.com/prikotov/pi) or [Codex CLI](https://github.com/openi/codex)).

Installation:

```bash
composer require prikotov/task-orchestrator
```

Minimal `config/chains.yaml` — two roles and a two-step chain:

```yaml
roles:
  analyst:
    prompt_file: prompts/analyst.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

  developer:
    prompt_file: prompts/developer.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

chains:
  implement:
    steps:
      - { type: agent, role: analyst, name: analyze }
      - { type: agent, role: developer, name: implement }
      - { type: quality_gate, command: "vendor/bin/phpunit", label: "Tests", timeout_seconds: 120 }
```

Launch:

```bash
task-orchestrator agent:orchestrate "Add user registration endpoint"
```

How to configure executors, choose YAML options, and connect to a project — see the [documentation](docs/guide/chains.md).

---

## Documentation

| Document | Description |
|---|---|
| [Architecture](docs/guide/architecture.md) | DDD layers, modules, CQRS |
| [Chains](docs/guide/chains.md) | Static / Dynamic / Conditional, YAML DSL |
| [Roles](docs/guide/roles.md) | Role configuration, prompt files, runners |
| [Reliability](docs/guide/reliability.md) | Retry, Circuit Breaker, Fallback, Sessions/Resume |
| [Observability](docs/guide/observability.md) | Audit trail, hooks, metrics |
| [Hooks](docs/guide/hooks.md) | Post-step hooks |
| [Extension](docs/guide/extension.md) | Adding runners and strategies |
| [Conventions](docs/conventions/index.md) | DDD patterns, layers, code style |
| [Research of 25+ frameworks](docs/research/agent-frameworks-summary.md) | LangGraph, CrewAI, Archon and 22 others |

---

## License

[MIT](LICENSE) · Copyright © 2025–2026 prikotov
