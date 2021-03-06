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

defined('MOODLE_INTERNAL') || die();

/** @global int $GROUPMANAGEMENT_COLUMN_HEIGHT */
global $GROUPMANAGEMENT_COLUMN_HEIGHT;
$GROUPMANAGEMENT_COLUMN_HEIGHT = 300;

/** @global int $GROUPMANAGEMENT_COLUMN_WIDTH */
global $GROUPMANAGEMENT_COLUMN_WIDTH;
$GROUPMANAGEMENT_COLUMN_WIDTH = 300;

define('GROUPMANAGEMENT_PUBLISH_ANONYMOUS', '0');
define('GROUPMANAGEMENT_PUBLISH_NAMES',     '1');
define('GROUPMANAGEMENT_PUBLISH_DEFAULT',   '1');

define('GROUPMANAGEMENT_SHOWRESULTS_NOT',          '0');
define('GROUPMANAGEMENT_SHOWRESULTS_AFTER_ANSWER', '1');
define('GROUPMANAGEMENT_SHOWRESULTS_AFTER_CLOSE',  '2');
define('GROUPMANAGEMENT_SHOWRESULTS_ALWAYS',       '3');
define('GROUPMANAGEMENT_SHOWRESULTS_DEFAULT',      '3');

define('GROUPMANAGEMENT_DISPLAY_HORIZONTAL',  '0');
define('GROUPMANAGEMENT_DISPLAY_VERTICAL',    '1');

/** @global array $GROUPMANAGEMENT_PUBLISH */
global $GROUPMANAGEMENT_PUBLISH;
$GROUPMANAGEMENT_PUBLISH = array (GROUPMANAGEMENT_PUBLISH_ANONYMOUS  => get_string('publishanonymous', 'groupmanagement'),
                         GROUPMANAGEMENT_PUBLISH_NAMES      => get_string('publishnames', 'groupmanagement'));

/** @global array $GROUPMANAGEMENT_SHOWRESULTS */
global $GROUPMANAGEMENT_SHOWRESULTS;
$GROUPMANAGEMENT_SHOWRESULTS = array (GROUPMANAGEMENT_SHOWRESULTS_NOT          => get_string('publishnot', 'groupmanagement'),
                         GROUPMANAGEMENT_SHOWRESULTS_AFTER_ANSWER => get_string('publishafteranswer', 'groupmanagement'),
                         GROUPMANAGEMENT_SHOWRESULTS_AFTER_CLOSE  => get_string('publishafterclose', 'groupmanagement'),
                         GROUPMANAGEMENT_SHOWRESULTS_ALWAYS       => get_string('publishalways', 'groupmanagement'));

/** @global array $GROUPMANAGEMENT_DISPLAY */
global $GROUPMANAGEMENT_DISPLAY;
$GROUPMANAGEMENT_DISPLAY = array (GROUPMANAGEMENT_DISPLAY_HORIZONTAL   => get_string('displayhorizontal', 'groupmanagement'),
                         GROUPMANAGEMENT_DISPLAY_VERTICAL     => get_string('displayvertical','groupmanagement'));

require_once($CFG->dirroot.'/group/lib.php');

/// Standard functions /////////////////////////////////////////////////////////

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $groupmanagement
 * @return object|null
 */
function groupmanagement_user_outline($course, $user, $mod, $groupmanagement) {
    if ($groupmembership = groupmanagement_get_user_answer($groupmanagement, $user)) { // if user has answered
        $result = new stdClass();
        $result->info = "'".format_string($groupmembership->name)."'";
        $result->time = $groupmembership->timeuseradded;
        return $result;
    }
    return NULL;
}

/**
 *
 */
