# Laravel + GitHub Copilot Code Review

## Components

### Repository instructions

.github/docs/review-rules.md

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

### Guided flows (skills, not slash commands)

.github/skills/

- full-review — runs the complete audit workflow
- fix-review — fixes selected findings only
- fix-tests, new-feature, new-resource, consistency-review, ux-implement

They are skills: activate them by name, there is no `/command` for them.

---

# Recommended Workflow

1. Develop feature.
2. Write tests.
3. Run local tests.
4. Run Review Orchestrator.
5. Inspect `.github/reviews/YYYY-MM-DD/CODE-REVIEW.md`.
6. Select findings.
7. Start a new Copilot Agent session.
8. Activate the fix-review skill.
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
