# Releases

Store packaged release ZIPs here after source changes are reviewed and tested.

Current implementation baseline: **MathBinder Core 30.32.0 / MathBinder Theme 1.0.0**.

Core 30.32.0 adds classwide student content access. An authorized student enrolled in an active class receives every student-facing Binder lesson and premium learning tool covered by the class organization’s active license; assignments and mastery paths guide learning without locking other published topics. Coverage follows school/district, classroom, family, direct, then free precedence; trial, grace, class, term, and enrollment windows are enforced; and ending coverage preserves the permanent account, notes, progress, evidence, and submitted work.

Core 30.31.0 adds invitation-based co-teacher, substitute, and class-aide access. Primary teachers choose selected classes, full or custom permissions, and optional access dates. Staff use individual identities; invitations expire and are single-use; delegated actions remain audit-attributed; and billing, ownership, and staff-management authority stay protected.

Core 30.30.0 adds External Practice Records for student-reported IXL, Khan Academy, DeltaMath, and other completed practice. Records connect to a MathBinder topic, appear in the Evidence Folder, support optional protected evidence files, and use class-authorized teacher verification, revision, and mastery review.

Core 30.28.0 adds the administrator-only Canvas LTI administration and sandbox
testing console. It provides Disabled and Sandbox operating modes, keeps Live
mode locked, and adds local diagnostics plus non-mutating launch, roster,
Deep Linking, grade-policy, Evidence Folder, mapping, and queue previews. Canvas
transport remains fail-closed until configuration, validation, Sandbox mode,
and the separate activation gate all pass.

Phase 1 adds the modular application foundation and live Student Dashboard
shell while preserving existing Binder Page and content-pack behavior. See
`PHASE-1.md` for staging and rollback instructions.
