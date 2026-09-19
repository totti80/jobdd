# AGENTS.md
## JobDD AI Coding Agent Instructions

This repository is the JobDD project.

Before making any implementation decision, read and follow the following documents in this priority order.

1. `docs/JobDD_Master.md`
2. `docs/JobDD_Decision_Log.md`
3. `docs/JobDD_AI_Coding_Rules.md`
4. Other documents under `docs/`
5. Existing implementation

If existing code, older documents, or comments conflict with `docs/JobDD_Master.md`, do not silently reconcile them.

Report the conflict first.

## Core product principle

JobDD is a Decision Support service.

AI must not make the final career decision for the user.

AI may:
- organize information
- normalize data
- compare options
- explain fit
- show evidence
- support decision making

The final decision belongs to the user.

## Current implementation principles

Follow the current JobDD architecture and decisions, including:

- Discovery-first / Evidence-first
- Raw → Normalized Fact → Decision View
- Do not assume missing information means negative information
- Preserve Evidence / Source transparency
- Do not infer private job counts for Agents
- Evaluate Agents based on consultation value, not public job count alone
- Prefer official / verifiable sources
- Do not bypass website restrictions or terms of service
- Do not expand scope without a clear validation purpose

## Coding rules

Always follow:

`docs/JobDD_AI_Coding_Rules.md`

In particular:

- define Scope
- define Do Not Change
- avoid unnecessary refactoring
- do not add dependencies without need
- do not perform irreversible operations without human approval
- verify diffs and tests
- report changed files and remaining risks

## Ambiguity rule

Ask only when ambiguity materially changes:
- product behavior
- DB schema
- API contracts
- Score / Fit logic
- Evidence semantics
- user decision flow

For minor implementation details, follow existing project patterns.

## Important

Do not treat old versioned documents as the source of truth if newer fixed-name documents exist.

Use:
- `docs/JobDD_Master.md`
- `docs/JobDD_Decision_Log.md`

as the current references.