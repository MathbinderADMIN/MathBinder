# Phase 1 — Foundation and Student Dashboard

## Included

- MathBinder Core 28.0.2 modular foundation, reconciled with the live 27.6.3 fixes
- Student Dashboard presentation assets supplied by Core for compatibility with PopularFX
- Versioned schema migration runner
- Audit-event, external-identity, and Canvas mapping tables
- Central capability service
- Versioned `/mathbinder/v1/student/dashboard` REST route
- Canvas adapter interface with a safe unconfigured implementation
- Draft Student Dashboard page created on activation
- MathBinder theme 1.0.0
- Responsive Student Dashboard shell
- Safe fixture data clearly identified as preview data

## Preserved

The existing Binder Page post type, taxonomy, content engine, shortcodes,
templates, lesson provisioning, and public page behavior remain in place.

## Installation order on staging

1. Back up the database, current MathBinder Core plugin, and active theme.
2. Upload and activate MathBinder Core 28.0.2.
3. Confirm existing Binder Pages and lessons still load.
4. Upload the MathBinder theme, but preview it before activation.
5. Publish the draft **Student Dashboard** page only after access has been tested.
6. Test with a staging student account and an administrator account.

## Phase 1 exit-gate checks

- Existing Binder Pages render without changes.
- `mathbinder_schema_version` is `1.0.0`.
- The three new database tables exist.
- Unauthorized REST requests are rejected.
- Authorized users can load the Student Dashboard.
- The dashboard works at desktop, tablet, and mobile widths.
- Keyboard focus, skip navigation, and reduced motion remain usable.
- No live Canvas connection or credential is present.

## Rollback

Reactivating MathBinder Core 27.6.3 restores the prior application code. The
Phase 1 tables are intentionally retained because rollback must not destroy
identity mappings or audit history. Reactivate the previous theme if the
MathBinder theme needs to be rolled back.
