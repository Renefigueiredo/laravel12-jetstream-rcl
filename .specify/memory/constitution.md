# ConciliaFuzzy Constitution

## Core Principles

### I. Laravel 12 First
All backend implementation MUST follow Laravel 12 conventions and native framework patterns.
Routing, middleware, exceptions, and console configuration MUST use `bootstrap/app.php` and
`routes/console.php` as applicable. Features MUST prefer Eloquent relationships, Form Requests,
policies, named routes, and framework commands over custom infrastructure. Business rules MUST live
in dedicated Action/Service classes; routes, controllers, and components only delegate. Statuses
and other fixed values MUST be PHP Enums, never free-text strings.

Rationale: Reduces accidental complexity and keeps the codebase aligned with maintainable Laravel
standards.

### II. Reactive UI via Livewire 4 + Filament
Interactive UI MUST be built with Blade + Alpine + Livewire 4. The design system MUST follow the
Jetstream base template and its established UI patterns. Livewire components MUST follow
server-driven state, validation, authorization, and lifecycle hook conventions. Administrative and
data-heavy UIs MUST use Filament Forms and Filament Tables before custom alternatives are
introduced. Styling MUST use Tailwind CSS v4 utilities and existing project design tokens.
User-facing text MUST come from translation files (pt-BR), and list filters MUST be persisted in
the URL so a filtered view can be shared. Screens MUST meet WCAG contrast, keyboard, and
screen-reader requirements.

Rationale: Enforces a single, consistent UI architecture and prevents fragmented frontend patterns.

### III. Test-First Delivery (NON-NEGOTIABLE)
Every behavioral change MUST be covered by automated tests. Work MUST follow a red-green-refactor
cycle: write or update a failing test first, implement, then pass. Feature-level behavior MUST be
validated in PHPUnit Feature tests; unit tests MUST be used for isolated domain logic.
Reconciliation rules (matching, scoring, tolerances, installments) MUST have tests for matching,
non-matching, and boundary cases, including every score classification threshold, each tolerance
mode, and two balance-changing actions on the same authorization. Tests MUST use fakes for
external integrations and MUST NOT touch production data.

Rationale: Prevents regressions and keeps delivery confidence high while evolving the system.

### IV. PostgreSQL Data Integrity
Production data MUST live in PostgreSQL on Supabase Cloud (paid, managed plan), used strictly as
a database through Laravel's `pgsql` connection. Schema changes MUST be shipped through Laravel
migrations that run on PostgreSQL, with explicit constraints, indexes, and foreign keys. Data
access MUST prefer Eloquent/query builder and MUST avoid bypassing model integrity rules.
Monetary values MUST be stored as integer cents and formatted in a single place; timestamps MUST
be stored in UTC. Totals, counts, and aggregations MUST be computed in the database, not by
loading or summing collections in PHP. Operations that change more than one financial record MUST
run inside a database transaction. Non-production environments MUST NOT connect to the production
database.

Rationale: The product exists to eliminate cent-level discrepancies; data correctness is the
product, and migrations keep schema changes auditable.

### V. Boost-Guided, Minimal Changes
For Laravel ecosystem decisions, implementation MUST consult Laravel Boost documentation search
before coding. Any question about framework or system behavior MUST use Laravel Boost as the
primary source of truth, treating it as the internal MCP for resolving doubts about the system and
framework. Changes MUST be minimal, scoped, and compatible with existing structure; dependency or
major architectural changes REQUIRE explicit approval.

Rationale: Ensures version-correct implementation choices and reduces risk from broad refactors.

### VI. Production-Ready Integrations
Background processing MUST use Laravel queues. The queue connection MUST come from
configuration, with the database driver as the default, and jobs MUST NOT depend on a specific
queue driver. Failed jobs MUST be recorded and reviewable by the system administrator. Laravel
Horizon MAY be adopted when volume justifies running Redis.
Reconciliation runs and spreadsheet processing MUST execute in queued jobs that report progress,
so the user can leave the screen without interrupting the run. Outbound HTTP calls MUST use
Laravel's native HTTP client. External data sources (spreadsheet, statement, or ERP formats) MUST
be accessed behind an application-owned interface so a source can be replaced without changing
reconciliation rules.

