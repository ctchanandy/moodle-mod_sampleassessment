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
 * Edit grade and view grade page
 *
 * @package     mod
 * @subpackage  sampleassessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$a    = optional_param('a', 0, PARAM_INT);           // Assessment ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$type = optional_param('type', 0, PARAM_INT);

$url = new moodle_url('/mod/sampleassessment/assessment_grades.php');

$marker  = required_param('marker', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);

if ($id) {
    if (! $cm = get_coursemodule_from_id('sampleassessment', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $sampleassessment = $DB->get_record("sampleassessment", array("id"=>$cm->instance))) {
        print_error('invalidid', 'sampleassessment');
    }
    if (! $course = $DB->get_record("course", array("id"=>$sampleassessment->course))) {
        print_error('coursemisconf', 'sampleassessment');
    }
    $url->param('id', $id);
} else {
    if (!$sampleassessment = $DB->get_record("sampleassessment",  array("id"=>$a))) {
        print_error('invalidid', 'sampleassessment');
    }
    if (! $course = $DB->get_record("course",  array("id"=>$sampleassessment->course))) {
        print_error('coursemisconf', 'sampleassessment');
    }
    if (! $cm = get_coursemodule_from_instance("sampleassessment", $sampleassessment->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

require_login($course->id, false, $cm);
require_capability('mod/sampleassessment:grade', context_module::instance($cm->id));

$url->param('mode', $mode);
$url->param('type', $type);
$url->param('marker', $marker);
$url->param('submissionid', $submissionid);

$PAGE->set_pagelayout('popup');
$PAGE->set_url($url);

/// Load up the required sampleassessment code
$assessmentinstance = new sampleassessment_base($cm->id, $sampleassessment, $cm, $course);
$assessmentinstance->process_sampleassessment_grades($mode, $type, $marker);   // Display or process the submissions
?>