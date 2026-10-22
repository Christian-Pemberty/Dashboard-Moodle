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
     * Hidden grade items must never appear in the grades email, whether or not
     * the student has a grade recorded on them.
     */
    public function test_email_excludes_hidden_grade_items(): void {
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
        $this->assertStringNotContainsString('9.9', $preview['html']);
    }
}
