<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Course mailbox';
$string['modulename'] = 'Course mailbox';
$string['modulenameplural'] = 'Course mailboxes';
$string['modulename_help'] = 'The Course mailbox activity provides an internal messaging inbox within a course. Staff can send messages to students individually, to the whole class or to groups, and students can read and reply to them. The activity can be marked complete when the student has read, or read and replied to, the messages received.';
$string['pluginadministration'] = 'Course mailbox administration';

// Capabilities.
$string['coursemail:addinstance'] = 'Add a new course mailbox';
$string['coursemail:view'] = 'View the course mailbox';
$string['coursemail:send'] = 'Send messages and start conversations (staff)';
$string['coursemail:reply'] = 'Reply to and start conversations';
$string['coursemail:viewall'] = 'View all mailboxes in the activity';

// Instance settings.
$string['settings'] = 'Mailbox settings';
$string['requireresponsedefault'] = 'Conversations require a response by default';
$string['requireresponsedefault_help'] = 'When enabled, new conversations started by staff are marked as requiring a response by default. This can be overridden per conversation. Conversations requiring a response are taken into account by the "Coursemail completion" condition.';
$string['requiremanualcompletedefault'] = 'Conversations are completed manually by default';
$string['requiremanualcompletedefault_help'] = 'When enabled, new conversations started by staff are marked by default as requiring manual completion. This can be overridden per conversation. In such conversations the activity is not completed for a student until a teacher presses "Mark as completed" for that student.';
$string['completionrequireread'] = 'Require reading all staff messages to complete';
$string['completionrequireread_help'] = 'Affects the "Coursemail completion" condition. When enabled, completing the activity also requires the student to have read every message sent to them by staff. When disabled, only the per-conversation obligations count (reply where a response is required, and manual completion where flagged), without a reading requirement.';

// Completion rules.
$string['completionmail'] = 'Student must keep up with course messaging';
$string['completionmail_help'] = 'If enabled, the activity is considered complete when the student has satisfied the requirements of each conversation they take part in: replying in conversations that require a response, and being marked as completed by a teacher in conversations flagged for manual completion. Whether reading all staff messages is also required is controlled by the "Require reading all staff messages to complete" setting.';

// Folders.
$string['inbox'] = 'Inbox';
$string['sent'] = 'Sent';
$string['drafts'] = 'Drafts';
$string['starred'] = 'Starred';
$string['supervision'] = 'Supervision';
$string['search'] = 'Search';
$string['filterunread'] = 'Unread';
$string['filterunanswered'] = 'Awaiting reply';
$string['selectconversation'] = 'Select conversation';
$string['markread'] = 'Mark as read';
$string['selectedcount'] = '{$a} selected';

// Course-page badges (shown next to the activity name).
$string['badgeunread'] = '{$a} unread';
$string['badgeunread_title'] = 'Unread messages';
$string['badgependingresponse'] = '{$a} to reply';
$string['badgependingresponse_title'] = 'Conversations awaiting your reply';
$string['badgenewfromstudents'] = '{$a} new';
$string['badgenewfromstudents_title'] = 'New messages from students';

// Attachments.
$string['attachments'] = 'Attachments';
$string['attachmentmaxbytes'] = 'Maximum attachment size';
$string['attachmentmaxbytes_desc'] = 'Maximum size, in bytes, allowed per attached file.';
$string['attachmentmaxfiles'] = 'Maximum attachments per message';
$string['attachmentmaxfiles_desc'] = 'Maximum number of files that can be attached to a single message.';
$string['attachmenttoolarge'] = 'The file is too large. The maximum allowed size is {$a}.';
$string['attachmenttoomany'] = 'You can attach at most {$a} files to a message.';
$string['uploadfailed'] = 'The file could not be uploaded.';
$string['nomessages'] = 'No messages';
$string['loadmore'] = 'Load more';
$string['selectmessage'] = 'Select a message to read';
$string['markunread'] = 'Mark as unread';
$string['markedunread'] = 'Marked as unread';
$string['collapsenav'] = 'Collapse the folder list';
$string['expandnav'] = 'Expand the folder list';
$string['scope'] = 'Mailbox scope';
$string['scopeactivity'] = 'This activity';
$string['scopecourse'] = 'Whole course';
$string['composeinactivity'] = 'Create the message in';

