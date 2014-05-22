<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_enrolmentreminder_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014051501) {

        // Define field leadtime to be added to enrolmentreminder.
        $table = new xmldb_table('enrolmentreminder');
        $field = new xmldb_field('leadtime', XMLDB_TYPE_INTEGER, '7', null, XMLDB_NOTNULL, null, '259200', 'tmpltext');

        // Conditionally launch add field leadtime.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Enrolmentreminder savepoint reached.
        upgrade_plugin_savepoint(true, '2014051501', 'local', 'enrolmentreminder');
    }

    return true;
}

