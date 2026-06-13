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
 * Spanish strings for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Buzón del curso';
$string['modulename'] = 'Buzón del curso';
$string['modulenameplural'] = 'Buzones del curso';
$string['modulename_help'] = 'La actividad Buzón del curso ofrece una bandeja de mensajería interna dentro de un curso. El profesorado puede enviar mensajes a estudiantes de forma individual, a toda la clase o a grupos, y los estudiantes pueden leerlos y responderlos. La actividad puede marcarse como completada cuando el estudiante ha leído, o leído y respondido, los mensajes recibidos.';
$string['pluginadministration'] = 'Administración del buzón del curso';

// Capacidades.
$string['coursemail:addinstance'] = 'Añadir un nuevo buzón del curso';
$string['coursemail:view'] = 'Ver el buzón del curso';
$string['coursemail:send'] = 'Enviar mensajes e iniciar conversaciones (profesorado)';
$string['coursemail:reply'] = 'Responder e iniciar conversaciones';
$string['coursemail:viewall'] = 'Ver todos los buzones de la actividad';

// Ajustes de la instancia.
$string['settings'] = 'Ajustes del buzón';
$string['requireresponsedefault'] = 'Las conversaciones requieren respuesta por defecto';
$string['requireresponsedefault_help'] = 'Si se activa, las conversaciones nuevas iniciadas por el profesorado se marcan por defecto como que requieren respuesta. Puede modificarse en cada conversación. Las conversaciones que requieren respuesta se tienen en cuenta en la condición de finalización «Finalización de Coursemail».';
$string['requiremanualcompletedefault'] = 'Las conversaciones se completan manualmente por defecto';
$string['requiremanualcompletedefault_help'] = 'Si se activa, las conversaciones nuevas iniciadas por el profesorado se marcan por defecto como de finalización manual. Puede modificarse en cada conversación. En esas conversaciones la actividad no se completa para un estudiante hasta que un profesor pulsa «Dar por completada» para ese estudiante.';
$string['completionrequireread'] = 'Exigir leer todos los mensajes del profesorado para completar';
$string['completionrequireread_help'] = 'Afecta a la condición «Finalización de Coursemail». Si se activa, completar la actividad exige además que el estudiante haya leído todos los mensajes que el profesorado le ha enviado. Si se desactiva, solo cuentan las obligaciones por conversación (responder donde se requiere respuesta y la finalización manual donde esté marcada), sin requisito de lectura.';

// Reglas de finalización.
$string['completionmail'] = 'El estudiante debe atender la mensajería del curso';
$string['completionmail_help'] = 'Si se activa, la actividad se considera completada cuando el estudiante ha cumplido los requisitos de cada conversación en la que participa: responder en las conversaciones que requieren respuesta y haber sido dado por completado por un profesor en las conversaciones marcadas de finalización manual. Que se exija además leer todos los mensajes del profesorado se controla con la opción «Exigir leer todos los mensajes del profesorado para completar».';

// Carpetas.
$string['inbox'] = 'Recibidos';
$string['sent'] = 'Enviados';
$string['drafts'] = 'Borradores';
$string['starred'] = 'Destacados';
$string['supervision'] = 'Supervisión';
$string['search'] = 'Buscar';
$string['filterunread'] = 'No leídos';
$string['filterunanswered'] = 'Sin responder';
$string['selectconversation'] = 'Seleccionar conversación';
$string['markread'] = 'Marcar como leído';
$string['selectedcount'] = '{$a} seleccionados';

// Distintivos en la página del curso (junto al nombre de la actividad).
$string['badgeunread'] = '{$a} sin leer';
$string['badgeunread_title'] = 'Mensajes sin leer';
$string['badgependingresponse'] = '{$a} por responder';
$string['badgependingresponse_title'] = 'Conversaciones que esperan tu respuesta';
$string['badgenewfromstudents'] = '{$a} nuevos';
$string['badgenewfromstudents_title'] = 'Mensajes nuevos de alumnos';

