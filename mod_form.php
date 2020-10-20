<?php
// This file is part of Sample Assessment module for Moodle - http://moodle.org/
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
 * sampleassessment instance add/edit form
 *
 * @package     mod
 * @subpackage  sampleassessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_sampleassessment_mod_form extends moodleform_mod {
    
    protected $course = null;
    
    public function mod_sampleassessment_mod_form($current, $section, $cm, $course) {
        $this->course = $course;
        parent::moodleform_mod($current, $section, $cm, $course);
    }
    
	function definition() {
		global $CFG, $COURSE, $PAGE, $DB;
        
		$mform =& $this->_form;
        
        $sampleassessment = new stdClass();
        if (!empty($this->_instance)) {
            if(!$sampleassessment = $DB->get_record('sampleassessment', array('id'=>$this->_instance))) {
                print_error('errorinvalidsampleassessment', 'sampleassessment');
            } else {
                $sampleassessment->submissions = $DB->get_records('sampleassessment_submissions', array('assessmentid'=>$sampleassessment->id));
            }
        }
        
        // load supporting javascript
        $PAGE->requires->js('/mod/sampleassessment/mod_form-script.js');
        
//-------------------------------------------------------------------------------
        // Setting for sample submissions
        $mform->addElement('header', 'submissionfieldset', get_string('sampleassessment', 'sampleassessment'));
        
        // Build-in file submission detail
        $submissionfilesoptions = array();
        for ($i=1; $i<=20; $i++) {
            $submissionfilesoptions[$i] = $i;
        }
        $attributes = array('onchange'=>'changeSampleNumber(this.options[this.selectedIndex].value)');
        $mform->addElement('select', 'numsubmission', get_string('submissionfilesnum', 'sampleassessment'), $submissionfilesoptions, $attributes);
        $mform->addElement('date_time_selector', 'gradestart', get_string('start', 'sampleassessment'));
        $mform->addElement('date_time_selector', 'gradeend', get_string('end', 'sampleassessment'));
        $mform->setDefault('gradeend', time()+7*24*3600);
        $mform->addElement('date_time_selector', 'gradepublish', get_string('publish', 'sampleassessment'));
        $mform->addElement('checkbox', 'autoshowcomment', get_string('autoshowcomment', 'sampleassessment'), get_string('autoshowcommenttext', 'sampleassessment'));

        $mform->disabledIf('submitstart', 'submissionfilesnumenabled');
        $mform->disabledIf('submitend', 'submissionfilesnumenabled');
        
//-------------------------------------------------------------------------------
        $mform->addElement('header', 'submissionuploadfieldset', get_string('sampleupload', 'sampleassessment'));
        
        $mform->addElement('hidden','sampleendindicator','yes');
        $mform->setType('sampleendindicator', PARAM_TEXT);
//-------------------------------------------------------------------------------
        /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('assessmentname', 'sampleassessment'), array('size'=>'64'));
		$mform->setType('name', PARAM_TEXT);
		$mform->addRule('name', null, 'required', null, 'client');
        
        $this->add_intro_editor(true, get_string('assessmentintro', 'sampleassessment'));
        
        /// Adding the "samplelabel" field
        $mform->addElement('text', 'samplelabel', get_string('samplelabel', 'sampleassessment'), array('size'=>'20'));
		$mform->setType('samplelabel', PARAM_TEXT);
		$mform->addRule('samplelabel', null, 'required', null, 'client');
        
        // Construct the rubric dropdown list
        $rubricoptions = array();
        $rubricoptions[0] = get_string('singlegrade', 'sampleassessment');
        $rubricoptions[1] = '-----------------------------------------';
        
        $rubricselect_onchange = 'updateElem(this.options[this.selectedIndex].value, '.$COURSE->id.', \''.$CFG->wwwroot.'\', \''.sesskey().'\')';
        $rubricselect = $mform->addElement('select', 'rubricid', get_string('loadrubric', 'sampleassessment'), array(), array('onchange' => $rubricselect_onchange));
        $rubricselect->addOption(get_string('singlegrade', 'sampleassessment'), '0');
        $rubricselect->addOption('-----------------------------------------', 'line1', array('disabled' => 'disabled'));
        if(!$rubrics = rubric_get_list($COURSE->id)){
            $rubricselect->addOption(get_string('norubrics', 'sampleassessment'), '0', array('disabled' => 'disabled'));
        } else {
            foreach ($rubrics as $rub_key => $rubric) {
                if(!is_object($rubric)) break; // TOP_COURSE produces this
                $rubricselect->addOption($rubric->text, $rubric->value);
            }
        }
        $rubricselect->addOption('-----------------------------------------', 'line2', array('disabled' => 'disabled'));
        $rubricselect->addOption(get_string('viewrubriclist', 'sampleassessment'), 'import');
        $rubricselect->addOption(get_string('createnewrubric', 'sampleassessment'), 'new');
        
        $this->standard_grading_coursemodule_elements();
//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
		$this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();
	}
    
	function data_preprocessing(&$default_values){
        global $DB, $COURSE;
        $mform =& $this->_form;
        $numsubmission = NULL;
        
        if (optional_param('numsubmission', NULL, PARAM_INT))
            $numsubmission = optional_param('numsubmission', NULL, PARAM_INT);
        
        $counter = 0;
        $numoldsubmission = 1;
        
        if ($this->current->instance) {
            $oldsubmissions = $DB->get_records("sampleassessment_submissions", array("assessmentid"=>$default_values['id']), "id");
            if ($oldsubmissions) {
                $numoldsubmission = sizeof($oldsubmissions);
                require_once("lib.php");
                foreach ($oldsubmissions as $oldsubmission) {
                    $counter++;
                    if ($numsubmission && $counter>$numsubmission) break;
                    
                    $sampleassessment = new stdClass();
                    $sampleassessment->course = $default_values['course'];
                    $samplelabel = $mform->createElement('static', 'samplelabel'.$counter, get_string('sample', 'sampleassessment').' '.$counter, '');
                    $samplename = $mform->createElement('text', 'samplename'.$counter, get_string('samplename', 'sampleassessment'), array('size'=>'64', 'value'=>$oldsubmission->title));
                    $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext'=>true, 'context'=>$this->context);
                    $sampleintro = $mform->createElement('editor', 'sampleintro'.$counter, get_string('sampleintro', 'sampleassessment'), null, $editoroptions);
                    $sampleintro->setValue(array('text' =>  $oldsubmission->description));
                    
                    // Moodle 2.0: file handling changes
                    $filepickeroptions = array();
                    $filepickeroptions['filetypes'] = '*';
                    $filepickeroptions['maxbytes'] = $this->course->maxbytes;
                    $samplefile = $mform->createElement('filepicker', 'samplefile'.$counter, get_string('attachment', 'sampleassessment'), null, $filepickeroptions);
                    
                    $mform->addElement($samplelabel);
                    $mform->addElement($samplename);
                    $mform->addElement($samplefile);
                    $mform->addElement($sampleintro);

                    $mform->insertElementBefore($mform->removeElement('samplelabel'.$counter, false), 'sampleendindicator');
                    $mform->insertElementBefore($mform->removeElement('samplename'.$counter, false), 'sampleendindicator');
                    $mform->insertElementBefore($mform->removeElement('samplefile'.$counter, false), 'sampleendindicator');
                    $mform->insertElementBefore($mform->removeElement('sampleintro'.$counter, false), 'sampleendindicator');
                    
                    $mform->setType('samplename'.$counter, PARAM_TEXT);
                    $mform->addRule('samplename'.$counter, null, 'required', null, 'client');
                    
                    $mform->setType('sampleintro'.$counter, PARAM_RAW);
                    $mform->setAdvanced('sampleintro'.$counter);
                    
                    // editing existing instance - copy existing files into draft area
                    $draftitemid = file_get_submitted_draft_itemid('samplefile'.$counter);
                    file_prepare_draft_area($draftitemid, $this->context->id, 'mod_sampleassessment', 'samplefile', $oldsubmission->id, array('subdirs'=>0, 'maxbytes' => $this->course->maxbytes, 'maxfiles' => 1));
                    $default_values['samplefile'.$counter] = $draftitemid;
                }
            }
        }
        
        if ($numsubmission) {
            $default_values['numsubmission'] = $numsubmission;
        } else {
            $default_values['numsubmission'] = $numoldsubmission;
            $numsubmission = $numoldsubmission;
        }
        
        if (!isset($default_values['samplelabel']) || !$default_values['samplelabel']) $default_values['samplelabel'] = get_string('sample', 'sampleassessment').' #';
        
        $counter_copy = $counter;
        for ($i=1; $i<=$numsubmission-$counter_copy; $i++) {
            $counter++;
            $sampletitle = $mform->createElement('static', 'sampletitle'.$counter, get_string('sample', 'sampleassessment').' '.$counter, '');
            $samplename = $mform->createElement('text', 'samplename'.$counter, get_string('samplename', 'sampleassessment'));
            
            // Moodle 2.0: file handling changes
            $filepickeroptions = array();
            $filepickeroptions['filetypes'] = '*';
            $filepickeroptions['maxbytes'] = $this->course->maxbytes;
            $samplefile = $mform->createElement('filepicker', 'samplefile'.$counter, get_string('attachment', 'sampleassessment'), null, $filepickeroptions);
            
            $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext'=>true, 'context'=>$this->context);
            $sampleintro = $mform->createElement('editor', 'sampleintro'.$counter, get_string('sampleintro', 'sampleassessment'), null, $editoroptions);
            
            $mform->addElement($sampletitle);
            $mform->addElement($samplename);
            $mform->addElement($samplefile);
            $mform->addElement($sampleintro);
            
            $mform->insertElementBefore($mform->removeElement('sampletitle'.$counter, false), 'sampleendindicator');
            $mform->insertElementBefore($mform->removeElement('samplename'.$counter, false), 'sampleendindicator');
            $mform->insertElementBefore($mform->removeElement('samplefile'.$counter, false), 'sampleendindicator');
            $mform->insertElementBefore($mform->removeElement('sampleintro'.$counter, false), 'sampleendindicator');
            
            $mform->setType('samplename'.$counter, PARAM_TEXT);
            $mform->addRule('samplename'.$counter, get_string('required'), 'required', null, 'client');
            
            $mform->setType('sampleintro'.$counter, PARAM_RAW);
            $mform->setAdvanced('sampleintro'.$counter);
        }
    }
    
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        if ($data['gradestart'] > $data['gradeend']) {
            $errors['gradeend'] = get_string('endearlythanstart', 'sampleassessment');
        }
        if (!empty($data['gradepublish'])) {
            if ($data['gradestart'] > $data['gradepublish']) {
                $errors['gradepublish'] = get_string('publishearlythanstart', 'sampleassessment');
            }
        }
        
        for ($i=1; $i<=$data['numsubmission']; $i++) {
            if (empty($data['samplename'.$i])) {
                $errors['samplename'.$i] = get_string('cannotbeempty', 'sampleassessment');
            }
        }
        
        // Un-comment this if want to debug the form
        //$errors['gradepublish'] = "DEBUGGING";
        return $errors;
    }
}
?>