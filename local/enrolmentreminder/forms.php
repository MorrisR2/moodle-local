<?php

global $CFG;
require_once("$CFG->libdir/formslib.php");
 
class enrolmentreminderadd_form extends moodleform {
    function definition() {
        global $CFG;
 
        $mform =& $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['reminderid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('textarea', 'tmpltext', 'Reminder text', 'wrap="virtual" rows="20" cols="50"');
        $mform->setType('tmpltext', PARAM_TEXT);
        $this->add_action_buttons(false);
    }
}


