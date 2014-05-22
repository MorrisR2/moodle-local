<?php


function enrolmentreminder_add_expire_calendar($courseid, $userid, $endtime, $ue_id = null) {
    global $CFG;
    global $client;

    require_once($CFG->dirroot . '/calendar/lib.php');
    $event = new stdClass;
    $event->name         = 'Enrollment expires';
    $event->description  = 'Please complete the course before this date.';
    $event->courseid     = $courseid;
    $event->groupid      = 0;
    $event->userid       = $userid;
    $event->instance     = $ue_id;
    $event->eventtype    = 'enrolmentexpires';
    $event->timestart    = $endtime;
    $event->visible      = true;
    $event->timeduration = 0;
    $created = calendar_event::create($event);

    /*
        $event = new calendar_event($properties);
        if ($event->update($properties)) {
    */
}


/**
 * Returns the correct link for the calendar event.
 * 
 * @return string complete url for the event
 */
function enrolmentreminder_generate_event_link() {
    $params = array('view' => 'day', 'cal_d' => date('j', $this->event->timestart),
        'cal_m' => date('n', $this->event->timestart), 'cal_y' => date('Y', $this->event->timestart));
    $calurl = new moodle_url('/calendar/view.php', $params);
    $calurl->set_anchor('event_'.$this->event->id);

    return $calurl->out(false);
}

function enrolmentreminder_processtemplate($string, array $params) {
    foreach($params as $name=>$param) {
         if ($param !== NULL) {
             if (is_array($param) or (is_object($param) && !($param instanceof lang_string))) {
                 $param = (array)$param;
                 $search = array();
                 $replace = array();
                 foreach ($param as $key=>$value) {
                     if (is_int($key)) {
                         // we do not support numeric keys - sorry!
                         continue;
                     }
                     if (is_array($value) or (is_object($value) && !($value instanceof lang_string))) {
                         // we support just string or lang_string as value
                         continue;
                     }
                     $search[]  = "{\$$name->".$key.'}';
                     $replace[] = (string)$value;
                 }
                 if ($search) {
                     $string = str_replace($search, $replace, $string);
                 }
             } else {
                 $string = str_replace("{\$$name}", (string)$param, $string);
             }
         }
    }
    return $string;
}


