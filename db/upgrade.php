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
 * sampleassessment module upgrade
 *
 * @package     mod
 * @subpackage  sampleassessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_sampleassessment_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;
    
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2013061300) {

        // Define field samplelabel to be added to sampleassessment
        $table = new xmldb_table('sampleassessment');
        $field = new xmldb_field('samplelabel', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'numsubmission');

        // Conditionally launch add field samplelabel
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // sampleassessment savepoint reached
        upgrade_mod_savepoint(true, 2013061300, 'sampleassessment');
    }
    
    if ($oldversion < 2013061301) {

        $DB->set_field_select('sampleassessment', 'samplelabel', get_string('sample', 'sampleassessment'), 'samplelabel IS NULL OR samplelabel = ""');

        // sampleassessment savepoint reached
        upgrade_mod_savepoint(true, 2013061301, 'sampleassessment');
    }
    
    return true;
}
