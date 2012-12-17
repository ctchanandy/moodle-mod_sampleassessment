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
 * This page lists all the instances of sampleassessment in a particular course
 *
 * @package     mod
 * @subpackage  sampleassessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id', PARAM_INT);   // course

    if (! $course = $DB->get_record("course", array("id"=>$id))) {
        print_error('coursemisconf', 'sampleassessment');
    }

    require_course_login($course);
    $PAGE->set_pagelayout('incourse');
    
    add_to_log($course->id, "sampleassessment", "view all", "index.php?id=".$course->id, "");
    
    $url = new moodle_url('/mod/sampleassessment/index.php', array('id'=>$id));
    
/// Get all required stringsassessment

    $strassessments = get_string("modulenameplural", "sampleassessment");
    $strassessment  = get_string("modulename", "sampleassessment");


/// Print the header
    $PAGE->set_url($url);
    $PAGE->navbar->add($strassessments);
    $PAGE->set_title($strassessments);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

/// Get all the appropriate data

    if (! $sampleassessment = get_all_instances_in_course("sampleassessment", $course)) {
        notice("There are no sampleassessment", "../../course/view.php?id=$course->id");
        die;
    }

/// Print the list of instances (your module will probably extend this)

    $timenow = time();
    $strname = get_string("name");
    $strweek = get_string("week");
    $strtopic = get_string("topic");
    
    $table = new html_table();
    
    if ($course->format == "weeks") {
        $table->head = array ($strweek, $strname);
        $table->align = array ("center", "left");
    } else if ($course->format == "topics") {
        $table->head = array ($strtopic, $strname);
        $table->align = array ("center", "left", "left", "left");
    } else {
        $table->head = array ($strname);
        $table->align = array ("left", "left", "left");
    }

    foreach ($sampleassessment as $sampleassessment) {
        if (!$sampleassessment->visible) {
            //Show dimmed if the mod is hidden
            $link = "<a class=\"dimmed\" href=\"view.php?id=$sampleassessment->coursemodule\">$sampleassessment->name</a>";
        } else {
            //Show normal if the mod is visible
            $link = "<a href=\"view.php?id=$sampleassessment->coursemodule\">$sampleassessment->name</a>";
        }

        if ($course->format == "weeks" or $course->format == "topics") {
            $table->data[] = array($sampleassessment->section, $link);
        } else {
            $table->data[] = array($link);
        }
    }

    echo "<br />";

    echo html_writer::table($table);

/// Finish the page

    echo $OUTPUT->footer();
?>