function groupmanagement_get_user_answer($groupmanagement, $user, $returnArray = FALSE, $refresh = FALSE) {
    global $DB, $groupmanagement_groups;

    static $user_answers = array();

    if (is_numeric($user)) {
        $userid = $user;
    }
    else {
        $userid = $user->id;
    }

    if (!$refresh and isset($user_answers[$userid])) {
        if ($returnArray === TRUE) {
            return $user_answers[$userid];
        } else {
            return $user_answers[$userid][0];
        }
    } else {
        $user_answers = array();
    }

    if (!count($groupmanagement_groups)) {
        $groupmanagement_groups = groupmanagement_get_groups($groupmanagement);
    }

    $groupids = array();
    foreach ($groupmanagement_groups as $group) {
        if (is_numeric($group->id)) {
            $groupids[] = $group->id;
        }
    }
    if ($groupids) {
        $params1 = array($userid);
        list($insql, $params2) = $DB->get_in_or_equal($groupids);
        $params = array_merge($params1, $params2);
        $groupmemberships = $DB->get_records_sql('SELECT * FROM {groups_members} WHERE userid = ? AND groupid '.$insql, $params);
        $groups = array();
        foreach ($groupmemberships as $groupmembership) {
            $group = $groupmanagement_groups[$groupmembership->groupid];
            $group->timeuseradded = $groupmembership->timeadded;
            $groups[] = $group;
        }
        if (count($groups) > 0) {
            $user_answers[$userid] = $groups;
            if ($returnArray === TRUE) {
                return $groups;
            } else {
                return $groups[0];
            }
        }
    }
    return false;

}

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $groupmanagement
 * @return string|void
 */
