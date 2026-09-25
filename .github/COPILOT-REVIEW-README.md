# Laravel + GitHub Copilot Code Review

## Components

### Repository instructions

.github/copilot-instructions.md

Common rules for Copilot.

### Specialist agents

.github/agents/

Contains specialized reviewers:

- Laravel
- Clean Code
- Security
- Architecture
- Database
- Performance
- Testing
- API
- Frontend
- Concurrency
- Dependencies
- DevOps
- Observability
- Business Logic
- Maintainability
- Devil's Advocate

### Orchestrator

.github/agents/review-orchestrator.agent.md

Runs the complete audit.

### Prompts

.github/prompts/full-review.prompt.md

Runs the complete audit workflow.

.github/prompts/fix-review.prompt.md

Fixes selected findings only.

---

# Recommended Workflow

1. Develop feature.
2. Write tests.
3. Run local tests.
4. Run Review Orchestrator.
5. Inspect CODE-REVIEW.md.
6. Select findings.
7. Start a new Copilot Agent session.
8. Use fix-review.prompt.md.
9. Specify finding IDs.
10. Run tests again.
11. Review the diff.
12. Commit.

Example:

Fix:

SEC-002
DB-004
TEST-003

---

# Important

AI review is advisory.

Always manually verify:

- security-critical findings
- financial logic
- destructive migrations
- authentication/authorization
- production infrastructure
- concurrency
- data migrations

Do not blindly apply every suggestion.
