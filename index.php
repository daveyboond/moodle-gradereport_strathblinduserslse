<?php

require_once '../../../config.php';
require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->dirroot.'/grade/report/strathblindusers/lib.php';
require_once $CFG->dirroot.'/mod/assign/locallib.php';
require_once $CFG->dirroot.'/user/profile/lib.php';

$courseid      = required_param('id', PARAM_INT);        // course id
$page          = optional_param('page', 0, PARAM_INT);   // active page
$edit          = optional_param('edit', -1, PARAM_BOOL); // sticky editting mode

$assignmentcmid    = optional_param('assign', false, PARAM_INT);//assignment id

$PAGE->set_url(new moodle_url('/grade/report/strathblindusers/index.php', array('id'=>$courseid)));

/// basic access checks
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
	print_error('nocourseid');
}
require_login($course);
$context = context_course::instance($course->id);

require_capability('gradereport/strathblindusers:view', $context);
require_capability('moodle/grade:viewall', $context);

/// return tracking object
$gpr = new grade_plugin_return(array('type'=>'report', 'plugin'=>'strathblindusers', 'courseid'=>$courseid, 'page'=>$page));

/// last selected report session tracking
if (!isset($USER->grade_last_report)) {
	$USER->grade_last_report = array();
}
$USER->grade_last_report[$course->id] = 'strathblindusers';

$reportname = get_string('pluginname', 'gradereport_strathblindusers');

/// Print header
$buttons = ''; // unused
print_grade_page_head($COURSE->id, 'report', 'strathblindusers', $reportname, false, $buttons);

//Initialise the grader report object that produces the table
//the class grade_report_grader_ajax was removed as part of MDL-21562
$sortitemid = null;
$report = new grade_report_strathblindusers($courseid, $gpr, $context, $page, $sortitemid);

