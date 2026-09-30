# Architecture Notes

Keep this document concise.

Update it when important architectural decisions are made.

---

## System Purpose

Authenticated users manage customers and their projects. Both resources can be
created, edited, searched, paginated, soft-deleted, restored, and permanently
deleted from a trash view.

---

## Main Modules / Domains

- Customer management: controllers, Form Requests, policy, query object, transformer,
  Eloquent model, active list, and trash list.
- Project management: controllers, Form Requests, policy, query object, transformer,
  Eloquent model, active list, and trash list. Each project belongs to one customer;
  a customer may have many projects.
- Authentication and account settings: Laravel Fortify, email verification, passkeys,
  two-factor authentication, profile and password management.

---

## Authentication / Authorization

- Fortify provides authentication and account security features.
- Customer and project routes require `auth` and `verified` middleware.
- `CustomerPolicy` is the authorization boundary for customer list, create, update,
  delete, restore, and force-delete actions. The current product scope permits every
  authenticated verified user to perform these actions.
- `ProjectPolicy` applies the same authorization boundary to project actions.

---

## Database

Engine: MySQL-compatible MariaDB in Docker; SQLite is used for isolated in-memory
tests.

Important constraints: customer and project names are unique among active records.
Soft-deleted names may be reused. Restoring a deleted record whose name is already
used by an active record is rejected with a conflict message. Projects require one
customer and have a nullable end date that cannot precede the required start date.
Customers with projects cannot be moved to the trash or permanently deleted.

Important transactions: no multi-step business transaction or queued write flow exists
currently.

---

## Queues / Async Processing

- The default local queue driver is database-backed, but no application jobs or
	asynchronous business workflows are currently defined.

---

## Frontend

Blade / Livewire / Vue / Inertia:

- Blade with Livewire and Flux components, bundled by Vite Plus and Tailwind CSS.
- Customer CRUD UI behavior is covered by Laravel Dusk tests.

---

## External Services

- No application-specific external service integration was identified.
- Docker Compose provides MariaDB, phpMyAdmin, and MailHog for local development.

---

## Important Architectural Decisions

- Keep customer and project list querying and presentation transformation separate
	from controllers.
- Enforce active customer-name uniqueness in both Form Request validation and the
	database; soft-deleted names may be reused. During creation, if a name is in the
	trash, the user can choose to create a new record or restore the existing one.
- Enforce project-name uniqueness in both Form Request validation and the database,
	while allowing names belonging to soft-deleted projects to be reused. During
	creation, if a name is in the trash, the user can choose to create a new record or
	restore the existing one.
- Enforce the project-to-customer relationship with a non-null foreign key and an
	Eloquent `belongsTo` / `hasMany` relationship.
- Keep browser tests in a separate CI job because they require a browser and a test
	database.

---

## Deliberately Rejected Patterns

Record patterns that should not be introduced without a concrete new requirement.

- No repository, service, DTO, domain-layer, CQRS, or event-sourcing abstraction is
	justified by the current application size.

---

## Production Assumptions

- CI runs PHP 8.3 and Node 22; local Docker currently runs PHP 8.4.
- Administrative Docker ports and development credentials are for local development
	only.
- Production deployments must provide a non-debug environment and isolated database
	credentials.

---

## Known Technical Debt

- CI now includes Dusk, but the browser job must be observed once in GitHub Actions to
	confirm the runner's Chrome/ChromeDriver paths.
