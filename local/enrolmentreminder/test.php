<?php

require_once('../../config.php');
global $DB;

$event = array (
    'name' => 'Enrollment expires',
    'description' => 'Please complete the course before this date.',
    'courseid' => 2,
    'groupid' => 0,
    'userid' => 275,
    'instance' => 1136,
    'eventtype' => 'enrolmentexpires',
    'timestart' => time() + 3600;
    'visible' => 1,
    'timeduration' => 0,
    'eventrepeats' => 0,
    'format' => 1,
    'timemodified' => time()
);

$properties = (object) $event;
$id = $DB->insert_record('event', $properties);
echo "id: $id\n";