// Adjuntos.
$string['attachments'] = 'Adjuntos';
$string['attachmentmaxbytes'] = 'Tamaño máximo de adjunto';
$string['attachmentmaxbytes_desc'] = 'Tamaño máximo, en bytes, permitido por archivo adjunto.';
$string['attachmentmaxfiles'] = 'Máximo de adjuntos por mensaje';
$string['attachmentmaxfiles_desc'] = 'Número máximo de archivos que se pueden adjuntar a un mensaje.';
$string['attachmenttoolarge'] = 'El archivo es demasiado grande. El tamaño máximo permitido es {$a}.';
$string['attachmenttoomany'] = 'Puedes adjuntar como máximo {$a} archivos a un mensaje.';
$string['uploadfailed'] = 'No se pudo subir el archivo.';
$string['nomessages'] = 'No hay mensajes';
$string['loadmore'] = 'Cargar más';
$string['selectmessage'] = 'Selecciona un mensaje para leer';
$string['markunread'] = 'Marcar como no leída';
$string['markedunread'] = 'Marcada como no leída';
$string['collapsenav'] = 'Contraer la lista de carpetas';
$string['expandnav'] = 'Expandir la lista de carpetas';
$string['scope'] = 'Ámbito del buzón';
$string['scopeactivity'] = 'Esta actividad';
$string['scopecourse'] = 'Todo el curso';
$string['composeinactivity'] = 'Crear el mensaje en';

// Pantalla de ayuda.
$string['help'] = 'Ayuda';
$string['helptitle'] = 'Sobre este buzón';
$string['helpback'] = 'Volver al buzón';
$string['helpintro'] = 'Este buzón es la mensajería interna del curso: te comunicas dentro de la plataforma, sin salir a tu correo electrónico.';
$string['helpabout'] = 'Qué es';
$string['helpuse'] = 'Uso típico';
$string['helpbenefits'] = 'Ventajas';
$string['helpstudentabout'] = 'Aquí recibes los mensajes de tu profesorado y puedes escribirles. No necesitas recordar el nombre de tu profesor o profesora: eliges la actividad y el mensaje llega a quien corresponde.';
$string['helpstudentuse'] = '<ul><li>Lee los mensajes de tu profesorado en <strong>Recibidos</strong>.</li><li>Abre un mensaje y <strong>responde</strong> en el mismo hilo.</li><li>Pulsa <strong>Escribir</strong> para plantear una duda a tu profesor o profesora.</li><li>Usa <strong>Borradores</strong> y <strong>Destacados</strong> para guardar y marcar lo importante.</li></ul>';
$string['helpstudentbenefits'] = '<ul><li>Escribes siempre a la persona correcta: sin direcciones que recordar ni errores de destinatario.</li><li>Todo queda ordenado por conversación dentro del curso.</li><li>Algunos mensajes pueden <strong>contar para completar la actividad</strong> (leerlos o responderlos), y aquí ves qué te falta.</li><li>Privado y dentro de la plataforma.</li></ul>';
$string['helpteacherabout'] = 'Buzón interno para comunicarte con el alumnado del curso sin usar el correo externo.';
$string['helpteacheruse'] = '<ul><li><strong>Escribe</strong> a un alumno, a <strong>toda la clase</strong> o a un <strong>grupo/agrupamiento</strong> en una sola acción.</li><li>En la cabecera <strong>Para:</strong> ves cada destinatario con su estado: <em>leído / respondido</em>.</li><li>Marca las conversaciones que <strong>requieren respuesta</strong>.</li><li>Usa <strong>Dar por completada</strong> para completar la actividad por cada alumno.</li><li>Alterna entre <strong>Esta actividad</strong> y <strong>Todo el curso</strong>.</li></ul>';
$string['helpteacherbenefits'] = '<ul><li>Llegas a toda la clase o a grupos concretos sin gestionar listas de direcciones.</li><li>Ves de un vistazo <strong>quién ha leído y quién ha respondido</strong> (el correo normal no da acuse de recibo).</li><li>Puedes vincular la mensajería a la <strong>finalización</strong> de la actividad (leído / respondido / marca manual).</li><li>Privado, trazable y en el contexto del curso.</li></ul>';
$string['resetmessages'] = 'Eliminar todas las conversaciones y mensajes';
$string['perpage'] = 'Elementos por página';
$string['perpage_desc'] = 'Cuántas conversaciones o mensajes carga por página una carpeta del buzón (lo usa el botón "Cargar más").';
$string['openinbrowser'] = 'Abrir el buzón en el navegador';
$string['requiresresponse'] = 'Requiere respuesta';
$string['compose'] = 'Redactar';
$string['messageteacher'] = 'Mandar mensaje a profesor/a';
$string['reply'] = 'Responder';
$string['send'] = 'Enviar';
$string['savedraft'] = 'Guardar borrador';
$string['cancel'] = 'Cancelar';
$string['subject'] = 'Asunto';
$string['messagebody'] = 'Mensaje';
$string['to'] = 'Para';
$string['recipienttype'] = 'Enviar a';
$string['recipients_users'] = 'Personas seleccionadas';
$string['recipients_class'] = 'Todo el curso';
$string['recipients_group'] = 'Grupo';
$string['recipients_staff'] = 'Profesorado';
$string['recipients_allstaff'] = 'Todos los profesores';
$string['recipients_selectedstaff'] = 'Profesores seleccionados';
$string['recipientsingle'] = 'Para: {$a}';
$string['recipientread'] = 'Leído';
$string['recipientreadon'] = 'Leído el {$a}';
$string['recipientunread'] = 'Aún sin leer';
$string['recipientreplied'] = 'Ha respondido';
$string['recipientnoreply'] = 'Sin respuesta';
$string['completedby'] = 'Completada por {$a->user} el {$a->date}';
$string['composemanualcomplete'] = 'La completa un profesor manualmente (por alumno)';
$string['manualcompletebadge'] = 'Finalización manual';
$string['completedbadge'] = 'Completada';
$string['markcompleted'] = 'Dar por completada';
$string['reopencompleted'] = 'Reabrir';
$string['selectrecipients'] = 'Seleccionar destinatarios';
$string['selectgroups'] = 'Seleccionar grupos';
$string['draftsaved'] = 'Borrador guardado';
$string['messagesent'] = 'Mensaje enviado';
$string['replysent'] = 'Respuesta enviada';
$string['norecipients'] = 'Debes elegir al menos un destinatario.';
$string['norecipientsstaff'] = 'No hay profesorado disponible al que escribir.';
$string['notparticipant'] = 'No participas en esta conversación.';
$string['invaliddraft'] = 'Mensaje borrador no válido.';
$string['writereply'] = 'Escribe una respuesta...';
$string['nosubject'] = 'Introduce un asunto.';
$string['nobody'] = 'Escribe un mensaje.';
$string['emptyreply'] = 'Escribe una respuesta antes de enviar.';
$string['mailboxplaceholder'] = 'La interfaz del buzón estará disponible en una fase posterior del desarrollo.';
$string['noinstances'] = 'No hay buzones del curso en este curso.';

