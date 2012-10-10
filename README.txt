This adds a tool for the Admin to upload courses from a file.

It is based off the Upload Users Admin tool that has been retrofitted to work for courses.

It also has the ability to import content from a template course.

To install copy the uploadcourses folder into /moodle/admin/tool

You will also need to insert the line $string['site:uploadcourses'] = 'Upload courses from file'; into /moodle/lang/en/role.php

Hit the notifications page to install and you will see a link under Site Administration/Courses called Upload Courses

Available Column fields
fullname                    Required, STRING,   fullname of the course
shortname                   Required, STRING,   shortname of the course, if creating new or updating existing it must be unique unless using the increment setting
category                    Required, INT,      Id of the category
idnumber                    Optional, STRING,                                       Default-''
summary                     Optional, STRING,                                       Default-''
summaryformat               Optional, INT,      0/1/2/4 (AUTO/HTML/PLAIN/MARKDOWN)  Default-0
format                      Optional, STRING,   scorm/social/topics/weeks,          Default-course default settings
showgrades                  Optional, INT,      0/1,                                Default-course default settings
newsitems                   Optional, INT,      0-10,                               Default-course default settings
startdate                   Optional, TIMESTAMP, XXXXXXXXXX,                        Default-current date
numsections                 Optional, INT,      0-?(see course default settings),   Default-course default settings
maxbytes                    Optional, INT,                                          Default-course default settings
showreports                 Optional, INT,      0/1,                                Default-course default settings
visible                     Optional, INT,      0/1,                                Default-course default settings
hiddensections              Optional, INT,      0/1,                                Default-course default settings
groupmode                   Optional, INT,      0/1/2,                              Default-course default settings
groupmodeforce              Optional, INT,      0/1,                                Default-course default settings
enablecompletion            Optional, INT,      0/1,                                Default-course default settings
completionstartonenrol      Optional, INT,      0/1,                                Default-course default settings
lang                        Optional, STRING,   en/?(whatever langs are installed)  Default-course default settings
theme                       Optional, STRING,   Theme setting must allow courses to set themes for this to have an effect
oldshortname                Optional, STRING,   Used to rename the shortname, Must be unique and not already exist
template                    Optional, STRING,   Shortname of course to import content from
deleted                     Optional, INT,      0/1, 0-don't delete, 1-delete
