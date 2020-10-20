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
 * Structure step to restore one sampleassessment activity
 */
class restore_sampleassessment_activity_structure_step extends restore_activity_structure_step {
 
    protected function define_structure() {
 
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');
 
        $paths[] = new restore_path_element('sampleassessment', '/activity/sampleassessment');
        //$paths[] = new restore_path_element('assessment_rubric', '/activity/assessment/rubrics/rubric');
        //$paths[] = new restore_path_element('assessment_rubric_row_spec', '/activity/assessment/rubrics/rubric/rubric_row_specs/rubric_row_spec');
        //$paths[] = new restore_path_element('assessment_rubric_col_spec', '/activity/assessment/rubrics/rubric/rubric_col_specs/rubric_col_spec');
        //$paths[] = new restore_path_element('assessment_rubric_spec', '/activity/assessment/rubrics/rubric/rubric_specs/rubric_spec');
        $paths[] = new restore_path_element('sampleassessment_submission', '/activity/sampleassessment/submissions/submission');
        
        if ($userinfo) {
            $paths[] = new restore_path_element('sampleassessment_grade', '/activity/sampleassessment/submissions/submission/grades/grade');
            $paths[] = new restore_path_element('sampleassessment_grade_spec', '/activity/sampleassessment/submissions/submission/grades/grade/grade_specs/grade_spec');
        }
        
        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }
 
    protected function process_sampleassessment($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        
        $data->gradestart = $this->apply_date_offset($data->gradestart);
        $data->gradeend = $this->apply_date_offset($data->gradeend);
        $data->gradepublish = $this->apply_date_offset($data->gradepublish);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if (!empty($data->rubricid)) {
            $data->rubricid = $this->get_mappingid('assessment_rubric', $data->rubricid);
        }
        
        // insert the sampleassessment record
        $newitemid = $DB->insert_record('sampleassessment', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }
 
    protected function process_sampleassessment_rubric($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
        
        // check if the same rubric exist in the course, if yes, we don't insert a new one
        if ($DB->count_records('assessment_rubrics', array('name'=>$data->name, 'courseid'=>$this->get_courseid(), 'points'=>$data->points))) {
            $data->name = $data->name.' copy';
        }
        
        if (!$data->creatorid = $this->get_mappingid('user', $data->creatorid)){
            global $USER;
            $data->creatorid = $USER->id;
        }
        $data->courseid = $this->get_courseid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);
 
        $newitemid = $DB->insert_record('assessment_rubrics', $data);
        $this->set_mapping('assessment_rubric', $oldid, $newitemid);
        
        // update rubricid in assessment
        $DB->set_field('sampleassessment', 'rubricid', $newitemid, array('id'=>$this->get_new_parentid('sampleassessment')));;
    }
    
    protected function process_sampleassessment_rubric_row_spec($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
        
        $data->rubricid = $this->get_new_parentid('assessment_rubric');
 
        $newitemid = $DB->insert_record('assessment_rubric_row_specs', $data);
        $this->set_mapping('assessment_rubric_row_spec', $oldid, $newitemid);
    }
    
    protected function process_assessment_rubric_col_spec($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
 
        $data->rubricid = $this->get_new_parentid('assessment_rubric');
 
        $newitemid = $DB->insert_record('assessment_rubric_col_specs', $data);
        $this->set_mapping('assessment_rubric_col_spec', $oldid, $newitemid);
    }
    
    protected function process_assessment_rubric_spec($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
 
        $data->rubricrowid = $this->get_mappingid('assessment_rubric_row_spec', $data->rubricrowid);
        $data->rubriccolid = $this->get_mappingid('assessment_rubric_col_spec', $data->rubriccolid);
 
        $newitemid = $DB->insert_record('assessment_rubric_specs', $data);
        // No need to save this mapping as far as nothing depend on it
    }
    
    protected function process_sampleassessment_submission($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
        
        $data->assessmentid = $this->get_new_parentid('sampleassessment');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
 
        $newitemid = $DB->insert_record('sampleassessment_submissions', $data);
        $this->set_mapping('sampleassessment_submission', $oldid, $newitemid, true); // files by this itemname
    }
    
    protected function process_sampleassessment_grade($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
        
        $data->submissionid = $this->get_new_parentid('sampleassessment_submission');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->marker = $this->get_mappingid('user', $data->marker);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
 
        $newitemid = $DB->insert_record('sampleassessment_grades', $data);
        $this->set_mapping('sampleassessment_grades', $oldid, $newitemid);
    }
    
    protected function process_sampleassessment_grade_spec($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
        
        $data->gradeid = $this->get_new_parentid('sampleassessment_grade');
        $data->rubricspecid = $this->get_mappingid('assessment_rubric_spec', $data->rubricspecid);
 
        $newitemid = $DB->insert_record('sampleassessment_grade_specs', $data);
        // No need to save this mapping as far as nothing depend on it
    }
    
    protected function after_execute() {
        // Add assessment related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_sampleassessment', 'intro', null);
        
        $this->add_related_files('mod_sampleassessment', 'submission', 'sampleassessment_submission');
        $this->add_related_files('mod_sampleassessment', 'submission_description', 'sampleassessment_submission');
        
        $this->add_related_files('mod_sampleassessment', 'grade_comment', 'sampleassessment_grade');
        
        //$this->add_related_files('mod_assessment', 'rubric_description', 'assessment_rubric');
        
        //$this->add_related_files('mod_assessment', 'message', 'assessment_post');   
    }
}