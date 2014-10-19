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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_groupmanagement_activity_task
 */

/**
 * Define the complete groupmanagement structure for backup, with file and id annotations
 */
class backup_groupmanagement_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // Define each element separated
        $groupmanagement = new backup_nested_element('groupmanagement', array('id'), array(
            'name', 'intro', 'introformat', 'publish',
            'showresults', 'display', 'allowupdate', 'allowunanswered',
            'limitmaxusersingroups', 'timeopen', 'timeclose', 'timemodified',
            'completionsubmit'));

        $options = new backup_nested_element('options');

        $option = new backup_nested_element('option', array('id'), array(
            'groupid', 'maxanswers', 'timemodified'));

        // Build the tree
        $groupmanagement->add_child($options);
        $options->add_child($option);

        // Define sources
        $groupmanagement->set_source_table('groupmanagement', array('id' => backup::VAR_ACTIVITYID));

        $option->set_source_sql('
            SELECT *
              FROM {groupmanagement_options}
             WHERE groupmanagementid = ?',
            array(backup::VAR_PARENTID));

        // Define file annotations
        $groupmanagement->annotate_files('mod_groupmanagement', 'intro', null); // This file area hasn't itemid

        // Return the root element (groupmanagement), wrapped into standard activity structure
        return $this->prepare_activity_structure($groupmanagement);
    }
}
