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
 * Bulk course script from a comma separated file
 *
 * @package    tool
 * @subpackage uploadcourse
 * @copyright  2004 onwards Martin Dougiamas (http://dougiamas.com)
 * @copyright  2012 James Henestofel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/csvlib.class.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once('locallib.php');
require_once('course_form.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/moodle2/restore_plan_builder.class.php');

$iid         = optional_param('iid', '', PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);

@set_time_limit(60*60); // 1 hour should be enough
raise_memory_limit(MEMORY_HUGE);

require_login();
admin_externalpage_setup('tooluploadcourse');
require_capability('moodle/site:uploadcourses', get_context_instance(CONTEXT_SYSTEM));
require_capability('moodle/course:create', get_context_instance(CONTEXT_SYSTEM));
require_capability('moodle/course:update', get_context_instance(CONTEXT_SYSTEM));
require_capability('moodle/course:delete', get_context_instance(CONTEXT_SYSTEM));

$strcourserenamed             = get_string('courserenamed', 'tool_uploadcourse');
$strcoursenotrenamedexists    = get_string('coursenotrenamedexists', 'tool_uploadcourse');
$strcoursenotrenamedoff       = get_string('coursenotrenamedoff', 'tool_uploadcourse');
$strcoursenotrenamedmissing   = get_string('coursenotrenamedmissing', 'tool_uploadcourse');

$strcourseupdated             = get_string('courseupdated', 'tool_uploadcourse');
$strcoursenotupdated          = get_string('coursenotupdatederror', 'tool_uploadcourse');
$strcoursenotupdatednotexists = get_string('coursenotupdatednotexists', 'tool_uploadcourse');

$strcourseuptodate            = get_string('courseuptodate', 'tool_uploadcourse');

$strcourseadded               = get_string('newcourse');
$strcoursenotadded            = get_string('coursenotaddedregistered', 'tool_uploadcourse');
$strcoursenotaddederror       = get_string('coursenotaddederror', 'tool_uploadcourse');

$strcoursedeleted             = get_string('coursedeleted', 'tool_uploadcourse');
$strcoursenotdeletederror     = get_string('coursenotdeletederror', 'tool_uploadcourse');
$strcoursenotallowdeleteerror = get_string('coursenotallowdeleteerror', 'tool_uploadcourse');
$strcoursenotdeletedmissing   = get_string('coursenotdeletedmissing', 'tool_uploadcourse');
$strcoursenotdeletedoff       = get_string('coursenotdeletedoff', 'tool_uploadcourse');

$errorstr                   = get_string('error');

$returnurl = new moodle_url('/admin/tool/uploadcourse/index.php');

// array of all valid fields for validation
$STD_FIELDS = array('id', 'fullname', 'shortname', 'category', 'idnumber',
        'summary', 'summaryformat', 'format', 'showgrades', 'newsitems',
        'startdate', 'numsections', 'maxbytes', 'showreports', 'visible',
        'hiddensections', 'groupmode', 'groupmodeforce',
        'enablecompletion', 'completionstartonenrol', 'lang', 'theme',
        'oldshortname', // use when renaming course - this is the original shortname
        'template',        // Must make sure this exists if present
        'deleted',        // 1 means delete course
    );

if (empty($iid)) {
    $mform1 = new admin_uploadcourse_form1();

    if ($formdata = $mform1->get_data()) {
        $iid = csv_import_reader::get_new_iid('uploadcourse');
        $cir = new csv_import_reader($iid, 'uploadcourse');
        $content = $mform1->get_file_content('coursefile');

        $readcount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
        unset($content);

        if ($readcount === false) {
            print_error('csvloaderror', '', $returnurl);
        } else if ($readcount == 0) {
            print_error('csvemptyfile', 'error', $returnurl);
        }
        // test if columns ok
        $filecolumns = cu_validate_course_upload_columns($cir, $STD_FIELDS, $returnurl);
        // continue to form2

    } else {
        echo $OUTPUT->header();

        echo $OUTPUT->heading_with_help(get_string('uploadcourses', 'tool_uploadcourse'), 'uploadcourses', 'tool_uploadcourse');

        $mform1->display();
        echo $OUTPUT->footer();
        die;
    }
} else {
    $cir = new csv_import_reader($iid, 'uploadcourse');
    $filecolumns = cu_validate_course_upload_columns($cir, $STD_FIELDS, $returnurl);
}

$mform2 = new admin_uploadcourse_form2(null, array('columns'=>$filecolumns, 'data'=>array('iid'=>$iid, 'previewrows'=>$previewrows)));

// If a file has been uploaded, then process it
if ($formdata = $mform2->is_cancelled()) {
    $cir->cleanup(true);
    redirect($returnurl);

} else if ($formdata = $mform2->get_data()) {
    // Print the header
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('uploadcoursesresult', 'tool_uploadcourse'));

    $optype = $formdata->cutype; // Upload type

    $updatetype        = isset($formdata->cuupdatetype) ? $formdata->cuupdatetype : 0; // Existing details
    $allowtemplate     = (!empty($formdata->cuallowtemplate) and $optype != CU_COURSE_ADDNEW and $optype != CU_COURSE_ADDINC);
    $importtype        = (isset($formdata->cuimporttype) and $allowtemplate != 0) ? $formdata->cuimporttype : 0;
    $allowrenames      = (!empty($formdata->cuallowrenames) and $optype != CU_COURSE_ADDNEW and $optype != CU_COURSE_ADDINC);
    $allowdeletes      = (!empty($formdata->cuallowdeletes) and $optype != CU_COURSE_ADDNEW and $optype != CU_COURSE_ADDINC);

    // verification moved to two places: after upload and into form2
    $coursesnew      = 0;
    $coursesupdated  = 0;
    $coursesuptodate = 0; //not printed yet anywhere
    $courseerrors   = 0;
    $deletes       = 0;
    $deleteerrors  = 0;
    $renames       = 0;
    $renameerrors  = 0;
    $coursesskipped  = 0;
    $templateerrors = 0;

    // caches
    $ccache         = array(); // course cache - do not fetch all courses here, we  will not probably use them all anyway!

    // init csv import helper
    $cir->init();
    $linenum = 1; //column header is first line

    // init upload progress tracker
    $upt = new cu_progress_tracker();
    $upt->start(); // start table

    while ($line = $cir->next()) {
        $upt->flush();
        $linenum++;

        $upt->track('line', $linenum);

        $course = new stdClass();

        // add fields to course object
        foreach ($line as $keynum => $value) {
            if (!isset($filecolumns[$keynum])) {
                // this should not happen
                continue;
            }
            $key = $filecolumns[$keynum];

            $course->$key = $value;

            if (in_array($key, $upt->columns)) {
                // default value in progress tracking table, can be changed later
                $upt->track($key, s($value), 'normal');
            }
        }

        if ($optype == CU_COURSE_ADDNEW or $optype == CU_COURSE_ADDINC) {
            $error = false;
            if (!isset($course->shortname) or $course->shortname === '') {
                $upt->track('status', get_string('missingfield', 'error', 'shortname'), 'error');
                $upt->track('shortname', $errorstr, 'error');
                $error = true;
            }
            if (!isset($course->fullname) or $course->fullname === '') {
                $upt->track('status', get_string('missingfield', 'error', 'fullname'), 'error');
                $upt->track('fullname', $errorstr, 'error');
                $error = true;
            }
            if (!isset($course->category) or $course->category === '') {
                $upt->track('status', get_string('missingfield', 'error', 'category'), 'error');
                $upt->track('category', $errorstr, 'error');
                $error = true;
            }
            if ($error) {
                $courseerrors++;
                continue;
            }
        }

        // make sure we really have shortname
        if (empty($course->shortname)) {
            $upt->track('status', get_string('missingfield', 'error', 'shortname'), 'error');
            $upt->track('shortname', $errorstr, 'error');
            $courseerrors++;
            continue;
        } 

        if ($existingcourse = $DB->get_record('course', array('shortname'=>$course->shortname))) {
            $upt->track('id', $existingcourse->id, 'normal', false);
        }

        // check to make sure we have category and it does exist if adding or updating
        // both elseif's do same thing just trying to keep database hits down
        if (empty($course->category)) {
            $upt->track('status', get_string('missingfield', 'error', 'category'), 'error');
            $upt->track('category', $errorstr, 'error');
            $courseerrors++;
            continue;
        // new or incremented exisiting courses
        } elseif (($optype == CU_COURSE_ADDNEW and !$existingcourse) or $optype == CU_COURSE_ADDINC) {
            if ($DB->get_record('course_categories', array('id'=>$course->category)) === false) {
                $upt->track('status', get_string('invalidcategoryid', 'error', $course->category), 'error');
                $upt->track('category', $errorstr, 'error');
                $courseerrors++;
                continue;
            }
        // existing course we are overwriting from file or defaults
        } elseif ($existingcourse and ($updatetype == CU_UPDATE_ALLOVERRIDE or $updatetype == CU_UPDATE_FILEOVERRIDE)) {
            if ($DB->get_record('course_categories', array('id'=>$course->category)) === false) {
                $upt->track('status', get_string('invalidcategoryid', 'error', $course->category), 'error');
                $upt->track('category', $errorstr, 'error');
                $courseerrors++;
                continue;
            }
        }

        // find out if shortname incrementing required
        if ($existingcourse and $optype == CU_COURSE_ADDINC) {
            $course->shortname = cu_increment_shortname($course->shortname);
            $existingcourse = false;
        }

        $upt->track('shortname', s($course->shortname), 'normal', false);

        // add default values for remaining fields
        $formdefaults = array();
        foreach ($STD_FIELDS as $field) {
            if (isset($course->$field)) {
                continue;
            }
            // all validation moved to form2
            if (isset($formdata->$field)) {
                $course->$field = cu_process_template($formdata->$field, $course);
                $formdefaults[$field] = true;
                if (in_array($field, $upt->columns)) {
                    $upt->track($field, s($course->$field), 'normal');
                }
            }
        }

        // delete course
        if (!empty($course->deleted)) {
            if (!$allowdeletes) {
                $coursesskipped++;
                $upt->track('status', $strcoursenotdeletedoff, 'warning');
                continue;
            }
            if ($existingcourse) {
                if (can_delete_course($existingcourse->id)) {
                    if (delete_course($existingcourse, false)) {
                        $upt->track('status', $strcoursedeleted);
                        $deletes++;
                    } else {
                        $upt->track('status', $strcoursenotdeletederror, 'error');
                        $deleteerrors++;
                    }
                } else {
                    $upt->track('status', $strcoursenotallowdeleteerror, 'error');
                    $deleteerrors++;
                }
            } else {
                $upt->track('status', $strcoursenotdeletedmissing, 'error');
                $deleteerrors++;
            }
            continue;
        }
        // we do not need the deleted flag anymore
        unset($course->deleted);

        // renaming requested?
        if (!empty($course->oldshortname) ) {
            if (!$allowrenames) {
                $coursesskipped++;
                $upt->track('status', $strcoursenotrenamedoff, 'warning');
                continue;
            }

            if ($existingcourse) {
                $upt->track('status', $strcoursenotrenamedexists, 'error');
                $coursesskipped++;
                $renameerrors++;
                continue;
            }

            // no guessing when looking for old shortname, it must be exact match
            if ($oldcourse = $DB->get_record('course', array('shortname'=>$course->oldshortname))) {
                $upt->track('id', $oldcourse->id, 'normal', false);
                $DB->set_field('course', 'shortname', $course->shortname, array('id'=>$oldcourse->id));
                $upt->track('shortname', '', 'normal', false); // clear previous
                $upt->track('shortname', s($course->oldshortname).'-->'.s($course->shortname), 'info');
                $upt->track('status', $strcourserenamed);
                $renames++;
            } else {
                $upt->track('status', $strcoursenotrenamedmissing, 'error');
                $renameerrors++;
                $coursesskipped++;
                continue;
            }
            $existingcourse = $oldcourse;
            $existingcourse->shortname = $course->shortname;
        }

        // can we process with update or insert?
        $skip = false;
        switch ($optype) {
            case CU_COURSE_ADDNEW:
                if ($existingcourse) {
                    $coursesskipped++;
                    $upt->track('status', $strcoursenotadded, 'warning');
                    $skip = true;
                }
                break;

            case CU_COURSE_ADDINC:
                if ($existingcourse) {
                    //this should not happen!
                    $upt->track('status', $strcoursenotaddederror, 'error');
                    $courseerrors++;
                    $skip = true;
                }
                break;

            case CU_COURSE_ADD_UPDATE:
                break;

            case CU_COURSE_UPDATE:
                if (!$existingcourse) {
                    $coursesskipped++;
                    $upt->track('status', $strcoursenotupdatednotexists, 'warning');
                    $skip = true;
                }
                break;

            default:
                // unknown type
                $skip = true;
        }

        if ($skip) {
            continue;
        }

        if ($existingcourse) {
            $course->id = $existingcourse->id;

            $upt->track('shortname', html_writer::link(new moodle_url('/course/view.php', array('id'=>$existingcourse->id)), s($existingcourse->shortname), array("target" => "_blank")), 'normal', false);

            $existingcourse->timemodified = time();
            // do NOT mess with timecreated here!

            $doupdate = false;
            $dotemplate = false;

            if ($updatetype != CU_UPDATE_NOCHANGES) {
                $allcolumns = $STD_FIELDS;
                foreach ($allcolumns as $column) {
                    if ($column === 'shortname' or $column === 'category') {
                        // these can not be changed here
                        continue;
                    }
                    if (!property_exists($course, $column) or !property_exists($existingcourse, $column)) {
                        // this should never happen
                        continue;
                    }
                    if ($updatetype == CU_UPDATE_MISSING) {
                        if (!is_null($existingcourse->$column) and $existingcourse->$column !== '') {
                            continue;
                        }
                    } else if ($updatetype == CU_UPDATE_ALLOVERRIDE) {
                        // we override everything

                    } else if ($updatetype == CU_UPDATE_FILEOVERRIDE) {
                        if (!empty($formdefaults[$column])) {
                            // do not override with form defaults
                            continue;
                        }
                    }
                    if ($existingcourse->$column !== $course->$column) {
                        if (in_array($column, $upt->columns)) {
                            $upt->track($column, s($existingcourse->$column).'-->'.s($course->$column), 'info', false);
                        }
                        $existingcourse->$column = $course->$column;
                        $doupdate = true;
                    }
                    if ($allowtemplate == 1 and !empty($course->template)) {
                        $dotemplate = true;
                    }
                }
            }

            if ($doupdate || $dotemplate) {
                // we want only courses that were really updated
                
                if($doupdate) {
                    $DB->update_record('course', $existingcourse);
                    $upt->track('status', $strcourseupdated);
                    $coursesupdated++;
                }
                if ($dotemplate) {
                    $existingcourse->template = $course->template;
                    if (($result = process_template_course($existingcourse, $importtype)) == "") {
                        $upt->track('status', $strcourseupdated . " - from template");
                        $coursesupdated++;
                    } else {                        
                        $upt->track('status', $result, 'error');
                        $courseerrors++;
                    }
                }

                events_trigger('course_updated', $existingcourse);

            } else {
                // no course information changed
                $upt->track('status', $strcourseuptodate);
                $coursesuptodate++;
            }

        } else {
            // save the new course to the database
            $course->timemodified = time();
            $course->timecreated  = time();

            // create course
            $course->id = create_course($course)->id;
            $upt->track('shortname', html_writer::link(new moodle_url('/course/view.php', array('id'=>$course->id)), s($course->shortname)), 'normal', false);

            $upt->track('status', $strcourseadded);
            $upt->track('id', $course->id, 'normal', false);
            $coursesnew++;

            // make sure course context exists
            get_context_instance(CONTEXT_COURSE, $course->id);

            events_trigger('course_created', $course);
            if (!empty($course->template)) {
                if (($result = process_template_course($course, $importtype)) == "") {
                    $upt->track('status', $strcourseadded . " - from template", 'normal', false);
                } else {                        
                    $upt->track('status', $result, 'error');
                }
            }
        }
    }
    $upt->close(); // close table

    $cir->close();
    $cir->cleanup(true);

    echo $OUTPUT->box_start('boxwidthnarrow boxaligncenter generalbox', 'uploadresults');
    echo '<p>';
    if ($optype != CU_COURSE_UPDATE) {
        echo get_string('coursescreated', 'tool_uploadcourse').': '.$coursesnew.'<br />';
    }
    if ($optype == CU_COURSE_UPDATE or $optype == CU_COURSE_ADD_UPDATE) {
        echo get_string('coursesupdated', 'tool_uploadcourse').': '.$coursesupdated.'<br />';
    }
    if ($allowdeletes) {
        echo get_string('coursesdeleted', 'tool_uploadcourse').': '.$deletes.'<br />';
        echo get_string('deleteerrors', 'tool_uploadcourse').': '.$deleteerrors.'<br />';
    }
    if ($allowrenames) {
        echo get_string('coursesrenamed', 'tool_uploadcourse').': '.$renames.'<br />';
        echo get_string('renameerrors', 'tool_uploadcourse').': '.$renameerrors.'<br />';
    }
    if ($coursesskipped) {
        echo get_string('coursesskipped', 'tool_uploadcourse').': '.$coursesskipped.'<br />';
    }
    echo get_string('errors', 'tool_uploadcourse').': '.$courseerrors.'</p>';
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    die;
}

