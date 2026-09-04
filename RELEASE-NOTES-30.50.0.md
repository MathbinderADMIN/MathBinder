# MathBinder Core 30.51.5

## 30.51.5

- Hides Parent Help and Teacher Notes, including their lesson-navigation links, from regular MathBinder student accounts as well as Canvas student launches.
- Keeps Parent Help available to signed-in parents and instructional staff, while Teacher Notes remain limited to teachers, classroom staff, school administrators, and site administrators.
- Repairs measurement-diagram typography by preventing SVG shape strokes from being inherited by text labels.
- Preserves the working Canvas assignment save, return, and submission workflow from 30.51.4.

## 30.51.4

- Returns students directly to the same Canvas lesson and assignment workspace after Save Notes.
- Preserves the signed assignment session across the redirect so notes remain visible, Parent Help and Teacher Notes stay hidden, and Canvas-only cookie suppression remains active.
- Restores the complete My MathBinder collection layout for the newer saved-item markup.
- Centers Log In and Sign Up using the same theme-isolated label structure as the corrected Log Out control.

## 30.51.3

- Replaces Canvas assignment POST dependence on WordPress cookies and resource-launch transients with a self-contained, signed 12-hour assignment session embedded in the form.
- Verifies the signed student, lesson, class, Canvas role, and score endpoint again on the server before saving or submitting.
- Restores the visible Log Out control using normal flex layout with a centered, theme-isolated label.

## 30.51.2

- Reconciles a validated Canvas learner and approved course mapping into the mapped active MathBinder class before saving assignment work.
- Uses the class carried by the validated Canvas launch instead of trusting a posted class identifier.
- Gives separate role, course-mapping, and enrollment error codes if a Canvas launch cannot be accepted.
- Centers the Log Out label independently of theme link spacing and generated menu decorations.

## 30.51.1

- Preserves the server-rendered Canvas launch token instead of allowing the legacy browser helper to overwrite it with an empty value before Save or Submit.
- Keeps the server-rendered Canvas form destination intact during submission.
- Removes theme-generated decorations from the account link and centers the Log In, Log Out, and Sign Up button labels.

## 30.51.0

- Restores the complete standards-domain catalog, Binder Section card, and notebook subtopic layouts.
- Centers the header menu and account controls when desktop navigation wraps.
- Suppresses CookieAdmin consent prompts and optional CookieAdmin assets only inside validated Canvas resource launches.
- Replaces the Canvas assignment form with a server-rendered POST containing the launch token and routing marker natively.
- Makes the validated Canvas launch identity authoritative when an embedded browser also sends an unrelated MathBinder login cookie.

## 30.50.9

- Uses the validated Canvas launch identity directly when saving or submitting assignment work instead of depending on iframe browser-login state.
- Reattaches the assignment marker and launch token at form submission time and through the browser FormData event.
- Uses the score endpoint from the current Canvas assignment launch, with the approved mapping retained only as a fallback.
- Adds distinct Canvas session and mapped-user error codes to make any remaining launch failure identifiable.

## 30.50.8

- Makes unfinished place-value charts directly writable with keyboard-accessible student input boxes.
- Carries the Canvas resource token from the validated server launch into the assignment form instead of relying only on the iframe URL.

## 30.50.7

- Prevents practice visuals from revealing stored answers in every lesson and every practice mode.
- Replaces completed place-value models in student problems with unfinished workspaces while preserving completed instructional examples.
- Preserves the signed Canvas launch token when students save or submit written and drawn assignment work.

## Canvas submission and vocabulary notes

- Canvas assignment forms now post back through the front-end lesson instead of the WordPress admin endpoint, avoiding iframe blocking after Save or Submit.
- A failed Canvas status update keeps the work editable so the student can retry instead of trapping the note in a locked state.
- Expanded vocabulary cards include **Add to My Notes**, which inserts the term and definition into the current assignment notes.

## Embedded Canvas login persistence

- A verified, short-lived Canvas resource session now restores the mapped MathBinder user on every embedded lesson request.
- The fix no longer depends on the browser accepting a third-party WordPress login cookie inside the Canvas frame.
- Canvas assignment save and submission requests restore the mapped student before verifying the account-bound WordPress nonce.

## Canvas student assignment workspace

- Verified Canvas student launches now show an account-backed assignment workspace inside the selected lesson.
- Students can type notes, draw work, save an editable draft, and submit one locked written-and-drawn snapshot.
- Submitting sends Canvas an AGS `Completed` / `PendingManual` activity event and makes the snapshot available to the mapped MathBinder class teacher.
- Canvas student launches hide Parent Help and Teacher Notes, including their lesson navigation links.
- The public/device-only Math Journal remains unchanged outside verified Canvas student launches.

## Student Math Notes

- Students can delete notes from **My Math Notes**.
- Private drafts are deleted completely.
- If a note was previously submitted, its locked submission snapshot remains available to authorized teachers as class evidence, while the working note is removed from the student's list.
- Student note cards now display typed note content and drawing content together.

## Teacher student view

- Student names in **Student Progress** open the student's dedicated progress and work view.
- The class workspace's **View full progress and work** control now opens that dedicated page instead of an older inline accordion.
- Classroom note totals count submitted structured Math Notes for that class.
- The view includes assignment progress and deliberately submitted Math Notes.
- Access remains limited to classes for which the current teacher or staff member has progress or evidence permission.

## Teacher navigation

- The Teacher Dashboard is now a progress overview.
- My Classes, Class Staff, Student Progress, Mastery Paths, Evidence, and Canvas each have a dedicated WordPress page and URL.
- Teacher navigation switches pages instead of scrolling through one long dashboard.
- Opening a student now hides the class-wide progress snapshot and shows only that student's progress and submitted work.

## Footer correction

- Teacher pages and individual student progress pages now reuse one official MathBinder footer.
- The older theme-footer conversion script no longer creates a second MathBinder footer.

## Canvas launch authentication

- A verified Canvas LTI launch now establishes the approved mapped MathBinder user session before opening a lesson.
- Students no longer need a separate MathBinder login after a valid signed Canvas launch.
- Newly selected Canvas lesson and evidence links open in their own window to avoid third-party iframe cookie restrictions.

## Upgrade notes

The new teacher pages are created or refreshed automatically on the first administrator request after the plugin update.
