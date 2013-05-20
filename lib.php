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
 * sampleassessment module library
 *
 * @package     mod
 * @subpackage  sampleassessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /**
  * This is NOT a standalone module and rely on the assessment module
  * Please make sure that assessment module have been installed first
  */
require_once ($CFG->dirroot.'/mod/assessment/rubric/lib.php');

class sampleassessment_base {
    var $cm;
    var $course;
    var $sampleassessment;
    
    var $strsampleassessment;
    var $strsampleassessments;
    var $strsampleassessmentgrades;
    var $strlastmodified;
    
    var $pagetitle;
    var $usehtmleditor;
    var $defaultformat;
    var $context;
    var $type;
    var $rubric;
    
    /**
    * Constructor
    */
    function sampleassessment_base($cmid='staticonly', $sampleassessment=NULL, $cm=NULL, $course=NULL) {
        global $COURSE, $DB;
        
        if ($cmid == 'staticonly') {
            //use static functions only!
            return;
        }
        
        if ($cm) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('sampleassessment', $cmid)) {
            print_error('errorincorrectcmid', 'sampleassessment');
        }
        
        $this->context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        
        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = $DB->get_record('course', array('id'=>$this->cm->course))) {
            print_error('coursemisconf', 'sampleassessment');
        }
        
        if ($sampleassessment) {
            $this->sampleassessment = $sampleassessment;
        } else if (! $this->sampleassessment = $DB->get_record('sampleassessment', array('id'=>$this->cm->instance))) {
            print_error('invalidid', 'sampleassessment');
        }
        
        $this->sampleassessment->cmidnumber = $this->cm->id;     // compatibility with modedit sampleassessment obj
        $this->sampleassessment->courseid   = $this->course->id; // compatibility with modedit sampleassessment obj

        $this->strsampleassessment = get_string('modulename', 'sampleassessment');
        $this->strsampleassessments = get_string('modulenameplural', 'sampleassessment');
        $this->strsampleassessmentgrades = get_string('grade', 'sampleassessment');
        $this->strlastmodified = get_string('lastmodified');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strsampleassessment.': '.format_string($this->sampleassessment->name,true));

        $this->rubric = new rubric($this->sampleassessment->rubricid, $this->sampleassessment);

        $this->sampleassessment->submissions = $this->get_sample_submissions();
        
        if ($this->usehtmleditor = can_use_html_editor()) {
            $this->defaultformat = FORMAT_HTML;
        } else {
            $this->defaultformat = FORMAT_MOODLE;
        }
    }
    
    function add_instance($sampleassessment, $mform) {
        global $DB;
        
        $sampleassessment->timemodified = time();
        if ($sampleassessment->rubricid != 0) {
            $selected_rubric = new rubric($sampleassessment->rubricid);
            $sampleassessment->grade = $selected_rubric->points;
        }
        
        if (!isset($sampleassessment->autoshowcomment)) $sampleassessment->autoshowcomment = 0;
        
        $sampleassessment->id = $DB->insert_record("sampleassessment", $sampleassessment);
        
        $this->add_sample_submissions($sampleassessment, $mform);
        
        return $sampleassessment->id;
    }
    
    function add_sample_submissions($sampleassessment, $mform, $count=1) {
        global $DB;
        $cm = $DB->get_record("course_modules", array("id"=>$sampleassessment->coursemodule));
        $course = $DB->get_record("course", array("id"=>$cm->course));
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        if ($numsubmission = $sampleassessment->numsubmission) {
            for ($i=$count; $i<=$numsubmission; $i++) {
                // insert record to sampleassessment_submissions
                $newsubmission = $this->add_sample_submission($sampleassessment, $i);
                // store file
                if ($filename = $mform->get_new_filename('samplefile'.$i)) {
                    $file = $mform->save_stored_file('samplefile'.$i, $context->id, 'mod_sampleassessment', 
                            'samplefile', $newsubmission->id, '/', $filename);
                }
            }
        }
    }
    
    function add_sample_submission($sampleassessment, $i) {
        global $DB;
        $newsubmission = new stdClass();
        $samplename = 'samplename'.$i;
        $sampleintro = 'sampleintro'.$i;
        $newsubmission->title = $sampleassessment->$samplename;
        $description = $sampleassessment->$sampleintro;
        $newsubmission->description = $description['text'];
        $newsubmission->assessmentid = $sampleassessment->id;
        $newsubmission->timecreated = time();
        
        if ($newsubmission->id = $DB->insert_record("sampleassessment_submissions", $newsubmission)) {
            return $newsubmission;
        } else {
            print_error('erroraddsamplefailed', 'sampleassessment', $newsubmission->title);
        }
    }
    
    function delete_instance($sampleassessment) {
        global $DB;
        
        $result = true;
        
        # Delete any dependent records here #
        // delete all submissions with all attachments - ignore errors
        $this->delete_sample_submission_files($sampleassessment);
        
        // Delete grade related records
        if ($allsubmissionids = $DB->get_records('sampleassessment_submissions', array('assessmentid'=>$sampleassessment->id), '', 'id')) {
            $allsubmissionids = array_keys($allsubmissionids);
            list($in_sql1, $in_params1) = $DB->get_in_or_equal($allsubmissionids, SQL_PARAMS_NAMED);
            if ($allgradeids = $DB->get_records_select('sampleassessment_grades', 'submissionid '.$in_sql1, $in_params1)) {
                $allgradeids = array_keys($allgradeids);
                list($in_sql2, $in_params2) = $DB->get_in_or_equal($allgradeids, SQL_PARAMS_NAMED);
                $deletegradespec = $DB->delete_records_select('sampleassessment_grade_specs', 'gradeid '.$in_sql2, $in_params2);
            }
            $deletegrades = $DB->delete_records_select('sampleassessment_grades', 'submissionid '.$in_sql1, $in_params1);
        }
        
        if (!$DB->delete_records("sampleassessment_submissions", array("assessmentid"=>$sampleassessment->id))) {
            $result = false;
        }
        
        if (!$DB->delete_records("sampleassessment", array("id"=>$sampleassessment->id))) {
            $result = false;
        }
        
        sampleassessment_grade_item_delete($sampleassessment);
    
        return $result;
    }
    
    function delete_sample_submission_files($sampleassessment) {
        // now get rid of all files
        $fs = get_file_storage();
        if ($cm = get_coursemodule_from_instance('sampleassessment', $sampleassessment->id)) {
            $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
            $fs->delete_area_files($context->id);
        }
   }
   
    function update_instance($sampleassessment, $mform) {
        global $DB;
        
        $cm = $DB->get_record("course_modules", array("id"=>$sampleassessment->coursemodule));
        $course = $DB->get_record("course", array("id"=>$cm->course));
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        
        $sampleassessment->timemodified = time();
        $sampleassessment->id = $sampleassessment->instance;
        
        if (!isset($sampleassessment->autoshowcomment)) {
            $sampleassessment->autoshowcomment = 0;
        }
        
        if ($sampleassessment->rubricid != 0) {
            $selected_rubric = new rubric($sampleassessment->rubricid);
            $sampleassessment->grade = $selected_rubric->points;
        }
        
        // Deal with old and new sample attachment files
        $oldsubmissionsid = array_keys($this->get_sample_submissions($sampleassessment->id));
        $numsubmission = $sampleassessment->numsubmission;
        
        for ($i=1; $i<=$numsubmission; $i++) {
            if (!empty($oldsubmissionsid)) {
                $submission = $this->update_sample_submission($sampleassessment, array_shift($oldsubmissionsid), $i);
                $overwrite = true;
            } else {
                $submission = $this->add_sample_submission($sampleassessment, $i);
                $overwrite = false;
            }
            if ($filename = $mform->get_new_filename('samplefile'.$i)) {
                $file = $mform->save_stored_file('samplefile'.$i, $context->id, 'mod_sampleassessment', 'samplefile', $submission->id, '/', $filename, $overwrite);
            }
        }
        // delete old samples if the number of samples decrease
        while (!empty($oldsubmissionsid)) {
            $oldsubmissionid = array_shift($oldsubmissionsid);
            $oldsubmission = new stdClass();
            $oldsubmission->id = $oldsubmissionid;
            $DB->delete_records("sampleassessment_submissions", array("id"=>$oldsubmissionid));
            
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_sampleassessment', 'samplefile', $oldsubmission->id);
        }
        
        return $DB->update_record("sampleassessment", $sampleassessment);
    }
    
    function update_sample_submission($sampleassessment, $submissionid, $i) {
        global $DB;
        
        $submission = new stdClass();
        $samplename = 'samplename'.$i;
        $sampleintro = 'sampleintro'.$i;
        $submission->id = $submissionid;
        $submission->title = $sampleassessment->$samplename;
        $description = $sampleassessment->$sampleintro;
        $submission->description = $description['text'];
        $submission->assessmentid = $sampleassessment->id;
        $submission->timecreated = time();
        
        if ($DB->update_record("sampleassessment_submissions", $submission)) {
            return $submission;
        } else {
            error("Update sample submission failed: ".$submission->title);
        }
    }
    
    function get_sample_submissions($assessmentid=0) {
        global $DB;
        if (!$assessmentid) $assessmentid = $this->sampleassessment->id;
        $submissions = $DB->get_records('sampleassessment_submissions', array('assessmentid'=>$assessmentid));
        return $submissions;
    }
    
    function count_sample_submissions() {
        global $DB;
        return $DB->count_records('sampleassessment_submission', array('assessmentid'=>$this->sampleassessment->id));
    }
    
    function view() {
        global $CFG, $DB, $USER, $SESSION, $OUTPUT, $PAGE;
        
        $course = $this->course;
        $sampleassessment = $this->sampleassessment;
        $cm = $this->cm;
        
        $context = get_context_instance(CONTEXT_MODULE,$cm->id);
        require_capability('mod/sampleassessment:view', $context);
        
        add_to_log($course->id, "sampleassessment", "view", "view.php?id={$cm->id}",$sampleassessment->name, $cm->id);
        
        $this->view_header();
        
        $course_context = get_context_instance(CONTEXT_COURSE, $course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
                 . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }
        
        $this->view_intro();
        
        $perpage = 30;
        $page = optional_param('page', 0, PARAM_INT);

        $currentgroup = '';
        /// find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        $allowgroups = groups_get_activity_allowed_groups($cm);
        
        /// Get all ppl that are allowed to submit assessment
        if ($users = get_users_by_capability($context, 'mod/sampleassessment:submit', 'u.id', 'u.id ASC', '', '', $currentgroup, '', false)) {
            $users = array_keys($users);
        }
        if ($users and !empty($CFG->enablegroupings) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id ASC')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }
        
        if (empty($users)) {
            echo $OUTPUT->box(print_string('errornousersforassessment', 'sampleassessment'));
        } else {
            if (has_capability('mod/sampleassessment:teachergrade', $context)) {
                
                // Filter store in session variable
                $filter = optional_param('filter', 0, PARAM_TEXT);
                if ($filter !== 0) {
                    $SESSION->mod_sampleassessment_teacher_view_where = $filter;
                } else {
                    if (isset($SESSION->mod_sampleassessment_teacher_view_where)) {
                        $filter = $SESSION->mod_sampleassessment_teacher_view_where;
                    }
                }
                
                // Count student who submitted all assessment
                $params = array();
                $query_params = array('assessmentid'=>$sampleassessment->id, 'numgraded'=>$sampleassessment->numsubmission);
                list($in_sql, $in_params) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
                $params = array_merge($in_params, $query_params);
                $count_graded_sql = "SELECT marker, COUNT(*) AS numgraded FROM {sampleassessment_grades} 
                                     WHERE submissionid IN 
                                         (SELECT id FROM {sampleassessment_submissions} WHERE assessmentid = :assessmentid) AND 
                                         type = 1 AND grade IS NOT NULL AND 
                                         marker $in_sql 
                                     GROUP BY marker 
                                     HAVING numgraded ";
                
                $totalnum = count($users);
                $gradedall = $DB->get_records_sql($count_graded_sql.'= :numgraded', $params);
                $gradedsome = $DB->get_records_sql($count_graded_sql.'< :numgraded', $params);
                $gradedallnum = $gradedall ? count($gradedall) : 0;
                $gradedsomenum = $gradedsome ? count($gradedsome) : 0;
                $notgradednum = $totalnum - $gradedallnum - $gradedsomenum;
                
                $viewer = 'teacher';
                $this->view_samples($viewer);
                $this->rubric->view();
                
                $submissions = $this->get_sample_submissions();
                $samplescount = sizeof($submissions);
                
                // Summary/Statistics of sample assessment activity
                echo $OUTPUT->box_start();
                echo "<h3>".get_string('sampleassessmentsummary', 'sampleassessment');
                echo " (<a href='view.php?id=".$cm->id."&currentgroup=".$currentgroup."&filter=all'>".get_string('showall', 'assessment')."</a>)";
                echo "<a name='sampleassessmentsummary'></a> ";
                echo "</h3>";
                
                echo "<table id='sampleassessmentsummarytable' width='95%' border='0' cellspacing='0' cellpadding='2'>";
                
                $gradedalllink = "<a href='view.php?id=".$cm->id."&currentgroup=".$currentgroup."&filter=gradedall#sampleassessmentsummary'>$gradedallnum / $totalnum</a>";
                $gradedsomelink = "<a href='view.php?id=".$cm->id."&currentgroup=".$currentgroup."&filter=gradedsome#sampleassessmentsummary'>$gradedsomenum / $totalnum</a>";
                $notgradedlink = "<a href='view.php?id=".$cm->id."&currentgroup=".$currentgroup."&filter=notgraded#sampleassessmentsummary'>$notgradednum / $totalnum</a>";
                
                echo "<tr><th".($filter==="gradedall"?" class='activefilter'":"").">".get_string('gradedall', 'sampleassessment').":</th>";
                echo "<td".($filter==="gradedall"?" class='activefilter'":"").">$gradedalllink</td>";
                echo "<th".($filter==="gradedsome"?" class='activefilter'":"").">".get_string('gradedsome', 'sampleassessment').":</th>";
                echo "<td".($filter==="gradedsome"?" class='activefilter'":"").">$gradedsomelink</td>";
                echo "<th".($filter==="notgraded"?" class='activefilter'":"").">".get_string('notgraded', 'sampleassessment').":</th>";
                echo "<td".($filter==="notgraded"?" class='activefilter'":"").">$notgradedlink</td></tr>";

                echo "</table>";
                echo $OUTPUT->box_end();
                
                $tablecolumns = array();
                $tableheaders = array();
                
                $tablecolumns[] = 'fullname';
                $tableheaders[] = get_string('fullname');
                
                for ($i=0; $i<$samplescount; $i++) {
                    $tableheaders[] = get_string('sample', 'sampleassessment').' '.($i+1);
                    $tablecolumns[] = 'grade'.$i;
                }
                
                require_once($CFG->libdir.'/tablelib.php');
                $table = new flexible_table('mod-sampleassessment-grades');
                
                $table->define_columns($tablecolumns);
                $table->define_headers($tableheaders);
                $table->define_baseurl($CFG->wwwroot.'/mod/sampleassessment/view.php?id='.$cm->id.'&amp;currentgroup='.$currentgroup);

                $table->sortable(true, 'lastname');//sorted by lastname by default
                $table->collapsible(true);
                $table->initialbars(false);
             
                $table->column_class('fullname', 'fullname');
                for ($i=0; $i<$samplescount; $i++) {
                    $table->column_class('grade'.$i, 'grade');
                    $table->no_sorting('grade'.$i);
                    $table->column_style('grade'.$i, 'text-align', 'center');
                }
                
                $table->set_attribute('cellspacing', '0');
                $table->set_attribute('id', 'attempts');
                $table->set_attribute('class', 'assessment_grades');
                $table->set_attribute('width', '95%');
                
                // Start working -- this is necessary as soon as the niceties are over
                $table->setup();
                
                if (list($where, $params) = $table->get_sql_where()) {
                    $where .= empty($where) ? '' : ' AND ';
                }
                
                if ($sort = $table->get_sql_sort()) {
                $sort = ' ORDER BY '.$sort;
                }
                
                $where .= $this->get_sql_filter_teacher_view($filter, $sampleassessment->numsubmission, $sampleassessment->id);
                
                $submission_ids = array_keys($submissions);
                
                // Get student assessment on samples first, return user name even there is no assessment
                $select = "SELECT u.id, u.firstname, u.lastname, 
                           (SELECT data FROM {user_info_data} WHERE userid = u.id AND fieldid = 
                               (SELECT id FROM {user_info_field} WHERE shortname = 'chiname')
                           ) as chiname ";
                $sql = "FROM {user} u WHERE ".$where."u.id IN (".implode(',',$users).") ";
                
                ///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
                $offset = $page * $perpage;
                $strupdate = get_string('update');
                $strgrade  = get_string('grade');
                
                $popup_width = $this->rubric->id ? 1024 : 800;
                $popup_height = $this->rubric->id ? 600 : 400;
                
                if (($ausers = $DB->get_records_sql($select.$sql.$sort, $params, $table->get_page_start(), $table->get_page_size())) !== false) {
                    $table->pagesize($perpage, count($ausers));
                    
                    foreach ($ausers as $auser) {
                        $popup_url = '/mod/sampleassessment/assessment_grades.php?id='.$this->cm->id.
                                     '&amp;inpopup=1&amp;mode=single&amp;marker='.$auser->id.'&amp;offset='.$offset++;
                   
                        // get grade of each sample
                        for ($i=0; $i<sizeof($submission_ids); $i++) {
                            $sample_grade_name = 'sample_grade_'.$i;
                            $sample_grade_sql = "SELECT * FROM {sampleassessment_grades} 
                                                 WHERE type = 1 AND submissionid = ".$submission_ids[$i]." AND marker = ".$auser->id;
                            if ($sample_grade_detail = $DB->get_record_sql($sample_grade_sql)) {
                                $$sample_grade_name = $this->display_grade($sample_grade_detail->grade);
                            } else {
                                $$sample_grade_name = get_string('notgraded', 'assessment');
                            }
                            $$sample_grade_name = $OUTPUT->action_link($popup_url.'&amp;submissionid='.$submission_ids[$i].'&amp;type=1', $$sample_grade_name, 
                                                  new popup_action('click', $popup_url.'&amp;submissionid='.$submission_ids[$i].'&amp;type=1', 'grade_'.$auser->id.'_'.$i, array('height' => $popup_height, 'width' => $popup_width)), 
                                                  array('title'=>$$sample_grade_name, 'target'=>'_blank'));
                        }
                        
                        $userlink = fullname($auser);
                        
                        $row = array();
                        $row[] = $userlink;
                        for ($i=0; $i<sizeof($submission_ids); $i++) {
                            $sample_grade_name = 'sample_grade_'.$i;
                            $row[] = $$sample_grade_name;
                        }
                        
                        $table->add_data($row);
                    }
                    $table->finish_output();
                }
            } else {
                $viewer = 'student';
                $this->view_samples($viewer);
                $user = $USER;
                
                echo '<script type="text/javascript">';
                echo 'function showhiderubric(divid, link) {
                          var targetdiv = document.getElementById(divid);
                          if (targetdiv.style.display == "none") {
                              targetdiv.style.display = "";
                              if (link != "none") link.innerHTML = "'.get_string('hiderubric', 'assessment').'"
                          } else {
                              targetdiv.style.display = "none";
                              if (link != "none") link.innerHTML = "'.get_string('showrubric', 'assessment').'"
                          }
                      }';
                echo '</script>';
            }
        }
        
        $this->view_dates();
        $this->view_footer();
    }
   
   function get_sql_filter_teacher_view($filter, $numsubmission, $sampleassessmentid) {
      global $CFG, $DB;
      
      $where = '';
      if ($filter == '0') return '';
      
      switch ($filter) {
         case 'gradedall':
            $options = array('IN', '= '.$numsubmission);
            break;
         case 'gradedsome':
            $options = array('IN', '< '.$numsubmission);
            break;
         case 'notgraded':
            $options = array('NOT IN', '<= '.$numsubmission);
            break;
         default:
            $options = array();
            break;
      }
      
      if (!empty($options)) {
         $sql = "SELECT marker, COUNT(*) AS numgraded FROM {sampleassessment_grades} 
                  WHERE submissionid IN 
                     (SELECT id FROM {sampleassessment_submissions} WHERE assessmentid = ".$sampleassessmentid.") AND 
                     type = 1 AND grade IS NOT NULL 
                  GROUP BY marker 
                  HAVING numgraded ".$options[1];
         $userids = $DB->get_records_sql($sql);
         if (!$userids) {
            if ($filter == 'gradedall')
               return 'u.id = -1 AND ';
            else if ($filter == 'notgraded')
               return '';
         }
         $userids = array_keys($userids);
         $where = "u.id ".$options[0]." (".implode(",", $userids).")";
         $where .= " AND ";
      }
      
      return $where;
   }
   
   function getfullnameformat($prefix='', $sql=true) {
      $fullnameformat = get_config('', 'fullnamedisplay');
      if (!$sql) return $fullnameformat;
      if ($fullnameformat == 'lastname firstname') {
         $selectfullname = $prefix.'lastname, " ", '.$prefix.'firstname';
      } else if ($fullnameformat == 'firstname lastname') {
         $selectfullname = $prefix.'firstname, " ", '.$prefix.'lastname';
      }
      $selectfullname = 'CONCAT('.$selectfullname.')';
      return $selectfullname;
   }
   
   function display_grade($grade, $percent=0) {
      static $scalegrades = array();   // Cache scales for each sampleassessment - they might have different scales!!
      $percentage = '';
      if ($this->sampleassessment->grade >= 0) {    // Normal number
         if ($grade == -1) {
            return 'N/A';
         } else {
            if ($this->sampleassessment->rubricid > 0) {
               if ($percent) $percentage = ' ('.round(($grade/$this->rubric->points)*100, 1).'%)';
               return $grade.' / '.$this->rubric->points.$percentage;
            } else {
               return $grade.' / '.$this->sampleassessment->grade.$percentage;
            }
         }
      } else {                          // Scale
         if (empty($scalegrades[$this->sampleassessment->id])) {
            if ($scale = get_record('scale', 'id', -($this->sampleassessment->grade))) {
               $scalegrades[$this->sampleassessment->id] = make_menu_from_list($scale->scale);
            } else {
               return 'N/A';
            }
         }
         if (isset($scalegrades[$this->sampleassessment->id][$grade])) {
            return $scalegrades[$this->sampleassessment->id][$grade];
         }
         return 'N/A';
      }
   }
   
    function view_header() {
        global $CFG, $PAGE, $OUTPUT;
        $PAGE->set_title($this->pagetitle);
        $PAGE->set_heading($this->course->fullname);
        echo $OUTPUT->header();
        groups_print_activity_menu($this->cm,  $CFG->wwwroot .'/mod/sampleassessment/view.php?id=' . $this->cm->id);
    }
   
    function view_intro() {
        global $OUTPUT;
        echo $OUTPUT->heading(get_string('description'), 2, 'main teacherviewheading');
        echo $OUTPUT->box_start();
        echo format_module_intro('sampleassessment', $this->sampleassessment, $this->cm->id);
        echo $OUTPUT->box_end();
    }
   
    function view_samples($role) {
        global $CFG, $OUTPUT, $USER, $DB;
        
        $sampleassessment = $this->sampleassessment;
        $submissions = $sampleassessment->submissions;
        $counter = 0;
        
        $popup_url = '/mod/sampleassessment/assessment_grades.php?id='.$this->cm->id.'&amp;mode=single';
        $popup_width = $this->rubric->id ? 1024 : 800;
        $popup_height = $this->rubric->id ? 600 : 400;
        
        if ($role == 'teacher') {
            $type = 0;
            $assessment_grade_label = get_string('modelassessment', 'sampleassessment');
        } else if ($role == 'student') {
            $type = 1;
            $assessment_grade_label = get_string('grade', 'sampleassessment');
        }
        
        $tabs_list = html_writer::start_tag('ul');
        $samples_div = html_writer::start_tag('div');
        
        foreach ($submissions as $submission) {
            $counter++;
            $tab_selected = ($counter == 1) ? 'selected' : '';
            $file = sampleassessment_display_sample_files($submission, $sampleassessment);
            $tabs_list .= html_writer::start_tag('li', array('class'=>$tab_selected));
            $tabs_list .= html_writer::link('#tab'.$counter, html_writer::tag('em', get_string('sample', 'sampleassessment').' '.$counter));
            $tabs_list .= html_writer::end_tag('li');
            
            $samples_div .= html_writer::start_tag('div', array('id'=>'tab'.$counter));
            $samples_div .= html_writer::start_tag('table', array('class'=>'sampledetailtable', 'width'=>'95%', 'border'=>'0', 'cellspacing'=>'0', 'cellpadding'=>'2'));
            $samples_div .= html_writer::start_tag('tr');
            $samples_div .= html_writer::tag('th', get_string('title', 'sampleassessment').':');
            $samples_div .= html_writer::tag('td', $submission->title);
            $samples_div .= html_writer::end_tag('tr');
            $samples_div .= html_writer::start_tag('tr');
            $samples_div .= html_writer::tag('th', get_string('attachment', 'sampleassessment').':');
            $samples_div .= html_writer::tag('td', $file);
            $samples_div .= html_writer::end_tag('tr');
            
            if (!empty($submission->description)) {
                $samples_div .= html_writer::start_tag('tr');
                $samples_div .= html_writer::tag('th', get_string('description').':');
                $samples_div .= html_writer::tag('td', format_text($submission->description));
                $samples_div .= html_writer::end_tag('tr');
            }
            
            $sampleassessment_grade = $this->get_sampleassessment_grade($USER->id, $submission->id, $type);
            
            if ($sampleassessment_grade) {
                $model_assessment = $this->display_grade($sampleassessment_grade->grade);
            } else {
                $model_assessment = 'N/A';
            }
            
            $status = $this->get_sampleassessment_status();
            $linktext =  get_string(($status['gradetime'] == 2) ? 'clicktoview' : 'clicktoupdate', 'sampleassessment');
            $markerid = ($type == 0 && $sampleassessment_grade) ? $sampleassessment_grade->marker : $USER->id;
            
            $link_to_grade = $OUTPUT->action_link($popup_url.'&amp;marker='.$markerid.'&amp;submissionid='.$submission->id.'&amp;type='.$type, $linktext, 
                                                  new popup_action('click', $popup_url.'&amp;marker='.$markerid.'&amp;submissionid='.$submission->id.'&amp;type='.$type, 
                                                  'grade_'.$USER->id.'_'.$counter, array('height' => $popup_height, 'width' => $popup_width)), 
                                                  array('title'=>$linktext, 'target'=>'_blank'));
            $samples_div .= html_writer::start_tag('tr');
            $samples_div .= html_writer::tag('th', $assessment_grade_label.':');
            $samples_div .= html_writer::tag('td', $model_assessment.' ('.$link_to_grade.')');
            $samples_div .= html_writer::end_tag('tr');
            
            // Average of grades by students
            if ($role == 'teacher') {
                $average_grade_sql = 'SELECT AVG(grade) AS agrade, COUNT(*) AS num FROM {sampleassessment_grades} WHERE type = 1 AND submissionid = ?';
                $average_grade = $DB->get_record_sql($average_grade_sql, array($submission->id));
                if ($average_grade) {
                    $average_grade = $this->display_grade(round($average_grade->agrade, 2)).' ('.get_string('numstudentgraded', 'sampleassessment', $average_grade->num).')';
                } else {
                    $average_grade = 'N/A';
                }
                $samples_div .= html_writer::start_tag('tr');
                $samples_div .= html_writer::tag('th', get_string('studentaveragegrade', 'sampleassessment').':');
                $samples_div .= html_writer::tag('td', $average_grade);
                $samples_div .= html_writer::end_tag('tr');
            }
            
            $samples_div .= html_writer::end_tag('table');
            $samples_div .= html_writer::end_tag('div');
        }
        $tabs_list .= html_writer::end_tag('ul');
        $samples_div .= html_writer::end_tag('div');
        
        echo html_writer::tag('div', $tabs_list.$samples_div, array('id'=>'samples'));
    }
    
    function view_dates() {
        global $OUTPUT;
        $sampleassessment = $this->sampleassessment;
        $notset = get_string('notset', 'sampleassessment');
        
        $gradestart = $sampleassessment->gradestart;
        $gradeend = $sampleassessment->gradeend;
        $gradepublish = $sampleassessment->gradepublish;
        
        $startendcolor = '<span class="calendarkey startendcolor">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>';
        $publishcolor = '<span class="calendarkey publishcolor">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>';
        
        $OUTPUT->box_start();
        echo '<table id="viewsampledatetable" width="100%">';
        echo '<tr><td class="c0">'.get_string('start','assessment').':</td>';
        echo '<td class="c1">'.$startendcolor.(!empty($gradestart) ? userdate($gradestart) : $na).'</td>';
        echo '<td rowspan="3"><div id="sampledatecalendar"></div></td></tr>';
        echo '<tr><td class="c0">'.get_string('end','assessment').':</td>';
        echo '<td class="c1">'.$startendcolor.(!empty($gradeend) ? userdate($gradeend) : $na).'</td></tr>';
        echo '<tr><td class="c0" style="vertical-align:top;">'.get_string('publish','assessment').':</td>';
        echo '<td class="c1" style="vertical-align:top;">'.$publishcolor.(!empty($gradepublish) ? userdate($gradepublish) : $na).'</td></tr>';
        echo '</table>';
        $OUTPUT->box_end();
        
        echo '<script type="text/javascript">
              YUI().use("calendar", function(Y)
              {   
                  var rules = {
                      "'.date("Y", $gradestart).'": {
                          "'.(date("n", $gradestart)-1).'": {
                              "'.date("j", $gradestart).'": {
                                  "all": "gradestart"
                              }
                         }
                      },
                      "'.date("Y", $gradeend).'": {
                          "'.(date("n", $gradeend)-1).'": {
                              "'.date("j", $gradeend).'": {
                                  "all": "gradeend"
                              }
                         }
                      },
                      "'.date("Y", $gradepublish).'": {
                          "'.(date("n", $gradepublish)-1).'": {
                              "'.date("j", $gradepublish).'": {
                                  "all": "gradepublish"
                              }
                         }
                      }
                  };
                  var filterFunction = function (date, node, rules) {
                      if (rules.indexOf("gradestart" >= 0)) {
                          node.addClass("gradestart_date");
                      }
                      if (rules.indexOf("gradeend" >= 0)) {
                          node.addClass("gradeend_date");
                      }
                      if (rules.indexOf("gradepublish" >= 0)) {
                          node.addClass("gradepublish_date");
                      }
                  }
                  var calendar = new Y.Calendar({
                      contentBox: "#sampledatecalendar",
                      width: "250px",
                      showPrevMonth: true,
                      showNextMonth: true,
                      date: new Date('.date("Y").','.(date("n")-1).','.date("j").')
                  });
                  calendar.set("customRenderer", {rules: rules, filterFunction: filterFunction});
                  calendar.render();
              });
              </script>';
    }
    
    function view_footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }
    
   function process_sampleassessment_grades($mode, $type, $marker) {
      ///The main switch is changed to facilitate
      ///1) Skip to the next one on the popup
      ///2) Save and Skip to the next one on the popup
      
      //make user global so we can use the id
      global $USER, $PAGE, $OUTPUT;
      
      switch ($mode) {
         case 'grade':                         // We are in a popup window grading
            if ($sampleassessment_grade = $this->process_feedback($type)) {
               //IE needs proper header with encoding
               $PAGE->set_title(get_string('feedback', 'sampleassessment').':'.format_string($this->sampleassessment->name));
               echo $OUTPUT->header();
               echo $OUTPUT->heading(get_string('changessaved'));
            }
            echo '<script type="text/javascript">
                     window.opener.location.reload();
                  </script>';
            close_window();
            break;
         
         case 'saveonly':
            if ($sampleassessment_grade = $this->process_feedback($type)) {
               $this->display_sampleassessment_grade($type, $marker);
            }
            echo '<script type="text/javascript">
                     window.opener.location.reload();
                  </script>';
            break;
         
         case 'deletegrade':
            if ($this->delete_model_grade()) {
               echo '<script type="text/javascript">
                        window.opener.location.reload();
                     </script>';
               close_window();
            } else {
                notice("Something is wrong, this model grade cannot be deleted.");
            }
            break;
         case 'single':                        // We are in a popup window displaying assessment_grade
            $this->display_sampleassessment_grade($type, $marker);
            break;
            
         case 'next':
            $this->display_sampleassessment_grade($type, $marker);
            break;
         
         case 'saveandnext':
            $this->display_sampleassessment_grade($type);
            break;
         
         default:
            notice("Something seriously is wrong!");
            break;
      }
   }
   
   // Compare current time to a given time or period, then return a number
   // For a given time, 0 = eariler, 1 = later
   // For a period, 0 = eariler, 1 = within, 2 = later
   function compare_time($date, $end=0) {
      $timenow = time();
      if ($end) {
         $start = $date;
         if ($timenow < $start)
            return 0;
         else if ($timenow > $start && $timenow < $end)
            return 1;
         else if ($timenow > $end)
            return 2;
      } else {
         if ($timenow < $date)
            return 0;
         else
            return 1;
      }
   }
   
    function get_sampleassessment_status($markerid=0) {
        global $DB;
        if ($this->sampleassessment->gradestart != 0 && $this->sampleassessment->gradeend != 0) {
            $status['gradetime'] = $this->compare_time($this->sampleassessment->gradestart, $this->sampleassessment->gradeend);
        } else {
            $status['gradetime'] = -1;
        }
        if ($this->sampleassessment->gradepublish != 0) {
            $status['gradepublished'] = $this->compare_time($this->sampleassessment->gradepublish);
        }
        $submissionids = array_keys($this->sampleassessment->submissions);
        if ($markerid) {
            for ($i=0; $i<sizeof($submissionids); $i++) {
                if ($grade = $DB->get_record('sampleassessment_grades', array('submissionid'=>$submissionids[$i], 'marker'=>$markerid))) {
                    $status['graded'.$i] = 1;
                } else {
                    $status['graded'.$i] = 0;
                }
            }
        }
        return $status;
    }
    
   function get_sampleassessment_grade($markerid=0, $submissionid, $type=0, $createnew=false, $teachermodified=false) {
      global $DB;
      
      // Only ONE model assessment, all teacher should update the same entry
      if ($type == 0) {
         $sampleassessment_grade = $DB->get_record('sampleassessment_grades', array('submissionid'=>$submissionid, 'type'=>$type));
      } else {
         $sampleassessment_grade = $DB->get_record('sampleassessment_grades', array('submissionid'=>$submissionid, 'marker'=>$markerid, 'type'=>$type));
      }
      
      if ($sampleassessment_grade || !$createnew) {
         if ($sampleassessment_grade) $sampleassessment_grade->comment = stripslashes($sampleassessment_grade->comment);
         return $sampleassessment_grade;
      }
      
      $new_sampleassessment_grade = $this->prepare_new_sampleassessment_grade($markerid, $submissionid, $type, $teachermodified);
      
      if (!$new_sampleassessment_grade->id = $DB->insert_record("sampleassessment_grades", $new_sampleassessment_grade)) {
         error("Could not insert a new empty sampleassessment grade");
      }
      
      return $new_sampleassessment_grade;
   }
   
    function display_sampleassessment_grade($type, $markerid) {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER;
        
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/tablelib.php');
        
        /// construct SQL, using current offset to find the data of the next student
        $course = $this->course;
        $sampleassessment = $this->sampleassessment;
        $cm = $this->cm;
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        $status = $this->get_sampleassessment_status();
        
        //if (has_capability('mod/sampleassessment:teachergrade', $context) && !has_capability('mod/sampleassessment:submit', $context)) {
        if (has_capability('mod/sampleassessment:teachergrade', $context)) {
            $viewer = 'teacher';
        } else {
            $viewer = 'student';
        }
        
        $offset = optional_param('offset', 0, PARAM_INT);//offset for where to start looking for student.
        $submissionid = required_param('submissionid', PARAM_INT);
        
        $display_mode = '';
        if ($USER->id == $markerid) {
            if ($status['gradetime'] == 1 || $viewer != 'student')
                $display_mode = 'edit';
        } else {
            // student cannot view other students, they can only view teacher assessment
            if ($viewer == 'student' && !has_capability('mod/sampleassessment:teachergrade', $context, $markerid)) {
                error('Access violation! You cannot view other student assessments!');
            }
        }
        
        $params = array($markerid, 'chiname', $markerid);
        $sql = "SELECT *, 
               (SELECT data FROM {user_info_data} WHERE userid = ? AND fieldid = 
                    (SELECT id FROM {user_info_field} WHERE shortname = ?)
                ) AS chiname
                FROM {user} u WHERE u.id = ?";
        
        if (!$marker = $DB->get_record_sql($sql, $params)) {
            error('No such marker!');
        }
        
        if (!$sampleassessment_grade = $this->get_sampleassessment_grade($marker->id, $submissionid, $type)) {
            $isgraded = 0;
            $sampleassessment_grade = $this->prepare_new_sampleassessment_grade($marker->id, $submissionid, $type);
        } else {
            $isgraded = 1;
        }
        
        /// Get all ppl that can submit assessments
        $currentgroup = groups_get_activity_group($cm);
        if ($users = get_users_by_capability($context, 'mod/sampleassessment:submit', 'u.id', '', '', '', $currentgroup, '', false)) {
            $users = array_keys($users);
        }
        
        // if groupmembersonly used, remove users who are not in any group
        if ($users and !empty($CFG->enablegroupings) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }
        
        $makername = fullname($marker, true);
        
        $nextid = 0;
        if ($users) {
            $params = array();
            $query_params = array('submissionid'=>$submissionid);
            list($in_sql, $in_params) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
            $params = array_merge($in_params, $query_params);
            $select = "SELECT u.id, u.firstname, u.lastname, s.id AS gradeid, s.grade, s.comment, s.timemodified ";
            $sql = "FROM {user} u 
                    LEFT JOIN {sampleassessment_grades} s ON u.id = s.marker
                    AND s.submissionid = :submissionid 
                    WHERE u.id $in_sql";
         
            if ($sort = flexible_table::get_sort_for_table('mod-sampleassessment-grade')) {
                $sort = 'ORDER BY '.$sort.' ';
            }
            
            if (($auser = $DB->get_records_sql($select.$sql.$sort, $params, $offset+1, 1)) !== false) {
                $nextuser = array_shift($auser);
                $nextid = $nextuser->id;
            }
        }
        
        ///Some javascript to help with setting up >.>
        echo '<script type="text/javascript">'."\n";
        echo 'function setNext(){'."\n";
        echo 'document.getElementById(\'submitform\').mode.value=\'next\';'."\n";
        echo 'document.getElementById(\'submitform\').marker.value="'.$nextid.'";'."\n";
        echo '}'."\n";
        echo 'function saveClose(){'."\n";
        echo 'document.getElementById(\'submitform\').menuindex.value = document.getElementById(\'submitform\').grade.selectedIndex;'."\n";
        echo '}'."\n";
        echo 'function saveOnly(){'."\n";
        echo 'document.getElementById(\'submitform\').menuindex.value = document.getElementById(\'submitform\').grade.selectedIndex;'."\n";
        echo 'document.getElementById(\'submitform\').mode.value=\'saveonly\';'."\n";
        echo '}'."\n";
        echo 'function saveNext(){'."\n";
        echo 'document.getElementById(\'submitform\').mode.value=\'saveandnext\';'."\n";
        echo 'document.getElementById(\'submitform\').marker.value="'.$nextid.'";'."\n";
        echo 'document.getElementById(\'submitform\').saveuserid.value="'.$marker->id.'";'."\n";
        echo 'document.getElementById(\'submitform\').menuindex.value = document.getElementById(\'submitform\').grade.selectedIndex;'."\n";
        echo '}'."\n";
        echo 'function deleteGrade() {
                  if (confirm(\''.get_string('confirmdeletegrade', 'sampleassessment').'\')) {
                      document.getElementById(\'submitform\').mode.value=\'deletegrade\';
                      document.getElementById(\'submitform\').submit();
                  } else {
                      return false;
                  }
             }';
        echo 'function view_comment(view) {
                  if (view) {
                      document.getElementById("div_view_comment").className="generalbox";
                      document.getElementById("div_edit_comment").className="generalbox div_hide";
                  } else {
                      document.getElementById("div_view_comment").className="generalbox div_hide";
                      document.getElementById("div_edit_comment").className="generalbox";
                  }
             }';
        echo '</script>'."\n";
        
        $style = "<style type=\"text/css\">
                  .div_hide {display: none;}
                 </style>";
        print $style;
        
        $PAGE->set_title(get_string('sampleassessment', 'sampleassessment').': '.fullname($marker).': '.format_string($this->sampleassessment->name));
        echo $OUTPUT->header();
        
        echo "<div style='width:100%; margin-bottom: 20px;'>";
        echo "<table width='100%' id='mod-sampleassessment-activitydetailtable' border='0' cellspacing='0' cellpadding='2'>";
        
        if ($submission = $this->get_submission($submissionid)) {
            $sampleattachment = sampleassessment_display_sample_files($submission, $sampleassessment);
            echo "<tr><th>".get_string('samplename', 'sampleassessment').":</th>";
            echo "<td>$submission->title</td></tr>";
            echo "<tr><th>".get_string('sample', 'sampleassessment').":</th>";
            echo "<td>$sampleattachment</td></tr>";
        }
        
        echo "<tr><th>".get_string('marker', 'sampleassessment').":</th>";
        echo "<td>".fullname($marker)."</td></tr>";
        if (isset($sampleassessment_grade->timemodified) && $sampleassessment_grade->timemodified != 0) {
            echo "<tr><th>".get_string('gradedate', 'sampleassessment').":</th>";
            echo "<td>".userdate($sampleassessment_grade->timemodified)."</td></tr>";
        }
        echo '</table>';
        echo '</div>';
        
        echo '<form id="submitform" action="assessment_grades.php" method="post">';
        echo '<table width="95%" cellspacing="0" class="feedback" >';
        ///Start of teacher info row
        echo '<tr>';
        echo '<td valign="top">';
        echo '<div>'; // xhtml compatibility - invisiblefieldset was breaking layout here
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'offset', 'value'=>($offset+1)));
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'marker', 'value'=>$marker->id));
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'submissionid', 'value'=>$submissionid));
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=>$this->cm->id));
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'mode', 'value'=>'grade'));
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'type', 'value'=>$type));
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'menuindex', 'value'=>'0'));
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'saveuserid', 'value'=>'-1'));

        echo '<div class="from">';
        
        // Show the "Compare with model assessment" button
        if ((($sampleassessment_grade->grade >= 0 && $status['gradepublished'] != 1 && $status['gradepublished']) || 
           ($sampleassessment->autoshowcomment == 1 && $isgraded)) && $type == 1) {
            $display_mode = '';
            // Get the model assessment by teacher
            $modelassessment_grade = $this->get_sampleassessment_grade($marker->id, $submissionid, 0);
            if ($modelassessment_grade && $modelassessment_grade->grade >= 0) {
                if($this->rubric->id){
                    $rubric = $this->rubric;
                    $rubric->is_graded = 1;
                    
                    $totalmodelgrade = $modelassessment_grade->grade;
                    
                    $modelgradeitems = $rubric->get_spec_data(0, 0, 0, $modelassessment_grade->marker, $submissionid);
                    
                    // Get grade for each row for creating JS array
                    $modelgrades = array();
                    foreach ($modelgradeitems as $item) {
                        $modelgrades[] = $item->score;
                    }
                    $modelgradesjs = 'var modelgrades = new Array('.implode(',', $modelgrades).');';
                    
                    // Get information for creating arrays for use in JS
                    $rubric->get_rowspecs();
                    $rubric->get_colspecs();
                    $rubric->get_specs();
                    ksort($rubric->rowspecs);

                    $rownum = sizeof($rubric->rowspecs);
                    $colnum = sizeof($rubric->colspecs);

                    $rowspecids = array();
                    $td_ids = array();
                    $td_ids_js = 'var td_ids = new Array();';
                    $td_classes = array();
                    $td_classes_js = 'var td_classes = new Array();';
                    
                    foreach ($rubric->rowspecs as $rowspecid => $rowspec) {
                        $rowindex = $rowspec['displayorder']-1;
                        $rowspecids[$rowindex] = $rowspecid;
                        $td_ids_js .= 'td_ids['.$rowindex.'] = new Array();';
                        $td_classes_js .= 'td_classes['.$rowindex.'] = new Array();';
                        foreach ($rubric->colspecs as $colspecid => $colspec) {
                            $colindex = $colspec['displayorder']-1;
                            $points = $colspec['points'];
                            $maxpoints = $colspec['maxpoints'];
                            $specid = $rubric->spec_map[$rowspecid][$colspecid];
                            if ($rowspec['custompoint'] == 1) {
                                $points = $rubric->specs[$specid]['points'];
                                $maxpoints = $rubric->specs[$specid]['maxpoints'];
                            }
                            $td_id = 'rb_td_'.$rowspecid.'_';
                            if (is_numeric($maxpoints) && $maxpoints != 0) {
                                $td_id .= $points.'_'.$maxpoints;
                                if ($modelgrades[$rowindex] >= $points && $modelgrades[$rowindex] <= $maxpoints)
                                    $td_classes_js .= 'td_classes['.$rowindex.']['.$colindex.'] = 1;';
                                else
                                    $td_classes_js .= 'td_classes['.$rowindex.']['.$colindex.'] = 0;';
                            } else {
                                $td_id .= $points;
                                if ($modelgrades[$rowindex] == $points)
                                    $td_classes_js .= 'td_classes['.$rowindex.']['.$colindex.'] = 1;';
                                else
                                    $td_classes_js .= 'td_classes['.$rowindex.']['.$colindex.'] = 0;';
                            }
                            $td_ids_js .= 'td_ids['.$rowindex.']['.$colindex.'] = "'.$td_id.'";';
                        }
                    }
                    
                    // Javascript function to compare model grade with student grade on the fly
                    echo '<script type="text/javascript">
                          function compare_grade(status) {'.
                           $modelgradesjs.
                           $td_ids_js.
                           $td_classes_js.'
                           var compareon = document.getElementById("compareon");
                           var compareoff = document.getElementById("compareoff");
                           var modelcomment = document.getElementById("modelcomment");
                           if (status) {
                              compareon.style.display = "none";
                              compareoff.style.display = "";
                              modelcomment.className = "generalbox";
                              for (var i=1; i<'.$rownum.'+1; i++) {
                                 var rowgrade = document.getElementById("rowgrade"+i);
                                 var diff = parseInt(modelgrades[i-1]) - parseInt(rowgrade.innerHTML);
                                 if (diff > 0) diff = "+" + String(diff);
                                 var modelgrade = "<span id=\'modelgrade" + i + "\' class=\'modelgrade\'>" + modelgrades[i-1] + " (" + diff + ")</span>";
                                 rowgrade.innerHTML += modelgrade;
                                 for (var j=1; j<'.$colnum.'+1; j++) {
                                    if (td_classes[i-1][j-1] == 1) {
                                       var td_to_change = document.getElementById(td_ids[i-1][j-1]);
                                       if (td_to_change.className == "shadebg") {
                                          td_to_change.className = "overlapbg";
                                       } else {
                                          td_to_change.className = "modelbg";
                                       }
                                    }
                                 }
                              }
                              
                              var totalgrade = document.getElementById("div_grandtotal");
                              var totaldiff = parseInt('.$totalmodelgrade.') - parseInt('.$sampleassessment_grade->grade.');
                              if (totaldiff > 0) totaldiff = "+" + String(totaldiff);
                              var totalmodelgrade = "<span id=\'totalmodelgrade\' class=\'modelgrade\'>" + '.$totalmodelgrade.' + " (" + totaldiff + ")</span>";
                              totalgrade.innerHTML += totalmodelgrade;
                           } else {
                              compareon.style.display = "";
                              compareoff.style.display = "none";
                              modelcomment.className = "generalbox div_hide";
                              for (var i=1; i<'.$rownum.'+1; i++) {
                                 var modelgrade = document.getElementById("modelgrade"+i);
                                 modelgrade.parentNode.removeChild(modelgrade);
                                 for (var j=1; j<'.$colnum.'+1; j++) {
                                    if (td_classes[i-1][j-1] == 1) {
                                       var td_to_change = document.getElementById(td_ids[i-1][j-1]);
                                       if (td_to_change.className == "overlapbg") {
                                          td_to_change.className = "shadebg";
                                       } else {
                                          td_to_change.className = "";
                                       }
                                    }
                                 }
                              }
                              
                              var totalmodelgrade = document.getElementById("totalmodelgrade");
                              totalmodelgrade.parentNode.removeChild(totalmodelgrade);
                           }
                        }
                     </script>';
            } else {
               $modelgrade = $this->display_grade($modelassessment_grade->grade);
               echo '<script type="text/javascript">
                        function compare_grade(status) {
                           var compareon = document.getElementById("compareon");
                           var compareoff = document.getElementById("compareoff");
                           if (status) {
                              compareon.style.display = "none";
                              compareoff.style.display = "";
                              modelcomment.className = "generalbox";
                              var grade_div = document.getElementById("totalgradedisplay");
                              var diff = parseInt('.$modelassessment_grade->grade.') - parseInt('.$sampleassessment_grade->grade.');
                              if (diff > 0) diff = "+" + String(diff);
                              var modelgrade = "<span id=\'modelgrade\' class=\'modelgrade\'>" + "'.$modelgrade.'" + " (" + diff + ")</span>";
                              grade_div.innerHTML += modelgrade;
                           } else {
                              compareon.style.display = "";
                              compareoff.style.display = "none";
                              modelcomment.className = "generalbox div_hide";
                              var modelgrade = document.getElementById("modelgrade");
                              modelgrade.parentNode.removeChild(modelgrade);
                           }
                        }
                     </script>';
            }
            echo '<div class="showteachergrade">
                     <input id="compareon" name="compareon" type="button" value="'.get_string('comparewithmodelassessment', 'sampleassessment').'" onclick="compare_grade(1)" />
                     <input id="compareoff" name="compareoff" style="display:none;" type="button" value="'.get_string('showstudentassessmentonly', 'sampleassessment').'" onclick="compare_grade(0)" />
                  </div>';
         }
      }
      echo '</div>';
      
      // If this assessment has a rubric, then use that to grade
      if($this->rubric->id){
         echo html_writer::end_tag('td');
         echo html_writer::end_tag('tr');
         echo html_writer::start_tag('tr');
         echo html_writer::start_tag('td');
         $this->rubric->grade($sampleassessment, $sampleassessment_grade, 0, $type, $viewer, $display_mode, $marker->id, $submissionid);
         echo html_writer::end_tag('td');
         echo html_writer::end_tag('tr');
         echo html_writer::start_tag('tr');
         echo html_writer::start_tag('td');
         echo html_writer::start_tag('div');
      } else {
         echo html_writer::start_tag('div', array('id'=>'totalgradedisplay', 'class'=>'grade'));
         echo html_writer::tag('strong', get_string('grade').':');
         if ($display_mode == 'edit') {
            echo html_writer::select(make_grades_menu($sampleassessment->grade), 'grade', $sampleassessment_grade->grade, array('0'=>get_string('nograde')));
            //choose_from_menu(make_grades_menu($sampleassessment->grade), 'grade', $sampleassessment_grade->grade, get_string('nograde'), '', -1, false);
         } else {
            echo $this->display_grade($sampleassessment_grade->grade);
         }
         echo html_writer::end_tag('div');
      }
      
      $switch_comment_mode = '(<a href="#" onclick="view_comment(1);return false;">'.get_string('view').'</a> / ';
      $switch_comment_mode .= '<a href="#" onclick="view_comment(0);return false;">'.get_string('edit').'</a>)';
      
      if ($display_mode == 'edit') {
         echo html_writer::start_tag('div', array('class'=>'commentmode'));
         echo html_writer::tag('strong', get_string('comment', 'assessment'));
         echo $switch_comment_mode;
         echo html_writer::end_tag('div');
         
         echo $OUTPUT->box_start('generalbox div_hide', 'div_view_comment');
         echo (trim($sampleassessment_grade->comment) == '' ? 'N/A': format_text($sampleassessment_grade->comment, FORMAT_HTML));
         echo html_writer::empty_tag('br');
         echo $OUTPUT->box_end();
         
         echo $OUTPUT->box_start('generalbox', 'div_edit_comment');
         print_textarea($this->usehtmleditor, 12, 65, 0, 0, 'comment', $sampleassessment_grade->comment, $this->course->id);
         echo html_writer::empty_tag('br');
         echo $OUTPUT->box_end();
      } else {
         echo $OUTPUT->box_start('generalbox', 'div_view_comment');
         echo (trim($sampleassessment_grade->comment) == '' ? 'N/A': format_text($sampleassessment_grade->comment, FORMAT_HTML));
         echo html_writer::empty_tag('br');
         echo $OUTPUT->box_end();
         
         // Model assessment
         if (isset($modelassessment_grade->comment)) {
            echo $OUTPUT->box_start('generalbox div_hide', 'modelcomment');
            echo (trim($modelassessment_grade->comment) == '' ? 'N/A': format_text($modelassessment_grade->comment, FORMAT_HTML));
            echo html_writer::empty_tag('br');
            echo $OUTPUT->box_end();
         }
      }
      
      if($this->rubric->id){
         echo html_writer::end_tag('div');
         echo html_writer::end_tag('td');
         echo html_writer::end_tag('tr');
         echo html_writer::start_tag('tr');
         echo html_writer::start_tag('td');
         echo html_writer::start_tag('div');
      }
      
      ///Print Buttons in Single View
      echo html_writer::start_tag('div', array('class'=>'buttons'));
      if (($display_mode == 'edit')) {
         echo html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'savechanges', 'value'=>get_string('savechanges'), 'onclick'=>'saveOnly()'));
         echo html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'saveandclose', 'value'=>get_string('saveandclose', 'sampleassessment'), 'onclick'=>'saveClose()'));
         if ($isgraded) echo html_writer::empty_tag('input', array('type'=>'button', 'name'=>'deletegrade', 'value'=>get_string('deletegrade', 'sampleassessment'), 'onclick'=>'deleteGrade()'));
         echo html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'cancel', 'value'=>get_string('cancel')));
      } else {
         echo html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'cancel', 'value'=>get_string('close', 'sampleassessment')));
      }
      
      //if there are more to be graded.
      if ($viewer == 'teacher' && $nextid) {
         if ($display_mode == 'edit') {
            //echo '<input type="submit" name="saveandnext" value="'.get_string('saveandnext').'" onclick="saveNext()" />';
         }
         echo html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'next', 'value'=>get_string('next'), 'onclick'=>'setNext()'));
      }
      echo html_writer::start_tag('div');
      echo html_writer::start_tag('div');
      echo html_writer::end_tag('td');
      echo html_writer::end_tag('tr');
      echo html_writer::end_tag('table');
      echo html_writer::end_tag('form');
      
      echo $OUTPUT->footer();
   }
   
    function get_submission($id) {
        global $DB;
        $submission = $DB->get_record("sampleassessment_submissions", array("id"=>$id));
        return $submission;
    }
   
   function view_submission($userid) {
      global $DB, $PAGE, $OUTPUT;
      
      if (!$user = $DB->get_record('user', array('id'=>$userid))) {
         print_error('No such user!');
      }
      
      /// construct SQL, using current offset to find the data of the next student
      $sampleassessment = $this->sampleassessment;
      
      $submission = $this->get_submission($userid);
      
      print "<style type=\"text/css\">
               body {min-width: 700px;}
               #submission_table .theader {width:25%;font-weight:bold;};
             </style>";
      
      $PAGE->set_title(format_string($this->sampleassessment->name).': '.get_string('viewsubmissionof', 'assessment').fullname($user, true));
      echo $OUTPUT->header();
      echo html_writer::tag('h2', get_string('viewsubmissionof', 'assessment').fullname($user, true), array('class'=>'main'));
      echo html_writer::start_tag('div', array('style'=>'width:95%;margin-top:10px;margin-bottom:10px;'));
      $this->print_submission($submission);
      echo html_writer::end_tag('div');
      
      echo $OUTPUT->footer();
   }
   
   function print_submission($submission) {
      global $OUTPUT;
      echo "<table id=\"submission_table\" cellpadding=\"5\" border=\"0\" width=\"100%\" align=\"center\">\n";
      echo "<tr valign=\"top\"><td class=\"theader\">". get_string("title", "assessment").":</td>\n";
      echo "<td>".$submission->title."</td></tr>\n";
      echo "<tr valign=\"top\"><td class=\"theader\" colspan=\"2\">".get_string("description").":</td></tr>\n";
      
      echo "<tr><td colspan=\"2\">";
      echo $OUTPUT->box_start();
      echo $submission->description;
      echo $OUTPUT->box_end();
      echo "</td></tr>\n";
      
      if ($this->sampleassessment->numfiles) {
         echo "<tr valign=\"top\"><td class=\"theader\" colspan=\"2\">". get_string("filesuploaded", "assessment").":</td></tr>\n";
         echo "<tr><td colspan=\"2\">";
         assessment_user_submitted_files($submission, $this->sampleassessment, 0);
         echo "</td></tr>\n";
      }
      
      echo "<tr valign=\"top\"><td class=\"theader\">". get_string("submissiondate", "assessment").":</td>\n";
      echo "<td>".userdate($submission->timecreated);
      if ($submission->timecreated > $this->sampleassessment->submitend) {
         echo ' <span style="color:red">'.get_string('latesubmission', 'assessment').'</span>';
      }
      echo "</td></tr>\n";
      echo "</table>\n";
   }
   
   function prepare_new_sampleassessment_grade($marker, $submissionid, $type=0) {
      $sampleassessment_grade = new Object;
      $sampleassessment_grade->submissionid = $submissionid;
      $sampleassessment_grade->marker = $marker;
      $sampleassessment_grade->userid = 0;
      $sampleassessment_grade->grade = -1;
      $sampleassessment_grade->type = $type;
      $sampleassessment_grade->timemodified = '';
      $sampleassessment_grade->comment = '';
      return $sampleassessment_grade;
   }
   
   function delete_model_grade() {
       global $DB;
       if (!$feedback = data_submitted()) return false;
       $return = true;
       $gradeid = $DB->get_field('sampleassessment_grades', 'id', array('submissionid'=>$feedback->submissionid, 'marker'=>$feedback->marker, 'type'=>0));
       $return = $DB->delete_records('sampleassessment_grades', array('id'=>$gradeid));
       $return = $return && $DB->delete_records('sampleassessment_grade_specs', array('gradeid'=>$gradeid));
       return $return;
   }
   
   function process_feedback($type) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir.'/gradelib.php');

        if (!$feedback = data_submitted()) return false;  // No incoming data?
        if (!empty($feedback->cancel)) return false;      // User hit cancel button
        
        $sampleassessment_grade = $this->get_sampleassessment_grade($feedback->marker, $feedback->submissionid, $type, 1);  // Get or make one
        
        if (is_array($sampleassessment_grade)) {
            $sampleassessment_grade = array_shift($sampleassessment_grade);
        }
        
        if ($sampleassessment_grade) {
            $sampleassessment_grade->marker = $USER->id;
            $sampleassessment_grade->userid = 0;
            $sampleassessment_grade->grade = $feedback->grade;
            $sampleassessment_grade->type = $feedback->type;
            $sampleassessment_grade->timemodified = time();
            $sampleassessment_grade->comment = addslashes($feedback->comment);
            
            if ($this->rubric->id) {
               $this->rubric->process_assessment_grade($feedback, $sampleassessment_grade->id, 1);
            }
            
            if (! $DB->update_record('sampleassessment_grades', $sampleassessment_grade)) {
                return false;
            }
            
            $samplename = '';
            if ($submission = $this->get_submission($feedback->submissionid)) {
               $samplename = $submission->title;
            }
            
            add_to_log($this->course->id, 'sampleassessment', 'update grade',
                     'assessment_grades.php?id='.$this->sampleassessment->id.'&submissionid='.$feedback->submissionid.'&marker='.$feedback->marker.'&mode=single&offset=&type='.$type, 
                     $this->sampleassessment->name.': '.$samplename, $this->cm->id);
        }
        return $sampleassessment_grade;
   }
}

