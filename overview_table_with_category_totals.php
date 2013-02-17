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
 * This file defines the quiz grades table.
 *
 * @package   quiz_overview
 * @copyright 2013 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/overview/overview_table.php');


/**
 * This is a table subclass for displaying the quiz grades report.
 *
 * @copyright 2008 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_overview_table_with_category_totals extends quiz_overview_table {

    protected function requires_latest_steps_loaded() {
        return true;
    }

    /**
     * Get all the questions in all the attempts being displayed that need regrading.
     * @return array A two dimensional array $questionusageid => $slot => $regradeinfo.
     */
    protected function get_regraded_questions() {
        global $DB;

        $qubaids = $this->get_qubaids_condition();
        $regradedqs = $DB->get_records_select('quiz_overview_regrades',
            'questionusageid ' . $qubaids->usage_id_in(), $qubaids->usage_id_in_params());
        return quiz_report_index_by_keys($regradedqs, array('questionusageid', 'slot'));
    }

    /**
     * @var null|array with key question id and value category id
     */
    protected $categoriesforqs = null;
    /**
     * @var null|array with key category id and value name of category
     */
    protected $categories = null;

    protected function get_categories_for_qs() {
        global $DB;
        if ($this->categoriesforqs === null) {
            $questionids = array_filter(explode(',', $this->quiz->questions));
            list($questionidssql, $questionidsparams) = $DB->get_in_or_equal($questionids);
            $qincatsql = "SELECT q.id as qid, cat.id AS catid, cat.name AS catname FROM {question_categories} cat, {question} q ".
                "WHERE q.category = cat.id AND q.id $questionidssql";
            $categoriesforqsrawrecs = $DB->get_records_sql($qincatsql, $questionidsparams);
            $this->categoriesforqs = array();
            $this->categories = array();
            foreach ($categoriesforqsrawrecs as  $categoriesforqsrawrec) {
                $this->categoriesforqs[$categoriesforqsrawrec->qid] = $categoriesforqsrawrec->catid;
                $this->categories[$categoriesforqsrawrec->catid] = $categoriesforqsrawrec->catname;
            }
        }
        return array($this->categoriesforqs, $this->categories);

    }

    function define_columns($columns) {
        list(, $cats) =  $this->get_categories_for_qs();
        foreach ($cats as $id => $cat) {
            $columns[] = "cat{$id}";
            $this->no_sorting("cat{$id}");
        }
        parent::define_columns($columns);
    }

    function define_headers($headers) {
        list(, $cats) =  $this->get_categories_for_qs();
        foreach ($cats as $id => $cat) {
            $header = $cat;
            if (!$this->is_downloading()) {
                $header .= '<br />';
            } else {
                $header .= ' ';
            }
            $header .= '/ ' . quiz_format_grade($this->quiz, $this->quiz->grade);
            $headers[] = $header;
        }
        parent::define_headers($headers);

    }

    public function other_cols($colname, $attempt) {
        if (!preg_match('/^cat(\d+)$/', $colname, $matches)) {
            return parent::other_cols($colname, $attempt);
        }
        if ($this->lateststeps === null) {
            $qubaids = $this->get_qubaids_condition();
            $this->lateststeps = $this->load_question_latest_steps($qubaids);
        }

        $catid = $matches[1];
        $qcount = 0;
        $total = 0;
        list($catforqs, ) =  $this->get_categories_for_qs();
        foreach ($this->lateststeps[$attempt->usageid] as $lateststep) {
            if ($catforqs[$lateststep->questionid] == $catid) {
                $qcount++;
                $total += $lateststep->fraction;
            }
        }
        return quiz_format_grade($this->quiz,  $total / $qcount * $this->quiz->grade);

    }

}
