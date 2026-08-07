# MathBinder Core 29.0.1

This maintenance release corrects the Phase 2 administrator workspace label created by Core 29.0.0.

## Installation

1. Confirm a current backup is available.
2. In WordPress, open Plugins > Add Plugin > Upload Plugin.
3. Upload `mathbinder-core-29.0.1-phase-2-workspace-fix.zip`.
4. Choose **Replace current with uploaded**.
5. Keep PopularFX active and leave MathBinder Account as a draft.
6. Reopen the MathBinder Account preview. The administrator assignment should read **Site workspace · Active**.

## Scope

The update fixes the role-assignment insert formats and repairs only the malformed WordPress-seeded assignment whose scope was stored as `0`. It does not modify Binder Pages, lesson content, dashboards, permanent MathBinder IDs, or other role assignments.

## Rollback

If installation fails, reinstall Core 29.0.0. Restoring the database is unnecessary unless WordPress reports a database error.
