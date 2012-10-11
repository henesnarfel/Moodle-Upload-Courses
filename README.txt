This adds a tool for the Admin to upload courses from a file.

It is based off the Upload Users Admin tool that has been retrofitted to work for courses.

The templating is based off the Import content functionality within courses.

It also has the ability to import content from a template course.

* Using a template for an existing course:
1. Must have "Existing course details" set to something besides "No changes"
2. Must set "Allow template for existing course" to "yes"
3. Choose whether to "add to existing course" or to "delete contents first"

* NOTE: There is an issue that may be solved by MDL-26442 when updating an existing course
* with a template.  If the current course already has a quiz that was imported
* from the template course and "Delete contents first" is selected the process
* will error out and all contents are removed from the existing course. If the process
* is run again on that existing course it runs fine and imports all the content 
* from the template course.

* NOTE: Defaults are only used if they don't exist in the file or for updating existing and you don't have it in the file and you select some form of default option for Existing course details

* Templating for fields
See http://docs.moodle.org/23/en/Upload_users#Templates for how the templating works
The available codes are as follows:
* %f - will be replaced by fullname
* %s - will be replaced by shortname
* %i - will be replaced by idnumber

* INSTALL:
copy the uploadcourses folder into /moodle/admin/tool

You will also need to insert the line $string['site:uploadcourses'] = 'Upload courses from file'; into /moodle/lang/en/role.php

Hit the notifications page to install and you will see a link under Site Administration/Courses called Upload Courses

* AVAILABLE COLUMN FIELDS:
fullname                    Required, STRING,   fullname of the course
shortname                   Required, STRING,   shortname of the course, if creating new or updating existing it must be unique unless using the increment setting
category                    Required, INT,      Id of the category
idnumber                    Optional, STRING,   ,                                   Default-''
summary                     Optional, STRING,   ,                                   Default-''
summaryformat               Optional, INT,      0/1/2/4 (AUTO/HTML/PLAIN/MARKDOWN), Default-0
format                      Optional, STRING,   scorm/social/topics/weeks,          Default-course default settings, Depending on version you may have more options. See http://docs.moodle.org/en/Course_formats
showgrades                  Optional, INT,      0/1,                                Default-course default settings
newsitems                   Optional, INT,      0-10,                               Default-course default settings
startdate                   Optional, TIMESTAMP, XXXXXXXXXX,                        Default-current date
numsections                 Optional, INT,      0-?(see course default settings),   Default-course default settings
maxbytes                    Optional, INT,      ,                                   Default-course default settings
showreports                 Optional, INT,      0/1,                                Default-course default settings
visible                     Optional, INT,      0/1,                                Default-course default settings
hiddensections              Optional, INT,      0/1,                                Default-course default settings
groupmode                   Optional, INT,      0/1/2,                              Default-course default settings
groupmodeforce              Optional, INT,      0/1,                                Default-course default settings
enablecompletion            Optional, INT,      0/1,                                Default-course default settings
completionstartonenrol      Optional, INT,      0/1,                                Default-course default settings
lang                        Optional, STRING,   en/?(whatever langs are installed), Default-course default settings
theme                       Optional, STRING,   Theme setting must allow courses to set themes for this to have an effect
oldshortname                Optional, STRING,   Used to rename the shortname. Must be the shortname of the course you are changing. The shortname column will contain the new name. Must choose to allow renames also.
template                    Optional, STRING,   Shortname of course to import content from
deleted                     Optional, INT,      0/1, 0-don't delete, 1-delete
