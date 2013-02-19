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
require_once($CFG->dirroot . '/mod/quiz/accessrule/gradebycategory/gradebycatcalculator.php');

/**
 * This is a table subclass for displaying the quiz grades report.
 *
 * @copyright 2013 Portsmouth University
 * @author    Jamie Pratt (me@jamiep.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_overview_table_with_category_totals extends quiz_overview_table {

    /**
     * @var quizaccess_gradebycategory_calculator
     */
    protected $catgrades;

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
     * 'columns' property is used to add identifiers for columns. These identifiers are later used to know what method to call to get
     * the data to put in the cells. In this case we will add "cat{$id}" where $id is the category id and the data for cells will
     * be fetched from other_cols.
     * @param array $columns
     */
    function add_extra_columns($catids) {
        foreach ($catids as $id) {
            $this->add_column("cat{$id}");
            $this->no_sorting("cat{$id}");
        }
    }

    protected function add_column($column) {
        $colnum = count($this->columns);

        $this->columns[$column]         = $colnum;
        $this->column_style[$column]    = array();
        $this->column_class[$column]    = '';
        $this->column_suppress[$column] = false;

    }

    /**
     * Add the titles of the columns.
     * @param array $headers
     */
    function add_extra_headers($catnames) {
        foreach ($catnames as $catname) {
            $header = $catname;
            if (!$this->is_downloading()) {
                $header .= '<br />';
            } else {
                $header .= ' ';
            }
            $header .= '/ ' . quiz_format_grade($this->quiz, 100);
            $this->add_header($header);
        }
    }

    protected function add_header($header) {
        $this->headers[] = $header;
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
        return $this->catgrades->grade_by_category($attempt->usageid, $catid);
    }

    public function query_db($pagesize, $useinitialsbar = true) {
        parent::query_db($pagesize, $useinitialsbar);
        if ($this->quiz->gradebycategory) {
            $this->catgrades = new quizaccess_gradebycategory_calculator($this->quiz);
            // $this->lateststeps may or may not already have been loaded depending if the reoprt
            // is set to show question grades.
            if ($this->lateststeps === null) {
                $this->catgrades->load_latest_steps($this->attempts);
            } else {
                $this->catgrades->set_latest_steps($this->lateststeps);
            }
            $cats = $this->catgrades->load_cat_data();
            $this->add_extra_headers($cats);
            $this->add_extra_columns(array_keys($cats));
        }
    }
}
