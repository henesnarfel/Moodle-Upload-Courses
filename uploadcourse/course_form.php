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
 * Bulk course upload forms
 *
 * @package    tool
 * @subpackage uploadcourse
 * @copyright  2007 Dan Poltawski
 * @copyright  2012 James Henestofel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/formslib.php';


/**
 * Upload a file CVS file with course information.
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_uploadcourse_form1 extends moodleform {
    function definition () {
        $mform = $this->_form;

        $mform->addElement('header', 'settingsheader', get_string('upload'));

        $mform->addElement('filepicker', 'coursefile', get_string('file'));
        $mform->addRule('coursefile', null, 'required');

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploadcourse'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $textlib = textlib_get_instance();
        $choices = $textlib->get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploadcourse'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        $choices = array('10'=>10, '20'=>20, '100'=>100, '1000'=>1000, '100000'=>100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'tool_uploadcourse'), $choices);
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(false, get_string('uploadcourses', 'tool_uploadcourse'));
    }
}


/**
 * Specify course upload details
 *
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @copyright  2012 James Henestofel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_uploadcourse_form2 extends moodleform {
    function definition () {
        global $CFG, $USER;

        $mform   = $this->_form;
        $columns = $this->_customdata['columns'];
        $data    = $this->_customdata['data'];

        // I am the template user, why should it be the administrator? we have roles now, other ppl may use this script ;-)
        $templateuser = $USER;

        $courseconfig = get_config('moodlecourse');

        // upload settings and file
        $mform->addElement('header', 'settingsheader', get_string('settings'));

        $choices = array(CU_COURSE_ADDNEW     => get_string('cuoptype_addnew', 'tool_uploadcourse'),
                         CU_COURSE_ADDINC     => get_string('cuoptype_addinc', 'tool_uploadcourse'),
                         CU_COURSE_ADD_UPDATE => get_string('cuoptype_addupdate', 'tool_uploadcourse'),
                         CU_COURSE_UPDATE     => get_string('cuoptype_update', 'tool_uploadcourse'));
        $mform->addElement('select', 'cutype', get_string('cuoptype', 'tool_uploadcourse'), $choices);

        $choices = array(CU_UPDATE_NOCHANGES    => get_string('nochanges', 'tool_uploadcourse'),
                         CU_UPDATE_FILEOVERRIDE => get_string('cuupdatefromfile', 'tool_uploadcourse'),
                         CU_UPDATE_ALLOVERRIDE  => get_string('cuupdateall', 'tool_uploadcourse'),
                         CU_UPDATE_MISSING      => get_string('cuupdatemissing', 'tool_uploadcourse'));
        $mform->addElement('select', 'cuupdatetype', get_string('cuupdatetype', 'tool_uploadcourse'), $choices);
        $mform->setDefault('cuupdatetype', CU_UPDATE_NOCHANGES);
        $mform->disabledIf('cuupdatetype', 'cutype', 'eq', CU_COURSE_ADDNEW);
        $mform->disabledIf('cuupdatetype', 'cutype', 'eq', CU_COURSE_ADDINC);

        $mform->addElement('selectyesno', 'cuallowtemplate', get_string('importexisting', 'tool_uploadcourse'));
        $mform->setDefault('cuallowtemplate', 0);
        $mform->disabledIf('cuallowtemplate', 'cutype', 'eq', CU_COURSE_ADDNEW);
        $mform->disabledIf('cuallowtemplate', 'cutype', 'eq', CU_COURSE_ADDINC);

        $choices = array(CU_ADD_TO_EXISTING    => get_string('cuaddtoexisting', 'tool_uploadcourse'),
                         CU_OVERWRITE_EXISTING => get_string('cuoverwriteexisting', 'tool_uploadcourse'));
        $mform->addElement('select', 'cuimporttype', get_string('importtypeexisting', 'tool_uploadcourse'), $choices);
        $mform->setDefault('cuimporttype', CU_ADD_TO_EXISTING);
        $mform->disabledIf('cuimporttype', 'cuallowtemplate', 'eq', 0);
        $mform->disabledIf('cuimporttype', 'cutype', 'eq', CU_COURSE_ADDNEW);
        $mform->disabledIf('cuimporttype', 'cutype', 'eq', CU_COURSE_ADDINC);

        $mform->addElement('selectyesno', 'cuallowrenames', get_string('allowrenames', 'tool_uploadcourse'));
        $mform->setDefault('cuallowrenames', 0);
        $mform->disabledIf('cuallowrenames', 'cutype', 'eq', CU_COURSE_ADDNEW);
        $mform->disabledIf('cuallowrenames', 'cutype', 'eq', CU_COURSE_ADDINC);

        $mform->addElement('selectyesno', 'cuallowdeletes', get_string('allowdeletes', 'tool_uploadcourse'));
        $mform->setDefault('cuallowdeletes', 0);
        $mform->disabledIf('cuallowdeletes', 'cutype', 'eq', CU_COURSE_ADDNEW);
        $mform->disabledIf('cuallowdeletes', 'cutype', 'eq', CU_COURSE_ADDINC);

        // default values
        $mform->addElement('header', 'defaultheader', get_string('defaultvalues', 'tool_uploadcourse'));

        $mform->addElement('text', 'shortname', get_string('cushortnametemplate', 'tool_uploadcourse'), 'size="20"');
        $mform->addRule('shortname', get_string('requiredtemplate', 'tool_uploadcourse'), 'required', null, 'client');
        $mform->disabledIf('shortname', 'cutype', 'eq', CU_COURSE_ADD_UPDATE);
        $mform->disabledIf('shortname', 'cutype', 'eq', CU_COURSE_UPDATE);

        $mform->addElement('text','idnumber', get_string('idnumbercourse'),'maxlength="100"  size="10"');
        $mform->addHelpButton('idnumber', 'idnumbercourse');
        $mform->setType('idnumber', PARAM_RAW);

        $courseformats = get_plugin_list('format');
        $formcourseformats = array();
        foreach ($courseformats as $courseformat => $courseformatdir) {
            $formcourseformats[$courseformat] = get_string('pluginname', "format_$courseformat");
        }

        $mform->addElement('select', 'format', get_string('format'), $formcourseformats);
        $mform->addHelpButton('format', 'format');
        $mform->setDefault('format', $courseconfig->format);

        $sectionmenu = array();
        for ($i = 0; $i <= $courseconfig->maxsections; $i++) {
            $sectionmenu[$i] = "$i";
        }
        $mform->addElement('select', 'numsections', get_string('numberweeks'), $sectionmenu);
        $mform->setType('numsections', PARAM_INT);
        $mform->setDefault('numsections', $courseconfig->numsections);

        $mform->addElement('date_selector', 'startdate', get_string('startdate'));
        $mform->addHelpButton('startdate', 'startdate');
        $mform->setDefault('startdate', time() + 3600 * 24);

        $choices = array();
        $choices['0'] = get_string('hiddensectionscollapsed');
        $choices['1'] = get_string('hiddensectionsinvisible');
        $mform->addElement('select', 'hiddensections', get_string('hiddensections'), $choices);
        $mform->addHelpButton('hiddensections', 'hiddensections');
        $mform->setDefault('hiddensections', $courseconfig->hiddensections);

        $options = range(0, 10);
        $mform->addElement('select', 'newsitems', get_string('newsitemsnumber'), $options);
        $mform->addHelpButton('newsitems', 'newsitemsnumber');
        $mform->setDefault('newsitems', $courseconfig->newsitems);

        $mform->addElement('selectyesno', 'showgrades', get_string('showgrades'));
        $mform->addHelpButton('showgrades', 'showgrades');
        $mform->setDefault('showgrades', $courseconfig->showgrades);

        $mform->addElement('selectyesno', 'showreports', get_string('showreports'));
        $mform->addHelpButton('showreports', 'showreports');
        $mform->setDefault('showreports', $courseconfig->showreports);

        $choices = get_max_upload_sizes($CFG->maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maximumupload'), $choices);
        $mform->addHelpButton('maxbytes', 'maximumupload');
        $mform->setDefault('maxbytes', $courseconfig->maxbytes);

        if (!empty($CFG->allowcoursethemes)) {
            $themeobjects = get_list_of_themes();
            $themes=array();
            $themes[''] = get_string('forceno');
            foreach ($themeobjects as $key=>$theme) {
                if (empty($theme->hidefromselector)) {
                    $themes[$key] = get_string('pluginname', 'theme_'.$theme->name);
                }
            }
            $mform->addElement('select', 'theme', get_string('forcetheme'), $themes);
        }

        $choices = array();
        $choices[NOGROUPS] = get_string('groupsnone', 'group');
        $choices[SEPARATEGROUPS] = get_string('groupsseparate', 'group');
        $choices[VISIBLEGROUPS] = get_string('groupsvisible', 'group');
        $mform->addElement('select', 'groupmode', get_string('groupmode', 'group'), $choices);
        $mform->addHelpButton('groupmode', 'groupmode', 'group');
        $mform->setDefault('groupmode', $courseconfig->groupmode);

        $choices = array();
        $choices['0'] = get_string('no');
        $choices['1'] = get_string('yes');
        $mform->addElement('select', 'groupmodeforce', get_string('groupmodeforce', 'group'), $choices);
        $mform->addHelpButton('groupmodeforce', 'groupmodeforce', 'group');
        $mform->setDefault('groupmodeforce', $courseconfig->groupmodeforce);

        $choices = array();
        $choices['0'] = get_string('courseavailablenot');
        $choices['1'] = get_string('courseavailable');
        $mform->addElement('select', 'visible', get_string('availability'), $choices);
        $mform->addHelpButton('visible', 'availability');
        $mform->setDefault('visible', $courseconfig->visible);

        $languages=array();
        $languages[''] = get_string('forceno');
        $languages += get_string_manager()->get_list_of_translations();
        $mform->addElement('select', 'lang', get_string('forcelanguage'), $languages);
        $mform->setDefault('lang', $courseconfig->lang);

        if (completion_info::is_enabled_for_site()) {
            $mform->addElement('select', 'enablecompletion', get_string('completion','completion'),
                array(0=>get_string('completiondisabled','completion'), 1=>get_string('completionenabled','completion')));
            $mform->setDefault('enablecompletion', $courseconfig->enablecompletion);

            $mform->addElement('checkbox', 'completionstartonenrol', get_string('completionstartonenrol', 'completion'));
            $mform->setDefault('completionstartonenrol', $courseconfig->completionstartonenrol);
            $mform->disabledIf('completionstartonenrol', 'enablecompletion', 'eq', 0);
        } else {
            $mform->addElement('hidden', 'enablecompletion');
            $mform->setType('enablecompletion', PARAM_INT);
            $mform->setDefault('enablecompletion',0);

            $mform->addElement('hidden', 'completionstartonenrol');
            $mform->setType('completionstartonenrol', PARAM_INT);
            $mform->setDefault('completionstartonenrol',0);
        }

        // hidden fields
        $mform->addElement('hidden', 'summaryformat');
        $mform->setDefault('summaryformat', 1);
        $mform->setType('summaryformat', PARAM_INT);

        $mform->addElement('hidden', 'iid');
        $mform->setType('iid', PARAM_INT);

        $mform->addElement('hidden', 'previewrows');
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(true, get_string('uploadcourses', 'tool_uploadcourse'));

        $this->set_data($data);
    }

    /**
     * Form tweaks that depend on current data.
     */
    function definition_after_data() {
        $mform   = $this->_form;
        $columns = $this->_customdata['columns'];

        foreach ($columns as $column) {
            if ($mform->elementExists($column)) {
                $mform->removeElement($column);
            }
        }
    }

    /**
     * Server side validation.
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $columns = $this->_customdata['columns'];
        $optype  = $data['cutype'];

        // look for other required data
        if ($optype != CU_COURSE_UPDATE) {
            if (!in_array('fullname', $columns)) {
                $errors['cutype'] = get_string('missingfield', 'error', 'fullname');
            }

            if (!in_array('shortname', $columns)) {
                if (isset($errors['cutype'])) {
                    $errors['cutype'] = '';
                } else {
                    $errors['cutype'] = ' ';
                }
                $errors['cutype'] .= get_string('missingfield', 'error', 'shortname');
            }

            if (!in_array('category', $columns)) {
                if (isset($errors['cutype'])) {
                    $errors['cutype'] = '';
                } else {
                    $errors['cutype'] = ' ';
                }
                $errors['cutype'] .= get_string('missingfield', 'error', 'category');
            }
        }

        return $errors;
    }

    /**
     * Used to reformat the data from the editor component
     *
     * @return stdClass
     */
    function get_data() {
        $data = parent::get_data();

        if ($data !== null and isset($data->description)) {
            $data->descriptionformat = $data->description['format'];
            $data->description = $data->description['text'];
        }

        return $data;
    }
}