// Notificaciones (Message API).
$string['messageprovider:newmessage'] = 'Mensajes nuevos en un buzón del curso';
$string['notifsubject'] = 'Buzón del curso: {$a}';
$string['notifsmall'] = '{$a->author} te ha escrito en «{$a->activity}».';
$string['notifbody'] = 'Tienes un mensaje nuevo de {$a->author} en «{$a->activity}».
Asunto: {$a->subject}
{$a->preview}';
$string['notifrequiresresponse'] = 'Este mensaje requiere tu respuesta para poder continuar en el curso.';
$string['notifopen'] = 'Abrir el buzón del curso';

// Eventos.
$string['eventconversationcreated'] = 'Conversación creada';
$string['eventmessagesent'] = 'Mensaje enviado';
$string['eventmessageread'] = 'Mensaje leído';
$string['eventmessagereplied'] = 'Mensaje respondido';
$string['eventconversationcompleted'] = 'Estudiante dado por completado';

// Privacidad.
$string['privacy:metadata'] = 'El plugin Buzón del curso almacena los mensajes intercambiados entre usuarios dentro de una actividad del curso.';
$string['privacy:metadata:coursemail_conversations'] = 'Conversaciones (hilos) iniciadas dentro de la actividad.';
$string['privacy:metadata:coursemail_conversations:creatorid'] = 'El usuario que inició la conversación.';
$string['privacy:metadata:coursemail_conversations:subject'] = 'El asunto de la conversación.';
$string['privacy:metadata:coursemail_conversations:timecreated'] = 'El momento en que se creó la conversación.';
$string['privacy:metadata:coursemail_messages'] = 'Mensajes escritos dentro de las conversaciones.';
$string['privacy:metadata:coursemail_messages:userid'] = 'El autor del mensaje.';
$string['privacy:metadata:coursemail_messages:body'] = 'El contenido del mensaje.';
$string['privacy:metadata:coursemail_messages:timesent'] = 'El momento en que se envió el mensaje.';
$string['privacy:metadata:coursemail_message_users'] = 'Estado por usuario de cada mensaje (acuses de lectura y destacados).';
$string['privacy:metadata:coursemail_message_users:userid'] = 'El usuario al que pertenece el estado.';
$string['privacy:metadata:coursemail_message_users:unread'] = 'Si el mensaje está sin leer por el usuario.';
$string['privacy:metadata:coursemail_message_users:timeread'] = 'El momento en que el usuario leyó el mensaje.';
$string['privacy:metadata:coursemail_message_users:starred'] = 'Si el usuario destacó el mensaje.';
$string['privacy:metadata:coursemail_manualcomplete'] = 'Finalización manual de una conversación por estudiante, registrada por el profesorado.';
$string['privacy:metadata:coursemail_manualcomplete:userid'] = 'El estudiante dado por completado.';
$string['privacy:metadata:coursemail_manualcomplete:completedby'] = 'El miembro del profesorado que registró la finalización.';
$string['privacy:metadata:coursemail_manualcomplete:timecompleted'] = 'El momento en que se dio por completado al estudiante.';
$string['privacy:metadata:coursemail_attachments'] = 'Archivos adjuntos a los mensajes intercambiados en la actividad.';