An uploaded file MUST be validated in full (extension, size, headers, and every data row) before
any row is persisted. An import MUST be atomic: the file is accepted whole or rejected whole, and
a rejection MUST report the row and column of each error. A file with no data rows MUST be
rejected. Imports and confirmations MUST be idempotent: submitting the same file or action twice
MUST NOT duplicate financial records. Upload size and export row limits MUST be enforced on the
server and read from configuration.

Uploaded files MUST be stored unmodified on the private disk, outside the public directory, and
served only through authorized routes; the folder MUST have its own off-server backup. Failure of
an external service MUST NOT block reconciliation or consultation. The product MUST NOT integrate
AI/LLM services. Feature flags, integrations, and environment-specific customization MUST be
configured via environment variables and surfaced through config files, never hard-coded or stored
directly in source control. Logs MUST NOT contain secrets, bank data, or supplier tax identifiers.

Rationale: Standardizes integrations for reliability, visibility, and secure configuration, keeps
bad spreadsheets out of the database, and avoids recurring AI cost for the client. The expected
load is a few imports and one reconciliation per month, which the database queue handles without
an extra service to operate.

### VII. Auditability & Traceability (NON-NEGOTIABLE)
Every confirmation, rejection, manual link, unlink, write-off, adjustment, session reopening,
session deletion, and configuration change (tolerance, excluded operation codes) MUST create an
audit record in the same database transaction as the change. An audit record MUST hold the acting
user, the UTC timestamp, the action type (Enum), the affected entity, and a snapshot of the data
before and after the change. Unlinking MUST also record the engine's original score and
suggestion. A write-off that closes a value difference MUST carry a justification category.

Audit records are append-only: the application MUST NOT offer any path that updates or deletes
them. Users MUST NOT be hard-deleted; they are deactivated so audit records keep pointing to the
person who acted. Imported files and the raw imported rows MUST be retained as audit evidence, and
records referenced by the audit trail MUST be protected by foreign keys.

A session MAY be hard-deleted, together with its files and rows, only while it has never been
processed and therefore holds no reconciliation link. Once processed, a session and its files
MUST be retained. An authorization or payment with reconciliation links MUST NOT be deleted until
those links are undone. The audit record of a deleted session MUST survive the deletion and
identify the period, the user, and the files removed.

Rationale: Total traceability and audit compliance are the core benefit promised to the finance
team; a broken trail invalidates the product. A session created by mistake carries no financial
decision, so removing it does not break the trail.

### VIII. Single-Company Role & Permission Access
The system serves a single company. Operating units (Social, Saúde) are regular records, not
tenants: every authenticated user works across all units, and no data is partitioned per unit.
Jetstream Teams MUST NOT be used to model units or roles.

Each user has exactly one role, defined as a PHP Enum: Administrador (unrestricted access,
configuration, and the full audit trail) or Operador (sessions, imports, reconciliation, and
operational reports). Capabilities beyond the role MUST be independent permissions granted per
user by an administrator, including: view reports, export data, manage excluded operation codes,
and configure tolerance. Every action MUST be authorized on the server through policies or gates
that check the role or permission; hiding a menu or button is not authorization, and code MUST NOT
compare role names as strings in views or components.

Public self-registration MUST be disabled; only an administrator creates users. Two-factor
authentication and login rate limiting MUST remain enabled.

Rationale: Reconciliation crosses units and settles pendencies between them, so unit isolation
would block the core workflow, while an internal financial system must not accept unknown users
or expose reports and exports to everyone.

### IX. Deterministic Reconciliation Engine
The matching engine MUST be deterministic: the same authorizations, payments, and parameters MUST
always produce the same scores and classifications. The score MUST be computed from at least two
axes, supplier-name compatibility and amount compatibility, using string-similarity and arithmetic
rules only. The engine MUST link records automatically only when the score reaches the automatic
threshold; every other classification requires an explicit human action.