// Help screen.
$string['help'] = 'Help';
$string['helptitle'] = 'About this mailbox';
$string['helpback'] = 'Back to the mailbox';
$string['helpintro'] = 'This mailbox is the course\'s internal messaging: you communicate inside the platform, without going to your email.';
$string['helpabout'] = 'What it is';
$string['helpuse'] = 'Typical use';
$string['helpbenefits'] = 'Advantages';
$string['helpstudentabout'] = 'You receive your teachers\' messages here and you can write to them. You don\'t need to remember your teacher\'s name: you choose the activity and the message reaches the right person.';
$string['helpstudentuse'] = '<ul><li>Read your teachers\' messages in <strong>Inbox</strong>.</li><li>Open a message and <strong>reply</strong> in the same thread.</li><li>Press <strong>Compose</strong> to ask your teacher a question.</li><li>Use <strong>Drafts</strong> and <strong>Starred</strong> to save and flag what matters.</li></ul>';
$string['helpstudentbenefits'] = '<ul><li>You always write to the right person: no addresses to remember and no wrong recipients.</li><li>Everything stays organised by conversation within the course.</li><li>Some messages may <strong>count towards completing the activity</strong> (reading or replying to them), and here you can see what is still pending.</li><li>Private and within the platform.</li></ul>';
$string['helpteacherabout'] = 'An internal mailbox to communicate with the students of the course, without using external email.';
$string['helpteacheruse'] = '<ul><li><strong>Compose</strong> to a single student, to the <strong>whole class</strong> or to a <strong>group/grouping</strong> in a single action.</li><li>In the <strong>To:</strong> header you see each recipient with their status: <em>read / replied</em>.</li><li>Flag the conversations that <strong>require a response</strong>.</li><li>Use <strong>Mark as completed</strong> to complete the activity for each student.</li><li>Switch between <strong>This activity</strong> and <strong>Whole course</strong>.</li></ul>';
$string['helpteacherbenefits'] = '<ul><li>You reach the whole class or specific groups without managing address lists.</li><li>You see at a glance <strong>who has read and who has replied</strong> (ordinary email gives no receipt).</li><li>You can tie messaging to activity <strong>completion</strong> (read / replied / manual mark).</li><li>Private, traceable and within the course context.</li></ul>';
$string['resetmessages'] = 'Delete all conversations and messages';
$string['perpage'] = 'Items per page';
$string['perpage_desc'] = 'How many conversations or messages a mailbox folder loads per page (used by the "Load more" button).';
$string['openinbrowser'] = 'Open the mailbox in the browser';
$string['requiresresponse'] = 'Requires response';
$string['compose'] = 'Compose';
$string['messageteacher'] = 'Send message to teacher';
$string['reply'] = 'Reply';
$string['send'] = 'Send';
$string['savedraft'] = 'Save draft';
$string['cancel'] = 'Cancel';
$string['subject'] = 'Subject';
$string['messagebody'] = 'Message';
$string['to'] = 'To';
$string['recipienttype'] = 'Send to';
$string['recipients_users'] = 'Selected people';
$string['recipients_class'] = 'Everyone in the course';
$string['recipients_group'] = 'Group';
$string['recipients_staff'] = 'Teachers';
$string['recipients_allstaff'] = 'All teachers';
$string['recipients_selectedstaff'] = 'Selected teachers';
$string['recipientsingle'] = 'To: {$a}';
$string['recipientread'] = 'Read';
$string['recipientreadon'] = 'Read on {$a}';
$string['recipientunread'] = 'Not read yet';
$string['recipientreplied'] = 'Has replied';
$string['recipientnoreply'] = 'No reply yet';
$string['completedby'] = 'Completed by {$a->user} on {$a->date}';
$string['composemanualcomplete'] = 'Completed manually by a teacher (per student)';
$string['manualcompletebadge'] = 'Manual completion';
$string['completedbadge'] = 'Completed';
$string['markcompleted'] = 'Mark as completed';
$string['reopencompleted'] = 'Reopen';
$string['selectrecipients'] = 'Select recipients';
$string['selectgroups'] = 'Select groups';
$string['draftsaved'] = 'Draft saved';
$string['messagesent'] = 'Message sent';
$string['replysent'] = 'Reply sent';
$string['norecipients'] = 'You must choose at least one recipient.';
$string['norecipientsstaff'] = 'There are no teachers available to write to.';
$string['notparticipant'] = 'You do not take part in this conversation.';
$string['invaliddraft'] = 'Invalid draft message.';
$string['writereply'] = 'Write a reply...';
$string['nosubject'] = 'Please enter a subject.';
$string['nobody'] = 'Please write a message.';
$string['emptyreply'] = 'Please write a reply before sending.';
$string['mailboxplaceholder'] = 'The mailbox interface will be available in a later development phase.';
$string['noinstances'] = 'There are no course mailboxes in this course.';

// Notifications (Message API).
$string['messageprovider:newmessage'] = 'New messages in a course mailbox';
$string['notifsubject'] = 'Course mailbox: {$a}';
$string['notifsmall'] = '{$a->author} wrote to you in "{$a->activity}".';
$string['notifbody'] = 'You have a new message from {$a->author} in "{$a->activity}".
Subject: {$a->subject}
{$a->preview}';
$string['notifrequiresresponse'] = 'This message requires your reply before you can continue in the course.';
$string['notifopen'] = 'Open the course mailbox';

