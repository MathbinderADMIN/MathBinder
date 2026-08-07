# MathBinder Phase 3 — Organizations, Classes, and Licensing

Core 30.0.0 adds an audited organization and access layer without changing
Binder Pages, lesson content, Student Dashboard data, or permanent MathBinder IDs.

## Included

- Organizations with verification state and scoped administrator workspaces
- Academic terms, classes, sections, teacher ownership, and unique class codes
- Account enrollment or pending email invitations
- Trial and premium licenses, seat limits, allocations, and coverage priority
- Native WordPress administrator dashboard under MathBinder Organizations
- Separate, versioned Phase 3 schema migration

Payment-provider checkout is intentionally isolated for a later connection. A
manual provider record is used now so no payment credentials or irreversible
billing actions are introduced during the Phase 3 acceptance test.

## Acceptance path

Create a test organization, term, class, student enrollment, trial license, and
seat allocation. Confirm the class code, seat count, and audit entries. Do not
use real billing information during this test.