function groupmanagement_user_complete($course, $user, $mod, $groupmanagement) {
    if ($groupmembership = groupmanagement_get_user_answer($groupmanagement, $user)) { // if user has answered
        $result = new stdClass();
        $result->info = "'".format_string($groupmembership->name)."'";
        $result->time = $groupmembership->timeuseradded;
        echo get_string("answered", "groupmanagement").": $result->info. ".get_string("updated", '', userdate($result->time));
    } else {
        print_string("notanswered", "groupmanagement");
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $groupmanagement
 * @return int
 */
function groupmanagement_add_instance($groupmanagement) {
    global $DB;

    $groupmanagement->timemodified = time();

    if (empty($groupmanagement->timerestrict)) {
        $groupmanagement->timeopen = 0;
        $groupmanagement->timeclose = 0;
    }

    //insert answers
    $groupmanagement->id = $DB->insert_record("groupmanagement", $groupmanagement);
    
    // deserialize the selected groups
    
    $groupIDs = explode(';', $groupmanagement->serializedselectedgroups);
    $groupIDs = array_diff( $groupIDs, array( '' ) );
    
    foreach ($groupIDs as $groupID) {
        $groupID = trim($groupID);
        if (isset($groupID) && $groupID != '') {
            $option = new stdClass();
            $option->groupid = $groupID;
            $option->groupmanagementid = $groupmanagement->id;
            $property = 'group_' . $groupID . '_limit';
            if (isset($groupmanagement->$property)) {
            	$option->maxanswers = $groupmanagement->$property;
            }
            $option->timemodified = time();
            $DB->insert_record("groupmanagement_options", $option);
        }	
    }
    
    return $groupmanagement->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $groupmanagement
 * @return bool
 */
function groupmanagement_update_instance($groupmanagement) {
    global $DB;

    $groupmanagement->id = $groupmanagement->instance;
    $groupmanagement->timemodified = time();


    if (empty($groupmanagement->timerestrict)) {
        $groupmanagement->timeopen = 0;
        $groupmanagement->timeclose = 0;
    }

    if (empty($groupmanagement->multipleenrollmentspossible)) {
        $groupmanagement->multipleenrollmentspossible = 0;
    }

    if (empty($groupmanagement->limitmaxusersingroups)) {
        $groupmanagement->limitmaxusersingroups = 0;
    }

    if (empty($groupmanagement->maxusersingroups)) {
        $groupmanagement->maxusersingroups = 0;
    }    

    if (empty($groupmanagement->freezegroups)) {
        $groupmanagement->freezegroups = 0;
    }

    if (empty($groupmanagement->freezegroupsaftertime)) {
        $groupmanagement->freezegroupsaftertime = null;
    }

    if (empty($groupmanagement->displaygrouppicture)) {
        $groupmanagement->displaygrouppicture = 0;
    }

    if (empty($groupmanagement->displaygroupvideo)) {
        $groupmanagement->displaygroupvideo = 0;
    }

    if (empty($groupmanagement->groupcreationpossible)) {
        $groupmanagement->groupcreationpossible = 0;
    }

    if (empty($groupmanagement->privategroupspossible)) {
        $groupmanagement->privategroupspossible = 0;
    }

    if (empty($groupmanagement->limitmaxgroups)) {
        $groupmanagement->limitmaxgroups = 0;
    }

    if (empty($groupmanagement->maxgroups)) {
        $groupmanagement->maxgroups = 0;
    }

    // deserialize the selected groups
    
    $groupIDs = explode(';', $groupmanagement->serializedselectedgroups);
    $groupIDs = array_diff( $groupIDs, array( '' ) );

    // prepare pre-existing selected groups from database
    
    if (!($preExistingGroups = $DB->get_records("groupmanagement_options", array("groupmanagementid" => $groupmanagement->id), "id"))) {
    	return false;
    }

    // walk through form-selected groups
    foreach ($groupIDs as $groupID) {
    	$groupID = trim($groupID);
    	if (isset($groupID) && $groupID != '') {
    		$option = new stdClass();
    		$option->groupid = $groupID;
    		$option->groupmanagementid = $groupmanagement->id;
    		$property = 'group_' . $groupID . '_limit';
    		if (isset($groupmanagement->$property)) {
    			$option->maxanswers = $groupmanagement->$property;
    		}
    		$option->timemodified = time();
    		// Find out if this selection already exists
    		foreach ($preExistingGroups as $key => $preExistingGroup) {
    			if ($option->groupid == $preExistingGroup->groupid) {
    				// match found, so instead of creating a new record we should merely update a pre-existing record
    				$option->id = $preExistingGroup->id;
    				$DB->update_record("groupmanagement_options", $option);
    				// remove the element from the array to not deal with it later
    				unset($preExistingGroups[$key]);
    				continue 2; // continue the big loop
    			}
    		}
    		$DB->insert_record("groupmanagement_options", $option);	
    	}
    	 
    }
    // remove all remaining pre-existing groups which did not appear in the form (and are thus assumed to have been deleted)
    foreach ($preExistingGroups as $preExistingGroup) {
    	$DB->delete_records("groupmanagement_options", array("id"=>$preExistingGroup->id));
    }

    return $DB->update_record('groupmanagement', $groupmanagement);

}

/**
 * @global object
 * @param object $groupmanagement
 * @param object $user
 * @param object $coursemodule
 * @param array $allresponses
 * @return array
 */
function groupmanagement_prepare_options($groupmanagement, $user, $coursemodule, $allresponses) {

    $cdisplay = array('options'=>array());

    $cdisplay['limitmaxusersingroups'] = true;
    $context = context_module::instance($coursemodule->id);
    $answers = groupmanagement_get_user_answer($groupmanagement, $user, TRUE);

    foreach ($groupmanagement->option as $optionid => $text) {
        if (isset($text)) { //make sure there are no dud entries in the db with blank text values.
            $option = new stdClass;
            $option->attributes = new stdClass;
            $option->attributes->value = $optionid;
            $option->groupid = $text;
            $option->maxanswers = $groupmanagement->maxanswers[$optionid];
            $option->groupvideo = $groupmanagement->groupvideo[$optionid];
            $option->creatorid = $groupmanagement->creatorid[$optionid];
            $option->enrollementkey = $groupmanagement->enrollementkey[$optionid];
            $option->displaylayout = $groupmanagement->display;

            if (isset($allresponses[$text])) {
                $option->countanswers = count($allresponses[$text]);
            } else {
                $option->countanswers = 0;
            }
            if (is_array($answers)) {
                foreach($answers as $answer) {
                    if ($answer && $text == $answer->id) {
                        $option->attributes->checked = true;
                    }
                }
            }
            if ($groupmanagement->limitmaxusersingroups && ($option->countanswers >= $option->maxanswers) && empty($option->attributes->checked)) {
                $option->attributes->disabled = true;
            }
            $cdisplay['options'][] = $option;
        }
    }

    $cdisplay['hascapability'] = is_enrolled($context, NULL, 'mod/groupmanagement:choose'); //only enrolled users are allowed to make a groupmanagement

    if ($groupmanagement->allowupdate && is_array($answers)) {
        $cdisplay['allowupdate'] = true;
    }

    return $cdisplay;
}

/**
 * @global object
 * @param int $formanswer
 * @param object $groupmanagement
 * @param int $userid
 * @param object $course Course object
 * @param object $cm
 */
function groupmanagement_user_submit_response($formanswer, $groupmanagement, $userid, $course, $cm) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $context = context_module::instance($cm->id);
    $eventparams = array(
        'context' => $context,
        'objectid' => $groupmanagement->id
    );

    $selected_option = $DB->get_record('groupmanagement_options', array('id' => $formanswer));

    $current = groupmanagement_get_user_answer($groupmanagement, $userid);
    if ($current) {
        $currentgroup = $DB->get_record('groups', array('id' => $current->id), 'id,name', MUST_EXIST);
    }
    $selectedgroup = $DB->get_record('groups', array('id' => $selected_option->groupid), 'id,name', MUST_EXIST);

    $countanswers=0;
    if($groupmanagement->limitmaxusersingroups) {
        $groupmembers = $DB->get_records('groups_members', array('groupid' => $selected_option->groupid));
        $countanswers = count($groupmembers);
        $maxans = $groupmanagement->maxanswers[$formanswer];
    }

    if (!($groupmanagement->limitmaxusersingroups && ($countanswers >= $maxans) )) {
        groups_add_member($selected_option->groupid, $userid);
        if ($current) {
            if (!($groupmanagement->multipleenrollmentspossible == 1)) {
                if ($selected_option->groupid != $current->id) {
                    if (groups_is_member($current->id, $userid)) {
                        groups_remove_member($current->id, $userid);
//                        $eventparams['groupname'] = $currentgroup->name;
                        $event = \mod_groupmanagement\event\choice_removed::create($eventparams);
                        $event->add_record_snapshot('course_modules', $cm);
                        $event->add_record_snapshot('course', $course);
                        $event->add_record_snapshot('groupmanagement', $groupmanagement);
                        $event->trigger();
                    }
                }
            }
        } else {
            // Update completion state
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $groupmanagement->completionsubmit) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
//            $eventparams['groupname'] = $selectedgroup->name;
            $event = \mod_groupmanagement\event\choice_updated::create($eventparams);
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('course', $course);
            $event->add_record_snapshot('groupmanagement', $groupmanagement);
            $event->trigger();
        }
    } else {
        if (!$current || !($current->id==$selected_option->groupid)) { //check to see if current groupmanagement already selected - if not display error
            print_error('groupmanagementfull', 'groupmanagement', $CFG->wwwroot.'/mod/groupmanagement/view.php?id='.$cm->id);
        }
    }
}

/**
 * @param object $groupmanagement
 * @param array $allresponses
 * @param object $cm
 * @return void Output is echo'd
 */
function groupmanagement_show_reportlink($groupmanagement, $allresponses, $cm) {
    $responsecount = 0;
    $respondents = array();
    foreach($allresponses as $optionid => $userlist) {
        if ($optionid) {
            $responsecount += count($userlist);
            if ($groupmanagement->multipleenrollmentspossible) {
                foreach ($userlist as $user) {
                    if (!in_array($user->id, $respondents)) {
                        $respondents[] = $user->id;
                    }
                }
            }
        }
    }
    echo '<div class="reportlink"><a href="report.php?id='.$cm->id.'">'.get_string("viewallresponses", "groupmanagement", $responsecount);
    if ($groupmanagement->multipleenrollmentspossible == 1) {
        echo ' ' . get_string("byparticipants", "groupmanagement", count($respondents));
    }
    echo '</a></div>';
}

/**
 * @global object
 * @param object $groupmanagement
 * @param object $course
 * @param object $coursemodule
 * @param array $allresponses

 *  * @param bool $allresponses
 * @return object
 */
function prepare_groupmanagement_show_results($groupmanagement, $course, $cm, $allresponses, $forcepublish=false) {
    global $CFG, $FULLSCRIPT, $PAGE, $OUTPUT;

    $display = clone($groupmanagement);
    $display->coursemoduleid = $cm->id;
    $display->courseid = $course->id;
//debugging('<pre>'.print_r($groupmanagement->option, true).'</pre>', DEBUG_DEVELOPER);
//debugging('<pre>'.print_r($allresponses, true).'</pre>', DEBUG_DEVELOPER);

    //overwrite options value;
    $display->options = array();
    $totaluser = 0;
    foreach ($groupmanagement->option as $optionid => $groupid) {
        $display->options[$optionid] = new stdClass;
        $display->options[$optionid]->groupid = $groupid;
        $display->options[$optionid]->maxanswer = $groupmanagement->maxanswers[$optionid];

        if (array_key_exists($groupid, $allresponses)) {
            $display->options[$optionid]->user = $allresponses[$groupid];
            $totaluser += count($allresponses[$groupid]);
        }
    }
    if ($groupmanagement->showunanswered) {
        $display->options[0]->user = $allresponses[0];
    }
    unset($display->option);
    unset($display->maxanswers);

    $display->numberofuser = $totaluser;
    $context = context_module::instance($cm->id);
    $display->viewresponsecapability = has_capability('mod/groupmanagement:readresponses', $context);
    $display->deleterepsonsecapability = has_capability('mod/groupmanagement:deleteresponses',$context);
    $display->fullnamecapability = has_capability('moodle/site:viewfullnames', $context);

    if (empty($allresponses)) {
        echo $OUTPUT->heading(get_string("nousersyet"));
        return false;
    }


    $totalresponsecount = 0;
    foreach ($allresponses as $optionid => $userlist) {
        if ($groupmanagement->showunanswered || $optionid) {
            $totalresponsecount += count($userlist);
        }
    }

    $context = context_module::instance($cm->id);

    $hascapfullnames = has_capability('moodle/site:viewfullnames', $context);

    $viewresponses = has_capability('mod/groupmanagement:readresponses', $context);
    switch ($forcepublish) {
        case GROUPMANAGEMENT_PUBLISH_NAMES:
            echo '<div id="tablecontainer">';
            if ($viewresponses) {
                echo '<form id="attemptsform" method="post" action="'.$FULLSCRIPT.'" onsubmit="var menu = document.getElementById(\'menuaction\'); return (menu.options[menu.selectedIndex].value == \'delete\' ? \''.addslashes_js(get_string('deleteattemptcheck','quiz')).'\' : true);">';
                echo '<div>';
                echo '<input type="hidden" name="id" value="'.$cm->id.'" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                echo '<input type="hidden" name="mode" value="overview" />';
            }

            echo "<table cellpadding=\"5\" cellspacing=\"10\" class=\"results names\">";
            echo "<tr>";

            $columncount = array(); // number of votes in each column
            if ($groupmanagement->showunanswered) {
                $columncount[0] = 0;
                echo "<th class=\"col0 header\" scope=\"col\">";
                print_string('notanswered', 'groupmanagement');
                echo "</th>";
            }
            $count = 1;
            foreach ($groupmanagement->option as $optionid => $optiontext) {
                $columncount[$optionid] = 0; // init counters
                echo "<th class=\"col$count header\" scope=\"col\">";
                echo format_string($optiontext);
                echo "</th>";
                $count++;
            }
            echo "</tr><tr>";

            if ($groupmanagement->showunanswered) {
                echo "<td class=\"col$count data\" >";
                // added empty row so that when the next iteration is empty,
                // we do not get <table></table> error from w3c validator
                // MDL-7861
                echo "<table class=\"groupmanagementresponse\"><tr><td></td></tr>";
                if (!empty($allresponses[0])) {
                    foreach ($allresponses[0] as $user) {
                        echo "<tr>";
                        echo "<td class=\"picture\">";
                        echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                        echo "</td><td class=\"fullname\">";
                        echo "<a href=\"$CFG->wwwroot/user/view.php?id=$user->id&amp;course=$course->id\">";
                        echo fullname($user, $hascapfullnames);
                        echo "</a>";
                        echo "</td></tr>";
                    }
                }
                echo "</table></td>";
            }
            $count = 1;
            foreach ($groupmanagement->option as $optionid => $optiontext) {
                    echo '<td class="col'.$count.' data" >';

                    // added empty row so that when the next iteration is empty,
                    // we do not get <table></table> error from w3c validator
                    // MDL-7861
                    echo '<table class="groupmanagementresponse"><tr><td></td></tr>';
                    if (isset($allresponses[$optionid])) {
                        foreach ($allresponses[$optionid] as $user) {
                            $columncount[$optionid] += 1;
                            echo '<tr><td class="attemptcell">';
                            if ($viewresponses and has_capability('mod/groupmanagement:deleteresponses',$context)) {
                                echo '<input type="checkbox" name="userid[]" value="'. $user->id. '" />';
                            }
                            echo '</td><td class="picture">';
                            echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                            echo '</td><td class="fullname">';
                            echo "<a href=\"$CFG->wwwroot/user/view.php?id=$user->id&amp;course=$course->id\">";
                            echo fullname($user, $hascapfullnames);
                            echo '</a>';
                            echo '</td></tr>';
                       }
                    }
                    $count++;
                    echo '</table></td>';
            }
            echo "</tr><tr>";
            $count = 1;

            if ($groupmanagement->showunanswered) {
                echo "<td></td>";
            }

            foreach ($groupmanagement->option as $optionid => $optiontext) {
                echo "<td align=\"center\" class=\"col$count count\">";
                if ($groupmanagement->limitmaxusersingroups) {
                    echo get_string("taken", "groupmanagement").":";
                    echo $columncount[$optionid];
                    echo "<br/>";
                    echo get_string("limit", "groupmanagement").":";
                    echo $groupmanagement->maxanswers[$optionid];
                } else {
                    if (isset($columncount[$optionid])) {
                        echo $columncount[$optionid];
                    }
                }
                echo "</td>";
                $count++;
            }
            echo "</tr>";

            /// Print "Select all" etc.
            if ($viewresponses and has_capability('mod/groupmanagement:deleteresponses',$context)) {
                echo '<tr><td></td><td>';
                echo '<a href="javascript:select_all_in(\'DIV\',null,\'tablecontainer\');">'.get_string('selectall').'</a> / ';
                echo '<a href="javascript:deselect_all_in(\'DIV\',null,\'tablecontainer\');">'.get_string('deselectall').'</a> ';
                echo '&nbsp;&nbsp;';
                echo html_writer::label(get_string('withselected', 'groupmanagement'), 'menuaction');
                echo html_writer::select(array('delete' => get_string('delete')), 'action', '', array(''=>get_string('withselectedusers')), array('id'=>'menuaction'));
                $PAGE->requires->js_init_call('M.util.init_select_autosubmit', array('attemptsform', 'menuaction', ''));
                echo '<noscript id="noscriptmenuaction" style="display:inline">';
                echo '<div>';
                echo '<input type="submit" value="'.get_string('go').'" /></div></noscript>';
                echo '</td><td></td></tr>';
            }

            echo "</table></div>";
            if ($viewresponses) {
                echo "</form></div>";
            }
            break;
    }
    return $display;
}

/**
 * @global object
 * @param array $userids
 * @param object $groupmanagement Choice main table row
 * @param object $cm Course-module object
 * @param object $course Course object
 * @return bool
 */
function groupmanagement_delete_responses($userids, $groupmanagement, $cm, $course) {
    global $CFG, $DB, $context;
    require_once($CFG->libdir.'/completionlib.php');

    if(!is_array($userids) || empty($userids)) {
        return false;
    }

    foreach($userids as $num => $userid) {
        if(empty($userid)) {
            unset($userids[$num]);
        }
    }

    $completion = new completion_info($course);
    $eventparams = array(
        'context' => $context,
        'objectid' => $groupmanagement->id
    );

    foreach($userids as $userid) {
        if ($current = groupmanagement_get_user_answer($groupmanagement, $userid)) {
            $currentgroup = $DB->get_record('groups', array('id' => $current->id), 'id,name', MUST_EXIST);
            if (groups_is_member($current->id, $userid)) {
                groups_remove_member($current->id, $userid);
                $event = \mod_groupmanagement\event\choice_removed::create($eventparams);
                $event->add_record_snapshot('course_modules', $cm);
                $event->add_record_snapshot('course', $course);
                $event->add_record_snapshot('groupmanagement', $groupmanagement);
                $event->trigger();
            }
            // Update completion state
            if ($completion->is_enabled($cm) && $groupmanagement->completionsubmit) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $userid);
            }
        }
    }
    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function groupmanagement_delete_instance($id) {
    global $DB;

    if (! $groupmanagement = $DB->get_record("groupmanagement", array("id"=>"$id"))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records("groupmanagement_options", array("groupmanagementid"=>"$groupmanagement->id"))) {
        $result = false;
    }

    if (! $DB->delete_records("groupmanagement", array("id"=>"$groupmanagement->id"))) {
        $result = false;
    }

    return $result;
}

