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
 * @package   quizaccess
 * @subpackage gradebycategory
 * @copyright 2013 Portsmouth University
 * @author    Jamie Pratt (me@jamiep.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/overview/overview_table.php');


/**
 * This is a table subclass for displaying the quiz grades report.
 *
 * @copyright 2013 Portsmouth University
 * @author    Jamie Pratt (me@jamiep.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_overview_table_with_category_totals extends quiz_overview_table {

    /**
     * @param object                $quiz
     * @param context               $context
     * @param string                $qmsubselect
     * @param quiz_overview_options $options
     * @param array                 $groupstudents
     * @param array                 $students
     * @param array                 $questions
     * @param moodle_url            $reporturl
     */
    public function __construct($quiz, $context, $qmsubselect,
            quiz_overview_options $options, $groupstudents, $students, $questions, $reporturl) {
        global $DB;
        $quiz->gradebycategory = $DB->get_field('quizaccess_gradebycategory', 'gradebycategory', array('quizid' => $quiz->id));
        parent::__construct($quiz, $context, $qmsubselect, $options, $groupstudents, $students, $questions, $reporturl);
    }

    /**
     * Load latest steps data if needed to show grades per question or per category.
     * @return bool
     */
    protected function requires_latest_steps_loaded() {
        return parent::requires_latest_steps_loaded() || $this->quiz->gradebycategory;
    }


    /**
     * @var null|array with key question id and value category id
     */
    protected $categoriesforqs = null;
    /**
     * @var null|array with key category id and value name of category
     */
    protected $categories = null;

    /**
     * Data needed to put category grades in cells.
     * @return array
     */
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

    /**
     * Define columns is used to add identifiers for columns. These identifiers are later used to know what method to call to get
     * the data to put in the cells. In this case we will add "cat{$id}" where $id is the category id and the data for cells will
     * be fetched from other_cols.
     * @param array $columns
     */
    function define_columns($columns) {
        if ($this->quiz->gradebycategory) {
            list(, $cats) =  $this->get_categories_for_qs();
            foreach ($cats as $id => $cat) {
                $columns[] = "cat{$id}";
                $this->no_sorting("cat{$id}");
            }
        }
        parent::define_columns($columns);
    }

    /**
     * Add some extra headings, the titles of the columns.
     * @param array $headers
     */
    function define_headers($headers) {
        if ($this->quiz->gradebycategory) {
            list(, $cats) =  $this->get_categories_for_qs();
            foreach ($cats as $id => $cat) {
                $header = $cat;
                if (!$this->is_downloading()) {
                    $header .= '<br />';
                } else {
                    $header .= ' ';
                }
                $header .= '/ ' . quiz_format_grade($this->quiz, 100);
                $headers[] = $header;
            }
        }
        parent::define_headers($headers);

    }

    /**
     * Return the column average or call the parent class to see if the parent class knows what do put in this cell.
     * @param string $colname the column name defined in define_columns
     * @param object $attempt attempt for this row
     * @return null|string null if we don't know what to put in this column or a string.
     */
    public function other_cols($colname, $attempt) {
        if (!preg_match('/^cat(\d+)$/', $colname, $matches)) {
            return parent::other_cols($colname, $attempt);
        }

        $catid = $matches[1]; // The thing that matches the first sub pattern in the regular expression above.
        $qcount = 0;
        $total = 0;
        list($catforqs, ) =  $this->get_categories_for_qs();
        foreach ($this->lateststeps[$attempt->usageid] as $lateststep) {
            if ($catforqs[$lateststep->questionid] == $catid) {
                $qcount++;
                $total += $lateststep->fraction;
            }
        }
        return quiz_format_grade($this->quiz,  $total / $qcount * 100);

    }

}
