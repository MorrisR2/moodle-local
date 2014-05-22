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
 * Library of interface functions and constants for module enrolmentreminder
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the enrolmentreminder specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    local
 * @subpackage enrolmentreminder
 * @copyright  2011 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/completionlib.php');

require_once(dirname(__FILE__).'/userselector.php');

define('ENROLMENTREMINDER_DAYS_AHEAD',3);
define('ENROLMENTREMINDER_FIRST_CRON_CYCLE_CUTOFF_DAYS',1);

function enrolmentreminder_enrolled($ue) {
    global $DB;
    require_once(dirname(__FILE__).'/locallib.php');
    $courseid = $DB->get_field('enrol', 'courseid', array ('id' => $ue->enrolid));
    enrolmentreminder_add_expire_calendar($courseid, $ue->userid, $ue->timeend, $ue->id);
}

function enrolmentreminder_unenrolled($ue) {
    enrolmentreminder_delete_reminder($ue);
	return true;
}


// For when they expire.  (Set enrolment cron action to suspend the enrolment).
function enrolmentreminder_enrolmodified($ue) {
    global $debug;
    global $CFG;
    
    if ($ue->status == ENROL_USER_SUSPENDED) {
        enrolmentreminder_delete_reminder($ue);
        return;
    }

	if ($ue->timeend > time()) {
        enrolmentreminder_update_reminder($ue);
		return;
	}
	if ( enrolmentreminder_completed_successfully($ue) ) {
        enrolmentreminder_delete_reminder($ue);
		return;
	}
}

function enrolmentreminder_delete_reminder($ue) {
    global $DB;
    global $CFG;

    $courseid = $DB->get_field('enrol', 'courseid', array ('id' => $ue->enrolid));
    $eventid = $DB->get_field('event', 'id', array ('courseid' => $courseid, 'userid' => $ue->userid, 'eventtype'=>'enrolmentexpires'));
    if ($eventid) {
        require_once($CFG->dirroot.'/calendar/lib.php');
        $calendar_event = new calendar_event( array('id' => $eventid) );
        $calendar_event->delete();
    }
}

function enrolmentreminder_update_reminder($ue) {
    global $DB;
    global $CFG;

    $courseid = $DB->get_field('enrol', 'courseid', array ('id' => $ue->enrolid));
    $event = $DB->get_record('event', array ('courseid' => $courseid, 'userid' => $ue->userid, 'eventtype'=>'enrolmentexpires'));
    mtrace('Updating reminder ' . $event->id . " to time " . $ue->timeend);
    if ($event) {
        require_once($CFG->dirroot.'/calendar/lib.php');
        $eventobj = new calendar_event($event);
        $event->timestart = $ue->timeend;
        $eventobj->update($event);
    }
}

function enrolmentreminder_completed_successfully($ue) {
	global $DB;
    global $CFG;
    global $debug;

    $courseid = $DB->get_field('enrol', 'courseid', array ('id' => $ue->enrolid));
    $course = $DB->get_record('course', array('id' => $courseid));
	if (! $course) {
        return;
	}
    $completioninfo = new completion_info($course);
	if ( $completioninfo->is_course_complete($ue->userid) ) {
		if ( enrolmentreminder_is_expired($ue) ) {
			return false;
		} else {
			return true;
		}
	} else {
		return false;
	}
}

function enrolmentreminder_completed($completion) {
    global $DB;
    global $CFG;

    $params = array ('courseid' => $completion->course, 'userid' => $completion->userid, 'eventtype'=>'enrolmentexpires');
    $eventid = $DB->get_field('event', 'id', $params);
    if ($eventid) {
        require_once($CFG->dirroot.'/calendar/lib.php');
        $calendar_event = new calendar_event( array('id' => $eventid) );
        $calendar_event->delete();
    }
    return true;
}


function enrolmentreminder_is_expired($ue) {
    global $DB;
    global $CFG;
    global $debug;

    $courseid = $DB->get_field('enrol', 'courseid', array ('id' => $ue->enrolid));
    $course = $DB->get_record('course', array('id' => $courseid));
    if (! $course) {
        return;
    }

    // If the user completed it before expiring, return false, not expired.
    $ccompletion = new completion_completion(array('userid' => $userid, 'course'=>$courseid));
    if($ccompletion->timecompleted < $ue->timeend) {
        return false;
    }

    $completioninfo = new completion_info($course);
    $usercompletion = $completioninfo->get_completion($ue->userid, COMPLETION_CRITERIA_TYPE_UNENROL);
	if ( $usercompletion && $usercompletion->is_complete() ) {
		return true;
	}
	$usercompletion = $completioninfo->get_completion($ue->userid, COMPLETION_CRITERIA_TYPE_DURATION);
	if ($usercompletion) {
		// Completing the duration without completing the work = expiring = Incomplete, but the duration is_complete.
		return $usercompletion->is_complete();
    } elseif ( ($ue->timeend > 0) && ($ue->timeend < time()) ) {
		return true;
	} else {
		return false;
	}
}