// Print the header
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('uploadcoursespreview', 'tool_uploadcourse'));

// NOTE: this is JUST csv processing preview, we must not prevent import from here if there is something in the file!!
//       this was intended for validation of csv formatting and encoding, not filtering the data!!!!
//       we definitely must not process the whole file!

// preview table data
$data = array();
$cir->init();
$linenum = 1; //column header is first line
while ($linenum <= $previewrows and $fields = $cir->next()) {
    $linenum++;
    $rowcols = array();
    $rowcols['line'] = $linenum;
    foreach($fields as $key => $field) {
        $rowcols[$filecolumns[$key]] = s($field);
    }
    $rowcols['status'] = array();

    if (!isset($rowcols['fullname'])) {
        $rowcols['status'][] = get_string('missingfullname');
    }

    if (isset($rowcols['shortname'])) {
        $stdshortname = clean_param($rowcols['shortname'], PARAM_RAW_TRIMMED);
        if ($courseid = $DB->get_field('course', 'id', array('shortname'=>$stdshortname))) {
            $rowcols['shortname'] = html_writer::link(new moodle_url('/course/view.php', array('id'=>$courseid)), $stdshortname, array('target'=>'_blank'));
        }
    } else {
        $rowcols['status'][] = get_string('missingshortname');
    }

    if (isset($rowcols['category'])) {
        $stdcategory = clean_param($rowcols['category'], PARAM_INT);
        if ($categoryid = $DB->get_field('course_categories', 'id', array('id'=>$stdcategory))) {
            $rowcols['category'] = html_writer::link(new moodle_url('/course/category.php', array('id'=>$categoryid)), $stdcategory, array('target'=>'_blank'));
        } else {
            $rowcols['status'][] = get_string('unknowncategory');
        }
    } else {
        $rowcols['status'][] = get_string('missingcategory', 'tool_uploadcourse');
    }

    if (isset($rowcols['template']) and $rowcols['template'] != "") {
        $stdtemplate = clean_param($rowcols['template'], PARAM_RAW_TRIMMED);
        if ($courseid = $DB->get_field('course', 'id', array('shortname'=>$stdtemplate))) {
            $rowcols['template'] = html_writer::link(new moodle_url('/course/view.php', array('id'=>$courseid)), $stdtemplate, array('target'=>'_blank'));
        } else {
            $rowcols['status'][] = "Template doesn't exist";
        }
    }

    $rowcols['status'] = implode('<br />', $rowcols['status']);
    $data[] = $rowcols;
}
if ($fields = $cir->next()) {
    $data[] = array_fill(0, count($fields) + 2, '...');
}
$cir->close();

