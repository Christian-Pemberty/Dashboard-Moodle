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
$string['gradesmail_confirm_body'] = 'Vas a enviar las notas a {count} estudiante(s). Cada uno recibirá un correo y un mensaje de Moodle con las notas de la actividad: {activity}.{passnote}';
$string['gradesmail_noselection'] = 'Selecciona al menos un estudiante primero.';
$string['gradesmail_nostudents'] = 'No se encontraron estudiantes matriculados en este curso.';
$string['gradesmail_results'] = 'Resultados';
$string['gradesmail_status_sent'] = 'Enviado';
$string['gradesmail_status_error'] = 'Error';
$string['gradesmail_status_notenrolled'] = 'No matriculado';
$string['gradesmail_channel_email'] = 'Correo';
$string['gradesmail_channel_moodle'] = 'Mensaje de Moodle';
$string['gradesmail_nograde'] = 'Sin nota aún';
// Grades notification - evaluation filters, history and dialogs.
$string['gradesmail_tab_send'] = 'Enviar notas';
$string['gradesmail_tab_history'] = 'Historial de envíos';
$string['gradesmail_evalselect'] = 'Actividad evaluada';
$string['gradesmail_passmin'] = 'Nota mínima de aprobación';
$string['gradesmail_passmin_custom'] = 'Nota de aprobación tomada de la actividad en Moodle (editable).';
$string['gradesmail_passmin_fallback'] = 'La actividad no tiene nota de aprobación en Moodle; se usó el 50% de su máximo. Ajusta si es necesario.';
$string['gradesmail_passmin_none'] = 'Esta actividad no tiene una nota de aprobación disponible; defínela para usar los filtros rápidos.';
$string['gradesmail_filter_all'] = 'Todos';
$string['gradesmail_filter_passed'] = 'Aprobados';
$string['gradesmail_filter_failed'] = 'Reprobados';
$string['gradesmail_filter_pending'] = 'Sin nota / pendientes';
$string['gradesmail_searchplaceholder'] = 'Buscar por nombre de estudiante...';
$string['gradesmail_resultcol'] = 'Resultado de la actividad';
$string['gradesmail_result_passed'] = 'Aprobado';
$string['gradesmail_result_failed'] = 'Reprobado';
$string['gradesmail_result_pending'] = 'Sin nota';
$string['gradesmail_hist_title'] = 'Historial de envíos de notas';
$string['gradesmail_hist_empty'] = 'Aún no se ha registrado ningún envío de notas en este curso.';
$string['gradesmail_hist_more'] = 'Cargar más';
$string['gradesmail_hist_state_all'] = 'Todos los estados';
$string['gradesmail_hist_state_sent'] = 'Entregado';
$string['gradesmail_hist_state_error'] = 'Rebotó / falló';
$string['gradesmail_col_date'] = 'Fecha';
$string['gradesmail_col_time'] = 'Hora';
$string['gradesmail_col_teacher'] = 'Docente';
$string['gradesmail_col_status'] = 'Estado';
$string['gradesmail_col_subject'] = 'Asunto';
$string['gradesmail_hist_bounced'] = 'Rebotó / falló';
// Confirmation and alert dialogs (centered, non-blocking).
$string['gradesmail_confirm_title'] = '¿Enviar las notas a los estudiantes?';
$string['gradesmail_confirm_passnote'] = ' Solo se usa para la clasificación por nota mínima de aprobación; no cambia el contenido del correo.';
$string['gradesmail_confirm_noactivity'] = ' (no se seleccionó una actividad; se mostrarán los resultados globales del curso)';
$string['gradesmail_confirm_cancel'] = 'Cancelar';
$string['gradesmail_confirm_send'] = 'Enviar ahora';
$string['gradesmail_alert_noselection'] = 'Selecciona al menos un estudiante primero.';
$string['gradesmail_error_title'] = 'Algo salió mal';
$string['gradesmail_error_generic'] = 'La solicitud falló. Por favor, inténtalo de nuevo.';
// Vista previa editable (T1) + chips de estudiantes seleccionados (T3a).
$string['gradesmail_preview_edit'] = 'Editar';
$string['gradesmail_preview_done'] = 'Listo';
$string['gradesmail_preview_chips_hint'] = 'Haz clic en un nombre para ver su correo';
// Gestión del historial (T3b): borrar por fila + limpiar todo.
$string['gradesmail_hist_del_title'] = '¿Borrar este registro?';
$string['gradesmail_hist_del_body'] = 'Se eliminará el registro de envío de notas de este estudiante. Esta acción no se puede deshacer.';
$string['gradesmail_hist_del_row'] = 'Borrar registro';
$string['gradesmail_hist_clear'] = 'Limpiar historial';
$string['gradesmail_hist_clear_title'] = '¿Limpiar todo el historial?';
$string['gradesmail_hist_clear_body'] = 'Se borrarán permanentemente todos los registros de envío de notas de este curso. Esta acción no se puede deshacer.';
$string['gradesmail_hist_clear_done'] = 'Historial limpiado: {count} registro(s) eliminado(s).';

$string['invalidrecipient'] = 'El destinatario seleccionado no es un usuario activo y matriculado en este curso.';
