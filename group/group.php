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
 * Create group OR edit group settings.
 *
 * @copyright 2006 The Open University, N.D.Freear AT open.ac.uk, J.White AT open.ac.uk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   core_group
 */

require_once('../../../config.php');
require_once('../lib.php');
require_once('./group_form.php');

/// get url variables
$cgid     = required_param('cgid', PARAM_INT);
$cmid     = required_param('cmid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$id       = optional_param('id', 0, PARAM_INT);
$delete   = optional_param('delete', 0, PARAM_BOOL);
$confirm  = optional_param('confirm', 0, PARAM_BOOL);

// This script used to support group delete, but that has been moved. In case
// anyone still links to it, let's redirect to the new script.
if ($delete) {
    debugging('Deleting a group through group/group.php is deprecated and will be removed soon. Please use group/require_capability instead');
    redirect(new moodle_url('delete.php', array('courseid' => $courseid, 'groups' => $id)));
}

if ($id) {
    if (!$group = $DB->get_record('groups', array('id'=>$id))) {
        print_error('invalidgroupid');
    }
    if (empty($courseid)) {
        $courseid = $group->courseid;

    } else if ($courseid != $group->courseid) {
        print_error('invalidcourseid');
    }

    if (!$course = $DB->get_record('course', array('id'=>$courseid))) {
        print_error('invalidcourseid');
    }

} else {
    if (!$course = $DB->get_record('course', array('id'=>$courseid))) {
        print_error('invalidcourseid');
    }
    $group = new stdClass();
    $group->courseid = $course->id;
}

if ($id !== 0) {
    $PAGE->set_url('/mod/groupmanagement/group/group.php', array('id'=>$id, 'cmid'=>$cmid, 'cgid'=>$cgid));
} else {
    $PAGE->set_url('/mod/groupmanagement/group/group.php', array('courseid'=>$courseid, 'cmid'=>$cmid, 'cgid'=>$cgid));
}

require_login($course);
$context = context_course::instance($course->id);
//require_capability('moodle/course:managegroups', $context);

$hasManageGroupsCapability = has_capability('mod/groupmanagement:managegroups', $context);
$groupmanagement = $DB->get_record("groupmanagement", array("id"=>$cgid));
$groupmanagement_options = $DB->get_record("groupmanagement_options", array("groupid"=>$id));
$nbOptions = $DB->count_records("groupmanagement_options", array("groupmanagementid"=>$groupmanagement->id)); 

// If the group management activity is frozen
if ($groupmanagement->freezegroups == 1 || (!empty($groupmanagement->freezegroupsaftertime) && time() >= $groupmanagement->freezegroupsaftertime)) {
    print_error('courseIsFrozen', 'groupmanagement');
}

// If the current user has no right to edit the group
if ($id && !$hasManageGroupsCapability && (!empty($groupmanagement_options) && $groupmanagement_options->creatorid != $USER->id)) { 
    print_error('userHasNoRightToManageGroups', 'groupmanagement');
}

$strgroups = get_string('groups');
$PAGE->set_title($strgroups);
$PAGE->set_heading($course->fullname . ': '.$strgroups);
$PAGE->set_pagelayout('admin');
navigation_node::override_active_url(new moodle_url('/mod/groupmanagement/group/group.php', array('id'=>$course->id, 'cmid'=>$cmid, 'cgid'=>$cgid)));

$returnurl = $CFG->wwwroot.'/mod/groupmanagement/view.php?id='.$cmid;

// Prepare the description editor: We do support files for group descriptions
$editoroptions = array('maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$course->maxbytes, 'trust'=>false, 'context'=>$context, 'noclean'=>true);
if (!empty($group->id)) {
    $editoroptions['subdirs'] = file_area_contains_subdirs($context, 'group', 'description', $group->id);
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', $group->id);
} else {
    $editoroptions['subdirs'] = false;
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', null);
}

if (isset($id) && $option = $DB->get_record("groupmanagement_options", array("groupid"=>$id))) {
    if (!empty($option->groupvideo)) {
        $group->groupvideo = 'https://www.youtube.com/watch?v='.$option->groupvideo;
    } else {
        $group->groupvideo = null;
    }
    $group->enrollementkey = $option->enrollementkey;
}

/// First create the form
$editform = new group_form(null, array('editoroptions'=>$editoroptions));
$editform->set_data($group);

if ($editform->is_cancelled()) {
    redirect($returnurl);

} elseif ($data = $editform->get_data()) {
    if (!has_capability('moodle/course:changeidnumber', $context)) {
        // Remove the idnumber if the user doesn't have permission to modify it
        unset($data->idnumber);
    }

    if ($data->id) {
        groups_update_group($data, $editform, $editoroptions);
        $option = $DB->get_record("groupmanagement_options", array("groupid" => $data->id));
        $option->timemodified = time();
        $option->groupvideo = null;
        if (isset($data->groupvideo) && !empty($data->groupvideo)) {
            $url = $data->groupvideo;
            parse_str(parse_url($url, PHP_URL_QUERY), $params);

            if (isset($params['v']) && !empty($params['v'])) {
                $option->groupvideo = $params['v'];
            }
        }

        $option->enrollementkey = null;
        if (isset($data->enrollementkey) && !empty($data->enrollementkey)) {
            $option->enrollementkey = $data->enrollementkey;
        }

        $DB->update_record("groupmanagement_options", $option);
    } else {
        // If the current user can not create new groups
        if (!$hasManageGroupsCapability && $groupmanagement->groupcreationpossible == 0) { 
            print_error('userHasNoRightToManageGroups', 'groupmanagement');
        }

        // If the number of groups has reached the limit
        if ($groupmanagement->limitmaxgroups == 1 && $nbOptions >= $groupmanagement->maxgroups) { 
            print_error('maxNumberOfGroupsReached', 'groupmanagement');
        }

        $id = groups_create_group($data, $editform, $editoroptions);

        // Update the Group Choice database
        $option = new stdClass();
        $option->groupmanagementid = $cgid;
        $option->groupid = $id;
        $option->timemodified = time();
        $option->creatorid = $USER->id;

        if ($groupmanagement = $DB->get_record("groupmanagement", array("id" => $cgid))) {
            if (isset($groupmanagement->maxusersingroups)) {
                $option->maxusersingroups = $groupmanagement->maxusersingroups;
            }
        }

        if (isset($data->groupvideo) && !empty($data->groupvideo)) {
            $url = $data->groupvideo;
            parse_str(parse_url($url, PHP_URL_QUERY), $params);

            if (isset($params['v']) && !empty($params['v'])) {
                $option->groupvideo = $params['v'];
            }
        }

        if (isset($data->enrollementkey) && !empty($data->enrollementkey)) {
            $option->enrollementkey = $data->enrollementkey;
        }

        $DB->insert_record("groupmanagement_options", $option);
    }

    redirect($returnurl);
}

$strgroups = get_string('groups');
$strparticipants = get_string('participants');

if ($id) {
    $strheading = get_string('editgroupsettings', 'group');
} else {
    $strheading = get_string('creategroup', 'group');
}

$PAGE->navbar->add($strparticipants, new moodle_url('/user/index.php', array('id'=>$courseid)));
$PAGE->navbar->add($strgroups, new moodle_url('/group/index.php', array('id'=>$courseid)));
$PAGE->navbar->add($strheading);

/// Print header
echo $OUTPUT->header();
echo '<div id="grouppicture">';
if ($id) {
    print_group_picture($group, $course->id);
}
echo '</div>';
$editform->display();
echo $OUTPUT->footer();
