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
 * A form for the creation and editing of groups.
 *
 * @copyright 2006 The Open University, N.D.Freear AT open.ac.uk, J.White AT open.ac.uk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   core_group
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Group form class
 *
 * @copyright 2006 The Open University, N.D.Freear AT open.ac.uk, J.White AT open.ac.uk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   core_group
 */
class group_form extends moodleform {

    /**
     * Definition of the form
     */
    function definition () {
        global $USER, $CFG, $DB, $COURSE, $cgid, $cmid;
        $coursecontext = context_course::instance($COURSE->id);

        $groupmanagement = $DB->get_record("groupmanagement", array("id"=>$cgid));

        $mform =& $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text','name', get_string('groupname', 'group'),'maxlength="254" size="50"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('editor', 'description_editor', get_string('groupdescription', 'group'), null, $editoroptions);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('filepicker', 'imagefile', get_string('newpicture', 'group'));
        $mform->addHelpButton('imagefile', 'newpicture', 'group');

        $groupVideoType = 'hidden';
        if (!empty($groupmanagement) && $groupmanagement->displaygroupvideo == 1) {
            $groupVideoType = 'text';
        }

        $attributes = array('placeholder' => 'http://', 'maxlength' => '254', 'size' => '100');
        $mform->addElement($groupVideoType, 'groupvideo', get_string('groupvideo', 'groupmanagement'), $attributes, get_string('groupvideo', 'groupmanagement'));
        $mform->setType('groupvideo', PARAM_URL);
    
        $groupEnrollementKeyType = 'hidden';
        if (!empty($groupmanagement) && $groupmanagement->privategroupspossible == 1) {
            $groupEnrollementKeyType = 'passwordunmask';
        }

        $mform->addElement($groupEnrollementKeyType, 'enrollementkey', get_string('groupenrollementkey', 'groupmanagement'), 'maxlength="254" size="24"', get_string('groupenrollementkey', 'groupmanagement'));
        $mform->setType('enrollementkey', PARAM_RAW);

        $mform->addElement('hidden','cgid', $cgid);
        $mform->setType('cgid', PARAM_INT);

        $mform->addElement('hidden','cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden','id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden','courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array $errors An array of errors
     */
    function validation($data, $files) {
        global $COURSE, $DB, $CFG;

        $errors = parent::validation($data, $files);

        $name = trim($data['name']);

        if ($data['id'] and $group = $DB->get_record('groups', array('id'=>$data['id']))) {
            if (core_text::strtolower($group->name) != core_text::strtolower($name)) {
                if (groups_get_group_by_name($COURSE->id,  $name)) {
                    $errors['name'] = get_string('groupnameexists', 'group', $name);
                }
            }
        } else if (groups_get_group_by_name($COURSE->id, $name)) {
            $errors['name'] = get_string('groupnameexists', 'group', $name);
        } 

        return $errors;
    }

    /**
     * Get editor options for this form
     *
     * @return array An array of options
     */
    function get_editor_options() {
        return $this->_customdata['editoroptions'];
    }
}
