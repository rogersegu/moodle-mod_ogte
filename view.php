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
 * Plugin view page
 *
 * @package mod_ogte
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2021 Tengku Alauddin - din@pukunui.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->dirroot.'/lib/completionlib.php');

use \mod_ogte\constants;
use \mod_ogte\utils;

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (! $cm = get_coursemodule_from_id('ogte', $id)) {
    print_error("Course Module ID was incorrect");
}

if (! $course = $DB->get_record("course", array('id' => $cm->course))) {
    print_error("Course is misconfigured");
}

$context = context_module::instance($cm->id);

require_login($course, true, $cm);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$entriesmanager = has_capability('mod/ogte:manageentries', $context);
$canadd = has_capability('mod/ogte:addentries', $context);

if (!$entriesmanager && !$canadd) {
    throw new \moodle_exception('accessdenied');
}

if (! $ogte = $DB->get_record("ogte", array("id" => $cm->instance))) {
    throw new \moodle_exception('invalidcoursemodule');
}
if (!empty($ogte->preventry)){
    $prev_ogte = $DB->get_record("ogte", array("id" => $ogte->preventry));
}

if (! $cw = $DB->get_record("course_sections", array("id" => $cm->section))) {
    throw new \moodle_exception('invalidcoursemodule');
}

$ogtename = format_string($ogte->name, true, array('context' => $context));

// Header.
$PAGE->set_url('/mod/ogte/view.php', array('id' => $id));
$PAGE->navbar->add($ogtename);
$PAGE->set_title($ogtename);
$PAGE->set_heading($course->fullname);

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

echo $renderer->header();


// Check to see if groups are being used here.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
// groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/ogte/view.php?id=$cm->id");

// if ($entriesmanager) {
    // $entrycount = ogte_count_entries($ogte, $currentgroup);
    // echo '<div class="reportlink"><a href="report.php?id='.$cm->id.'">'.
          // get_string('viewallentries', 'ogte', $entrycount).'</a></div>';
// }

//template data
$tdata=[];

//introl
if (!empty($ogte->intro)) {
    $ogte->intro = trim($ogte->intro);
    $tdata['intro'] = format_module_intro('ogte', $ogte, $cm->id);
}

//Check download mode, and display the download page button
if ($ogte->mode == 1){
    $tdata['downloadbutton'] = $renderer->single_button('download.php?id='.$cm->id, get_string('download', 'ogte'), 'get',
                array("class" => "singlebutton ogtestart"));
    echo $renderer->render_from_template('mod_ogte/downloadpage', $tdata);
    echo $renderer->footer();
    die;
}


// Display entries
$lists=utils::get_level_options();
$entries = $DB->get_records(constants::M_ENTRIESTABLE, array('userid' => $USER->id, 'ogte' => $ogte->id));
if($entries) {
    $theentries =[];
    $sesskey = sesskey();
    foreach(array_values($entries) as $i=>$entry){
        $arrayitem = (Array)$entry;
        $arrayitem['index']=($i+1);
        //get list and level info
        $thelevels=utils::get_level_options($entry->listid);
        if(array_key_exists($entry->levelid, $thelevels)) {
            $arrayitem['listinfo'] = $thelevels[$entry->levelid]['listname'] . ' - ' . $thelevels[$entry->levelid]['label'];
        }else{
            $arrayitem['listinfo'] ='';
        }

        $editurl=new moodle_url('/mod/ogte/edit.php', array('id'=>$cm->id, 'entryid'=>$entry->id,'sesskey'=>$sesskey ,'action'=>'edit'));
        $downloadurl=new moodle_url('/mod/ogte/edit.php', array('id'=>$cm->id, 'entryid'=>$entry->id,'sesskey'=>$sesskey ,'action'=>'download'));
        $deleteurl=new moodle_url('/mod/ogte/edit.php', array('id'=>$cm->id, 'entryid'=>$entry->id,'sesskey'=>$sesskey ,'action'=>'confirmdelete'));
        $arrayitem['editurl']=$editurl->out();
        $arrayitem['downloadurl']=$downloadurl->out();
        $arrayitem['deleteurl']=$deleteurl->out();

        $theentries[]= $arrayitem;
    }
    $tdata['haveentries']=true;
    $tdata['entries'] =  $theentries;
    $ee=new moodle_url('/mod/ogte/edit.php', array('id'=>$cm->id, 'entryid'=>$entry->id,'action'=>'edit'));
    $ee->out();
}

if ($canadd) {
    $tdata['addnewbutton'] = $renderer->single_button('edit.php?id='.$cm->id, get_string('addnew', 'ogte'), 'get',
        array("class" => "singlebutton ogtestart"));
}

echo $renderer->render_from_template('mod_ogte/viewpage', $tdata);


//lists page button
if(has_capability('mod/ogte:manage', $context)) {
    echo '<br><hr>';
    echo $renderer->back_to_lists_button($cm, get_string('addeditlists', constants::M_COMPONENT));
}

// Trigger module viewed event.
$event = \mod_ogte\event\course_module_viewed::create(array(
   'objectid' => $ogte->id,
   'context' => $context
));
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('ogte', $ogte);
$event->trigger();

echo $OUTPUT->footer();
