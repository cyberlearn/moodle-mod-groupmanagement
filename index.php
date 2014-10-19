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

$id = required_param('id',PARAM_INT);   // course

$PAGE->set_url('/mod/groupmanagement/index.php', array('id'=>$id));

if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('invalidcourseid');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_groupmanagement\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strgroupmanagement = get_string("modulename", "groupmanagement");
$strgroupmanagements = get_string("modulenameplural", "groupmanagement");
$strsectionname  = get_string('sectionname', 'format_'.$course->format);
$PAGE->set_title($strgroupmanagements);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strgroupmanagements);
echo $OUTPUT->header();

if (! $groupmanagements = get_all_instances_in_course("groupmanagement", $course)) {
    notice(get_string('thereareno', 'moodle', $strgroupmanagements), "../../course/view.php?id=$course->id");
}

$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $modinfo = get_fast_modinfo($course->id);
    $sections = $modinfo->get_section_info_all();
}

$table = new html_table();

if ($usesections) {
    $table->head  = array ($strsectionname, get_string("question"), get_string("answer"));
    $table->align = array ("center", "left", "left");
} else {
    $table->head  = array (get_string("question"), get_string("answer"));
    $table->align = array ("left", "left");
}

$currentsection = "";

foreach ($groupmanagements as $groupmanagement) {
    $groupmanagement_groups = groupmanagement_get_groups($groupmanagement);
    $answer = groupmanagement_get_user_answer($groupmanagement, $USER->id);
    if (!empty($answer->id)) {
        $aa = $answer->name;
    } else {
        $aa = "";
    }
    if ($usesections) {
        $printsection = "";
        if ($groupmanagement->section !== $currentsection) {
            if ($groupmanagement->section) {
                $printsection = get_section_name($course, $sections[$groupmanagement->section]);
            }
            if ($currentsection !== "") {
                $table->data[] = 'hr';
            }
            $currentsection = $groupmanagement->section;
        }
    }

    //Calculate the href
    if (!$groupmanagement->visible) {
        //Show dimmed if the mod is hidden
        $tt_href = "<a class=\"dimmed\" href=\"view.php?id=$groupmanagement->coursemodule\">".format_string($groupmanagement->name,true)."</a>";
    } else {
        //Show normal if the mod is visible
        $tt_href = "<a href=\"view.php?id=$groupmanagement->coursemodule\">".format_string($groupmanagement->name,true)."</a>";
    }
    if ($usesections) {
        $table->data[] = array ($printsection, $tt_href, $aa);
    } else {
        $table->data[] = array ($tt_href, $aa);
    }
}
echo "<br />";
echo html_writer::table($table);

echo $OUTPUT->footer();

