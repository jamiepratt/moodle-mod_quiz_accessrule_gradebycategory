A plug in to add a setting to quizzes to allow the display of grades by question category
=========================================================================================

Operation
---------

This plug in will introduce :

### 1. a new quiz setting 'Show grade by question category' in the admin menu (on the site admin -> plug ins -> activity
module->quiz
 page). Here you can set the default for all new quizzes, whether to show grades by question category or not. This will be the
 default for all new quizzes you create.
### 2. a setting in the quiz settings at the bottom of the display section 'Show grade by question category'.
If you have just
installed this plug in then all existing quizzes will default to this setting being off.
### 3. extra average columns for each question category

These extra columns are :
* in downloadable teacher's 'Grades' (overview) report
* and student attempts summary table

They are shown if the setting for the quiz is on.

The columns show the percent of marks achieved for each question category in the quiz. For the purposes of calculating this
percentage grade the ammount of marks for each question in the quiz is ignored ie. you
 can assign in a quiz 2 marks to one question and 5 to another but this will not affect our average calculation. For each
 question the weight is equal ie. the maximum grade for each question is :
** normally 100%
** but a student might achieve a partially correct results somewhere between 0 and 100%
** Or in Certainty Based Marking a student might be awarded 200 % for a correct
answer or a negative grade for example.

And we calculate the average of this unweighted grade for all questions in a category.

We have made this a quiz access rule plug in as this was the only plug in that would allow us to add settings to the quiz form.

This quiz access rule was created by Jamie Pratt.

Compatability
-------------

It can be used with versions 2.3 of Moodle, or later.

Installation
------------

To install using git, type (or copy and paste) these commands in the root of your Moodle install :

    git clone git://github.com/jamiepratt/moodle-quizaccess_gradebycategory.git mod/quiz/accessrule/gradebycategory
    echo '/mod/quiz/accessrule/gradebycategory/' >> .git/info/exclude

Alternatively, download the zip from [this url](https://github.com/jamiepratt/moodle-quizaccess_gradebycategory/zipball/master)
unzip it into the mod/quiz/accessrule folder, and then rename the new
folder to gradebycategory.


### Changes to core code

A number of changes to core code are necessary for full functionality.

#### Adding default settings to admin menu

Add this line :

    require($CFG->dirroot.'/mod/quiz/accessrule/gradebycategory/settings.php');

To the very end of file /mod/quiz/settings.php

#### Adding category total columns to teacher's quiz overview ("Grades") report.

Around line 31 change :

    require_once($CFG->dirroot . '/mod/quiz/report/overview/overview_table.php');

to :

    require_once($CFG->dirroot . '/mod/quiz/accessrule/gradebycategory/overview_table_with_category_totals.php');

And at around line 71 change where it says :

    new quiz_overview_table

to :

    new quiz_overview_table_with_category_totals



#### Adding category total columns to details of past attempts displayed to students (modify your theme)

Make the two changes to the theme you are using. If you are not [using a child theme](http://docs.moodle.org/dev/Themes_2.2_how_to_clone_a_Moodle_2.2_theme) as the theme for your web site and keeping
your custom changes to the core or downloaded theme you are using in that,
then we would recommend you do that. Then these following changes would be applied to
your child theme.

##### Add a line to the end of your theme config.php

First search for '$THEME->rendererfactory' if there is no assignment to this property then simply add the following to the end of
 the file.

$THEME->rendererfactory = 'theme_overridden_renderer_factory';

If not then you probably already have a renderers.php file

##### Now Either

###### If you have no renderers.php file in the root of your theme directory then

* Just copy the file in this plug in folder /mod/quiz/accessrule/gradebycategory/copytotheme/renderers.php to your theme directory.
* Change where it says '{put your theme name in here}' to your theme name ie. the same name as the directory name you copied renderers
.php to.

###### If you do have a renderers.php file then

* just copy the contents of /mod/quiz/accessrule/gradebycategory/copytotheme/renderers.php to add it to the end of your theme
renderers.php file, note you don't need to copy the comments but the require and the class definition can be added to the end of
the file.
* Change where it says '{put your theme name in here}' to your theme name ie. the same name as the directory name you found
renderers
.php in.

### Install new db records

Once the code is installed you need to go to the Site administration -> Notifications page
to let the plugin install itself.
