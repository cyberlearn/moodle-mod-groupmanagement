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

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_groupmanagement_mod_form extends moodleform_mod {

	function definition() {
		global $CFG, $GROUPMANAGEMENT_SHOWRESULTS, $GROUPMANAGEMENT_PUBLISH, $GROUPMANAGEMENT_DISPLAY, $DB, $COURSE, $PAGE;

		$mform    =& $this->_form;

		// -------------------------
		// General section
		// -------------------------
		$mform->addElement('header', 'general', get_string('general', 'form'));

		$mform->addElement('text', 'name', get_string('groupmanagementname', 'groupmanagement'), array('size'=>'64'));
		if (!empty($CFG->formatstringstriptags)) {
			$mform->setType('name', PARAM_TEXT);
		} else {
			$mform->setType('name', PARAM_CLEANHTML);
		}
		$mform->addRule('name', null, 'required', null, 'client');

		$this->add_intro_editor(true, get_string('chatintro', 'chat'));

		$groups = array();
		$db_groups = $DB->get_records('groups', array('courseid' => $COURSE->id));

		$groupsToCreate = 2 - count($db_groups);
		while ($groupsToCreate > 0) {
			$data = new stdClass();
			$data->courseid = $COURSE->id;
			$data->name = 'Group '.$groupsToCreate;
			groups_create_group($data);
			$groupsToCreate--;
		}

		$db_groups = $DB->get_records('groups', array('courseid' => $COURSE->id));
		foreach ($db_groups as $group) {
			$groups[$group->id] = new stdClass();
			$groups[$group->id]->name = $group->name;
			$groups[$group->id]->mentioned = false;
			$groups[$group->id]->id = $group->id;
		}

		$db_groupings = $DB->get_records('groupings', array('courseid' => $COURSE->id));
        $groupings = array();
        if ($db_groupings) {
            foreach ($db_groupings as $grouping) {
                $groupings[$grouping->id] = new stdClass();
                $groupings[$grouping->id]->name = $grouping->name;
            }

            list($sqlin, $inparams) = $DB->get_in_or_equal(array_keys($groupings));
            $db_groupings_groups = $DB->get_records_select('groupings_groups', 'groupingid '.$sqlin, $inparams);

            foreach ($db_groupings_groups as $grouping_group_link) {
                $groupings[$grouping_group_link->groupingid]->linkedGroupsIDs[] =  $grouping_group_link->groupid;
            }
        }

		// -------------------------
		// Groups section
		// -------------------------
		$mform->addElement('header', 'groups', get_string('groupsheader', 'groupmanagement'));
		$mform->setExpanded('groups');

		$mform->addElement('selectyesno', 'groupcreationpossible', get_string('groupcreationpossible', 'groupmanagement'));

		$mform->addElement('selectyesno', 'privategroupspossible', get_string('privategroupspossible', 'groupmanagement'));
		$mform->disabledIf('privategroupspossible', 'groupcreationpossible', 'eq', 0);

		$mform->addElement('selectyesno', 'allowupdate', get_string("allowupdate", "groupmanagement"));

		$mform->addElement('selectyesno', 'multipleenrollmentspossible', get_string('multipleenrollmentspossible', 'groupmanagement'));

		$mform->addElement('selectyesno', 'limitmaxgroups', get_string('limitmaxgroups', 'groupmanagement'));

		$mform->addElement('text', 'maxgroups', get_string('maxgroups', 'groupmanagement'), array('size' => 6));
		$mform->setType('maxgroups', PARAM_INT);
		$mform->addRule('maxgroups', get_string('error'), 'numeric', 'extraruledata', 'client', false, false);
		$mform->setDefault('maxgroups', 0);
		$mform->disabledIf('maxgroups', 'limitmaxgroups', 'eq', 0);

		$serializedselectedgroupsValue = '';
		if (isset($this->_instance) && $this->_instance != '') {
			// this is presumably edit mode, try to fill in the data for javascript
			$cg = groupmanagement_get_groupmanagement($this->_instance);
			foreach ($cg->option as $optionID => $groupID) {
				$serializedselectedgroupsValue .= ';' . $groupID;
				$mform->setDefault('group_' . $groupID . '_limit', $cg->maxanswers[$optionID]);
			}
		}

		$mform->addElement('selectyesno', 'limitmaxusersingroups', get_string('limitmaxusersingroups', 'groupmanagement'));
		$mform->addHelpButton('limitmaxusersingroups', 'limitmaxusersingroups', 'groupmanagement');

		$mform->addElement('text', 'maxusersingroups', get_string('generallimitation', 'groupmanagement'), array('size' => '6'));
		$mform->setType('maxusersingroups', PARAM_INT);
		$mform->disabledIf('maxusersingroups', 'limitmaxusersingroups', 'eq', 0);
		$mform->addRule('maxusersingroups', get_string('error'), 'numeric', 'extraruledata', 'client', false, false);
		$mform->setDefault('maxusersingroups', 0);
		$mform->addElement('button', 'setlimit', get_string('applytoallgroups', 'groupmanagement'));
		$mform->disabledIf('setlimit', 'limitmaxusersingroups', 'eq', 0);

		// -------------------------
		// Advanced section
		// -------------------------
		$mform->addElement('header', 'advancedsettingshdr', get_string('advancedheader', 'groupmanagement'));

		$mform->addElement('checkbox', 'displaygrouppicture', get_string('displaygrouppicture', 'groupmanagement'));
		$mform->setDefault('displaygrouppicture', 'checked');

		$mform->addElement('checkbox', 'displaygroupvideo', get_string('displaygroupvideo', 'groupmanagement'));
		$mform->setDefault('displaygroupvideo', 'checked');

		$mform->addElement('html', '<fieldset class="clearfix">
				<div class="fcontainer clearfix">
				<div id="fitem_id_option_0" class="fitem fitem_fselect ">
				<div class="fitemtitle"><label for="id_option_0">'.get_string('groupsheader', 'groupmanagement').'</label><span class="helptooltip"><a href="'. $CFG->wwwroot .'/help.php?component=groupmanagement&amp;identifier=groupmanagementoptions&amp;lang=en" title="Help with Choice options" aria-haspopup="true" target="_blank"><img src="'.$CFG->wwwroot.'/theme/image.php?theme='.$PAGE->theme->name.'&component=core&image=help" alt="Help with Choice options" class="iconhelp"></a></span></div><div class="felement fselect">

				<table><tr><td>'.get_string('available_groups', 'groupmanagement').'</td><td>&nbsp;</td><td>'.get_string('selected_groups', 'groupmanagement').'</td><td>&nbsp;</td></tr><tr><td style="vertical-align: top">');

		$mform->addElement('html','<select id="availablegroups" name="availableGroups" multiple size=10 style="width:200px">');
		foreach ($groupings as $groupingID => $grouping) {
			// find all linked groups to this grouping
			if (isset($grouping->linkedGroupsIDs) && count($grouping->linkedGroupsIDs) > 1) { // grouping has more than 2 items, thus we should display it (otherwise it would be clearer to display only that single group alone)
				$mform->addElement('html', '<option value="'.$groupingID.'" style="font-weight: bold" class="grouping">'.get_string('char_bullet_expanded', 'groupmanagement').$grouping->name.'</option>');
				foreach ($grouping->linkedGroupsIDs as $linkedGroupID) {
					$mform->addElement('html', '<option value="'.$linkedGroupID.'" class="group nested">&nbsp;&nbsp;&nbsp;&nbsp;'.$groups[$linkedGroupID]->name.'</option>');
					$groups[$linkedGroupID]->mentioned = true;
				}
			}
		}
		foreach ($groups as $group) {
			if ($group->mentioned === false) {
				$mform->addElement('html', '<option value="'.$group->id.'" class="group toplevel">'.$group->name.'</option>');
			}
		}
		$mform->addElement('html','</select><br><button name="expandButton" type="button" disabled id="expandButton">'.get_string('expand_all_groupings', 'groupmanagement').'</button><button name="collapseButton" type="button" disabled id="collapseButton">'.get_string('collapse_all_groupings', 'groupmanagement').'</button><br>'.get_string('double_click_grouping_legend', 'groupmanagement').'<br>'.get_string('double_click_group_legend', 'groupmanagement'));

		$mform->addElement('html','
				</td><td><button id="addGroupButton" name="add" type="button" disabled>'.get_string('add', 'groupmanagement').'</button><div><button name="remove" type="button" disabled id="removeGroupButton">'.get_string('del', 'groupmanagement').'</button></div></td>');
		$mform->addElement('html','<td style="vertical-align: top"><select id="id_selectedGroups" name="selectedGroups" multiple size=10 style="width:200px">');

		if (!isset($this->_instance) || empty($this->_instance)) {
			foreach ($groups as $group) {
				if ($group->mentioned === false) {
					$mform->addElement('html', '<option value="'.$group->id.'" class="group toplevel">'.$group->name.'</option>');
				}
			}
		}

		$mform->addElement('html','</select></td><td><div><div id="fitem_id_limit_0" class="fitem fitem_ftext" style="display:none"><div class=""><label for="id_limit_0" id="label_for_limit_ui">'.get_string('set_limit_for_group', 'groupmanagement').'</label></div><div class="ftext">
				<input class="mod-groupmanagement-limit-input" type="text" value="0" id="ui_limit_input" disabled="disabled"></div></div></div></td></tr></table>
				</div></div>

				</div>
				</fieldset>');

		foreach ($groups as $group) {
			$mform->addElement('hidden', 'group_' . $group->id . '_limit', '', array('id' => 'group_' . $group->id . '_limit', 'class' => 'limit_input_node'));
			$mform->setType('group_' . $group->id . '_limit', PARAM_RAW);
		}

		$mform->addElement('selectyesno', 'showunanswered', get_string("showunanswered", "groupmanagement"));

		$mform->addElement('select', 'showresults', get_string("publish", "groupmanagement"), $GROUPMANAGEMENT_SHOWRESULTS);
		$mform->setDefault('showresults', GROUPMANAGEMENT_SHOWRESULTS_DEFAULT);

		$mform->addElement('select', 'publish', get_string("privacy", "groupmanagement"), $GROUPMANAGEMENT_PUBLISH, GROUPMANAGEMENT_PUBLISH_DEFAULT);
		$mform->setDefault('publish', GROUPMANAGEMENT_PUBLISH_DEFAULT);
		$mform->disabledIf('publish', 'showresults', 'eq', 0);

		$mform->addElement('selectyesno', 'freezegroups', get_string('freezegroups', 'groupmanagement'));

		$mform->addElement('date_time_selector', 'freezegroupsaftertime', get_string('freezegroupsaftertime', 'groupmanagement'), array('optional' => true));

		//-------------------------------------------------------------------------------

		$mform->addElement('hidden', 'serializedselectedgroups', $serializedselectedgroupsValue, array('id' => 'serializedselectedgroups'));
		$mform->setType('serializedselectedgroups', PARAM_RAW);

		$mform->addElement('header', 'timerestricthdr', get_string('timerestrict', 'groupmanagement'));
		$mform->addElement('checkbox', 'timerestrict', get_string('timerestrict', 'groupmanagement'));

		$mform->addElement('date_time_selector', 'timeopen', get_string("groupmanagementopen", "groupmanagement"));
		$mform->disabledIf('timeopen', 'timerestrict');

		$mform->addElement('date_time_selector', 'timeclose', get_string("groupmanagementclose", "groupmanagement"));
		$mform->disabledIf('timeclose', 'timerestrict');

		//-------------------------------------------------------------------------------
		$this->standard_coursemodule_elements();
		//-------------------------------------------------------------------------------
		$this->add_action_buttons();
}

	function data_preprocessing(&$default_values) {
		global $DB;
		$this->js_call();

		if (empty($default_values['timeopen'])) {
			$default_values['timerestrict'] = 0;
		} else {
			$default_values['timerestrict'] = 1;
		}
	}

	function validation($data, $files) {
		$errors = parent::validation($data, $files);

		$groupIDs = explode(';', $data['serializedselectedgroups']);
		$groupIDs = array_diff( $groupIDs, array( '' ) );

		if (array_key_exists('multipleenrollmentspossible', $data) && $data['multipleenrollmentspossible'] === '1') {
			if (count($groupIDs) < 1) {
				$errors['serializedselectedgroups'] = get_string('fillinatleastoneoption', 'groupmanagement');
			}
		} else {
			if (count($groupIDs) < 2) {
				$errors['serializedselectedgroups'] = get_string('fillinatleasttwooptions', 'groupmanagement');
			}
		}


		return $errors;
	}

	function get_data() {
		$data = parent::get_data();
		if (!$data) {
			return false;
		}
		// Set up completion section even if checkbox is not ticked
		if (empty($data->completionsection)) {
			$data->completionsection=0;
		}
		return $data;
	}

	function add_completion_rules() {
		$mform =& $this->_form;

		$mform->addElement('checkbox', 'completionsubmit', '', get_string('completionsubmit', 'groupmanagement'));
		return array('completionsubmit');
	}

	function completion_rule_enabled($data) {
		return !empty($data['completionsubmit']);
	}

	public function js_call() {
		global $PAGE;
		$PAGE->requires->yui_module('moodle-mod_groupmanagement-form', 'Y.Moodle.mod_groupmanagement.form.init');
		foreach (array_keys(get_string_manager()->load_component_strings('groupmanagement', current_language())) as $string) {
			$PAGE->requires->string_for_js($string, 'groupmanagement');
		}
	}

}