/**
 * Returns text string which is the answer that matches the id
 *
 * @global object
 * @param object $groupmanagement
 * @param int $id
 * @return string
 */
function groupmanagement_get_option_text($groupmanagement, $id) {
    global $DB;

    if ($result = $DB->get_record('groups', array('id' => $id))) {
        return $result->name;
    } else {
        return get_string("notanswered", "groupmanagement");
    }
}

/*
 * Returns DB records of groups used by the groupmanagement activity
 *
 * @global object
 * @param object $groupmanagement
 * @return array
 */
function groupmanagement_get_groups($groupmanagement) {
    global $DB;

    static $groups = array();

    if (count($groups)) {
        return $groups;
    }

    if (is_numeric($groupmanagement)) {
        $groupmanagementid = $groupmanagement;
    }
    else {
        $groupmanagementid = $groupmanagement->id;
    }

    $groups = array();
    $options = $DB->get_records('groupmanagement_options', array('groupmanagementid' => $groupmanagementid));
    foreach ($options as $option) {
        if ($group = $DB->get_record('groups', array('id' => $option->groupid)))
        $groups[$group->id] = $group;
    }
    return $groups;
}

/**
 * Gets a full groupmanagement record
 *
 * @global object
 * @param int $groupmanagementid
 * @return object|bool The groupmanagement or false
 */
