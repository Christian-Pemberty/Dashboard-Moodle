<?php
// This file is part of Moodle - http://moodle.org/.

namespace block_mwa_dashboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the "Send grades to students" module: preview, batch send, capabilities
 * and the per-student audit trail in block_mwa_dashboard_messages.
 *
 * @package    block_mwa_dashboard
 * @copyright  2026 Bruno Porto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class send_grades_mail_test extends \advanced_testcase {

    /**
     * Enrol a new user in a course and return the user record.
     *
     * @param \stdClass $course Course record.
     * @param string $role Shortname of the role to assign.
     * @param string $firstname First name.
     * @param string $lastname Last name.
     * @return \stdClass Created and enrolled user.
     */
    private function enrol_user_in_course(\stdClass $course, string $role, string $firstname, string $lastname): \stdClass {
        $generator = $this->getDataGenerator();
        $user = $generator->create_user(['firstname' => $firstname, 'lastname' => $lastname]);
        $generator->enrol_user($user->id, $course->id, $role);
        return $user;
    }

    /**
     * Create a visible (or hidden) graded assignment activity. The student grade
     * is recorded directly in the grade grader, which is exactly what
     * api::get_grades() reads for the grades email.
     *
     * @param \stdClass $course Course record.
     * @param \stdClass $student Student receiving the grade.
     * @param float $grade Score recorded for the student.
     * @param int $grademax Grade item maximum.
     * @param bool $hidden Whether the grade item should be hidden.
     * @return \stdClass Assignment activity record.
     */
    private function create_graded_assign(\stdClass $course, \stdClass $student, float $grade, int $grademax = 10, bool $hidden = false): \stdClass {
        global $DB;

        $generator = $this->getDataGenerator();
        $assign = $generator->create_module('assign', [
            'courseid' => $course->id,
            'name'     => 'Test assignment',
            'grademax' => $grademax,
        ]);
        if ($hidden) {
            $DB->set_field(
                'grade_items',
                'hidden',
                1,
                ['courseid' => $course->id, 'itemmodule' => 'assign', 'iteminstance' => $assign->instance]
            );
        }

        $gradeitem = \grade_item::fetch([
            'courseid'     => $course->id,
            'itemmodule'   => 'assign',
            'iteminstance' => $assign->instance,
        ]);

        $record = new \stdClass();
        $record->itemid     = $gradeitem->id;
        $record->userid     = $student->id;
        $record->rawgrade   = $grade;
        $record->finalgrade = $grade;
        $record->hidden     = 0;
        $record->locked     = 0;
        $record->timecreated = time();
        $DB->insert_record('grade_grades', $record);

        return $assign;
    }

    /**
     * Count how many act_i columns the activity_names metadata row carries.
     *
     * @param array $rows Rows produced by api::get_grades().
     * @return int Number of activities in the metadata row (0 when absent).
     */
    private function count_activity_meta(array $rows): int {
        foreach ($rows as $r) {
            if (($r['__mwa_type__'] ?? '') === 'activity_names') {
                $count = 0;
                $i = 1;
                while (array_key_exists('act_' . $i, $r)) {
                    $count++;
                    $i++;
                }
                return $count;
            }
        }
        return 0;
    }

    /**
     * Enrol a user in a course and give them the plugin "view" capability but
     * strip the "manageinterventions" capability, so the grades mail
     * capability check is what fails.
     *
     * @param \stdClass $course Course record.
     * @return \stdClass User with manageinterventions prevented.
     */
    private function create_user_without_manage_interventions(\stdClass $course): \stdClass {
        $user = $this->enrol_user_in_course($course, 'teacher', 'Sin', 'Permiso');
        $ctx = \context_course::instance($course->id);
        set_user_capability_override($user->id, 'block/mwa_dashboard:manageinterventions', false, $ctx->id);
        return $user;
    }

    /**
     * A preview of one student's email must not contain any other student's
     * grade, name or email.
     */
    public function test_preview_contains_only_the_requested_students_data(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ana = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $bruno = $this->enrol_user_in_course($course, 'student', 'Bruno', 'Bachiller');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5);
        $this->create_graded_assign($course, $bruno, 9.5);

        $preview = \block_mwa_dashboard\external::get_grades_mail_preview($course->id, $ana->id, 0, '');

        $this->assertSame('Ana Alumna', $preview['studentname']);
        $this->assertStringContainsString('8.5', $preview['subject']);
        $this->assertStringContainsString('8.5', $preview['html']);
        $this->assertStringNotContainsString('9.5', $preview['subject']);
        $this->assertStringNotContainsString('9.5', $preview['html']);
        $this->assertStringNotContainsString('Bruno Bachiller', $preview['html']);
        $this->assertStringNotContainsString($bruno->email, $preview['html']);
    }

    /**
     * A student without any recorded grade must see "Pending" per activity and a
     * "no grade" notice in the global-result line.
     */
    public function test_preview_marks_ungraded_activities_as_pending(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $dora = $this->enrol_user_in_course($course, 'student', 'Dora', 'Sinnota');
        $edwin = $this->enrol_user_in_course($course, 'student', 'Edwin', 'Connota');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        // Create the gradeable activity but only record a grade for Edwin, so
        // the course has at least one usable grade item and Dora has none.
        $this->create_graded_assign($course, $edwin, 8.5);

        $preview = \block_mwa_dashboard\external::get_grades_mail_preview($course->id, $dora->id, 0, '');

        $this->assertStringContainsString(get_string('gradesmail_email_pending', 'block_mwa_dashboard'), $preview['html']);
        $this->assertStringContainsString(get_string('gradesmail_email_no_grade', 'block_mwa_dashboard'), $preview['html']);
        $this->assertStringNotContainsString('8.5', $preview['html']);
    }

    /**
     * Batch send: enrolled recipients succeed, non-enrolled recipients are
     * reported as not_enrolled, and each enrolled student gets exactly one
     * audit row with the correct flags and their own grade only.
     */
    public function test_send_batch_mixed_enrolled_and_not_enrolled(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ana = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $bruno = $this->enrol_user_in_course($course, 'student', 'Bruno', 'Bachiller');
        $outsider = $this->getDataGenerator()->create_user(['firstname' => 'Otro', 'lastname' => 'Cronista']);
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5);
        $this->create_graded_assign($course, $bruno, 9.5);

        $result = \block_mwa_dashboard\external::send_grades_mail($course->id, [$ana->id, $bruno->id, $outsider->id], '', 0);

        $this->assertSame(2, $result['sent']);
        $this->assertSame(1, $result['failed']);
        $this->assertFalse($result['success']);
        $this->assertCount(3, $result['results']);

        foreach ($result['results'] as $r) {
            if ((int)$r['userid'] === (int)$outsider->id) {
                $this->assertFalse($r['success']);
                $this->assertSame('not_enrolled', $r['status']);
                $this->assertSame(0, $r['recordid']);
                $this->assertFalse($r['emailsent']);
                $this->assertFalse($r['moodlesent']);
                continue;
            }
            $this->assertTrue($r['success']);
            $this->assertSame('sent', $r['status']);
            $this->assertTrue($r['emailsent']);
            $this->assertTrue($r['moodlesent']);
            $this->assertGreaterThan(0, $r['recordid']);
        }

        $rows = $DB->get_records('block_mwa_dashboard_messages',
            ['courseid' => $course->id, 'intervention_reason' => 'Grades']);
        $this->assertCount(2, $rows);

        $anarow = null;
        $brunarow = null;
        foreach ($rows as $row) {
            $this->assertContains((int)$row->userid, [(int)$ana->id, (int)$bruno->id]);
            $this->assertSame((int)$teacher->id, (int)$row->teacherid);
            $this->assertSame('sent', $row->status);
            $this->assertSame('both', $row->send_type);
            $this->assertSame('Grades', $row->intervention_reason);
            $this->assertGreaterThan(0, (int)$row->moodle_msgid);
            if ((int)$row->userid === (int)$ana->id) {
                $anarow = $row;
            }
            if ((int)$row->userid === (int)$bruno->id) {
                $brunarow = $row;
            }
        }
        $this->assertNotNull($anarow);
        $this->assertNotNull($brunarow);

        $this->assertStringContainsString('8.5', $anarow->message);
        $this->assertStringNotContainsString('9.5', $anarow->message);
        $this->assertStringContainsString('9.5', $brunarow->message);
        $this->assertStringNotContainsString('8.5', $brunarow->message);
    }

    /**
     * A user who has the "view" capability but not "manageinterventions" must be
     * rejected by the send.
     */
    public function test_send_requires_manage_interventions_capability(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ana = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $noperm = $this->create_user_without_manage_interventions($course);
        $this->setUser($noperm);

        $this->expectException(\required_capability_exception::class);
        \block_mwa_dashboard\external::send_grades_mail($course->id, [$ana->id]);
    }

    /**
     * A user who has the "view" capability but not "manageinterventions" must be
     * rejected by the preview.
     */
    public function test_preview_requires_manage_interventions_capability(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ana = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $noperm = $this->create_user_without_manage_interventions($course);
        $this->setUser($noperm);

        $this->expectException(\required_capability_exception::class);
        \block_mwa_dashboard\external::get_grades_mail_preview($course->id, $ana->id, 0, '');
    }

    /**
     * A course with no usable grade items must still send successfully and the
     * email must say there is no grade yet instead of a blank table.
     */
    public function test_send_course_without_grade_items(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $dora = $this->enrol_user_in_course($course, 'student', 'Dora', 'Sinnota');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $result = \block_mwa_dashboard\external::send_grades_mail($course->id, [$dora->id], '', 0);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, $result['failed']);

        $row = $DB->get_record('block_mwa_dashboard_messages',
            ['courseid' => $course->id, 'userid' => $dora->id, 'intervention_reason' => 'Grades']);
        $this->assertNotFalse($row);
        $this->assertSame('sent', $row->status);
        $this->assertStringContainsString(get_string('gradesmail_email_no_grade', 'block_mwa_dashboard'), $row->message);
        // The subject must contain the real course name, not the raw placeholder.
        $expectedsubject = get_string('gradesmail_email_subj_nograde', 'block_mwa_dashboard',
            ['course' => $course->fullname]);
        $this->assertSame($expectedsubject, $row->subject);
        $this->assertStringContainsString($course->fullname, $row->subject);
        $this->assertStringNotContainsString('{course}', $row->subject);
    }

    /**
     * The teacher's optional comment must be included in the sent email body.
     */
    public function test_send_includes_teacher_comment(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ana = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5);

        $comment = 'Comentario de prueba del docente.';
        $result = \block_mwa_dashboard\external::send_grades_mail($course->id, [$ana->id], $comment, 0);

        $this->assertSame(1, $result['sent']);

        $row = $DB->get_record('block_mwa_dashboard_messages',
            ['courseid' => $course->id, 'userid' => $ana->id, 'intervention_reason' => 'Grades']);
        $this->assertNotFalse($row);
        $this->assertStringContainsString(get_string('gradesmail_email_commentlabel', 'block_mwa_dashboard'), $row->message);
        $this->assertStringContainsString($comment, $row->message);
    }

    /**
     * T8 (user request 28-oct): the section TITLE of the teacher message is now
     * editable too. An empty title must fall back to the localized default
     * (and the anchor must expose data-gm-field="commentlabel" for the in-app
     * editor); a custom title must replace it in preview AND in the sent mail.
     */
    public function test_comment_label_editable_default_and_custom(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ana = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5);

        // 1) Empty title -> localized default label, editable anchor present.
        $preview = \block_mwa_dashboard\external::get_grades_mail_preview(
            $course->id, $ana->id, 0, 'Cuerpo del mensaje.', '');
        $this->assertStringContainsString(get_string('gradesmail_email_commentlabel', 'block_mwa_dashboard'), $preview['html']);
        $this->assertStringContainsString('data-gm-field="commentlabel"', $preview['html']);
        $this->assertStringNotContainsString('Etiqueta personalizada:', $preview['html']);

        // 2) Custom title -> replaces the default in the preview ...
        $previewcustom = \block_mwa_dashboard\external::get_grades_mail_preview(
            $course->id, $ana->id, 0, 'Cuerpo del mensaje.', 'Etiqueta personalizada:');
        $this->assertStringNotContainsString(get_string('gradesmail_email_commentlabel', 'block_mwa_dashboard'), $previewcustom['html']);
        $this->assertStringContainsString('Etiqueta personalizada:', $previewcustom['html']);

        // ... and in the actually sent email (WYSIWYG).
        $result = \block_mwa_dashboard\external::send_grades_mail(
            $course->id, [$ana->id], 'Cuerpo del mensaje.', 0, 'Etiqueta personalizada:');
        $this->assertSame(1, $result['sent']);

        $row = $DB->get_record('block_mwa_dashboard_messages',
            ['courseid' => $course->id, 'userid' => $ana->id, 'intervention_reason' => 'Grades']);
        $this->assertNotFalse($row);
        $this->assertStringContainsString('Etiqueta personalizada:', $row->message);
    }

    /**
     * T6 (2026-10-27 decision): the grades-SEND flow deliberately includes grade
     * items hidden in the gradebook (the teacher selects and sends them without
     * unhiding them). This test is the INVERTED version of the former
     * "test_email_excludes_hidden_grade_items": the hidden assign's 9.9 must
     * now appear in the preview/email.
     */
    public function test_email_preview_includes_hidden_grade_items(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ana = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5, 10, false);
        $this->create_graded_assign($course, $ana, 9.9, 10, true);

        $preview = \block_mwa_dashboard\external::get_grades_mail_preview($course->id, $ana->id, 0, '');

        $this->assertStringContainsString('8.5 / 10', $preview['subject']);
        $this->assertStringContainsString('8.5', $preview['html']);
        $this->assertStringContainsString('9.9', $preview['html']);
    }

    /**
     * T6 regression guard: api::get_grades() WITHOUT the includehidden flag must
     * still exclude hidden grade items (dashboard / snapshots / export keep the
     * historical behaviour), while the send-flow opt-in includes them.
     */
    public function test_api_default_still_excludes_hidden(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ana = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5, 10, false);
        $this->create_graded_assign($course, $ana, 9.9, 10, true);

        $defaultcount = $this->count_activity_meta(\block_mwa_dashboard\api::get_grades($course->id));
        $hiddencount = $this->count_activity_meta(\block_mwa_dashboard\api::get_grades($course->id, 0, true));

        $this->assertSame(1, $defaultcount, 'Default must keep excluding the hidden item.');
        $this->assertSame(2, $hiddencount, 'Send-flow opt-in must include the hidden item.');
    }

    /**
     * T6: get_grades_send_options() must expose grade items hidden in the
     * gradebook with "hidden" => true and their REAL grademax / passmark
     * (50% fallback when Moodle has no explicit pass mark).
     */
    public function test_send_options_exposes_hidden_flag(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ana = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 9.9, 10, true);

        $opts = \block_mwa_dashboard\external::get_grades_send_options($course->id, 0);

        $this->assertCount(1, $opts['activities']);
        $this->assertTrue($opts['activities'][0]['hidden']);
        $this->assertEqualsWithDelta(10, $opts['activities'][0]['grademax'], 0.001);
        $this->assertEqualsWithDelta(5, $opts['activities'][0]['passmark'], 0.001);
        $this->assertFalse($opts['activities'][0]['hascustompassmark']);
    }

    // ---- Plan SS15 / T1 — comment confinement (anti-manipulation) -----------

    /**
     * The teacher comment is the ONLY free-form input of the email. A malicious
     * comment must not alter the rebuilt parts (subject, greeting from the DB)
     * and must appear exactly once, inside the editable anchor.
     */
    public function test_comment_confined_to_editable_anchor_and_rebuilt_data(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $ana     = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5);

        $malicious = 'HI EVERYONE!! <script>alert(1)</script> Nota real: 99/100';

        // 1) The subject must be the DB-rebuilt one, and never inherit comment content.
        $expectedsubject = get_string('gradesmail_email_subj', 'block_mwa_dashboard',
            ['course' => $course->fullname, 'grade' => '8.5 / 10']);

        $preview = \block_mwa_dashboard\external::get_grades_mail_preview($course->id, $ana->id, 0, $malicious);

        $this->assertSame($expectedsubject, $preview['subject']);
        $this->assertStringNotContainsString('99/100', $preview['subject']);

        // 2) Greeting and table are rebuilt from the DB, not from the comment.
        $this->assertStringContainsString(
            s(get_string('gradesmail_email_hi', 'block_mwa_dashboard', ['name' => 'Ana Alumna'])),
            $preview['html']);
        $this->assertStringContainsString('8.5', $preview['html']);

        // 3) The comment appears exactly once (no duplication) and ONLY inside
        //    the <div data-gm-editable="comment"> anchor.
        $html     = $preview['html'];
        $anchor   = strpos($html, 'data-gm-editable="comment"');
        $comment  = strpos($html, 'HI EVERYONE');
        $this->assertNotFalse($anchor, 'The editable anchor must exist');
        $this->assertNotFalse($comment, 'The comment text must be present');
        $this->assertGreaterThan($anchor, $comment, 'The comment must be after the anchor start');
        $close = strpos($html, '</div>', $comment);
        $this->assertNotFalse($close, 'The anchor must close after the comment');
        $this->assertSame(1, substr_count($html, 'HI EVERYONE'), 'The comment must appear exactly once');
        $this->assertStringContainsString('data-gm-field="commenttext"', $html);
    }

    // ---- Plan SS15 / T2 — Gmail/Outlook-safe inline styles ------------------

    /**
     * The mail HTML must be email-safe: no <style> block, web-safe inline font
     * stack and an inline-bordered table with a coloured header background.
     */
    public function test_mail_html_uses_inline_styles_only(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $ana     = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5);

        $preview = \block_mwa_dashboard\external::get_grades_mail_preview($course->id, $ana->id, 0, '');
        $html    = $preview['html'];

        // No external/centralised styling: inline only.
        $this->assertStringNotContainsString('<style>', $html);
        $this->assertStringNotContainsString('<link', $html);

        // Web-safe font stack on the wrapper.
        $this->assertStringContainsString('font-family:Arial,Helvetica,sans-serif', $html);

        // Inline-bordered table with the coloured header (acceptance T2).
        $this->assertStringContainsString('border-collapse:collapse', $html);
        $this->assertStringContainsString('border:1px solid #d9dee5', $html);
        $this->assertStringContainsString('background:#f1f3f7', $html);

        // Plain text keeps derivating from the same HTML source (WYSIWYG):
        // the activity name must still be present after html_to_text().
        $this->assertStringContainsString('Test assignment', $preview['plain']);
    }

    // ---- Plan SS15 / T3 — history management -------------------------------

    /**
     * Only the original sender (or a site admin) can delete a grades-mail
     * audit row; a foreign teacher gets a clean refusal and the row survives.
     */
    public function test_delete_grades_mail_record_owner_only(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $dora    = $this->enrol_user_in_course($course, 'student', 'Dora', 'Alumna');
        $tito    = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $carla   = $this->enrol_user_in_course($course, 'editingteacher', 'Carla', 'Docente');

        $this->setUser($tito);
        $result = \block_mwa_dashboard\external::send_grades_mail($course->id, [$dora->id], '', 0);
        $this->assertSame(1, $result['sent']);

        $row = $DB->get_record('block_mwa_dashboard_messages',
            ['courseid' => $course->id, 'userid' => $dora->id, 'intervention_reason' => 'Grades']);
        $this->assertNotFalse($row);

        // 1) Foreign teacher: refused, row untouched.
        $this->setUser($carla);
        $this->assertSame(['success' => false], \block_mwa_dashboard\external::delete_grades_mail_record($row->id));
        $this->assertNotFalse($DB->get_record('block_mwa_dashboard_messages', ['id' => $row->id], 'id', IGNORE_MISSING));

        // 2) Owner: deleted.
        $this->setUser($tito);
        $this->assertSame(['success' => true], \block_mwa_dashboard\external::delete_grades_mail_record($row->id));
        $this->assertFalse($DB->get_record('block_mwa_dashboard_messages', ['id' => $row->id], 'id', IGNORE_MISSING));

        // 3) Unknown ID: clean refusal (no exception).
        $this->assertSame(['success' => false], \block_mwa_dashboard\external::delete_grades_mail_record($row->id));
    }

    /**
     * Pedagogical intervention rows (intervention_reason <> 'Grades') are
     * never deletable through the grades-history web service, even for the
     * teaching owner of the row.
     */
    public function test_delete_grades_mail_record_refuses_non_grades_rows(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $ana     = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $tito    = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($tito);

        // A pedagogical-intervention style row, owned by the same teacher.
        $record = new \stdClass();
        $record->courseid            = $course->id;
        $record->userid              = $ana->id;
        $record->teacherid           = $tito->id;
        $record->subject             = 'Intervención pedagógica';
        $record->message             = 'Mensaje de intervención.';
        $record->timesent            = time();
        $record->status              = 'sent';
        $record->ai_generated        = 0;
        $record->intervention_reason = 'Low engagement';
        $record->send_type           = 'moodle';
        $record->teacher_note_updated = 0;
        $intervid = $DB->insert_record('block_mwa_dashboard_messages', $record);

        $this->assertSame(['success' => false], \block_mwa_dashboard\external::delete_grades_mail_record($intervid));
        $this->assertNotFalse($DB->get_record('block_mwa_dashboard_messages', ['id' => $intervid], 'id', IGNORE_MISSING));
    }

    /**
     * clear_grades_mail_history() removes only the caller's own 'Grades' rows
     * (a site admin removes every teacher's rows) and never touches
     * pedagogical-intervention rows.
     */
    public function test_clear_grades_mail_history_scoped_and_admin(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $dora  = $this->enrol_user_in_course($course, 'student', 'Dora', 'Alumna');
        $tito  = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $carla = $this->enrol_user_in_course($course, 'editingteacher', 'Carla', 'Docente');

        $this->setUser($tito);
        $this->assertSame(1, \block_mwa_dashboard\external::send_grades_mail($course->id, [$dora->id], '', 0)['sent']);
        $this->setUser($carla);
        $this->assertSame(1, \block_mwa_dashboard\external::send_grades_mail($course->id, [$dora->id], '', 0)['sent']);

        // A non-Grades row that must survive every clear.
        $this->setUser($tito);
        $record = new \stdClass();
        $record->courseid            = $course->id;
        $record->userid              = $dora->id;
        $record->teacherid           = $tito->id;
        $record->subject             = 'Intervención pedagógica';
        $record->message             = 'Mensaje de intervención.';
        $record->timesent            = time();
        $record->status              = 'sent';
        $record->ai_generated        = 0;
        $record->intervention_reason = 'Low engagement';
        $record->send_type           = 'moodle';
        $record->teacher_note_updated = 0;
        $intervid = $DB->insert_record('block_mwa_dashboard_messages', $record);

        // 1) Tito clears: only his row goes; Carla's and the intervention remain.
        $this->setUser($tito);
        $res = \block_mwa_dashboard\external::clear_grades_mail_history($course->id);
        $this->assertSame(['success' => true, 'deleted' => 1], $res);
        $this->assertNotFalse($DB->get_record('block_mwa_dashboard_messages', ['id' => $intervid], 'id', IGNORE_MISSING));
        $this->assertSame(1, $DB->count_records_select(
            'block_mwa_dashboard_messages',
            "courseid = :c AND intervention_reason = 'Grades'",
            ['c' => $course->id]));

        // 2) Clearing again is a clean no-op.
        $this->assertSame(['success' => true, 'deleted' => 0],
            \block_mwa_dashboard\external::clear_grades_mail_history($course->id));

        // 3) A site admin clears everything, including Carla's row.
        $admin = $this->getDataGenerator()->create_admin_user();
        $this->setUser($admin);
        $this->assertSame(['success' => true, 'deleted' => 1], \block_mwa_dashboard\external::clear_grades_mail_history($course->id));
        $this->assertSame(0, $DB->count_records_select(
            'block_mwa_dashboard_messages',
            "courseid = :c AND intervention_reason = :r",
            ['c' => $course->id, 'r' => 'Grades']));
        // And the pedagogical intervention is still there.
        $this->assertNotFalse($DB->get_record('block_mwa_dashboard_messages', ['id' => $intervid], 'id', IGNORE_MISSING));
    }

    // ---- T7 — institutional banner above the greeting -----------------------

    /**
     * The grades email HTML opens with the institutional banner (WYSIWYG for
     * preview and send): an email-safe <img> with inline sizing, placed ABOVE
     * the greeting. Default source is the plugin's public pix/banner.png.
     */
    public function test_mail_html_includes_banner_by_default(): void {
        $this->resetAfterTest(true);
        set_config('block_mwa_dashboard', 'gradesmail_banner', '');

        $course  = $this->getDataGenerator()->create_course();
        $ana     = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5);

        $html = \block_mwa_dashboard\external::get_grades_mail_preview($course->id, $ana->id, 0, '')['html'];

        // Banner image is present and email-safe (inline sizing, no CSS link).
        $this->assertStringContainsString('/blocks/mwa_dashboard/pix/banner.png', $html);
        // T7.1: full content width (same as the 100% grades table), responsive;
        // no max-width cap anymore (the width="600" attribute is only the
        // Outlook Word-engine fallback and is not asserted here).
        $this->assertStringContainsString('style="display:block;width:100%;height:auto;margin:0 0 16px;border:0;"', $html);
        $this->assertStringNotContainsString('max-width:600px', $html);
        $this->assertStringNotContainsString('<style>', $html);

        // The banner must be the first block: above the greeting.
        $bannerpos = strpos($html, 'pix/banner.png');
        $greeting  = strpos($html, get_string('gradesmail_email_hi', 'block_mwa_dashboard'));
        $this->assertNotFalse($bannerpos, 'The banner img must exist');
        $this->assertNotFalse($greeting, 'The greeting must exist');
        $this->assertLessThan($greeting, $bannerpos, 'The banner must be above the greeting');
    }

    /**
     * The admin setting controls the banner: a custom public URL is used as-is,
     * and the literal word 'none' removes the banner from the email.
     */
    public function test_mail_banner_setting_override_and_disable(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $ana     = $this->enrol_user_in_course($course, 'student', 'Ana', 'Alumna');
        $teacher = $this->enrol_user_in_course($course, 'editingteacher', 'Tito', 'Docente');
        $this->setUser($teacher);

        $this->create_graded_assign($course, $ana, 8.5);

        // 1) Custom URL is used verbatim.
        set_config('block_mwa_dashboard', 'gradesmail_banner', 'https://www.example.edu.co/banner-20anos.png');
        $html = \block_mwa_dashboard\external::get_grades_mail_preview($course->id, $ana->id, 0, '')['html'];
        $this->assertStringContainsString('https://www.example.edu.co/banner-20anos.png', $html);

        // 2) The literal word 'none' disables the banner completely.
        set_config('block_mwa_dashboard', 'gradesmail_banner', 'none');
        $html = \block_mwa_dashboard\external::get_grades_mail_preview($course->id, $ana->id, 0, '')['html'];
        $this->assertStringNotContainsString('pix/banner.png', $html);
        $this->assertStringNotContainsString('<img', $html, 'With "none" there must be no image at all');
        // The greeting must still open the body.
        $this->assertStringContainsString(get_string('gradesmail_email_hi', 'block_mwa_dashboard'), $html);
    }
}
