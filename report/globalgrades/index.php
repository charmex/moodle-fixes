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
 * @package    report
 * @subpackage globalgrades
 * @copyright  2013 Catalyst IT
 * @author     Eugene Venter <eugene@catalyst.net.nz>
 * @author     Francois Marier <francois@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Download grades for the activities of multiple courses all on one page/spreadsheet.
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once $CFG->dirroot.'/grade/lib.php';
require_once('globalgrades_export_form.php');

$outputformat = optional_param('outputformat', '', PARAM_ALPHA);

$params = array();
if (!empty($outputformat)) {
    $params['outputformat'] = $outputformat;
}
$PAGE->set_url('/report/globalgrades/index.php', $params);
$PAGE->set_pagelayout('report');

// Check permissions
require_login();
$systemcontext = context_system::instance();
require_capability('report/globalgrades:view', $systemcontext);

admin_externalpage_setup('reportglobalgrades');
echo $OUTPUT->header();

// Make a list of the clean course ids that were selected (if any)
$selectedcourseids = array();
if ($data = data_submitted()) {
    $rawids = array();

    // There are two ways to receive course IDs
    if (!empty($data->generatereport) && !empty($data->courses)) {  // course selection page
        $rawids = $data->courses;
    } else if (!empty($data->id)) {  // export options and grade selection page (using moodleform)
        $rawids = explode(',', $data->id);
    }

    // Clean the course ids
    foreach ($rawids as $rawcourseid) {
        if ($courseid = clean_param($rawcourseid, PARAM_INT)) {
            $selectedcourseids[] = $courseid;
        }
    }
}

$mform = new globalgrades_export_form(null, array('courseids' => $selectedcourseids,
                                                  'outputformat' => $outputformat));
if ($data = $mform->get_data()) {  // Export page
    list($sqlin, $sqlparams) = $DB->get_in_or_equal(explode(',', $data->id));
    if (!$courses = $DB->get_records_select('course', "id {$sqlin}", $sqlparams)) {
        print_error('nocourseid');
    }

    // do export permission checks on all courses
    foreach ($courses as $course) {
        $context = context_course::instance($course->id);

        require_capability('moodle/grade:export', $context);
        require_capability("gradeexport/{$data->outputformat}:view", $context);

        if (groups_get_course_groupmode($course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
            if (!groups_is_member($groupid, $USER->id)) {
                print_error('cannotaccessgroup', 'grades');
            }
        }
    }

    require_once("{$CFG->dirroot}/grade/export/{$outputformat}/grade_export_{$outputformat}.php");

    // Export preview form
    $classname = 'grade_export_'.$data->outputformat;
    if (!class_exists($classname)) {
        print_error('exportclassnotfound', 'report_globalgrades');
    }
    $export = new $classname($courses, 0, '', false, false, $data->display, $data->decimals);

    // print the grades on screen for feedbacks
    $export->process_form($data);
    $export->print_continue();
    $export->display_preview();
} else if (($data = data_submitted()) && !empty($data->generatereport)) {  // Export options page
    if (count($selectedcourseids) > 0) {
        echo $OUTPUT->box_start();
        echo '<div class="clearer"></div>';
        $mform->display();
        echo $OUTPUT->box_end();
    } else {
        error(get_string('notenoughparameters', 'report_globalgrades'), 'index.php');
    }
} else {  // Course selection page
    echo $OUTPUT->box_start();
    echo html_writer::start_tag('form', array('method' => 'post', 'action' => 'index.php'));

    print html_writer::tag('p', get_string('selectcourses', 'report_globalgrades'));

    echo print_course_list();

    echo html_writer::start_tag('p');
    echo html_writer::empty_tag('input', array('type' => 'button', 'value' => get_string('selectall')));  //todo onclick=\"checkall()\"
    echo html_writer::empty_tag('input', array('type' => 'button', 'value' => get_string('deselectall')));  //todo onclick=\"uncheckall()\"
    echo html_writer::end_tag('p');

    $exports = get_list_of_plugins('grade/export');
    $exportnames = array();
    if (!empty($exports)) {
        foreach ($exports as $plugin) {
            $exportnames[$plugin] = get_string('pluginname', 'gradeexport_'.$plugin);
        }
        asort($exportnames);
    }
    echo html_writer::tag('p', get_string('outputformat', 'report_globalgrades').' '.
        html_writer::select($exportnames, 'outputformat', 'xls'));

    echo html_writer::start_tag('p');
    echo html_writer::empty_tag('input',
        array('type' => 'submit', 'value' => get_string('generatereport', 'report_globalgrades'), 'name' => 'generatereport'));

    echo html_writer::end_tag('form');
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();

function print_course_list() {
    global $PAGE, $OUTPUT;

    $nojs = true;  // todo determine nojs
    if (!$nojs) {
        $courserenderer = $PAGE->get_renderer('core', 'course');
        return $courserenderer->frontpage_combo_list();  // todo - adjust this to add checkboxes and grades icons OR add it with js afterwards ;)
        // todo: wait until MDL-38661 is integrated as this will be scalable
        // also see https://moodle.org/mod/forum/discuss.php?d=228586#p1014306
    }

    // js not enabled - return non-js version
    $catseparator = '<~~~>';  // something unique ;)
    $catlist = coursecat::make_categories_list('', 0, $catseparator);

    $content = '';
    foreach ($catlist as $catid => $catpath) {
        $category = coursecat::get($catid);
        $indents = substr_count($catpath, $catseparator);
        $catspacer = $OUTPUT->spacer(array('width' => $indents*20));

        $catclass = 'course-tree-category';
        if ($category->coursecount > 0 || $category->has_children()) {
            $catclass = 'course-tree-category-expandable';
        }
        $content .= $OUTPUT->container_start($catclass);
        $content .= $catspacer . html_writer::tag('strong', format_string($category->name)) .'<br>';
        if ($courses = $category->get_courses()) {
            $coursespacer = $OUTPUT->spacer(array('width' => ($indents*20)+20));

        $content .= $OUTPUT->container_start('course-tree-courses');
            foreach ($courses as $course) {
                $content .= $OUTPUT->container_start();

                $content .= $coursespacer;

                $content .= html_writer::empty_tag('input', array('type' => 'checkbox', 'name' => 'courses[]',
                    'id' => "course{$course->id}", 'value' => $course->id));

                $content .= $course->fullname;

                $gradesurl = new moodle_url('/grade/report/grader/index.php', array('id' => $course->id));
                $content .= $OUTPUT->action_icon($gradesurl, new pix_icon('i/grades', get_string('grades')));

                $content .= $OUTPUT->container_end();
            }
            $content .= $OUTPUT->container_end();  // course-tree-courses
        }
        $content .= $OUTPUT->container_end();  // course-tree-category
    }

    return $content;
}

?>