// Events.
$string['eventconversationcreated'] = 'Conversation created';
$string['eventmessagesent'] = 'Message sent';
$string['eventmessageread'] = 'Message read';
$string['eventmessagereplied'] = 'Message replied';
$string['eventconversationcompleted'] = 'Student marked as completed';

// Privacy.
$string['privacy:metadata'] = 'The Course mailbox plugin stores messages exchanged between users within a course activity.';
$string['privacy:metadata:coursemail_conversations'] = 'Conversations (threads) started within the activity.';
$string['privacy:metadata:coursemail_conversations:creatorid'] = 'The user who started the conversation.';
$string['privacy:metadata:coursemail_conversations:subject'] = 'The conversation subject.';
$string['privacy:metadata:coursemail_conversations:timecreated'] = 'The time the conversation was created.';
$string['privacy:metadata:coursemail_messages'] = 'Messages written within conversations.';
$string['privacy:metadata:coursemail_messages:userid'] = 'The author of the message.';
$string['privacy:metadata:coursemail_messages:body'] = 'The message content.';
$string['privacy:metadata:coursemail_messages:timesent'] = 'The time the message was sent.';
$string['privacy:metadata:coursemail_message_users'] = 'Per-user state for each message (read receipts and starring).';
$string['privacy:metadata:coursemail_message_users:userid'] = 'The user the state belongs to.';
$string['privacy:metadata:coursemail_message_users:unread'] = 'Whether the message is unread by the user.';
$string['privacy:metadata:coursemail_message_users:timeread'] = 'The time the user read the message.';
$string['privacy:metadata:coursemail_message_users:starred'] = 'Whether the user starred the message.';
$string['privacy:metadata:coursemail_manualcomplete'] = 'Per-student manual completion of a conversation, recorded by staff.';
$string['privacy:metadata:coursemail_manualcomplete:userid'] = 'The student marked as completed.';
$string['privacy:metadata:coursemail_manualcomplete:completedby'] = 'The staff member who recorded the completion.';
$string['privacy:metadata:coursemail_manualcomplete:timecompleted'] = 'The time the student was marked as completed.';
$string['privacy:metadata:coursemail_attachments'] = 'Files attached to the messages exchanged within the activity.';

// Test course (demo) tool — administration.
$string['testcoursepage'] = 'Create demo course';
$string['testcoursepagedesc'] = 'Builds a self-contained demonstration course ("{$a}") with dummy content, three Course mailbox activities, eight students in two groups and two teachers, and seeds the professor messages. Aborts if the demo course already exists.';
$string['testcoursesettingslink'] = '<a href="{$a}">Open the demo course tool</a> to create a ready-made course showcasing the Course mailbox activity.';
$string['testcoursehelpheading'] = 'Demo course accounts';
$string['testcourseintro'] = 'This tool creates a demonstration course showcasing the Course mailbox activity. All accounts below share the same password, which is not shown here for security. Use this only on a test site.';
$string['testcoursecreate'] = 'Create demo course';
$string['testcoursecreated'] = 'Demo course created successfully.';
$string['testcourseopen'] = 'Open the demo course';
$string['testcoursealreadyexists'] = 'The demo course already exists. Delete it first if you want to recreate it.';
$string['testcourseexists'] = 'The demo course already exists.';
$string['testcoursepassnote'] = 'All accounts share a single password (not shown here).';
$string['testcoursecolusername'] = 'Username';
$string['testcoursecolname'] = 'Name';
$string['testcoursecolrole'] = 'Role';
$string['testcoursecolgroup'] = 'Group';
$string['testcourseteachersheading'] = 'Teachers';
$string['testcoursestudentsheading'] = 'Students';
$string['testcourseroleteacher'] = 'Teacher';
$string['testcourserolestudent'] = 'Student';
$string['testcourseactivitiesheading'] = 'Course mailbox activities';
$string['testcourseactivitiesdesc'] = 'Three Course mailbox activities are created, all with mandatory response and automatic completion (the student must reply): "Presentación del curso" (welcome, one message to the whole class), "Tutoría a mitad de curso" (mid-course, one message to the whole class) and "Comentarios antes del examen" (one personalised message per student before the final exam). The later activities are restricted with native "restrict access by activity completion", so a student cannot move on until they have replied to the preceding coursemail(s). (Requires the site setting "Enable restricted access" to be on.)';

