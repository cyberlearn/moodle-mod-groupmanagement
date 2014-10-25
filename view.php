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
 * Version information
 *
 * @package    mod
 * @subpackage groupmanagement
 * @copyright  2013 Université de Lausanne
 * @author     Nicolas Dunand <Nicolas.Dunand@unil.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->dirroot.'/group/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id              = required_param('id', PARAM_INT);                 // Course Module ID
$action          = optional_param('action', '', PARAM_ALPHA);
$userids         = optional_param_array('userid', array(), PARAM_INT); // array of attempt ids for delete action
$error           = optional_param('error', '', PARAM_INT);

$url = new moodle_url('/mod/groupmanagement/view.php', array('id'=>$id));
if ($action !== '') {
    $url->param('action', $action);
}

$PAGE->set_url($url);
$PAGE->requires->jquery();

if (! $cm = get_coursemodule_from_id('groupmanagement', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

require_login($course, false, $cm);

if (!$groupmanagement = groupmanagement_get_groupmanagement($cm->instance)) {
    print_error('invalidcoursemodule');
}

$groupmanagement_groups = groupmanagement_get_groups($groupmanagement);
$groupmanagement_users = array();

$strgroupmanagement = get_string('modulename', 'groupmanagement');
$strgroupmanagements = get_string('modulenameplural', 'groupmanagement');

if (!$context = context_module::instance($cm->id)) {
    print_error('badcontext');
}

$eventparams = array(
    'context' => $context,
    'objectid' => $groupmanagement->id
);

$current = groupmanagement_get_user_answer($groupmanagement, $USER);
if ($action == 'delgroupmanagement' and confirm_sesskey() and is_enrolled($context, NULL, 'mod/groupmanagement:choose') and $groupmanagement->allowupdate) {
    // user wants to delete his own choice:
    if ($current !== false) {
        if (groups_is_member($current->id, $USER->id)) {
            $currentgroup = $DB->get_record('groups', array('id' => $current->id), 'id,name', MUST_EXIST);
            groups_remove_member($current->id, $USER->id);
            $event = \mod_groupmanagement\event\choice_removed::create($eventparams);
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('course', $course);
            $event->add_record_snapshot('groupmanagement', $groupmanagement);
            $event->trigger();
        }
        $current = groupmanagement_get_user_answer($groupmanagement, $USER, FALSE, TRUE);
        // Update completion state
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) && $groupmanagement->completionsubmit) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE);
        }
    }
}

$PAGE->set_title(format_string($groupmanagement->name));
$PAGE->set_heading($course->fullname);

/// Mark as viewed
$completion=new completion_info($course);
$completion->set_module_viewed($cm);

