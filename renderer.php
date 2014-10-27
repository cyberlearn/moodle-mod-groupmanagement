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

define ('GROUPMANAGEMENT_DISPLAY_HORIZONTAL_LAYOUT', 0);
define ('GROUPMANAGEMENT_DISPLAY_VERTICAL_LAYOUT', 1);

class mod_groupmanagement_renderer extends plugin_renderer_base {

    /**
     * Returns HTML to display groupmanagements of option
     * @param object $options
     * @param int  $coursemoduleid
     * @param bool $vertical
     * @return string
     */
    public function display_options($options, $coursemoduleid, $vertical = true, $publish = false, $limitmaxusersingroups = false, $showresults = false, $current = false, $groupmanagementopen = false, $disabled = false, $multipleenrollmentspossible = false) {
        global $DB, $PAGE, $USER, $OUTPUT, $course, $groupmanagement_groups, $groupmanagement_users, $groupmanagement, $context;

        $PAGE->requires->js('/mod/groupmanagement/javascript.js');

        $layoutclass = 'vertical';
        $target = new moodle_url('/mod/groupmanagement/view.php');
        $attributes = array('method'=>'POST', 'action'=>$target, 'class'=> $layoutclass);

        $html = html_writer::start_tag('form', $attributes);
        $html .= html_writer::start_tag('table', array('class'=>'groupmanagements'));

        $html .= html_writer::start_tag('tr');
        $html .= html_writer::tag('th', get_string('choice', 'groupmanagement'));

        $group = get_string('group');
        $group .= html_writer::tag('a', get_string('showdescription', 'groupmanagement'), array('class' => 'groupmanagement-descriptiondisplay groupmanagement-descriptionshow', 'href' => '#'));
        $group .= html_writer::tag('a', get_string('hidedescription', 'groupmanagement'), array('class' => 'groupmanagement-descriptiondisplay groupmanagement-descriptionhide hidden', 'href' => '#'));
        $html .= html_writer::tag('th', $group);

        if ( $showresults == GROUPMANAGEMENT_SHOWRESULTS_ALWAYS or
        ($showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_ANSWER and $current) or
        ($showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_CLOSE and !$groupmanagementopen)) {
            if ($limitmaxusersingroups) {
                $html .= html_writer::tag('th', get_string('members/max', 'groupmanagement'));
            } else {
                $html .= html_writer::tag('th', get_string('members/', 'groupmanagement'));
            }

            if (!empty($groupmanagement->privategroupspossible) && $groupmanagement->privategroupspossible == 1) {
                $html .= html_writer::tag('th', get_string('private', 'groupmanagement'));
            }

            $html .= html_writer::tag('th', get_string('groupcreator', 'groupmanagement'));

            if ($publish == GROUPMANAGEMENT_PUBLISH_NAMES) {
                $membersdisplay_html = html_writer::tag('a', get_string('show'), array('class' => 'groupmanagement-memberdisplay groupmanagement-membershow', 'href' => '#'));
                $membersdisplay_html .= html_writer::tag('a', get_string('hide'), array('class' => 'groupmanagement-memberdisplay groupmanagement-memberhide hidden', 'href' => '#'));
                $html .= html_writer::tag('th', get_string('groupmembers', 'groupmanagement') . $membersdisplay_html);
            }

            $html .= html_writer::tag('th', '');
        }

        $html .= html_writer::end_tag('tr');

        $availableoption = count($options['options']);
        if ($multipleenrollmentspossible == 1) {
            $i=0;
            $answer_to_groupid_mappings = '';
        }

        $initiallyHideSubmitButton = false;
        $private_groups_id = array();

        $disableDeletion = false;
        if(count($options['options']) <= 2) {
            $disableDeletion = true;
        }

        foreach ($options['options'] as $option) {
            $group = (isset($groupmanagement_groups[$option->groupid])) ? ($groupmanagement_groups[$option->groupid]) : (false);
            if (!$group) {
                $colspan = 2;
                if ( $showresults == GROUPMANAGEMENT_SHOWRESULTS_ALWAYS or ($showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_ANSWER and $current) or ($showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_CLOSE and !$groupmanagementopen)) {
                    $colspan++;
                    if ($publish == GROUPMANAGEMENT_PUBLISH_NAMES) {
                        $colspan++;
                    }
                }
                $cell = html_writer::tag('td', get_string('groupdoesntexist', 'groupmanagement'), array('colspan' => $colspan));
                $html .= html_writer::tag('tr', $cell);
                break;
            }
            $html .= html_writer::start_tag('tr', array('class'=>'option'));
            $html .= html_writer::start_tag('td', array());

            if ($multipleenrollmentspossible == 1) {
                $option->attributes->name = 'answer_'.$i;
                $option->attributes->type = 'checkbox';
                $answer_to_groupid_mappings .= '<input type="hidden" name="answer_'.$i.'_groupid" value="'.$option->groupid.'">';
                $option->attributes->onchange = 'if ($("#enrollementKeyKey" + this.value).css("display") == "none" && $(this).is(":checked")) {
                                                    $("#enrollementKeyKey" + this.value).show();
                                                    $("#enrollementKeyLabel" + this.value).show();
                                                 } else {
                                                    $("#enrollementKeyKey" + this.value).hide();
                                                    $("#enrollementKeyLabel" + this.value).hide();
                                                 }';
                $i++;
            } else {
                $option->attributes->name = 'answer';
                $option->attributes->type = 'radio';
                $option->attributes->onchange = '$(".enrollementKey").hide();
                                                 $("#enrollementKeyKey" + this.value).show();
                                                 $("#enrollementKeyLabel" + this.value).show();';
                if (array_key_exists('attributes', $option) && array_key_exists('checked', $option->attributes) && $option->attributes->checked == true) {
                    $initiallyHideSubmitButton = true;
                }
            }

            $group_title = "";

            if($groupmanagement->displaygrouppicture == 1) {
                $group_title .= print_group_picture($group, $course->id, false, true).' ';
            }

            $group_title .= '<b>'.$group->name.'</b>';

            $labeltext = html_writer::tag('label', $group_title, array('for' => 'choiceid_' . $option->attributes->value));

            $group_members = $DB->get_records_sql('SELECT {user}.id, CONCAT_WS(", ", {user}.lastname, {user}.firstname) AS fullname 
                                                   FROM {user}
                                                   RIGHT OUTER JOIN {groups_members} ON {user}.id = {groups_members}.userid 
                                                   WHERE groupid = ? 
                                                   ORDER BY fullname ASC', array($group->id));

            if (!empty($option->attributes->disabled) || ($limitmaxusersingroups && sizeof($group_members) >= $option->maxanswers)) {
                $labeltext .= ' ' . html_writer::tag('em', get_string('full', 'groupmanagement'));
                $option->attributes->disabled=true;
                $availableoption--;
            }

            if ($groupmanagement->freezegroups == 1 || (!empty($groupmanagement->freezegroupsaftertime) && time() >= $groupmanagement->freezegroupsaftertime)) {
                $option->attributes->disabled=true;
            }

            $group_description = $group->description;

            $labeltext .= html_writer::tag('div', $group_description, array('class' => 'groupmanagements-descriptions hidden'));

            if($groupmanagement->displaygroupvideo == 1) {
                if(isset($option->groupvideo)) {
                    $videoEmbed = '<iframe width="320" height="180" src="https://www.youtube.com/embed/'.$option->groupvideo.'?rel=0&amp;controls=0&amp;showinfo=0" frameborder="0" allowfullscreen></iframe>';
                    $labeltext .= html_writer::tag('div', $videoEmbed, array('class' => 'groupmanagements-descriptions hidden'));
                }
            }
            
            if ($disabled) {
                $option->attributes->disabled=true;
            }
            $attributes = (array) $option->attributes;
            $attributes['id'] = 'choiceid_' . $option->attributes->value;
            $html .= html_writer::empty_tag('input', $attributes);
            $html .= html_writer::end_tag('td');
            $html .= html_writer::tag('td', $labeltext, array('for'=>$option->attributes->name));

            if ( $showresults == GROUPMANAGEMENT_SHOWRESULTS_ALWAYS or
            ($showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_ANSWER and $current) or
            ($showresults == GROUPMANAGEMENT_SHOWRESULTS_AFTER_CLOSE and !$groupmanagementopen)) {

                $maxanswers = ($limitmaxusersingroups) ? (' / '.$option->maxanswers) : ('');
                $html .= html_writer::tag('td', sizeof($group_members).$maxanswers, array('class' => 'center'));

                if (!empty($groupmanagement->privategroupspossible) && $groupmanagement->privategroupspossible == 1) {
                    $privateImage = '';
                    if (isset($option->enrollementkey)) {
                        $privateImage = '<img src="'.$this->output->pix_url('t/locked').'" alt="'.get_string('private', 'groupmanagement').'" />';
                        $private_groups_id[] = $option->attributes->value;
                    }
                    $html .= html_writer::tag('td', $privateImage, array('class' => 'center'));
                }
                
                $group_creator_links = '-';
                if (isset($option->creatorid)) {
                    $group_creator = $DB->get_record('user', array('id' => $option->creatorid));
                    if (isset($group_creator)) {
                        $url = new moodle_url('/message/index.php', array('id'=>$option->creatorid, 'course'=>$course->id));
                        $creatorImage = '<img src="'.$this->output->pix_url('t/email').'" alt="'.get_string('contact', 'groupmanagement').'" />';
                        $group_creator_links = html_writer::link($url, $creatorImage);

                        $group_creator_links .= ' ';

                        $url = new moodle_url('/user/view.php', array('id'=>$option->creatorid, 'course'=>$course->id));
                        $group_creator_name = $group_creator->lastname.', '.$group_creator->firstname;
                        $group_creator_links .= html_writer::link($url, $group_creator_name);
                    }
                }
                $html .= html_writer::tag('td', $group_creator_links, array('class' => 'center'));

                if ($publish == GROUPMANAGEMENT_PUBLISH_NAMES) {
                    $group_member_html = '';
                    foreach ($group_members as $group_member) {
                        $url = new moodle_url('/user/view.php', array('id'=>$group_member->id, 'course'=>$course->id));
                        $group_member_link = html_writer::link($url, $group_member->fullname.'<br />');
                        $group_member_html .= html_writer::tag('div', $group_member_link, array('class' => 'groupmanagements-membersnames hidden', 'id' => 'groupmanagement_'.$option->attributes->value));
                    }
                    if (empty($group_member_html)) {
                        $group_member_html = html_writer::tag('div', '-', array('class' => 'groupmanagements-membersnames hidden', 'id' => 'groupmanagement_'.$option->attributes->value));
                    }
                    $html .= html_writer::tag('td', $group_member_html, array('class' => 'center'));
                }

                $actionLinks = '';
                $hasManageGroupsCapability = has_capability('mod/groupmanagement:managegroups', $context);
                if ($hasManageGroupsCapability || (isset($option->creatorid) && $option->creatorid == $USER->id)) {
                    if ($groupmanagement->freezegroups == 0 && (empty($groupmanagement->freezegroupsaftertime) || time() < $groupmanagement->freezegroupsaftertime)) {
                        $url = new moodle_url('/mod/groupmanagement/group/group.php', array('id'=>$option->groupid, 'courseid'=>$course->id, 'cgid'=>$groupmanagement->id, 'cmid'=>$coursemoduleid));
                        $editImage = '<img src="'.$this->output->pix_url('t/edit').'" alt="'.get_string('edit', 'moodle').'" />';
                        $actionLinks .= html_writer::link($url, $editImage);

                        $actionLinks .= '&nbsp;&nbsp;';

                        if(!$disableDeletion) {
                            $url = new moodle_url('/mod/groupmanagement/group/delete.php', array('groups'=>$option->groupid, 'courseid'=>$course->id, 'cmid'=>$coursemoduleid));
                            $deleteImage = '<img src="'.$this->output->pix_url('t/delete').'" alt="'.get_string('delete', 'moodle').'" />';
                            $actionLinks .= html_writer::link($url, $deleteImage);
                        }
                    }
                }
                $html .= html_writer::tag('td', $actionLinks, array('class' => 'center'));
            }
            $html .= html_writer::end_tag('tr');
        }
        $html .= html_writer::end_tag('table');
        if ($multipleenrollmentspossible == 1) {
            $html .= '<input type="hidden" name="number_of_groups" value="'.$i.'">' . $answer_to_groupid_mappings;
        }
        $html .= html_writer::tag('div', '', array('class'=>'clearfloat'));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=>$coursemoduleid));

        if (!empty($options['hascapability']) && ($options['hascapability'])) {
            if ($availableoption < 1) {
               $html .= html_writer::tag('div', get_string('groupmanagementfull', 'groupmanagement'));
            } else {
                if (!$disabled) {
                    foreach ($private_groups_id as $private_group_id) {
                        $groupmanagement_option = $DB->get_record('groupmanagement_options', array('id'=>$private_group_id));
                        $groupName = $groupmanagement_groups[$groupmanagement_option->groupid]->name;
                        $labelText = '<b>'.get_string('enrollementKeyForgroupmanagement', 'groupmanagement').' '.$groupName.'</b><br />';
                        
                        if (!empty($groupmanagement_option->creatorid)) {
                            $group_creator_links = '';
                            $group_creator = $DB->get_record('user', array('id' => $groupmanagement_option->creatorid));
                            if (isset($group_creator)) {
                                $url = new moodle_url('/message/index.php', array('id'=>$option->creatorid, 'course'=>$course->id));
                                $group_creator_name = $group_creator->firstname.' '.$group_creator->lastname;
                                $group_creator_links .= html_writer::link($url, $group_creator_name);
                            }

                            $labelText .= get_string('requestEnrollementKeyFrom', 'groupmanagement').' '.$group_creator_links.'<br />';    
                        }

                        $html .= html_writer::tag('label', $labelText, array('for'=>'enrollementKeyKey'.$private_group_id, 'id'=>'enrollementKeyLabel'.$private_group_id, 'class'=>'enrollementKey', 'style'=>'display: none;'));
                        $html .= html_writer::empty_tag('input', array('type'=>'password', 'name'=>'enrollementKeyKey'.$private_group_id, 'id'=>'enrollementKeyKey'.$private_group_id, 'class'=>'enrollementKey', 'style'=>'display: none;'));
                    }
                    if ($groupmanagement->freezegroups == 0 && (empty($groupmanagement->freezegroupsaftertime) || time() < $groupmanagement->freezegroupsaftertime)) {
                        $html .= html_writer::empty_tag('input', array('type'=>'submit', 'value'=>get_string('savemygroupmanagement','groupmanagement'), 'class'=>'button', 'style' => $initiallyHideSubmitButton?'display: none':''));
                    }
                }
            }

            if (!empty($options['allowupdate']) && ($options['allowupdate']) && !($multipleenrollmentspossible == 1)) {
                if ($groupmanagement->freezegroups == 0 && (empty($groupmanagement->freezegroupsaftertime) || time() < $groupmanagement->freezegroupsaftertime)) {
                    $url = new moodle_url('view.php', array('id'=>$coursemoduleid, 'action'=>'delgroupmanagement', 'sesskey'=>sesskey()));
                    $html .= ' ' . html_writer::link($url, get_string('removemygroupmanagement','groupmanagement')).'<br />';
                }
            }
        } else {
            $html .= html_writer::tag('div', get_string('havetologin', 'groupmanagement'));
        }
        
        if ($groupmanagement->groupcreationpossible == 1) {    
            if ($groupmanagement->freezegroups == 0 && (empty($groupmanagement->freezegroupsaftertime) || time() < $groupmanagement->freezegroupsaftertime)) {
                if ($groupmanagement->limitmaxgroups == 0 || count($options['options']) < $groupmanagement->maxgroups) {
                    $url = new moodle_url('/mod/groupmanagement/group/group.php', array('cgid'=>$groupmanagement->id, 'cmid'=>$coursemoduleid, 'courseid'=>$course->id));
                    $html .= '<br/>'.html_writer::empty_tag('input', array('type'=>'button', 'value'=>get_string('creategroup','groupmanagement'), 'class'=>'button', 'onclick'=>'window.location="'.html_entity_decode($url).'"'));
                }
            }
        }

        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * Returns HTML to display groupmanagements result
     * @param object $groupmanagements
     * @param bool $forcepublish
     * @return string
     */
    public function display_result($groupmanagements, $forcepublish = false) {
        if (empty($forcepublish)) { //allow the publish setting to be overridden
            $forcepublish = $groupmanagements->publish;
        }

        $displaylayout = ($groupmanagements) ? ($groupmanagements->display) : (GROUPMANAGEMENT_DISPLAY_HORIZONTAL);

        if ($forcepublish) {  //GROUPMANAGEMENT_PUBLISH_NAMES
            return $this->display_publish_name_vertical($groupmanagements);
        } else { //GROUPMANAGEMENT_PUBLISH_ANONYMOUS';
            if ($displaylayout == GROUPMANAGEMENT_DISPLAY_HORIZONTAL_LAYOUT) {
                return $this->display_publish_anonymous_horizontal($groupmanagements);
            }
            return $this->display_publish_anonymous_vertical($groupmanagements);
        }
    }

    /**
     * Returns HTML to display groupmanagements result
     * @param object $groupmanagements
     * @param bool $forcepublish
     * @return string
     */
    public function display_publish_name_vertical($groupmanagements) {
        global $PAGE;
        global $DB;
        global $context;

        if (!has_capability('mod/groupmanagement:downloadresponses', $context)) {
            return; // only the (editing)teacher can see the diagram
        }
        if (!$groupmanagements) {
            return; // no answers yet, so don't bother
        }

        $html ='';
        $html .= html_writer::tag('h2',format_string(get_string("responses", "groupmanagement")), array('class'=>'main'));

        $attributes = array('method'=>'POST');
        $attributes['action'] = new moodle_url($PAGE->url);
        $attributes['id'] = 'attemptsform';

        if ($groupmanagements->viewresponsecapability) {
            $html .= html_writer::start_tag('form', $attributes);
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=> $groupmanagements->coursemoduleid));
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=> sesskey()));
            $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'mode', 'value'=>'overview'));
        }

        $table = new html_table();
        $table->cellpadding = 0;
        $table->cellspacing = 0;
        $table->attributes['class'] = 'results names ';
        $table->tablealign = 'center';
        $table->data = array();

        $count = 0;
        ksort($groupmanagements->options);

        $columns = array();
        foreach ($groupmanagements->options as $optionid => $options) {
            $coldata = '';
            if ($groupmanagements->showunanswered && $optionid == 0) {
                $coldata .= html_writer::tag('div', format_string(get_string('notanswered', 'groupmanagement')), array('class'=>'option'));
            } else if ($optionid > 0) {
                $coldata .= html_writer::tag('div', format_string(groupmanagement_get_option_text($groupmanagements, $groupmanagements->options[$optionid]->groupid)), array('class'=>'option'));
            }
            $numberofuser = 0;
            if (!empty($options->user) && count($options->user) > 0) {
                $numberofuser = count($options->user);
            }

            $coldata .= html_writer::tag('div', ' ('.$numberofuser. ')', array('class'=>'numberofuser', 'title' => get_string('numberofuser', 'groupmanagement')));
            $columns[] = $coldata;
        }

        $table->head = $columns;

        $coldata = '';
        $columns = array();
        foreach ($groupmanagements->options as $optionid => $options) {
            $coldata = '';
            if ($groupmanagements->showunanswered || $optionid > 0) {
                if (!empty($options->user)) {
                    foreach ($options->user as $user) {
                        $data = '';
                        if (empty($user->imagealt)){
                            $user->imagealt = '';
                        }

                        if ($groupmanagements->viewresponsecapability && $groupmanagements->deleterepsonsecapability  && $optionid > 0) {
                            $attemptaction = html_writer::checkbox('userid[]', $user->id,'');
                            $data .= html_writer::tag('div', $attemptaction, array('class'=>'attemptaction'));
                        }
                        $userimage = $this->output->user_picture($user, array('courseid'=>$groupmanagements->courseid));
                        $data .= html_writer::tag('div', $userimage, array('class'=>'image'));

                        $userlink = new moodle_url('/user/view.php', array('id'=>$user->id,'course'=>$groupmanagements->courseid));
                        $name = html_writer::tag('a', fullname($user, $groupmanagements->fullnamecapability), array('href'=>$userlink, 'class'=>'username'));
                        $data .= html_writer::tag('div', $name, array('class'=>'fullname'));
                        $data .= html_writer::tag('div','', array('class'=>'clearfloat'));
                        $coldata .= html_writer::tag('div', $data, array('class'=>'user'));
                    }
                }
            }

            $columns[] = $coldata;
            $count++;
        }

        $table->data[] = $columns;
        foreach ($columns as $d) {
            $table->colclasses[] = 'data';
        }
        $html .= html_writer::tag('div', html_writer::table($table), array('class'=>'response'));

        $actiondata = '';
        if ($groupmanagements->viewresponsecapability && $groupmanagements->deleterepsonsecapability) {
            $selecturl = new moodle_url('#');

            $selectallactions = new component_action('click',"checkall");
            $selectall = new action_link($selecturl, get_string('selectall'), $selectallactions);
            $actiondata .= $this->output->render($selectall) . ' / ';

            $deselectallactions = new component_action('click',"checknone");
            $deselectall = new action_link($selecturl, get_string('deselectall'), $deselectallactions);
            $actiondata .= $this->output->render($deselectall);

            $actiondata .= html_writer::tag('label', ' ' . get_string('withselected', 'choice') . ' ', array('for'=>'menuaction'));

            $actionurl = new moodle_url($PAGE->url, array('sesskey'=>sesskey(), 'action'=>'delete_confirmation()'));
            $select = new single_select($actionurl, 'action', array('delete'=>get_string('delete')), null, array(''=>get_string('chooseaction', 'groupmanagement')), 'attemptsform');

            $actiondata .= $this->output->render($select);
        }
        $html .= html_writer::tag('div', $actiondata, array('class'=>'responseaction'));

        if ($groupmanagements->viewresponsecapability) {
            $html .= html_writer::end_tag('form');
        }

        return $html;
    }


    /**
     * Returns HTML to display groupmanagements result
     * @param object $groupmanagements
     * @return string
     */
    public function display_publish_anonymous_horizontal($groupmanagements) {
        global $context, $DB, $GROUPMANAGEMENT_COLUMN_WIDTH;

        if (!has_capability('mod/groupmanagement:downloadresponses', $context)) {
            return; // only the (editing)teacher can see the diagram
        }

        $table = new html_table();
        $table->cellpadding = 5;
        $table->cellspacing = 0;
        $table->attributes['class'] = 'results anonymous ';
        $table->data = array();

        $count = 0;
        ksort($groupmanagements->options);

        $rows = array();
        foreach ($groupmanagements->options as $optionid => $options) {
            $numberofuser = 0;
            $graphcell = new html_table_cell();
            if (!empty($options->user)) {
               $numberofuser = count($options->user);
            }

            $width = 0;
            $percentageamount = 0;
            $columndata = '';
            if($groupmanagements->numberofuser > 0) {
               $width = ($GROUPMANAGEMENT_COLUMN_WIDTH * ((float)$numberofuser / (float)$groupmanagements->numberofuser));
               $percentageamount = ((float)$numberofuser/(float)$groupmanagements->numberofuser)*100.0;
            }
            $displaydiagram = html_writer::tag('img','', array('style'=>'height:50px; width:'.$width.'px', 'alt'=>'', 'src'=>$this->output->pix_url('row', 'groupmanagement')));

            $skiplink = html_writer::tag('a', get_string('skipresultgraph', 'groupmanagement'), array('href'=>'#skipresultgraph'. $optionid, 'class'=>'skip-block'));
            $skiphandler = html_writer::tag('span', '', array('class'=>'skip-block-to', 'id'=>'skipresultgraph'.$optionid));

            $graphcell->text = $skiplink . $displaydiagram . $skiphandler;
            $graphcell->attributes = array('class'=>'graph horizontal');

            $datacell = new html_table_cell();
            if ($groupmanagements->showunanswered && $optionid == 0) {
                $columndata .= html_writer::tag('div', format_string(get_string('notanswered', 'groupmanagement')), array('class'=>'option'));
            } else if ($optionid > 0) {
                $columndata .= html_writer::tag('div', format_string(groupmanagement_get_option_text($groupmanagements, $groupmanagements->options[$optionid]->groupid)), array('class'=>'option'));
            }
            $columndata .= html_writer::tag('div', ' ('.$numberofuser.')', array('title'=> get_string('numberofuser', 'groupmanagement'), 'class'=>'numberofuser'));

            if($groupmanagements->numberofuser > 0) {
               $percentageamount = ((float)$numberofuser/(float)$groupmanagements->numberofuser)*100.0;
            }
            $columndata .= html_writer::tag('div', format_float($percentageamount,1). '%', array('class'=>'percentage'));

            $datacell->text = $columndata;
            $datacell->attributes = array('class'=>'header');

            $row = new html_table_row();
            $row->cells = array($datacell, $graphcell);
            $rows[] = $row;
        }

        $table->data = $rows;

        $html = '';
        $header = html_writer::tag('h2',format_string(get_string("responses", "groupmanagement")));
        $html .= html_writer::tag('div', $header, array('class'=>'responseheader'));
        $html .= html_writer::table($table);

        return $html;
    }

}