// Test course (demo) content — kept in Spanish on purpose (Spanish demo course).
$string['testcoursefullname'] = 'Curso de prueba de coursemail';
$string['testcoursesummary'] = '<p>Curso de demostración generado automáticamente para mostrar la actividad Buzón del curso (coursemail).</p>';
$string['testcoursesection1'] = 'Presentación';
$string['testcoursesection2'] = 'Contenidos del curso';
$string['testcoursesection3'] = 'Tutoría a mitad de curso';
$string['testcoursesection4'] = 'Preparación del examen final';
$string['testcourselabelwelcome'] = '<h4>¡Bienvenido/a al curso!</h4><p>Este es un curso de demostración. Empieza por leer el mensaje de presentación del profesor en el buzón.</p>';
$string['testcourselabelcontents'] = '<p>Aquí encontrarás los materiales y actividades del curso.</p>';
$string['testcourselabelexam'] = '<h4>📝 Examen final (simulación)</h4><p>Actividad de ejemplo que representa el examen final del curso.</p>';
$string['testcoursepagename'] = 'Programa del curso';
$string['testcoursepagebody'] = '<p>Este es el programa del curso de demostración: presentación, contenidos, tutoría y examen final.</p>';
$string['testcourseforumname'] = 'Foro de dudas';
$string['testcourseforumintro'] = '<p>Espacio para plantear dudas generales sobre el curso.</p>';
$string['testcoursemail1name'] = 'Presentación del curso';
$string['testcoursemail1intro'] = '<p>Buzón de presentación: el profesor te da la bienvenida y te invita a responder.</p>';
$string['testcoursemail2name'] = 'Tutoría a mitad de curso';
$string['testcoursemail2intro'] = '<p>Buzón de seguimiento a mitad de curso.</p>';
$string['testcoursemail3name'] = 'Comentarios antes del examen';
$string['testcoursemail3intro'] = '<p>Buzón con comentarios personalizados del profesor antes del examen final.</p>';
$string['testcoursemsg1body'] = '<p>¡Hola y bienvenido/a al curso!</p><p>Soy tu profesor/a y me alegra empezar este curso contigo. Para conocerte mejor, <strong>responde a este mensaje</strong> contándome:</p><ul><li>Qué esperas aprender en el curso (tus expectativas).</li><li>Tus circunstancias personales que deba tener en cuenta (horarios, conocimientos previos, etc.).</li></ul><p><strong>¿Por qué necesito tu respuesta?</strong> He marcado esta presentación como «requiere respuesta» porque, con lo que me cuentes, voy a <strong>adaptar el ritmo, los materiales y los grupos de trabajo</strong> a la situación real de la clase. Si no respondes, no puedo tenerte en cuenta en esa planificación inicial. ¡Gracias!</p>';
$string['testcoursemsg2body'] = '<p>Hola de nuevo:</p><p>Ya estamos a mitad de curso. Me gustaría hacer un seguimiento de cómo va todo.</p><p><strong>Responde a este mensaje</strong> indicándome:</p><ul><li>Cómo valoras tu progreso hasta ahora.</li><li>Si hay algún contenido que se te esté resistiendo.</li><li>En qué puedo ayudarte de cara a la segunda mitad.</li></ul><p><strong>¿Por qué necesito tu respuesta?</strong> He marcado esta tutoría como «requiere respuesta» porque ahora es cuando puedo <strong>detectar a tiempo</strong> quién necesita refuerzo y <strong>reorganizar la segunda mitad</strong> del curso. Tu respuesta es justo lo que me permite intervenir antes de que sea tarde. ¡Seguimos!</p>';
$string['testcoursemsg3subject'] = 'Comentarios para tu examen, {$a->firstname}';
$string['testcoursemsg3body'] = '<p>Hola {$a->firstname}:</p><p>Antes del examen final quiero darte unas notas personalizadas para ayudarte a prepararlo.</p><p><strong>Tu punto a reforzar:</strong> {$a->focus}</p><p><strong>¿Por qué necesito tu respuesta?</strong> He marcado este mensaje como «requiere respuesta» porque necesito que me <strong>confirmes si podrás reforzar ese punto por tu cuenta</strong> o si, por el contrario, prefieres que te <strong>reserve una sesión de repaso</strong> antes del examen. Según lo que me respondas, organizaré las tutorías de repaso. ¡Mucho ánimo con el examen!</p>';
$string['testcoursefocus1'] = 'los conceptos fundamentales del primer bloque (asegúrate de dominar las definiciones).';
$string['testcoursefocus2'] = 'la resolución de ejercicios prácticos (dedica tiempo a los problemas de ejemplo).';
$string['testcoursefocus3'] = 'la parte teórica del segundo bloque (repasa los esquemas y resúmenes).';
$string['testcoursefocus4'] = 'la gestión del tiempo durante el examen (practica con exámenes anteriores cronometrados).';
