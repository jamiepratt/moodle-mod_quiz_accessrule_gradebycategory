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
 * Implementaton of the quizaccess_gradebycategory plugin.
 *
 * @package   quizaccess
 * @subpackage gradebycategory
 * @copyright 2013 Portsmouth University
 * @author    Jamie Pratt (me@jamiep.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');


/**
 * A rule requiring the student to promise not to cheat.
 *
 * @copyright 2013 Portsmouth University
 * @author    Jamie Pratt (me@jamiep.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_gradebycategory extends quiz_access_rule_base {

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        global $THEME;
        if (empty($quizobj->get_quiz()->gradebycategory)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public static function add_settings_form_fields(
            mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->insertElementBefore($mform->createElement('selectyesno', 'gradebycategory',
            get_string('gradebycategory', 'quizaccess_gradebycategory')), 'security');

        $mform->setDefault('gradebycategory', get_config('quizaccess_gradebycategory', 'gradebycategory'));
        $mform->addHelpButton('gradebycategory',
                'gradebycategory', 'quizaccess_gradebycategory');
    }

    public static function save_settings($quiz) {
        global $DB;
        if (empty($quiz->gradebycategory)) {
            $DB->delete_records('quizaccess_gradebycategory', array('quizid' => $quiz->id));
        } else {
            if (!$DB->record_exists('quizaccess_gradebycategory', array('quizid' => $quiz->id))) {
                $record = new stdClass();
                $record->quizid = $quiz->id;
                $record->gradebycategory = 1;
                $DB->insert_record('quizaccess_gradebycategory', $record);
            }
        }
    }

    public static function get_settings_sql($quizid) {
        return array(
            'COALESCE(gradebycategory, 0) AS gradebycategory',// Using COALESCE to replace NULL with 0.
            'LEFT JOIN {quizaccess_gradebycategory} qa_gbc ON qa_gbc.quizid = quiz.id',
            array());
    }
}
