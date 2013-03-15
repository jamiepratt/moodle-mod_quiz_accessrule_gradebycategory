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
 * A class to encapsulate the loading of data and calculation of grades by category.
 *
 * @package   quizaccess
 * @subpackage gradebycategory
 * @copyright 2013 Portsmouth University
 * @author    Jamie Pratt (me@jamiep.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class quizaccess_gradebycategory_calculator {
    protected $lateststeps;

    protected $qidtocatidhash;

    protected $categorynames;

    protected $quiz;

    public function __construct($quiz) {
        $this->quiz = $quiz;
    }

    public function set_latest_steps($lateststeps){
        $this->lateststeps = $lateststeps;
    }

    public function load_latest_steps($attempts) {
        $qubaids = array();
        foreach ($attempts as $attempt) {
            $qubaids[] = $attempt->uniqueid;
        }

        $dm = new question_engine_data_mapper();
        $qubaidcondition = new qubaid_list($qubaids);
        $slots = array_filter(explode(',', $attempt->layout));
        $lateststeps = $dm->load_questions_usages_latest_steps($qubaidcondition, $slots);


        $this->lateststeps = array();
        foreach ($lateststeps as $step) {
            $this->lateststeps[$step->questionusageid][$step->slot] = $step;
        }
    }

    protected function get_question_ids() {
        $questionids = array();
        foreach ($this->lateststeps as $attempt) {
            foreach ($attempt as $slot => $lateststep) {
                if ($lateststep->maxmark != 0) {
                    $questionids[] = $lateststep->questionid;
                }
            }
        }
        return $questionids;
    }

    public function load_cat_data() {
        global $DB;
        list($questionidssql, $questionidsparams) = $DB->get_in_or_equal($this->get_question_ids());
        $qincatsql = "SELECT q.id as qid, cat.id AS catid, cat.name AS catname FROM {question_categories} cat, {question} q ".
            "WHERE q.category = cat.id AND q.id $questionidssql";
        $categoriesforqsrawrecs = $DB->get_records_sql($qincatsql, $questionidsparams);
        $this->qidtocatidhash = array();
        $this->categorynames = array();
        foreach ($categoriesforqsrawrecs as  $categoriesforqsrawrec) {
            $this->qidtocatidhash[$categoriesforqsrawrec->qid] = $categoriesforqsrawrec->catid;
            $this->categorynames[$categoriesforqsrawrec->catid] = $categoriesforqsrawrec->catname;
        }
        return $this->categorynames;
    }

    public function grade_by_category($uniqueattemptid, $catid) {
        $qcount = 0;
        $total = 0;
        foreach ($this->lateststeps[$uniqueattemptid] as $lateststep) {
            if (isset($this->qidtocatidhash[$lateststep->questionid])) {
                if ($this->qidtocatidhash[$lateststep->questionid] == $catid) {
                    $qcount++;
                    $total += $lateststep->fraction;
                }
            }
        }
        if ($qcount > 0) {
            $grade = quiz_format_grade($this->quiz,  $total / $qcount * 100);
        } else {
            $grade = '--';
        }

        return "$grade ($qcount)";
    }

}