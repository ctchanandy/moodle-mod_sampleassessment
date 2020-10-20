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
 * @package mod
 * @subpackage sampleassessment
 * @author Andy Chan <ctchan.andy@gmail.com>
 * @copyright 2012 Andy Chan <ctchan.andy@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_sampleassessment_activity_task
 */
 class backup_sampleassessment_activity_structure_step extends backup_activity_structure_step {
 
    protected function define_structure() {
 
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');
 
        // Define each element separated
        $sampleassessment = new backup_nested_element('sampleassessment', array('id'), array(
                            'grade', 'rubricid', 'name', 'intro', 'introformat', 'numsubmission', 'forum', 
                            'gradestart', 'gradeend', 'gradepublish', 'autoshowcomment', 'timemodified'));
        
        /*
        $rubrics = new backup_nested_element('rubrics');
        
        $rubric = new backup_nested_element('rubric', array('id'), array(
                                 'name', 'description', 'creatorid', 'courseid', 'points', 'rowcoldefine', 'timemodified'));
        
        $rubric_row_specs = new backup_nested_element('rubric_row_specs');
        
        $rubric_row_spec = new backup_nested_element('rubric_row_spec', array('id'), array(
                                          'displayorder', 'name', 'custompoint'));
        
        $rubric_col_specs = new backup_nested_element('rubric_col_specs');
        
        $rubric_col_spec = new backup_nested_element('rubric_col_spec', array('id'), array(
                                          'displayorder', 'name', 'points', 'maxpoints'));
        
        $rubric_specs = new backup_nested_element('rubric_specs');
        
        $rubric_spec = new backup_nested_element('rubric_spec', array('id'), array(
                                      'rubricrowid', 'rubriccolid', 'description', 'points', 'maxpoints'));
        */
        
        $submissions = new backup_nested_element('submissions');
        
        $submission = new backup_nested_element('submission', array('id'), array(
                                     'assessmentid', 'title', 'description', 'timecreated', 'url'));
        
        
        $grades = new backup_nested_element('grades');
        
        $grade = new backup_nested_element('grade', array('id'), array(
                                'submissionid', 'userid', 'marker', 'grade', 'type', 'comment', 'timemodified'));
        
        
        $grade_specs = new backup_nested_element('grade_specs');
        
        $grade_spec = new backup_nested_element('grade_spec', array('id'), array(
                                     'gradeid', 'rubricspecid', 'value'));
        
        
        // Build the tree
        $sampleassessment->add_child($submissions);
        $submissions->add_child($submission);
        
        /*
        $rubric->add_child($rubric_row_specs);
        $rubric_row_specs->add_child($rubric_row_spec);
        
        $rubric->add_child($rubric_col_specs);
        $rubric_col_specs->add_child($rubric_col_spec);
        
        $rubric->add_child($rubric_specs);
        $rubric_specs->add_child($rubric_spec);
        */
        
        $submission->add_child($grades);
        $grades->add_child($grade);
        
        $grade->add_child($grade_specs);
        $grade_specs->add_child($grade_spec);
        
        // Define sources
        $sampleassessment->set_source_table('sampleassessment', array('id' => backup::VAR_ACTIVITYID));
        
        /*
        $rubric->set_source_table('assessment_rubrics', array('id' => '../../rubricid'));
        
        $rubric_row_spec->set_source_table('assessment_rubric_row_specs', array('rubricid' => backup::VAR_PARENTID));
        
        $rubric_col_spec->set_source_table('assessment_rubric_col_specs', array('rubricid' => backup::VAR_PARENTID));
        
        $rubric_spec->set_source_sql('
            SELECT * FROM {assessment_rubric_specs} WHERE 
                rubricrowid IN (SELECT id FROM {assessment_rubric_row_specs} WHERE rubricid = ?) AND
                rubriccolid IN (SELECT id FROM {assessment_rubric_col_specs} WHERE rubricid = ?)', 
            array(backup::VAR_PARENTID, backup::VAR_PARENTID));
        */
        
        $submission->set_source_table('sampleassessment_submissions', array('assessmentid' => backup::VAR_PARENTID));
        
        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $grade->set_source_table('sampleassessment_grades', array('submissionid' => backup::VAR_PARENTID));
            
            $grade_spec->set_source_table('sampleassessment_grade_specs', array('gradeid' => backup::VAR_PARENTID));
        }
        
        // Define id annotations
        $grade->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'marker');
        
        /*
        $rubric->annotate_ids('user', 'creatorid');
        */
        
        // Define file annotations
        $sampleassessment->annotate_files('mod_sampleassessment', 'intro', null); // This file area hasn't itemid
        
        $submission->annotate_files('mod_sampleassessment', 'submission_description', 'id');
        $submission->annotate_files('mod_sampleassessment', 'submission', 'id');
        
        $grade->annotate_files('mod_sampleassessment', 'grade_comment', 'id');
        
        /*
        $rubric->annotate_files('mod_assessment', 'rubric_description', 'id');
        */
        
        // Return the root element (sampleassessment), wrapped into standard activity structure
        return $this->prepare_activity_structure($sampleassessment);
    }
}