function local_enrolmentreminder_extends_settings_navigation($settingsnav) {
    global $PAGE;
    global $DB;
    global $USER;
    global $SITE;

    if (has_capability('moodle/site:config', context_system::instance())) {
        if ( ($PAGE->context->contextlevel == CONTEXT_COURSE) && ($PAGE->course->id != $SITE->id) ) {
            if ($settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
                $url = new moodle_url('/local/enrolmentreminder/index.php', array('course' => $PAGE->course->id));
                $mynode = navigation_node::create(
                    get_string('enrolmentreminder', 'local_enrolmentreminder'),
                    $url,
                    navigation_node::NODETYPE_LEAF,
                    'local_enrolmentreminder',
                    'local_enrolmentreminder',
                    new pix_icon('enrolmentreminder-16', get_string('enrolmentreminder', 'local_enrolmentreminder'), 'local_enrolmentreminder')
                );
                if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
                    $mynode->make_active();
                }
                $settingnode->add_node($mynode);
            }
        }
    }
}


function local_enrolmentreminder_cron() {
    global $CFG;
    global $DB;
    $now = time();

    $params = array();
    $selector = "l.course = 0 AND l.module = 'enrolmentreminder' AND l.action = 'update'";
    $logrows = get_logs($selector, $params, 'l.time DESC', '', 1, $totalcount);
    if (!$logrows) {  // this is the first cron cycle, after plugin is just installed
        mtrace("This is the first cron cycle");
        $timewindowstart = $now - ENROLMENTREMINDER_FIRST_CRON_CYCLE_CUTOFF_DAYS * 24 * 3600;
    } else {
        // info field includes that starting time of last cron cycle.
        $firstrecord = current($logrows);
        $timewindowstart = $firstrecord->info + 1;
    }

    $events = local_enrolmentreminder_get_events($timewindowstart, $now, ENROLMENTREMINDER_DAYS_AHEAD);
    mtrace("Found " . count($events) . " expiring enrollments to send reminders for.\n");
    local_enrolmentreminder_send_messages($events);
    add_to_log(0, 'enrolmentreminder', 'update', '', "$now");
}

function local_enrolmentreminder_get_events($timestart, $timeend, $daysahead) {
    global $DB;
    $timestart += $daysahead * 60 * 60 *24;
    $timeend += $daysahead * 60 * 60 *24;
    $where = "eventtype='enrolmentexpires' AND timestart >= :timestart AND timestart <= :timeend";
    mtrace("Finding expirations between $timestart and $timeend");
    return $DB->get_records_select('event', $where, array('timestart'=>$timestart,'timeend'=>$timeend));
}

function local_enrolmentreminder_send_messages($events) {
    global $DB;
    $eventdata = new stdClass();
    $eventdata->component           = 'local_enrolmentreminder';   // plugin name
    $eventdata->name                = 'enrolmentending';     // message interface name
    $eventdata->userfrom            = get_admin();

    $dateformat = '%b %e';
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
        $dateformat = '%b %#d';
    }

    foreach($events as $event) {
        $course = $DB->get_record('course', array('id'=>$event->courseid));
        $user = $DB->get_record('user', array('id'=>$event->userid));
        $ending = userdate($event->timestart, $dateformat);
        $eventdata->fullmessage  = local_enrolmentreminder_get_message_plaintext($course, $user, $ending);
        if (!empty($eventdata->fullmessage)) {
            $eventdata->subject             = $course->shortname . ' ending ' . $ending;
            $eventdata->smallmessage        = $course->fullname . ' ending ' . $ending;
            $eventdata->fullmessagehtml     = '';
            $eventdata->fullmessageformat   = FORMAT_PLAIN;
            $eventdata->notification        = 1;
            // $eventdata->userto = $user;
            $eventdata->userto = get_admin();
            $mailresult = message_send($eventdata);
        }
    }
}

function local_enrolmentreminder_get_message_plaintext($course, $user, $ending) {
    global $CFG;
    $reminder = local_enrolmentreminder_getexisting($course, false);
    if (!empty($reminder->tmpltext)) {
        return enrolmentreminder_processtemplate($reminder->tmpltext, array('course'=>$course,'user'=>$user,'enddate'=>$ending, 'CFG'=>$CFG));
    }
}

function local_enrolmentreminder_getexisting($courseid, $defaultifnone = false) {
    global $DB;

    $result = $DB->get_record('enrolmentreminder', array('courseid'=>$courseid));
    if ($result) {
        return $result;
    } else {
        if ($defaultifnone) {
           $default = file_get_contents(__DIR__.'/emailtemplates/default.php.inc');
           return array('courseid' => $courseid, 'tmpltext' => $default);
        }
    }
}