// Do the actual report
$assignments = array();
if ($assignmentcmid) {
	$cm = get_coursemodule_from_id('assign',$assignmentcmid);
	$assignments = $DB->get_records('assign', array('course' => $courseid, 'blindmarking' => 1, 'revealidentities' => 0, 'id'=>$cm->instance));

} else {
	$assignments = $DB->get_records('assign', array('course' => $courseid, 'blindmarking' => 1, 'revealidentities' => 0));
}
if (!$assignments){
	echo $OUTPUT->box(get_string('noblindassignments', 'gradereport_strathblindusers'));
} else {
	$mapping = array(); // user id to array of assignmentid => blind ID
	foreach ($assignments as $assignment) {
		if ($usermappings = $DB->get_records('assign_user_mapping', array('assignment' => $assignment->id))) {
			foreach ($usermappings as $usermapping) {
				if (!isset($mapping[$usermapping->userid])) {
					$mapping[$usermapping->userid] = array($assignment->id => $usermapping->id);
				} else {
					$mapping[$usermapping->userid][$assignment->id] = $usermapping->id;
				}
			}
		}
	}
	
	if ($assignmentcmid) {
		echo $OUTPUT->box(get_string('explanationsingle', 'gradereport_strathblindusers'));

		$ret_url = new moodle_url('/mod/assign/view.php', array(
			'id' => $assignmentcmid,
			'action' => 'grading',
		));
		echo $OUTPUT->action_link($ret_url,'Return to Assignment');

	} else {
		echo $OUTPUT->box(get_string('explanation', 'gradereport_strathblindusers'));

	}

	
	
	
	$table = new flexible_table('gradereport_strathblindusers');
	//$table->attributes('class', 'generaltable');
	$table->set_attribute('class', "generaltable");
	$table->baseurl = new moodle_url('/grade/report/strathblindusers/index.php', array(
		'id' => $courseid,
		'assign' => $assignmentcmid
	));
	$columns = array('userpicture','lastname', 'registrationnumber');
	$headers = array(null,'Name', 'Registration Number');
	foreach ($assignments as $assignment) {
		$columns[] = "assign_" . $assignment->id;///$assignment->name;
		$headers[] = $assignment->name;
	}
	if ($assignmentcmid) {
		$columns[] = 'extensions';
		$headers[] = null;
	}
	
	$table->define_columns($columns);
	$table->define_headers($headers);

	foreach ($assignments as $assignment) {
		$table->no_sorting( "assign_" . $assignment->id);///$assignment->name;
	}
	$table->sortable(true,'lastname');
	$table->setup();
	
	
	$orderby = $table->get_sort_for_table('gradereport_strathblindusers');
	//echo "Sorting: ".strtolower($orderby);
	$users = array();
	if(false !== strpos(strtolower($orderby),'registrationnumber asc')) {
		$users = get_enrolled_users($context, 'mod/assign:submit');
		foreach($users as $user) {
			profile_load_custom_fields($user);
		}
		uasort($users, function($a,$b) {
			//profile_load_custom_fields($a);
			//profile_load_custom_fields($b);
			return ($a->profile['registrationno'] < $b->profile['registrationno']) ? -1 : 1; 
		
		});
	} else if (false !== strpos(strtolower($orderby),'registrationnumber desc')) {
		$users = get_enrolled_users($context, 'mod/assign:submit');
		foreach($users as $user) {
			profile_load_custom_fields($user);
		}
		uasort($users, function($a,$b) {
			//profile_load_custom_fields($a);
			//profile_load_custom_fields($b);
			return ($a->profile['registrationno'] < $b->profile['registrationno']) ? 1 : -1;
		});
		
	} else {
	
		$users = get_enrolled_users($context, 'mod/assign:submit', 0, 'u.*', $orderby);
		foreach($users as $user) {
			profile_load_custom_fields($user);
		}

	}
	//function get_enrolled_users(context $context, $withcapability = '', $groupid = 0, $userfields = 'u.*', $orderby = null, $limitfrom = 0, $limitnum = 0) {
	
	$table_data = array();
	foreach($users as $user) {
		
		//print_object($user);
		$row = array();
		$row[] = $OUTPUT->user_picture($user);
		$row[] = fullname($user);
		$row[] = "";
		foreach ($assignments as $assignment) {
			$c = get_string('hiddenuser', 'assign') . assign::get_uniqueid_for_user_static($assignment->id, $user->id);
			$row[] = $c;
		
			if ($assignmentcmid) {
				$ext_url = new moodle_url('/mod/assign/view.php', array(
						'id' => $assignmentcmid,
						'userid' => $user->id,
						'action' => 'grantextension',
						'sesskey' => sesskey()
				));
				$action = new confirm_action(
						get_string('confirmextensionforuser', 'gradereport_strathblindusers', fullname($user)),
						null,
						'Yes',
						'No'
				);
		
				$row[]= $OUTPUT->action_link($ext_url, 'Grant Extension', $action);
			}
		
		}
		//var_dump($row);
		$table->add_data($row);
		
		//$table_data[] = $row;
	}
	$table->finish_output();
}	
echo $OUTPUT->footer();

	
	/*
	$table = new html_table();
	
	$table->head = array('', 'Name', 'Registration Number');
	foreach ($assignments as $assignment) {
		$table->head[] = $assignment->name;
	}
	if ($assignmentcmid) {
		$table->head[] = '';
	}

	foreach($users as $user) {
		profile_load_custom_fields($user);
		$tr = new html_table_row();
		$tr->cells[] = $OUTPUT->user_picture($user);
		$tr->cells[] = fullname($user);
		$tr->cells[] = $user->profile['registrationno'];
		foreach ($assignments as $assignment) {
			$c = get_string('hiddenuser', 'assign') . assign::get_uniqueid_for_user_static($assignment->id, $user->id);
			$tr->cells[] = $c;
			if ($assignmentcmid) {
				$ext_url = new moodle_url('/mod/assign/view.php', array(
					'id' => $assignmentcmid,
					'userid' => $user->id,
					'action' => 'grantextension',
					'sesskey' => sesskey()
				));
				$action = new confirm_action(
					get_string('confirmextensionforuser', 'gradereport_strathblindusers', fullname($user)),
					null,
					'Yes',
					'No'
				);

				$tr->cells[]= $OUTPUT->action_link($ext_url, 'Grant Extension', $action);
//"<a href='{$ext_url->out()}'>Grant Extension</a>";
			}
//			$tr->cells[] = $c;//get_string('hiddenuser', 'assign') . assign::get_uniqueid_for_user_static($assignment->id, $user->id);
		}
		$table->data[] = $tr;
	}
	
	echo html_writer::table($table);
	*/

