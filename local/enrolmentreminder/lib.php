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

function local_enrolmentreminder_extends_settings_navigation($settingsnav) {
    global $PAGE;
    global $DB;
    global $USER;
    global $SITE;

    if (has_capability('moodle/site:config', context_system::instance())) {
        if ( ($PAGE->context->contextlevel == CONTEXT_COURSE) && ($PAGE->course->id != $SITE->id) ) {
            if ($settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
                $url = new moodle_url('/local/enrolmentreminder/index.php', array('courseid' => $PAGE->course->id));
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

    $lastcron = get_config('local_enrolmentreminder', 'lastcron') ?: (time() - (24 * 3600));
    $events = local_enrolmentreminder_get_events($lastcron + 1, time());
    mtrace("Found " . count($events) . " expiring enrollments");
    local_enrolmentreminder_send_messages($events);
}


function local_enrolmentreminder_get_events($timestart, $timeend) {
    global $DB;
    $query = "SELECT ue.id, timestart, timeend, e.courseid, userid " . 
                  "FROM {user_enrolments} ue, {enrol} e, {enrolmentreminder} er ".
                  "WHERE e.courseid=er.courseid AND ue.enrolid=e.id AND " .
                      "timeend >= :timestart + er.leadtime AND " . 
                      "timeend <= :timeend + er.leadtime AND ue.status != " . ENROL_USER_SUSPENDED;

    return $DB->get_records_sql($query, array('timestart'=>$timestart,'timeend'=>$timeend));
}


function local_enrolmentreminder_send_messages($events) {
    global $DB;
    global $CFG;

    require_once($CFG->libdir.'/completionlib.php');

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

        if (empty($completioninfo[$event->courseid])) {
            $completioninfo[$event->courseid] = new completion_info($course);
        }
        if ( $completioninfo[$event->courseid]->is_course_complete($event->userid) ) {
            mtrace("user $event->userid has completed course $event->courseid");
            continue;
        }
        // use timeend for enrolments, timestart for events.
        $ending = userdate($event->timeend, $dateformat);
        $eventdata->fullmessage  = local_enrolmentreminder_get_message_plaintext($course, $user, $ending);
        if (!empty($eventdata->fullmessage)) {
            $eventdata->subject             = $course->shortname . ' ending ' . $ending;
            $eventdata->smallmessage        = $course->fullname . ' ending ' . $ending;
            $eventdata->fullmessagehtml     = '';
            $eventdata->fullmessageformat   = FORMAT_PLAIN;
            $eventdata->notification        = 1;
            $eventdata->userto = $user;
            // $eventdata->userto = get_admin();
            $mailresult = message_send($eventdata);
        }
    }
}

function local_enrolmentreminder_get_message_plaintext($course, $user, $ending) {
    global $CFG;
    require_once($CFG->dirroot . '/local/enrolmentreminder/locallib.php');
    $reminder = local_enrolmentreminder_getexisting($course->id, false);
    if (!empty($reminder->tmpltext)) {
        return enrolmentreminder_processtemplate($reminder->tmpltext, array('course'=>$course,'user'=>$user,'enddate'=>$ending, 'CFG'=>$CFG));
    }
}

function local_enrolmentreminder_getexisting($courseid, $defaultifnone = false) {
    global $DB;

    $result = $DB->get_record('enrolmentreminder', array('courseid'=>$courseid));
    if ($result) {
        $result->submitbutton = 'Update reminder';
        return $result;
    } else {
        if ($defaultifnone) {
           $default = file_get_contents(__DIR__.'/emailtemplates/default.php.inc');
           return array('courseid' => $courseid, 'tmpltext' => $default, 'submitbutton' => 'Add new reminder');
        }
    }
}