$table = new html_table();
$table->id = "cupreview";
$table->attributes['class'] = 'generaltable';
$table->tablealign = 'center';
$table->summary = get_string('uploadcoursespreview', 'tool_uploadcourse');
$table->head = array();
$table->data = $data;

$table->head[] = get_string('cucsvline', 'tool_uploadcourse');
foreach ($filecolumns as $column) {
    $table->head[] = $column;
}
$table->head[] = get_string('status');

echo html_writer::tag('div', html_writer::table($table), array('class'=>'flexible-wrap'));

/// Print the form

$mform2->display();
echo $OUTPUT->footer();
die;

/**
 * Processes the template course for the course being created or modified.
 *
 * There is an issue that may be solved by MDL-26442 when updating a current course
 * with a template.  If the current course already has a quiz that was imported
 * from the template course and "Delete contents first" is selected the process
 * will error out and all contents are removed from the current course. If the process
 * is run again it runs fine and imports all the content from the template.
 *
 * @copyright  2012 James Henestofel
 * @param object $course The course that needs imported into from the template
 * @param int $importtype Add to course or delete course contents first
 * @return string Error message if any indicating what went wrong
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function process_template_course($course, $importtype) {
    global $CFG, $DB, $USER;
 
    // Check to make sure the Template course exists
    if(($tempcourse = $DB->get_record('course', array('shortname' => $course->template), '*', IGNORE_MISSING)) !== FALSE) {
        $courseid = $course->id;
        $context = get_context_instance(CONTEXT_COURSE, $courseid);

        $importcourseid = $tempcourse->id;
        $importcontext = get_context_instance(CONTEXT_COURSE, $importcourseid);
        if(has_capability('moodle/restore:restoretargetimport', $context) and has_capability('moodle/backup:backuptargetimport', $importcontext)) {
            $restoretarget = ($importtype == 0) ? backup::TARGET_CURRENT_ADDING : backup::TARGET_CURRENT_DELETING;

            $bc = new backup_controller(backup::TYPE_1COURSE, $importcourseid, backup::FORMAT_MOODLE,
			                backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id);
            $bc->execute_plan();

            $backupid = $bc->get_backupid();
            $bc->destroy();

            $tempdestination = $CFG->tempdir . '/backup/' . $backupid;

            // Restore the content into the newly created course
            $rc = new restore_controller($backupid, $courseid, backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id, $restoretarget);
            if (!$rc->execute_precheck()) {
	            $precheckresults = $rc->get_precheck_results();
	            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
	                fulldelete($tempdestination);
                    return "Error occured during template restore";
	            }
            } else {
                if ($restoretarget == backup::TARGET_CURRENT_DELETING || $restoretarget == backup::TARGET_EXISTING_DELETING) {
                    restore_dbops::delete_course_content($course->id);
                }
                // Execute the restore
                try {
                    $rc->execute_plan();
                } catch (Exception $e) {
                    return "An error occured during the restore process.<br />Most likely caused by already having a quiz that was imported from the template course you are using.<br />The contents of the course are gone.  You can run the upload script again on this course and it will process the template correctly.";
                }
            }
            $rc->destroy();
            // Delete the temp directory now
            fulldelete($tempdestination);

        } else {
            return "You do not have permission to use a template";
        }
    } else {        
        return "Template course does not exist";
    }
    return "";
}