function groupmanagement_get_groupmanagement($groupmanagementid) {
    global $DB;

    if ($groupmanagement = $DB->get_record("groupmanagement", array("id" => $groupmanagementid))) {
        if ($options = $DB->get_records("groupmanagement_options", array("groupmanagementid" => $groupmanagementid), "id")) {
            foreach ($options as $option) {
                $groupmanagement->option[$option->id] = $option->groupid;
                $groupmanagement->maxanswers[$option->id] = $option->maxanswers;
                $groupmanagement->groupvideo[$option->id] = $option->groupvideo;
                $groupmanagement->creatorid[$option->id] = $option->creatorid;
                $groupmanagement->enrollementkey[$option->id] = $option->enrollementkey;
            }
            return $groupmanagement;
        }
    }
    return false;
}

/**
 * @return array
 */
function groupmanagement_get_view_actions() {
    return array('view','view all','report');
}

/**
 * @return array
 */
function groupmanagement_get_post_actions() {
    return array('choose','choose again');
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the groupmanagement.
 *
 * @param object $mform form passed by reference
 */
function groupmanagement_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'groupmanagementheader', get_string('modulenameplural', 'groupmanagement'));
    $mform->addElement('advcheckbox', 'reset_groupmanagement', get_string('removeresponses','groupmanagement'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function groupmanagement_reset_course_form_defaults($course) {
    return array('reset_groupmanagement'=>1);
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $groupmanagement
 * @param object $cm
 * @return array
 */
function groupmanagement_get_response_data($groupmanagement, $cm) {
    global $CFG, $context, $choicegrop_users;

    /// Initialise the returned array, which is a matrix:  $allresponses[responseid][userid] = responseobject
    static $allresponses = array();

    if (count($allresponses)) {
        return $allresponses;
    }

    /// First get all the users who have access here
    /// To start with we assume they are all "unanswered" then move them later
    $choicegrop_users = get_enrolled_users($context, 'mod/groupmanagement:choose', 0, user_picture::fields('u', array('idnumber')), 'u.lastname ASC,u.firstname ASC');
    $allresponses[0] = $choicegrop_users;

    if ($allresponses[0]) {
        // if groupmembersonly used, remove users who are not in any group
        if (!empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $allresponses[0] = array_intersect_key($allresponses[0], $groupingusers);
            }
        }
    }

    foreach ($allresponses[0] as $user) {
        $currentAnswers = groupmanagement_get_user_answer($groupmanagement, $user, TRUE);
        if ($currentAnswers != false) {
            foreach ($currentAnswers as $current) {
                $allresponses[$current->id][$user->id] = clone($allresponses[0][$user->id]);
                $allresponses[$current->id][$user->id]->timemodified = $current->timeuseradded;
            }
            unset($allresponses[0][$user->id]);   // Remove from unanswered column
        }
    }
    return $allresponses;
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function groupmanagement_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function groupmanagement_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $groupmanagementnode The node to add module settings to
 */
function groupmanagement_extend_settings_navigation(settings_navigation $settings, navigation_node $groupmanagementnode) {
    global $PAGE;

    if (has_capability('mod/groupmanagement:readresponses', $PAGE->cm->context)) {

        $groupmode = groups_get_activity_groupmode($PAGE->cm);
        if ($groupmode) {
            groups_get_activity_group($PAGE->cm, true);
        }
        if (!$groupmanagement = groupmanagement_get_groupmanagement($PAGE->cm->instance)) {
            print_error('invalidcoursemodule');
            return false;
        }
        $allresponses = groupmanagement_get_response_data($groupmanagement, $PAGE->cm, $groupmode);   // Big function, approx 6 SQL calls per user

        $responsecount = 0;
        $respondents = array();
        foreach($allresponses as $optionid => $userlist) {
            if ($optionid) {
                $responsecount += count($userlist);
                if ($groupmanagement->multipleenrollmentspossible) {
                    foreach ($userlist as $user) {
                        if (!in_array($user->id, $respondents)) {
                            $respondents[] = $user->id;
                        }
                    }
                }
            }
        }
        $viewallresponsestext = get_string("viewallresponses", "groupmanagement", $responsecount);
        if ($groupmanagement->multipleenrollmentspossible == 1) {
            $viewallresponsestext .= ' ' . get_string("byparticipants", "groupmanagement", count($respondents));
        }
        $groupmanagementnode->add($viewallresponsestext, new moodle_url('/mod/groupmanagement/report.php', array('id'=>$PAGE->cm->id)));
    }
}

/**
 * Obtains the automatic completion state for this groupmanagement based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function groupmanagement_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Get groupmanagement details
    $groupmanagement = $DB->get_record('groupmanagement', array('id'=>$cm->instance), '*', MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false
    if($groupmanagement->completionsubmit) {
        $useranswer = groupmanagement_get_user_answer($groupmanagement, $userid);
        return $useranswer !== false;
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}


/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function groupmanagement_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-groupmanagement-*'=>get_string('page-mod-groupmanagement-x', 'choice'));
    return $module_pagetype;
}

