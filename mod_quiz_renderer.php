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
 * A renderer to override the default renderer for quiz view table.
 *
 * @package   quizaccess
 * @subpackage gradebycategory
 * @copyright 2013 Portsmouth University
 * @author    Jamie Pratt (me@jamiep.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot.'/mod/quiz/renderer.php');


class quizaccess_gradebycategory_mod_quiz_renderer extends mod_quiz_renderer {

    /**
     * Generates the table of data
     *
     * @param array $quiz Array contining quiz data
     * @param int $context The page context ID
     * @param mod_quiz_view_object $viewobj
     */
    public function view_table($quiz, $context, $viewobj) {
        global $DB;
        if (!$quiz->gradebycategory) {
            return parent::view_table($quiz, $context, $viewobj);
        }

        if (!$viewobj->attempts) {
            return '';
        }

        $qubaids = array();
        foreach ($viewobj->attempts as $attempt) {
            $qubaids[] = $attempt->uniqueid;
        }

        $dm = new question_engine_data_mapper();
        $qubaidcondition = new qubaid_list($qubaids);
        $slots = array_filter(explode(',', $attempt->layout));
        $lateststeps = $dm->load_questions_usages_latest_steps($qubaidcondition, $slots);

        $questionids = array_filter(explode(',', $quiz->questions));
        list($questionidssql, $questionidsparams) = $DB->get_in_or_equal($questionids);
        $qincatsql = "SELECT q.id as qid, cat.id AS catid, cat.name AS catname FROM {question_categories} cat, {question} q ".
                    "WHERE q.category = cat.id AND q.id $questionidssql";
        $qincategories = $DB->get_records_sql($qincatsql, $questionidsparams);

        $category = array();

        foreach ($lateststeps as $lateststep) {
            $qcat = $qincategories[$lateststep->questionid];
            if (!isset($category[$lateststep->questionusageid][$qcat->catid])) {
                $category[$lateststep->questionusageid][$qcat->catid] = new stdClass();
                $category[$lateststep->questionusageid][$qcat->catid]->total = 0;
                $category[$lateststep->questionusageid][$qcat->catid]->qcount = 0;
                $category[$lateststep->questionusageid][$qcat->catid]->name = $qcat->catname;
            }

            $category[$lateststep->questionusageid][$qcat->catid]->total += $lateststep->fraction;
            $category[$lateststep->questionusageid][$qcat->catid]->qcount ++;
        }

        $datainrows = array();
        foreach ($category as $questionusageid => $usagecats) {
            foreach ($usagecats as $usagecat) {
                $datainrows[$questionusageid][$usagecat->name] = $usagecat->total / $usagecat->qcount;
            }

        }



        // Prepare table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizattemptsummary';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        if ($viewobj->attemptcolumn) {
            $table->head[] = get_string('attemptnumber', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        $table->head[] = get_string('attemptstate', 'quiz');
        $table->align[] = 'left';
        $table->size[] = '';
        if ($viewobj->markcolumn) {
            $table->head[] = get_string('marks', 'quiz') . ' / ' .
                quiz_format_grade($quiz, $quiz->sumgrades);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->gradecolumn) {
            $table->head[] = get_string('grade') . ' / ' .
                quiz_format_grade($quiz, $quiz->grade);
            $table->align[] = 'center';
            $table->size[] = '';
        }

        foreach (current($datainrows) as $catname => $unused) {
            $table->head[] = $catname. ' / ' .
                quiz_format_grade($quiz, 100);
            $table->align[] = 'center';
            $table->size[] = '';
        }

        if ($viewobj->canreviewmine) {
            $table->head[] = get_string('review', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->feedbackcolumn) {
            $table->head[] = get_string('feedback', 'quiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }

        // One row for each attempt.
        foreach ($viewobj->attemptobjs as $attemptobj) {
            $attemptoptions = $attemptobj->get_display_options(true);
            $row = array();

            // Add the attempt number.
            if ($viewobj->attemptcolumn) {
                if ($attemptobj->is_preview()) {
                    $row[] = get_string('preview', 'quiz');
                } else {
                    $row[] = $attemptobj->get_attempt_number();
                }
            }

            $row[] = $this->attempt_state($attemptobj);

            if ($viewobj->markcolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                    $attemptobj->is_finished()) {
                    $row[] = quiz_format_grade($quiz, $attemptobj->get_sum_marks());
                } else {
                    $row[] = '';
                }
            }

            // Ouside the if because we may be showing feedback but not grades.
            $attemptgrade = quiz_rescale_grade($attemptobj->get_sum_marks(), $quiz, false);

            if ($viewobj->gradecolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                    $attemptobj->is_finished()) {

                    // Highlight the highest grade if appropriate.
                    if ($viewobj->overallstats && !$attemptobj->is_preview()
                        && $viewobj->numattempts > 1 && !is_null($viewobj->mygrade)
                        && $attemptgrade == $viewobj->mygrade
                        && $quiz->grademethod == QUIZ_GRADEHIGHEST) {
                        $table->rowclasses[$attemptobj->get_attempt_number()] = 'bestrow';
                    }

                    $row[] = quiz_format_grade($quiz, $attemptgrade);
                } else {
                    $row[] = '';
                }
            }

            foreach ($datainrows[$attemptobj->get_uniqueid()] as $cell) {
                $row[] = quiz_format_grade($quiz,  $cell * 100);
            }

            if ($viewobj->canreviewmine) {
                $row[] = $viewobj->accessmanager->make_review_link($attemptobj->get_attempt(),
                    $attemptoptions, $this);
            }

            if ($viewobj->feedbackcolumn && $attemptobj->is_finished()) {
                if ($attemptoptions->overallfeedback) {
                    $row[] = quiz_feedback_for_grade($attemptgrade, $quiz, $context);
                } else {
                    $row[] = '';
                }
            }

            if ($attemptobj->is_preview()) {
                $table->data['preview'] = $row;
            } else {
                $table->data[$attemptobj->get_attempt_number()] = $row;
            }
        } // End of loop over attempts.

        $output = '';
        $output .= $this->view_table_heading();
        $output .= html_writer::table($table);
        return $output;
    }
}