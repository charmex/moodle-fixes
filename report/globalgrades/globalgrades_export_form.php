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
 * @package report
 * @subpackage globalgrades
 * @copyright 2013 Catalyst IT
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/grade/lib.php');

class globalgrades_export_form extends moodleform {

    function definition() {
        global $CFG;

        $mform =& $this->_form;
        if (isset($this->_customdata)) {  // hardcoding plugin names here is hacky
            $features = $this->_customdata;
        } else {
            $features = array();
        }

        $mform->addElement('header', 'options', get_string('options', 'grades'));

        $mform->addElement('advcheckbox', 'export_feedback', get_string('exportfeedback', 'grades'));
        $mform->setDefault('export_feedback', 0);

        $options = array('10' => 10, '20' => 20, '100' => 100, '1000' => 1000, '100000' => 100000);
        $mform->addElement('select', 'previewrows', get_string('previewrows', 'grades'), $options);
        $mform->setType('previewrows', PARAM_INT);

        $options = array(GRADE_DISPLAY_TYPE_REAL       => get_string('real', 'grades'),
                         GRADE_DISPLAY_TYPE_PERCENTAGE => get_string('percentage', 'grades'),
                         GRADE_DISPLAY_TYPE_LETTER     => get_string('letter', 'grades'));

        $mform->addElement('select', 'display', get_string('gradeexportdisplaytype', 'grades'), $options);
        $mform->setType('display', PARAM_INT);
        $mform->setDefault('display', $CFG->grade_export_displaytype);

        $options = array(0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5);
        $mform->addElement('select', 'decimals', get_string('gradeexportdecimalpoints', 'grades'), $options);
        $mform->setType('decimals', PARAM_INT);
        $mform->setDefault('decimals', $CFG->grade_export_decimalpoints);
        $mform->disabledIf('decimals', 'display', 'eq', GRADE_DISPLAY_TYPE_LETTER);

        $mform->addElement('header', 'gradeitems', get_string('gradeitemsinc', 'grades'));

        $needs_multiselect = false;
        foreach ($features['courseids'] as $courseid) {
            $switch = grade_get_setting($courseid, 'aggregationposition', $CFG->grade_aggregationposition);

            // Grab the grade_seq for this course
            $gseq = new grade_seq($courseid, $switch);

            if ($grade_items = $gseq->items) {
                foreach ($grade_items as $grade_item) {
                    $mform->addElement('advcheckbox', 'itemids['.$grade_item->id.']', $grade_item->get_name(), null, array('group' => 1));
                    $mform->setDefault('itemids['.$grade_item->id.']', 1);
                    $needs_multiselect = true;
                }
            }
        }

        if ($needs_multiselect) {
            $this->add_checkbox_controller(1, null, null, 1); // 1st argument is group name, 2nd is link text, 3rd is attributes and 4th is original value
        }

        $mform->addElement('hidden', 'id', implode(',', $features['courseids']));
        $mform->setType('id', PARAM_SEQUENCE);
        $mform->addElement('hidden', 'outputformat', $features['outputformat']);
        $mform->setType('outputformat', PARAM_ALPHA);

        $this->add_action_buttons(false, get_string('submit'));
    }
}

?>
