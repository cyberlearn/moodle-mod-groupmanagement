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
 * Delete group
 *
 * @package   core_group
 * @copyright 2008 The Open University, s.marshall AT open.ac.uk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../lib.php');

// Get and check parameters
$courseid = required_param('courseid', PARAM_INT);
$groupids = required_param('groups', PARAM_SEQUENCE);
$cmid     = required_param('cmid', PARAM_INT);
$confirm  = optional_param('confirm', 0, PARAM_BOOL);

$PAGE->set_url('/mod/groupmanagement/group/delete.php', array('courseid'=>$courseid,'groups'=>$groupids, 'cmid'=>$cmid));
$PAGE->set_pagelayout('standard');

// Make sure course is OK and user has access to manage groups
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}
require_login($course);
$context = context_course::instance($course->id);

// require_capability('moodle/course:managegroups', $context);
$changeidnumber = has_capability('moodle/course:changeidnumber', $context);
$hasManageGroupsCapability = has_capability('mod/groupmanagement:managegroups', $context);

// Make sure all groups are OK and belong to course
$groupidarray = explode(',',$groupids);
$groupnames = array();
foreach($groupidarray as $groupid) {
    $groupmanagement_options = $DB->get_record("groupmanagement_options", array("groupid"=>$groupid));
    $groupmanagement = $DB->get_record("groupmanagement", array("id"=>$groupmanagement_options->groupmanagementid));
    if (!$group = $DB->get_record('groups', array('id' => $groupid))) {
        print_error('invalidgroupid');
    }
    if (!empty($group->idnumber) && !$changeidnumber) {
        print_error('grouphasidnumber', '', '', $group->name);
    }
    if ($courseid != $group->courseid) {
        print_error('groupunknown', '', '', $group->courseid);
    }
    // If the current user has no right on the group
    if (!$hasManageGroupsCapability && $groupmanagement_options->creatorid != $USER->id) { 
        print_error('userHasNoRightToManageGroups', 'groupmanagement');
    }
    // If the group management activity is frozen
    if ($groupmanagement->freezegroups == 1 || (!empty($groupmanagement->freezegroupsaftertime) && time() >= $groupmanagement->freezegroupsaftertime)) {
        print_error('courseIsFrozen', 'groupmanagement');
    }
    
    $nbOptions = $DB->count_records("groupmanagement_options", array("groupmanagementid"=>$groupmanagement->id)); 
    // If the group has not more than 2 options
    if (($nbOptions - count($groupidarray)) < 2) {
        print_error('atLeastTwoGroupsRequired', 'groupmanagement');
    }

    $groupnames[] = format_string($group->name);
}

$returnurl = $CFG->wwwroot.'/mod/groupmanagement/view.php?id='.$cmid;

if(count($groupidarray)==0) {
    print_error('errorselectsome','group',$returnurl);
}

if ($confirm && data_submitted()) {
    if (!confirm_sesskey() ) {
        print_error('confirmsesskeybad','error',$returnurl);
    }

    foreach($groupidarray as $groupid) {
        $DB->delete_records("groupmanagement_options", array("groupid"=>$groupid));
        groups_delete_group($groupid);
    }

    redirect($returnurl);
} else {
    $PAGE->set_title(get_string('deleteselectedgroup', 'group'));
    $PAGE->set_heading($course->fullname . ': '. get_string('deleteselectedgroup', 'group'));
    echo $OUTPUT->header();
    $optionsyes = array('courseid'=>$courseid, 'groups'=>$groupids, 'sesskey'=>sesskey(), 'confirm'=>1, 'cmid'=>$cmid);
    $optionsno = array('id'=>$courseid, 'id'=>$cmid);
    if(count($groupnames)==1) {
        $message=get_string('deletegroupconfirm', 'group', $groupnames[0]);
    } else {
        $message=get_string('deletegroupsconfirm', 'group').'<ul>';
        foreach($groupnames as $groupname) {
            $message.='<li>'.$groupname.'</li>';
        }
        $message.='</ul>';
    }
    $formcontinue = new single_button(new moodle_url('delete.php', $optionsyes), get_string('yes'), 'post');
    $formcancel = new single_button(new moodle_url('../view.php', $optionsno), get_string('no'), 'get');
    echo $OUTPUT->confirm($message, $formcontinue, $formcancel);
    echo $OUTPUT->footer();
}