Score thresholds and the tolerance margin (fixed amount, percentage, or both) MUST be
configuration, not literals in code. Each reconciliation run MUST record the parameters in effect,
and a later parameter change MUST NOT alter the result of a run already processed.

An authorization's outstanding balance and status MUST be derived from its linked payments and
MUST change only through reconciliation actions, never by direct edit. Two actions that change the
same authorization MUST NOT overwrite each other: they are serialized or the later one is
rejected and the user is shown current data. Payments carrying an excluded operation code MUST be
skipped by the engine and MUST remain stored and reportable. Re-running a reopened session MUST
discard the previous result of that session before producing a new one.

Rationale: The finance team must be able to explain every match to an auditor. A result that
shifts between runs, or a balance corrupted by simultaneous edits, cannot be defended.

## Technology Stack Constraints

Product scope: ConciliaFuzzy is an internal web system for the finance, accounting, and
controllership team that cross-checks, validates, and reconciles purchase authorizations against
actual payment records of the Social and Saúde operating units, including installments, partial
payments, and divergences. Work is organized in sessions tied to a reference month and year; each
session requires three spreadsheets (authorizations, Social payments, Saúde payments) before
reconciliation can run. The system does not process payments, issue invoices, or bill users.

The canonical application stack is:
- Laravel 12
- Blade + Alpine.js
- Livewire 4
- Filament Forms + Filament Tables
- Tailwind CSS 4
- PostgreSQL on Supabase Cloud (production)
- Laravel Jetstream + Fortify
- Laravel Boost
- PHPUnit
- Laravel Queues (database driver; Horizon optional)
- Laravel HTTP Client
- Laravel Pennant

`TECH_STACK.md` at the repository root records the detailed technical decisions and their reasons
and is the runtime guidance file. If it conflicts with this constitution, the constitution prevails
and `TECH_STACK.md` MUST be corrected.

Any proposal that introduces an additional framework for a capability already covered by this stack
MUST include written justification and explicit approval before implementation.

## Development Workflow & Quality Gates

1. Specification artifacts (`spec.md`, `plan.md`, `tasks.md`) MUST explicitly map to constitution
   principles.
2. Constitution check gates in planning MUST pass before implementation begins and MUST be
   re-validated after design.
3. Changes MUST reach the main branch through a reviewed pull request; commit messages MUST follow
   the Conventional Commits format.
4. Pull requests MUST document: scope, tests executed, migration impact, and principle compliance.
5. New endpoints, workflows, or UI paths MUST include test coverage for both happy paths and
   relevant failure paths.
6. Formatting and static quality tooling configured by the repository MUST run on changed files
   before finalization.
7. Migration files MUST be named in English and follow Laravel's migration naming conventions.
8. When creating a new screen or interface, clarifying questions MUST ask the user for their
   desired UI direction before implementation.
9. A specification that conflicts with a principle MUST be corrected, or the constitution amended
   first; implementation MUST NOT proceed on the conflicting requirement.

## Governance

This constitution supersedes local workflow preferences when conflict exists.

Amendment Process:
- Propose changes in a documented update that includes rationale, affected principles, and
  migration/transition impact.
- Obtain maintainer approval before merging amendments.
- Record all amendments with a semantic version update.

Versioning Policy:
- MAJOR: Backward-incompatible governance changes, removed principles, or principle redefinitions.
- MINOR: New principle/section added or materially expanded governance guidance.
- PATCH: Clarifications, wording improvements, and non-semantic refinements.

Compliance Review Expectations:
- Every implementation plan MUST include a Constitution Check.
- Every task list MUST include explicit testing tasks.
- Every pull request review MUST verify constitutional compliance prior to approval.

**Version**: 2.1.0 | **Ratified**: 2026-02-19 | **Last Amended**: 2026-10-08
