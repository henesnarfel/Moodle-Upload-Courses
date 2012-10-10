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
 * Bulk course registration functions
 *
 * @package    tool
 * @subpackage uploadcourse
 * @copyright  2004 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('CU_COURSE_ADDNEW', 0);
define('CU_COURSE_ADDINC', 1);
define('CU_COURSE_ADD_UPDATE', 2);
define('CU_COURSE_UPDATE', 3);

define('CU_UPDATE_NOCHANGES', 0);
define('CU_UPDATE_FILEOVERRIDE', 1);
define('CU_UPDATE_ALLOVERRIDE', 2);
define('CU_UPDATE_MISSING', 3);

define('CU_ADD_TO_EXISTING', 0);
define('CU_OVERWRITE_EXISTING', 1);

/**
 * Tracking of processed courses.
 *
 * This class prints course information into a html table.
 *
 * @package    core
 * @subpackage admin
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cu_progress_tracker {
    private $_row;
    public $columns = array('status', 'line', 'id', 'fullname', 'shortname', 'category', 'template');

    /**
     * Print table header.
     * @return void
     */
    public function start() {
        $ci = 0;
        echo '<table id="curesults" class="generaltable boxaligncenter flexible-wrap" summary="'.get_string('uploadcoursesresult', 'tool_uploadcourse').'">';
        echo '<tr class="heading r0">';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('status').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('cucsvline', 'tool_uploadcourse').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">ID</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('fullname').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('shortname').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('category').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('template', 'tool_uploadcourse').'</th>';
        echo '</tr>';
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     * @return void
     */
    public function flush() {
        if (empty($this->_row) or empty($this->_row['line']['normal'])) {
            // Nothing to print - each line has to have at least number
            $this->_row = array();
            foreach ($this->columns as $col) {
                $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        echo '<tr class="r'.$ri.'">';
        foreach ($this->_row as $key=>$field) {
            foreach ($field as $type=>$content) {
                if ($field[$type] !== '') {
                    $field[$type] = '<span class="cu'.$type.'">'.$field[$type].'</span>';
                } else {
                    unset($field[$type]);
                }
            }
            echo '<td class="cell c'.$ci++.'">';
            if (!empty($field)) {
                echo implode('<br />', $field);
            } else {
                echo '&nbsp;';
            }
            echo '</td>';
        }
        echo '</tr>';
        foreach ($this->columns as $col) {
            $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
        }
    }

    /**
     * Add tracking info
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) {
        if (empty($this->_row)) {
            $this->flush(); //init arrays
        }
        if (!in_array($col, $this->columns)) {
            debugging('Incorrect column:'.$col);
            return;
        }
        if ($merge) {
            if ($this->_row[$col][$level] != '') {
                $this->_row[$col][$level] .='<br />';
            }
            $this->_row[$col][$level] .= $msg;
        } else {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     * @return void
     */
    public function close() {
        $this->flush();
        echo '</table>';
    }
}

/**
 * Validation callback function - verified the column line of csv file.
 * Converts standard column names to lowercase.
 * @param csv_import_reader $cir
 * @param array $stdfields standard course fields
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of fields
 */
function cu_validate_course_upload_columns(csv_import_reader $cir, $stdfields, moodle_url $returnurl) {
    $columns = $cir->get_columns();

    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    }
    if (count($columns) < 2) {
        $cir->close();
        $cir->cleanup();
        print_error('csvfewcolumns', 'error', $returnurl);
    }

    $textlib = textlib_get_instance(); // fields may contain unicode chars

    // test columns
    $processed = array();
    foreach ($columns as $key=>$unused) {
        $field = $columns[$key];
        $lcfield = $textlib->strtolower($field);
        if (in_array($field, $stdfields) or in_array($lcfield, $stdfields)) {
            // standard fields are only lowercase
            $newfield = $lcfield;

        } else {
            $cir->close();
            $cir->cleanup();
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }
        if (in_array($newfield, $processed)) {
            $cir->close();
            $cir->cleanup();
            print_error('duplicatefieldname', 'error', $returnurl, $newfield);
        }
        $processed[$key] = $newfield;
    }

    return $processed;
}

/**
 * Increments shortname - increments trailing number or adds it if not present.
 * Verifies that the new shortname does not exist yet
 * @param string $shortname
 * @return incremented shortname which does not exist yet
 */
function cu_increment_shortname($shortname) {
    global $DB, $CFG;

    if (!preg_match_all('/(.*?)([0-9]+)$/', $shortname, $matches)) {
        $shortname = $shortname.'2';
    } else {
        $shortname = $matches[1][0].($matches[2][0]+1);
    }

    if ($DB->record_exists('course', array('shortname'=>$shortname))) {
        return cu_increment_shortname($shortname);
    } else {
        return $shortname;
    }
}

/**
 * Check if default field contains templates and apply them.
 * @param string template - potential tempalte string
 * @param object course object- we need fullname, shortname
 * @return string field value
 */
function cu_process_template($template, $course) {
    if (is_array($template)) {
        // hack for for support of text editors with format
        $t = $template['text'];
    } else {
        $t = $template;
    }
    if (strpos($t, '%') === false) {
        return $template;
    }

    $fullname  = isset($course->fullname)  ? $course->fullname  : '';
    $shortname = isset($course->shortname) ? $course->shortname : '';

    $callback = partial('cu_process_template_callback', $fullname, $shortname);

    $result = preg_replace_callback('/(?<!%)%([+-~])?(\d)*([fs])/', $callback, $t);

    if (is_null($result)) {
        return $template; //error during regex processing??
    }

    if (is_array($template)) {
        $template['text'] = $result;
        return $t;
    } else {
        return $result;
    }
}

/**
 * Internal callback function.
 */
function cu_process_template_callback($fullname, $shortname, $block) {
    $textlib = textlib_get_instance();

    switch ($block[3]) {
        case 'f':
            $repl = $fullname;
            break;
        case 's':
            $repl = $shortname;
            break;
        default:
            return $block[0];
    }

    switch ($block[1]) {
        case '+':
            $repl = $textlib->strtoupper($repl);
            break;
        case '-':
            $repl = $textlib->strtolower($repl);
            break;
        case '~':
            $repl = $textlib->strtotitle($repl);
            break;
    }

    if (!empty($block[2])) {
        $repl = $textlib->substr($repl, 0 , $block[2]);
    }

    return $repl;
}

