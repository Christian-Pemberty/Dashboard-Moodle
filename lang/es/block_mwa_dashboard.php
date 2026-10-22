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
 * Spanish language strings for block_mwa_dashboard.
 *
 * @package    block_mwa_dashboard
 * @copyright  2026 Bruno Porto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Grades notification (email + Moodle message) - email content.
$string['gradesmail_email_subj'] = 'Tus notas en {$a->course}: {$a->grade}';
$string['gradesmail_email_subj_nograde'] = 'Tus notas en {$a->course}';
$string['gradesmail_email_hi'] = 'Hola {$a->name},';
$string['gradesmail_email_intro'] = 'A continuación te compartimos tu avance en el curso {$a->course}, a fecha {$a->date}.';
$string['gradesmail_email_global'] = 'Resultado global: {$a->grade}';
$string['gradesmail_email_no_grade'] = 'Aún no se ha registrado ninguna nota para este curso.';
$string['gradesmail_email_activity'] = 'Actividad';
$string['gradesmail_email_grade'] = 'Nota';
$string['gradesmail_email_pending'] = 'Pendiente';
$string['gradesmail_email_commentlabel'] = 'Un mensaje de tu docente';
$string['gradesmail_email_footer'] = 'Este es un mensaje automático enviado por tu docente a través de la plataforma del curso. Por favor, no respondas a este correo.';
$string['gradesmail_error_nonestudent'] = 'Selecciona al menos un estudiante antes de enviar.';
// Grades notification - UI (student list, preview, send, results).
$string['gradesmail_button'] = 'Enviar notas';
$string['gradesmail_title'] = 'Enviar notas a estudiantes';
$string['gradesmail_page_desc'] = 'Selecciona uno o varios estudiantes, visualiza el mensaje exacto y envía sus notas (correo + mensaje de Moodle).';
$string['gradesmail_jsnotice'] = 'Esta página requiere que JavaScript esté habilitado. Por favor, habilítalo y recarga la página.';
$string['gradesmail_students'] = 'Estudiantes';
$string['gradesmail_col_student'] = 'Estudiante';
$string['gradesmail_col_grade'] = 'Resultado global';
$string['gradesmail_selectall'] = 'Seleccionar todos';
$string['gradesmail_deselectall'] = 'Deseleccionar todos';
$string['gradesmail_selectedcount'] = '{count} estudiante(s) seleccionado(s)';
$string['gradesmail_comment'] = 'Mensaje para incluir en el correo (opcional)';
$string['gradesmail_commentplaceholder'] = 'Una nota para los estudiantes, que se incluirá debajo de la tabla de notas en el correo...';
$string['gradesmail_preview'] = 'Vista previa';
$string['gradesmail_previewtitle'] = 'Vista previa del correo';
$string['gradesmail_previewsubject'] = 'Asunto';
$string['gradesmail_send'] = 'Enviar notas';
$string['gradesmail_sending'] = 'Enviando...';
$string['gradesmail_confirm_send'] = '¿Enviar las notas a todos los estudiantes seleccionados? Cada estudiante recibirá un correo y un mensaje de Moodle.';
$string['gradesmail_noselection'] = 'Selecciona al menos un estudiante primero.';
$string['gradesmail_nostudents'] = 'No se encontraron estudiantes matriculados en este curso.';
$string['gradesmail_results'] = 'Resultados';
$string['gradesmail_status_sent'] = 'Enviado';
$string['gradesmail_status_error'] = 'Error';
$string['gradesmail_status_notenrolled'] = 'No matriculado';
$string['gradesmail_channel_email'] = 'Correo';
$string['gradesmail_channel_moodle'] = 'Mensaje de Moodle';
$string['gradesmail_nograde'] = 'Sin nota aún';
$string['gradesmail_error_generic'] = 'La solicitud falló. Por favor, inténtalo de nuevo.';

$string['invalidrecipient'] = 'El destinatario seleccionado no es un usuario activo y matriculado en este curso.';
