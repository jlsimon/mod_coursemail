# Changelog

All notable changes to mod_coursemail are documented here.

## v0.1.0 — First public release (2026-06-13)

mod_coursemail is a Moodle 4.1 activity module that gives each activity instance
its own internal mailbox, with configurable activity completion. It is inspired by
`local_mail` but is a self-contained activity with no dependency on it.

Highlights:

- **Threaded conversations** between teachers and students, entirely inside the
  course — no external email involved.
- **Teacher messaging:** send to a single student, the whole class, or a
  group/grouping in one action. The conversation's "To:" header shows each
  recipient's *read / replied* status.
- **Student messaging:** read, reply within the thread, and start conversations
  with the teaching staff.
- **Folders:** Inbox, Sent, Drafts, Starred, plus a Supervision folder for
  managers (`mod/coursemail:viewall`).
- **Productivity:** conversation search, a *This activity / Whole course* scope
  toggle, attachments, quick filters and bulk actions, course-page status badges,
  and a role-aware in-app help screen.
- **Activity completion tied to messaging:** a single rule that combines reading
  all staff messages (optional), replying where a response is required, and
  per-student manual completion marked by a teacher.
- **Full Moodle integration:** events, a privacy (GDPR) provider, backup/restore,
  capabilities, and English and Spanish language packs.

Requires Moodle 4.1 (LTS). Tested on PHP 7.4.
