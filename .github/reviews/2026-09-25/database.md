# Database Review

Result: The requested product rule is implemented: customer names are unique among active records and can be reused after soft deletion. Validation and the database enforce the rule; soft-delete, restore and force-delete behavior is covered by Feature tests.

The implementation uses a new migration: a partial unique index for SQLite/PostgreSQL and a generated active-name column for MySQL/MariaDB.