// Use add_instance() defined in the sampleassessment_base class
function sampleassessment_add_instance($sampleassessment, $mform) {
    global $CFG;
    //require_once("$CFG->dirroot/mod/sampleassessment/lib.php");
    $ass = new sampleassessment_base();
    return $ass->add_instance($sampleassessment, $mform);
}

// Use update_instance() defined in the sampleassessment_base class
function sampleassessment_update_instance($sampleassessment, $mform){
    global $CFG;
    //require_once("$CFG->dirroot/mod/sampleassessment/lib.php");
    $ass = new sampleassessment_base();
    return $ass->update_instance($sampleassessment, $mform);
}

// Use delete_instance() defined in the sampleassessment_base class
function sampleassessment_delete_instance($id){
    global $CFG, $DB;
    // Normal module deletion only required parameter $id, need to get $sampleassessment object first
    if (! $sampleassessment = $DB->get_record('sampleassessment', array('id'=>$id))) {
        return false;
    }
    //require_once($CFG->dirroot."/mod/sampleassessment/lib.php");
    $module = $DB->get_record('modules', array('name'=>'sampleassessment'));
    $cm = $DB->get_record('course_modules', array('course'=>$sampleassessment->course, 'module'=>$module->id, 'instance'=>$id));
    $ass = new sampleassessment_base($cm->id);
    return $ass->delete_instance($ass->sampleassessment);
}

