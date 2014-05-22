<?php

$handlers = array (
    'user_unenrolled' => array (
        'handlerfile'      => '/local/enrolmentreminder/lib.php',
        'handlerfunction'  => 'enrolmentreminder_unenrolled',
        'schedule'         => 'cron',
        'internal'         => 0
    ),
    'course_completed' => array (
        'handlerfile'      => '/local/enrolmentreminder/lib.php',
        'handlerfunction'  => 'enrolmentreminder_completed',
        'schedule'         => 'cron',
        'internal'         => 0
    ),
	'user_enrol_modified' => array (
        'handlerfile'      => '/local/enrolmentreminder/lib.php',
        'handlerfunction'  => 'enrolmentreminder_enrolmodified',
        'schedule'         => 'cron',
        'internal'         => 0
    ),
    'user_enrolled'  => array (
        'handlerfile'      => '/local/enrolmentreminder/lib.php',
        'handlerfunction'  => 'enrolmentreminder_enrolled',
        'schedule'         => 'cron',
        'internal'         => 0
    )

);


