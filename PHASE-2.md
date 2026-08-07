# MathBinder Phase 2 — Identity, Roles, and Continuity

Core 29.0.0 adds the account foundation that future organizations, classes, licensing, and Canvas connections use.

## Included

- Permanent MathBinder identity key attached to one WordPress user
- Multiple MathBinder role assignments without duplicate logins
- Student, parent, teacher, and administrator workspaces
- Draft Account & Workspaces page using `[mathbinder_account_workspace]`
- Workspace switching with ownership checks, nonces, and audit events
- Email-verification links for newly registered accounts
- Parent and school authorization records for minors
- Duplicate-account detection and external-identity collision protection
- Transfer requests that preserve personal records
- Versioned identity REST endpoints
- Administrator role-assignment and transfer-request tools under Users

## Installation gate

Back up files and database before replacing Core 28.0.3. Install the ZIP by replacement; do not delete the active plugin first. After installation, confirm all 44 Binder Pages and the Student Dashboard still load before testing the new identity workspace.

The Account & Workspaces page is created as a draft. Keep it unpublished until the Phase 2 acceptance checks pass.

## Rollback

Restore Core 28.0.3 and the pre-install database backup together. Core 29.0.0 migrations add tables and do not remove existing Binder Page data.
