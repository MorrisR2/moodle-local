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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    local
 * @subpackage enrolmentreminder
 * @copyright  2012, 2013 Texas A&M Engineering Extension Service by Ray Morris
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 1);

require_once('../../config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/forms.php');

require_login();

if ( empty($_REQUEST['action']) ) {
    $_REQUEST['action'] = 'show';
}

if (!empty($_REQUEST['courseid'])) {
    $context = context_course::instance($_REQUEST['courseid']);
} else {
    $context = context_user::instance($USER->id);
}

$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_url('/local/enrolmentreminder/index.php', array());
$PAGE->set_title(get_string('pluginname', 'local_enrolmentreminder'));
$PAGE->set_heading(get_string('pluginname', 'local_enrolmentreminder'));


echo $OUTPUT->header();

if (empty($_REQUEST['courseid']) ) {
    local_enrolmentreminder_choosecourse();
} else {
    if ($_REQUEST['action'] == 'delete') {
        local_enrolmentreminder_delete($_REQUEST['courseid']);
    }
    $mform = local_enrolmentreminder_addform(local_enrolmentreminder_getexisting($_REQUEST['courseid'], true));
    if (empty($_REQUEST['action']) || ($_REQUEST['action'] != 'delete')) {
        echo '<a href="index.php?action=delete&courseid='.$_REQUEST['courseid'].'"><img src="/pix/t/delete.png" /> Delete</a>'."\n\n";
    }
}

echo $OUTPUT->footer();


function local_enrolmentreminder_choosecourse() {
	global $DB;
    global $PAGE;
?>
    <script src="/teex/js/jquery-1.9.1.js"></script>
    <link href="select2/select2.css" rel="stylesheet"/>
    <script src="select2/select2.js"></script>
    <script>
        $(document).ready(function() { $("#course").select2(); });
    </script>

<form id="assignform" method="post" action="<?php echo $PAGE->url ?>">
  <input type="hidden" name="sesskey" value="<?php echo sesskey() ?>" />
  <table summary="" class="roleassigntable generaltable generalbox boxaligncenter" cellspacing="0">
    <tr>
      <td id="existingcell">
          <p><label for="course">Select a course</label></p>
		  <select name="courseid" id="course">
		  <?php
				$result = $DB->get_records('course');
				foreach ($result as $course) {
					if (! empty ($course->idnumber) ) {
						?>
						<option value="<?php echo $course->id ?>"><?php echo $course->idnumber . ': ' . $course->fullname ?></option>
						<?php
					}
				}
		  ?>
		  </select>
      </td>
    </tr>
	<tr><td><input type="submit" value="Continue" /></td</tr>
  </table>
</form>
<?php
}

function local_enrolmentreminder_addform($data) {
    $mform = new enrolmentreminderadd_form();
    if ($fromform = $mform->get_data()) {
        // print_r($fromform);
        local_enrolmentreminder_add($fromform);
        $_REQUEST['courseid'] = $fromform->courseid;
        $mform->display();
    } else {
        // print_r($data);
        $mform->set_data($data);
        $mform->display();
    }
}

function local_enrolmentreminder_add($fromform) {
    global $DB;

    if (!empty($fromform->tmpltext)) {
        $fromform->submitbutton = null;
        if ((!empty($fromform->id)) && ($fromform->id > 0)) {
            $DB->update_record('enrolmentreminder', $fromform);
        } else {
            $fromform->id = null;
            $DB->insert_record('enrolmentreminder', $fromform);
        }
    }
}

function local_enrolmentreminder_delete($courseid) {
    global $DB;
    $DB->delete_records('enrolmentreminder', array('courseid'=>$courseid));
}

