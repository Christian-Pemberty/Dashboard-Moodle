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
 * Send the grades notification (email + Moodle message) to selected students.
 *
 * @package    block_mwa_dashboard
 * @copyright  2026 Bruno Porto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('course', PARAM_INT);
require_login($courseid);

$context = context_course::instance($courseid);
require_capability('block/mwa_dashboard:view', $context);
require_capability('block/mwa_dashboard:manageinterventions', $context);

$PAGE->set_context($context);
$PAGE->set_url('/blocks/mwa_dashboard/enviarnotas.php', ['course' => $courseid]);
$PAGE->set_title(get_string('gradesmail_title', 'block_mwa_dashboard'));
$PAGE->set_heading(get_string('gradesmail_title', 'block_mwa_dashboard'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_cacheable(false);

$PAGE->requires->css(new moodle_url('/blocks/mwa_dashboard/styles/gradesmail.css'));

$gmkeys = [
    'students', 'col_student', 'col_grade', 'selectall', 'deselectall', 'selectedcount',
    // T8: el textarea opcional se retiró; el mensaje (título + texto) se
    // edita dentro de la vista previa. 'comment_hint' orienta al docente.
    'comment_hint', 'preview', 'previewtitle', 'previewsubject', 'send',
    'sending', 'confirm_send', 'noselection', 'nostudents', 'results', 'status_sent',
    'status_error', 'status_notenrolled', 'channel_email', 'channel_moodle', 'nograde',
    'error_generic',
    // Tabs / activity selection / pass mark.
    'tab_send', 'tab_history', 'evalselect', 'evalselect_empty', 'act_hidden_tag',
    'passmin', 'passmin_custom', 'passmin_fallback', 'passmin_none',
    // Quick filters + search + result column.
    'filter_all', 'filter_passed', 'filter_failed', 'filter_pending',
    'searchplaceholder', 'resultcol', 'result_passed', 'result_failed', 'result_pending',
    // History tab.
    'hist_title', 'hist_empty', 'hist_more', 'hist_state_all',
    'hist_state_sent', 'hist_state_error',
    'col_date', 'col_time', 'col_teacher', 'col_status', 'col_subject', 'hist_bounced',
    // Confirmation and alert dialogs (centered, non-blocking).
    'confirm_title', 'confirm_body', 'confirm_passnote', 'confirm_noactivity',
    'confirm_cancel', 'alert_noselection', 'error_title',
    // Preview editor (plan T1) + student chips (plan T3a).
    'preview_edit', 'preview_done', 'preview_chips_hint', 'email_commentlabel',
    // History management (plan T3b): row delete + clear all.
    'hist_del_title', 'hist_del_body', 'hist_del_row',
    'hist_clear', 'hist_clear_title', 'hist_clear_body', 'hist_clear_done',
];
// Fetch the localised UI strings. "gradesmail_selectedcount" keeps a {count}
// placeholder that enviarnotas.js injects at runtime; we pass a same-named
// sentinel so get_string() always resolves a real string.
$gmstr = [];
foreach ($gmkeys as $gmkey) {
    $gmstr[$gmkey] = get_string('gradesmail_' . $gmkey, 'block_mwa_dashboard');
}

$PAGE->requires->js_call_amd('block_mwa_dashboard/enviarnotas', 'init', [
    [
        'courseid' => $courseid,
        'groupid'  => 0,
    ],
]);

echo $OUTPUT->header();

// The ~60 UI strings are handed to the AMD module through this inline JSON
// block (window.MWA_GRADEMAIL_STR) instead of as js_call_amd() arguments:
// passing them there would push the argument string well past the 1024-char
// limit and trigger the core "Too much data passed as arguments" debugging
// notice. JSON_HEX_TAG keeps the inline <script> safe even if a translated
// string ever contains "</script>".
$gmstrjson = json_encode($gmstr, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
echo html_writer::tag('script', 'window.MWA_GRADEMAIL_STR=' . $gmstrjson . ';',
    ['type' => 'application/javascript']);

// Build the static fallback markup with start_tag()/end_tag(). Those helpers
// have a stable signature in every Moodle version (the 2nd argument of
// start_tag() is always the attributes array). html_writer::div() is NOT
// portable: on some Moodle cores its 2nd argument is a CSS class STRING and
// only the 3rd argument accepts the attributes array. Passing the attributes
// array into the $class slot puts an array into an attribute value and PHP 8
// dies with "htmlspecialchars(): Argument #1 ($string) must be of type string,
// array given" (the exact reported crash).
$mwaCard =
    html_writer::tag('h1', get_string('gradesmail_title', 'block_mwa_dashboard'),
        ['style' => 'font-size:1.4rem;font-weight:800;color:#1f2937;margin:0 0 .5rem;'])
    . html_writer::tag('p', get_string('gradesmail_page_desc', 'block_mwa_dashboard'),
        ['style' => 'font-size:.95rem;color:#495057;margin-bottom:1rem;'])
    . html_writer::start_tag('div',
        ['class' => 'alert alert-info', 'style' => 'font-size:.9rem;border-radius:.5rem;'])
    . s(get_string('gradesmail_jsnotice', 'block_mwa_dashboard'))
    . html_writer::end_tag('div');

echo html_writer::start_tag('div', [
    'id'            => 'mwaGradesMailRoot',
    'data-courseid' => (int)$courseid,
    'data-groupid'  => 0,
    'style'         =>
        'max-width:760px;margin:1.5rem auto 2rem;padding:1.25rem;background:#fff;' .
        'border:1px solid #e3e8ee;border-radius:.75rem;box-shadow:0 1px 4px rgba(15,35,60,.06);',
]) . $mwaCard . html_writer::end_tag('div');

echo $OUTPUT->footer();