/// Submit any new data if there is any
if (data_submitted() && is_enrolled($context, NULL, 'mod/groupmanagement:choose') && confirm_sesskey()) {

    if ($groupmanagement->multipleenrollmentspossible == 1) {
        $number_of_groups = optional_param('number_of_groups', '', PARAM_INT);

        $incorrectEnrollementKey = false;

        for ($i = 0; $i < $number_of_groups; $i++) {
            $answer_value = optional_param('answer_' . $i, '', PARAM_INT);
            if ($answer_value != '') {
                $selected_option = $DB->get_record('groupmanagement_options', array('id' =>$answer_value));
                if (!groups_is_member($selected_option->groupid, $USER->id)) {
                    $enrollementkey = optional_param('enrollementKeyKey'.$answer_value, '', PARAM_TEXT);
                    
                    if ($groupmanagement->privategroupspossible == 1) {
                        if (!empty($selected_option->enrollementkey)) {
                            if ($enrollementkey != $selected_option->enrollementkey) {
                                $incorrectEnrollementKey = true;
                                continue;
                            }
                        }
                    }
                }

                groupmanagement_user_submit_response($answer_value, $groupmanagement, $USER->id, $course, $cm);
            } else {
                $answer_value_group_id = optional_param('answer_'.$i.'_groupid', '', PARAM_INT);
                if (groups_is_member($answer_value_group_id, $USER->id)) {
                    $answer_value_group = $DB->get_record('groups', array('id' => $answer_value_group_id), 'id,name', MUST_EXIST);
                    groups_remove_member($answer_value_group_id, $USER->id);
                    $event = \mod_groupmanagement\event\choice_removed::create($eventparams);
                    $event->add_record_snapshot('course_modules', $cm);
                    $event->add_record_snapshot('course', $course);
                    $event->add_record_snapshot('groupmanagement', $groupmanagement);
                    $event->trigger();
                }
            }
        }

        if($incorrectEnrollementKey) {
            redirect("view.php?id=$cm->id&error=1");
        }

    } else { // multipleenrollmentspossible != 1

        $timenow = time();
        if (has_capability('mod/groupmanagement:deleteresponses', $context)) {
            if ($action == 'delete') { //some responses need to be deleted
                groupmanagement_delete_responses($userids, $groupmanagement, $cm, $course); //delete responses.
                redirect("view.php?id=$cm->id");
            }
        }

        $answer = optional_param('answer', '', PARAM_INT);

        if (empty($answer)) {
            redirect("view.php?id=$cm->id", get_string('mustchooseone', 'groupmanagement'));
        } else {
            $enrollementkey = optional_param('enrollementKeyKey'.$answer, '', PARAM_TEXT);
            $selected_option = $DB->get_record('groupmanagement_options', array('id' => $answer));

            if ($groupmanagement->privategroupspossible == 1) {
                if (!empty($selected_option->enrollementkey)) {
                    if ($enrollementkey != $selected_option->enrollementkey) {
                        redirect("view.php?id=$cm->id&error=1");
                    }
                }
            }

            groupmanagement_user_submit_response($answer, $groupmanagement, $USER->id, $course, $cm);
        }
    }

    redirect("view.php?id=$cm->id", get_string('groupmanagementsaved', 'groupmanagement'));
} else {
    echo $OUTPUT->header();
}


/// Display the groupmanagement and possibly results


$event = \mod_groupmanagement\event\course_module_viewed::create($eventparams);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('groupmanagement', $groupmanagement);
$event->trigger();


/// Check to see if groups are being used in this groupmanagement
$groupmode = groups_get_activity_groupmode($cm);

if ($groupmode) {
    groups_get_activity_group($cm, true);
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/groupmanagement/view.php?id='.$id);
}

$allresponses = groupmanagement_get_response_data($groupmanagement, $cm);   // Big function, approx 6 SQL calls per user


if (has_capability('mod/groupmanagement:readresponses', $context)) {
    groupmanagement_show_reportlink($groupmanagement, $allresponses, $cm);
}

echo '<div class="clearer"></div>';

if ($groupmanagement->intro) {
    echo $OUTPUT->box(format_module_intro('groupmanagement', $groupmanagement, $cm->id), 'generalbox', 'intro');
}

//if user has already made a selection, and they are not allowed to update it, show their selected answer.
if (isloggedin() && ($current !== false) ) {
    if ($groupmanagement->multipleenrollmentspossible == 1) {
        $currents = groupmanagement_get_user_answer($groupmanagement, $USER, TRUE);

        $names = array();
        foreach ($currents as $current) {
            $names[] = format_string($current->name);
        }
        $formatted_names = join(' '.get_string("and", "groupmanagement").' ', array_filter(array_merge(array(join(', ', array_slice($names, 0, -1))), array_slice($names, -1))));
        echo $OUTPUT->box(get_string("yourselection", "groupmanagement", userdate($groupmanagement->timeopen)).": ".$formatted_names, 'generalbox', 'yourselection');

    } else {
        echo $OUTPUT->box(get_string("yourselection", "groupmanagement", userdate($groupmanagement->timeopen)).": ".format_string($current->name), 'generalbox', 'yourselection');
    }
}

if(isset($error) && $error == 1) {
    echo $OUTPUT->box(get_string('incorrectEnrollementKey', 'groupmanagement'), 'generalbox enrollementKey enrollementKeyError');
}

