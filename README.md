# Releases

Store packaged release ZIPs here after source changes are reviewed and tested.

Current implementation baseline: **MathBinder Core 30.35.0 / MathBinder Theme 1.0.0**.

Core 30.35.0 adds Teacher Roster and Staff Management. Authorized teachers and delegated staff with the remove-students permission can remove an active student enrollment from one selected class after confirmation. Primary teachers and administrators can also remove co-teachers, substitutes, and aides from a selected class. Removal immediately ends only that class membership or delegated access while preserving each account, saved student work, other class enrollments, and audit history. Pending staff invitations are revoked, every removal is recorded, and either person can be invited again later.

Core 30.34.0 adds the Student Classroom Experience. Active enrollments now appear as identifiable class cards with subject, teacher, grade, term, and a secure Go to Class destination. The class view shows assigned class learning or a clear empty state while preserving classwide Binder access. Teacher enrollment results now distinguish immediate enrollment from a pending invitation, send the appropriate student email, and report email-delivery failures without undoing a valid enrollment.

Core 30.33.0 provides a fully front-end account and class-management experience. Teacher, classroom-staff, student, and parent accounts no longer see the WordPress toolbar or enter the WordPress administration area; native sign-in routes them to the correct MathBinder workspace while site administrators retain WordPress access. Authorized teachers and delegated staff with class-settings permission can edit class identity, subject, grade level, term, enrollment setting, and status without changing the class code, roster, or saved student work.

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
