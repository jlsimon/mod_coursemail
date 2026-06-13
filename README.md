# Course mailbox (mod_coursemail)

**Place teacher–student communication where it belongs: inside the course, as a learning activity.**

> 🇪🇸 Versión en español: [README_es.md](README_es.md).

A Moodle course is an *ordered sequence* of activities and resources: a topic, a
reading, a video, an assignment, an exam. But personal communication with students
almost always escapes that sequence into email — where you can't tell if a message
was read, the student has to leave the course to see it, ignoring it has no
consequences, and nothing is recorded in the course reports.

**coursemail** fixes that. It's a messaging inbox that is, at the same time, an
**email service** *and* a **Moodle activity**:

- As an **email service** it gives you what you'd expect: threaded conversations,
  Inbox / Sent / Drafts / Starred folders, an HTML editor, and sending to one
  student, a group or the whole class — all **inside the LMS**, with no external
  mail server and no third party holding your students' data.
- As a **Moodle activity** it brings **activity completion** (by reading, by
  replying, or manually marked per student by the teacher), **progress tracking** in
  the course reports, and the ability to act as a **learning checkpoint**: combined
  with Moodle's native access restriction, the mailbox can gate progress — *"you
  can't move on to the next topic until you've read (and replied to) this message."*

> Compatible with **Moodle 4.1 or later**.

## Why coursemail instead of Moodle's standard messaging or local_mail?

| | Moodle messaging (core) | local_mail | **coursemail** |
|---|:---:|:---:|:---:|
| Scope | Site-wide | Per course | **Per activity** |
| Threaded conversations | No | Yes | Yes |
| Folders (Inbox / Sent / Drafts / Starred) | Limited | Yes | Yes |
| Attachments | Yes | Yes | Yes |
| Email / push notifications | Yes | Yes | No (v1) |
| **Activity completion** | No | No | **Yes** |
| **Read receipt that completes the activity** | No | No | **Yes** |
| **Required reply that completes the activity** | No | No | **Yes** |
| **Manual marking per student by teacher** | No | No | **Yes** |
| **Progress gate** (access restriction) | No | No | **Yes** |
| Multiple independent instances per course | No | No | **Yes** |
| Per-instance configuration | No | No | **Yes** |
| GDPR (export + deletion) | Yes | Yes | Yes |
| Backup / restore with the course | Yes | No¹ | Yes |

¹ `local_mail` is a `local`-type plugin, so its data does not travel with the
standard Moodle course backup.

**In short:**

- **Moodle standard messaging** — useful for informal site-wide communication, but
  it lives outside the course context and generates no completion record. It cannot
  be used as a gate.
- **local_mail** — an excellent course-scoped mailbox with attachments and
  notifications, but it is a `local`-type plugin, not an activity. Because it does
  not plug into Moodle's completion engine, it cannot gate progress and does not
  appear in completion reports. Course data is also not included in backups.
- **coursemail** — choose this plugin when communication must be a **learning
  checkpoint**: "the student cannot move on until they have read and replied."
  The trade-off in v1 is the absence of email/push notifications.

## How you use it

The same plugin supports three levels of control — you choose per activity:

1. **Accompany** — an open mailbox in the course, no blocking. Already better than
   email because it lives in context and leaves a record.
2. **Validate** — turn on *Replied* completion and/or mark a conversation as
   resolved manually, student by student, for qualitative follow-up.
3. **Block** — enable *Read* / *Replied* completion and add an **access
   restriction** on the next activity, so the mailbox gates progress.

Typical workflow:

1. A teacher (anyone with `mod/coursemail:send`) adds a **Course mailbox** activity
   to the course and, in the activity settings, picks the **completion** conditions
   (*viewed*, *read*, *replied*) and whether conversations require a response.
2. Staff start conversations to individuals, a group or the whole class; students
   read and reply from the same inbox and can also open threads addressed to staff.
3. Messages from staff are tracked with read receipts; completion updates
   automatically (and re-opens if conditions change, per Moodle's standard
   recalculation).
4. To gate the course, add *Restrict access → Activity completion → Course mailbox
   must be marked complete* on the following activity.

## Features

- **Threaded conversations** (not flat messages).
- **Recipients:** individual users, the whole class, or groups/groupings
  (teacher capability). Students start threads addressed to staff.
- **Folders:** Inbox, Sent, Drafts and Starred, with paginated "Load more"
  loading.
- **Drafts** that can be edited and later sent.
- **Activity completion** (in addition to the core "view" rule):
  - *Read*: the student has read every staff message they received.
  - *Replied*: as above and, in every conversation flagged as requiring a
    response, the student has replied at least once.
  - *Manually marked by the teacher*, per student, for conversations that use it.
- **HTML message bodies** with **attachment** support (configurable: maximum size
  and maximum number of files per message from the site settings).
- **Privacy (GDPR):** full provider — export (including drafts) and deletion.
- **Backup / restore:** all data and the activity description files.
- **Course reset:** option to delete all conversations and messages.
- **Mobile app:** minimal support (shows the description and a link to open the
  mailbox in the browser).

## Requirements

- **Moodle 4.1 or later** (`$plugin->requires = 2022112800`).
- **PHP 7.4+** (the code stays within the 7.4 language feature set).

## Installation

1. Copy this folder to `mod/coursemail` inside your Moodle installation (the
   folder **must** be named `coursemail`).
2. Visit *Site administration → Notifications* (or run
   `php admin/cli/upgrade.php`) to install.

## Settings

*Site administration → Plugins → Activity modules → Course mailbox*:

- **Items per page** (`mod_coursemail/perpage`, default 50): the page size used
  by the folder "Load more" pagination.

## Capabilities

| Capability | Default roles | Purpose |
|---|---|---|
| `mod/coursemail:addinstance` | editingteacher | Add the activity to a course. |
| `mod/coursemail:view` | student, teacher | See the mailbox. |
| `mod/coursemail:send` | teacher, manager | Send to individuals/class/groups. These messages count for completion. |
| `mod/coursemail:reply` | student, teacher | Start a thread to staff and reply. |
| `mod/coursemail:viewall` | manager | Supervise every mailbox. |

## Development

- Frontend: Mustache templates + native AMD modules (no external frameworks).
  After editing `amd/src/*`, rebuild `amd/build/*` with `grunt amd`.
- Coding style: Moodle coding standard (`moodle-cs`).
- Tests: PHPUnit under `tests/`. Behat features under `tests/behat/` (require a
  JavaScript-capable Behat environment).

## License

GNU GPL v3 or later. See <https://www.gnu.org/licenses/>.
