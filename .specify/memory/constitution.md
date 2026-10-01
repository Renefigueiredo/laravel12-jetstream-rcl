<!--
Sync Impact Report
- Version change: 1.4.0 → 1.5.0
- Modified principles:
  - IV. PostgreSQL Data Integrity → IV. PostgreSQL Data Integrity (production on Supabase Cloud;
    aggregation in the database; transactions; money as integer cents)
  - VI. Production-Ready Integrations → VI. Production-Ready Integrations (interfaces for external
    sources, private file storage, no AI/LLM integration)
- Added principles:
  - VII. Auditability & Traceability (NON-NEGOTIABLE)
  - VIII. Single-Company Access Control
- Added sections:
  - Product Scope (inside Technology Stack Constraints)
- Removed sections:
  - None
- Stack changes:
  - Removed: Laravel AI (product does not use AI)
  - Changed: PostgreSQL → PostgreSQL on Supabase Cloud (paid, managed) in production
- Follow-up TODOs:
  - TODO(HORIZON): Principle VI requires Horizon, but laravel/horizon is not installed and
    TECH_STACK.md records QUEUE_CONNECTION=database. Decide: install Horizon (needs Redis and
    dependency approval) or amend Principle VI.
  - TODO(DEV_DATABASE): TECH_STACK.md keeps SQLite as the dev default. Decide whether dev and
    tests move to PostgreSQL to match production.
  - TODO(PRODUCTION_HOST): where the production application runs is undecided.
  - TODO(PROJECT_NAME): title still carries the starter-kit name; the product has no name yet.
  - laravel/ai remains in composer.json; removal requires dependency approval.
-->

# Laravel12JetstreamStarter Constitution

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
Reconciliation rules (matching, tolerances, installments) MUST have tests for matching,
non-matching, and boundary cases. Tests MUST use fakes for external integrations and MUST NOT
touch production data.

Rationale: Prevents regressions and keeps delivery confidence high while evolving the system.

### IV. PostgreSQL Data Integrity
Production data MUST live in PostgreSQL on Supabase Cloud (paid, managed plan), used strictly as
a database through Laravel's `pgsql` connection. Schema changes MUST be shipped through Laravel
migrations that run on PostgreSQL, with explicit constraints, indexes, and foreign keys. Data
access MUST prefer Eloquent/query builder and MUST avoid bypassing model integrity rules.
Monetary values MUST be stored as integer cents and formatted in a single place; timestamps MUST
be stored in UTC. Totals and aggregations MUST be computed in the database, not by summing
collections in PHP. Operations that change more than one financial record MUST run inside a
database transaction. Non-production environments MUST NOT connect to the production database.

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
Background processing MUST use Laravel queues with Horizon for monitoring and operations. Outbound
HTTP calls MUST use Laravel's native HTTP client. External data sources (spreadsheet, statement,
or ERP formats) MUST be accessed behind an application-owned interface so a source can be replaced
without changing reconciliation rules. Imports and confirmations MUST be idempotent: submitting
the same file or action twice MUST NOT duplicate financial records. Uploaded files MUST be stored
on the private disk, outside the public directory, and served only through authorized routes; the
folder MUST have its own off-server backup. Failure of an external service MUST NOT block
reconciliation or consultation. The product MUST NOT integrate AI/LLM services; reconciliation is
deterministic. Feature flags, integrations, and environment-specific customization MUST be
configured via environment variables and surfaced through config files, never hard-coded or stored
directly in source control. Logs MUST NOT contain secrets, bank data, or supplier tax identifiers.

Rationale: Standardizes integrations for reliability, visibility, and secure configuration, and
avoids recurring AI cost for the client.

### VII. Auditability & Traceability (NON-NEGOTIABLE)
Every reconciliation, adjustment, and divergence resolution MUST record who performed it, when,
the previous value, and the new value. Audit records MUST NOT be editable or deletable through the
application. Users MUST NOT be hard-deleted; they are deactivated so audit records keep pointing
to the person who acted. Imported files and the raw imported rows MUST be retained as audit
evidence. Records referenced by the audit trail MUST be protected by foreign keys.

Rationale: Total traceability and audit compliance are the core benefit promised to the finance
team; a broken trail invalidates the product.

### VIII. Single-Company Access Control
The system serves a single company. An operating unit is a regular record, not a tenant; access
to units MUST be granted per user through permissions, and finance users MUST be able to work
across the units they are permitted to see. Jetstream Teams MUST NOT be used to model units.
Public self-registration MUST be disabled; only an administrator creates users. Every action on a
resource MUST be authorized through policies that check the user's permission for that unit.
Two-factor authentication and login rate limiting MUST remain enabled.

Rationale: Reconciliation crosses units, so tenant isolation would block the core workflow, while
an internal financial system must not accept unknown users.

## Technology Stack Constraints

Product scope: an internal web system for the finance, accounting, and controllership team that
cross-checks, validates, and reconciles purchase authorizations against actual payment records
across multiple operating units, including installments and divergences. It does not process
payments, issue invoices, or bill users.

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
- Laravel Horizon
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

**Version**: 1.5.0 | **Ratified**: 2026-02-19 | **Last Amended**: 2026-10-01