function sampleassessment_grade_item_delete($sampleassessment) {
   global $CFG;
   
   // delete grades pushed to gradebook
   require_once($CFG->libdir.'/gradelib.php');
   return grade_update('mod/sampleassessment', $sampleassessment->course, 'mod', 'sampleassessment', $sampleassessment->id, 0, NULL, array('deleted'=>1));
}

function sampleassessment_count_graded($sampleassessment) {
/// Returns the count of all graded submissions by ENROLLED students (even empty)
    global $CFG, $DB;
    
    $cm = get_coursemodule_from_instance('sampleassessment', $sampleassessment->id);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    // this is all the users with this capability set, in this context or higher
    if ($sampleassessment->submissions) {
        foreach ($sampleassessment->submissions as $submission) {
            $array[] = $submission->id;
        }
        $submissionlists = '('.implode(',',$array).')';

        return $DB->count_records_sql("SELECT COUNT(*)
                                       FROM {sampleassessment_grades}
                                       WHERE timemodified > 0 AND submissionid IN $submissionlists");
    } else {
        return 0; // no users enroled in course
    }
}

function sampleassessment_display_sample_files($submission, $sampleassessment, $mode='html') {
    global $CFG;
    
    $cm = get_coursemodule_from_instance('sampleassessment', $sampleassessment->id);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    $countfiles = '';
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_sampleassessment', 'samplefile', $submission->id);
    
    foreach ($files as $file) {
        $url = $CFG->wwwroot."/pluginfile.php/".$file->get_contextid()."/mod_sampleassessment/samplefile";
        $filename = $file->get_filename();
        $fileurl = $url.$file->get_filepath().$file->get_itemid().'/'.$filename;
        if ($filename != ".") {
            $countfiles .= html_writer::link($fileurl, $filename, array('target'=>'_blank'));
        }
    }
    
    if (!$countfiles) $countfiles = "N/A";
    return $countfiles;
}

function sampleassessment_print_file($file, $filearea,$return=NULL) {
   global $CFG;
   require_once($CFG->libdir.'/filelib.php');
   
   $imagereturn = "";
   $output = "";

   $icon = mimeinfo("icon", $file);
   $type = mimeinfo("type", $file);
   if ($CFG->slasharguments) {
      $ffurl = "$CFG->wwwroot/file.php/$filearea/$file";
   } else {
      $ffurl = "$CFG->wwwroot/file.php?file=/$filearea/$file";
   }
   $image = "<img src=\"$CFG->pixpath/f/$icon\" class=\"icon\" alt=\"\" />";
   
   // Special case: sample is video/audio, display player
   global $COURSE;
   require_once($CFG->dirroot.'/filter/mediaplugin/filter.php');
   $filter_text = '<a href="'.$ffurl.'?d=640x480">'.$file.'</a>';  // Construct a link to be use by multimedia filter
   $filtered_text = mediaplugin_filter($COURSE->id, $filter_text);
   if (!($filter_text === $filtered_text)) {
      return $filtered_text;
   }
   
   if ($return == "html") {
      $filelinkstr = '<span id="'.$file.'_linkstr">'.$file.'</span>';
      $output .= link_to_popup_window($ffurl, $file, $image, 600, 800, $image, 'none', true, $file);
      $output .= link_to_popup_window($ffurl, $file, $filelinkstr, 600, 800, $file, 'none', true, $file);
   } else if ($return == "text") {
      $output .= "$strattachment $file:\n$ffurl\n";
   } else {
      if (in_array($type, array('image/gif', 'image/jpeg', 'image/png'))) {    // Image attachments don't get printed as links
         $imagereturn .= "<p><img src=\"$ffurl\" alt=\"\" /></p>";
      } else {
         echo "<a href=\"$ffurl\">$image</a> ";
         echo filter_text("<a href=\"$ffurl\">$file</a><br />");
      }
   }

   if ($return) {
      return $output;
   }

   return $imagereturn;
}

/**
 * Return a small object with summary information about what a 
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 **/
function sampleassessment_user_outline($course, $user, $mod, $sampleassessment) {
    return $return;
}

/**
 * Print a detailed representation of what a user has done with 
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function sampleassessment_user_complete($course, $user, $mod, $sampleassessment) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity 
 * that has occurred in assessment activities and print it out. 
 * Return true if there was output, or false is there was none. 
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function sampleassessment_print_recent_activity($course, $isteacher, $timestart) {
    global $CFG;

    return false;  //  True if anything was printed, otherwise false 
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such 
 * as sending out mail, toggling flags etc ... 
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function sampleassessment_cron () {
    global $CFG;

    return true;
}

/**
 * Must return an array of grades for a given instance of this module, 
 * indexed by user.  It also returns a maximum allowed grade.
 * 
 * Example:
 *    $return->grades = array of grades;
 *    $return->maxgrade = maximum allowed grade;
 *
 *    return $return;
 *
 * @param int $sampleassessmentid ID of an instance of this module
 * @return mixed Null or object with an array of grades and with the maximum grade
 **/
function sampleassessment_grades($sampleassessmentid) {
   return NULL;
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of assessment. Must include every user involved
 * in the instance, independent of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $sampleassessmentid ID of an instance of this module
 * @return mixed boolean/array of students
 **/
function sampleassessment_get_participants($sampleassessmentid) {
    return false;
}

/**
 * This function returns if a scale is being used by one assessment
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $sampleassessmentid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 **/
function sampleassessment_scale_used ($sampleassessmentid,$scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of assessment.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any assessment
 */
function sampleassessment_scale_used_anywhere($scaleid) {
    global $DB;
    if ($scaleid and $DB->record_exists('sampleassessment', array('grade'=>-$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function sampleassessment_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_ADVANCED_GRADING:        return false;

        default: return null;
    }
}

//////////////////////////////////////////////////////////////////////////////////////
/// Any other assessment functions go here.  Each of them must have a name that 
/// starts with assessment_
/// Remember (see note in first lines) that, if this section grows, it's HIGHLY
/// recommended to move all funcions below to a new "localib.php" file.
function sampleassessment_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea === 'samplefile') {
        if (!has_any_capability(array('mod/sampleassessment:view'), $context)) {
            send_file_not_found();
        }

        $submissionid = array_shift($args); // we do not use itemids here
        
        if (!$submission = $DB->get_record('sampleassessment_submissions', array('id'=>$submissionid))) {
            return false;
        }

        if (!$sampleassessment = $DB->get_record('sampleassessment', array('id'=>$cm->instance))) {
            return false;
        }
        
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_sampleassessment/$filearea/$submissionid/$relativepath"; // beware, slashes are not used here!

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            send_file_not_found();
        }

        $lifetime = isset($CFG->filelifetime) ? $CFG->filelifetime : 86400;

        // finally send the file
        send_stored_file($file, $lifetime, 0);
    }

    return false;
}

?>