// Herramienta de curso de prueba (demo) — administración.
$string['testcoursepage'] = 'Crear curso de prueba';
$string['testcoursepagedesc'] = 'Crea un curso de demostración autocontenido ("{$a}") con contenido de relleno, tres actividades Buzón del curso, ocho alumnos en dos grupos y dos profesores, y siembra los mensajes del profesor. Aborta si el curso de prueba ya existe.';
$string['testcoursesettingslink'] = '<a href="{$a}">Abrir la herramienta de curso de prueba</a> para crear un curso ya preparado que muestra la actividad Buzón del curso.';
$string['testcoursehelpheading'] = 'Cuentas del curso de prueba';
$string['testcourseintro'] = 'Esta herramienta crea un curso de demostración que muestra la actividad Buzón del curso. Todas las cuentas siguientes comparten la misma contraseña, que no se muestra aquí por seguridad. Úsalo solo en un sitio de pruebas.';
$string['testcoursecreate'] = 'Crear curso de prueba';
$string['testcoursecreated'] = 'Curso de prueba creado correctamente.';
$string['testcourseopen'] = 'Abrir el curso de prueba';
$string['testcoursealreadyexists'] = 'El curso de prueba ya existe. Bórralo primero si quieres volver a crearlo.';
$string['testcourseexists'] = 'El curso de prueba ya existe.';
$string['testcoursepassnote'] = 'Todas las cuentas comparten una única contraseña (no se muestra aquí).';
$string['testcoursecolusername'] = 'Usuario';
$string['testcoursecolname'] = 'Nombre';
$string['testcoursecolrole'] = 'Rol';
$string['testcoursecolgroup'] = 'Grupo';
$string['testcourseteachersheading'] = 'Profesores';
$string['testcoursestudentsheading'] = 'Alumnos';
$string['testcourseroleteacher'] = 'Profesor';
$string['testcourserolestudent'] = 'Alumno';
$string['testcourseactivitiesheading'] = 'Actividades Buzón del curso';
$string['testcourseactivitiesdesc'] = 'Se crean tres actividades Buzón del curso, todas con respuesta obligatoria y finalización automática (el alumno debe responder): "Presentación del curso" (bienvenida, un mensaje a toda la clase), "Tutoría a mitad de curso" (a mitad, un mensaje a toda la clase) y "Comentarios antes del examen" (un mensaje personalizado por alumno antes del examen final). Las actividades posteriores llevan una restricción de acceso nativa por finalización, de modo que el alumno no puede avanzar hasta haber respondido al buzón o buzones anteriores. (Requiere que el ajuste del sitio "Habilitar acceso restringido" esté activado.)';

// Contenido del curso de prueba (demo) — en español (curso de demostración en español).
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