if ($groupmanagement->freezegroups == 1 || (!empty($groupmanagement->freezegroupsaftertime) && time() >= $groupmanagement->freezegroupsaftertime)) {
    if (!empty($groupmanagement->freezegroupsaftertime) && time() >= $groupmanagement->freezegroupsaftertime) {
        $freezeTime = gmdate("Y-m-d H:i:s", $groupmanagement->freezegroupsaftertime);
        echo $OUTPUT->box(get_string("groupsfrozenaftertime", "groupmanagement").' '.$freezeTime, 'generalbox', 'frozenlabel');
    } else {
        echo $OUTPUT->box(get_string("groupsfrozen", "groupmanagement"), 'generalbox', 'frozenlabel');
    }
}

/// Print the form
$groupmanagementopen = true;
$timenow = time();
if ($groupmanagement->timeclose !=0) {
    if ($groupmanagement->timeopen > $timenow ) {
        echo $OUTPUT->box(get_string("notopenyet", "groupmanagement", userdate($groupmanagement->timeopen)), "generalbox notopenyet");
        echo $OUTPUT->footer();
        exit;
    } else if ($timenow > $groupmanagement->timeclose) {
        echo $OUTPUT->box(get_string("expired", "groupmanagement", userdate($groupmanagement->timeclose)), "generalbox expired");
        $groupmanagementopen = false;
    }
}

$options = groupmanagement_prepare_options($groupmanagement, $USER, $cm, $allresponses);
$renderer = $PAGE->get_renderer('mod_groupmanagement');
if ( (!$current or $groupmanagement->allowupdate) and $groupmanagementopen and is_enrolled($context, NULL, 'mod/groupmanagement:choose')) {
// They haven't made their groupmanagement yet or updates allowed and groupmanagement is open

    echo $renderer->display_options($options, $cm->id, $groupmanagement->display, $groupmanagement->publish, $groupmanagement->limitmaxusersingroups, $groupmanagement->showresults, $current, $groupmanagementopen, false, $groupmanagement->multipleenrollmentspossible);
} else {
    // form can not be updated
    echo $renderer->display_options($options, $cm->id, $groupmanagement->display, $groupmanagement->publish, $groupmanagement->limitmaxusersingroups, $groupmanagement->showresults, $current, $groupmanagementopen, true, $groupmanagement->multipleenrollmentspossible);
}
$groupmanagementformshown = true;

$sitecontext = context_system::instance();

if (isguestuser()) {
    // Guest account
    echo $OUTPUT->confirm(get_string('noguestchoose', 'groupmanagement').'<br /><br />'.get_string('liketologin'),
                    get_login_url(), new moodle_url('/course/view.php', array('id'=>$course->id)));
} else if (!is_enrolled($context)) {
    // Only people enrolled can make a groupmanagement
    $SESSION->wantsurl = $FULLME;
    $SESSION->enrolcancel = (!empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '';

    $coursecontext = context_course::instance($course->id);
    $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));

    echo $OUTPUT->box_start('generalbox', 'notice');
    echo '<p align="center">'. get_string('notenrolledchoose', 'groupmanagement') .'</p>';
    echo $OUTPUT->container_start('continuebutton');
    echo $OUTPUT->single_button(new moodle_url('/enrol/index.php?', array('id'=>$course->id)), get_string('enrolme', 'core_enrol', $courseshortname));
    echo $OUTPUT->container_end();
    echo $OUTPUT->box_end();

}

// print the results at the bottom of the screen
if ( $groupmanagement->showresults == GROUPMANAGEMENT_SHOWRESULTS_ALWAYS or
    ($groupmanagement->showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_ANSWER and $current) or
    ($groupmanagement->showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_CLOSE and !$groupmanagementopen)) {
}
else if ($groupmanagement->showresults == GROUPMANAGEMENT_SHOWRESULTS_NOT) {
    echo $OUTPUT->box(get_string('neverresultsviewable', 'groupmanagement'));
}
else if ($groupmanagement->showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_ANSWER && !$current) {
    echo $OUTPUT->box(get_string('afterresultsviewable', 'groupmanagement'));
}
else if ($groupmanagement->showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_CLOSE and $groupmanagementopen) {
    echo $OUTPUT->box(get_string('notyetresultsviewable', 'groupmanagement'));
}
else if (!$groupmanagementformshown) {
    echo $OUTPUT->box(get_string('noresultsviewable', 'groupmanagement'));
}

echo $OUTPUT->footer();

