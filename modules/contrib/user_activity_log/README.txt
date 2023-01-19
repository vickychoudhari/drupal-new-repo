--INTRODUCTION--

This module provide the block which contains current login user activity,
   like total no. of created nodes, total no. of comments,
   recent 3 created nodes and recent 3 comments.

-- SUMMARY --

For a full description of the module, visit the project page:
  https://www.drupal.org/project/user_activity_log

To submit bug reports and feature suggestions, or to track changes:
  https://www.drupal.org/project/issues/user_activity_log


-- REQUIREMENTS --

This module requires node and comments module.


-- INSTALLATION --

* Install as usual, see https://www.drupal.org/project/user
_activity_log for further information.


-- CONFIGURATION --

* Configure user-activity-log listing block in in Administration >> S
tructure >> Blocks:

   Go to admin/structure/block and
   enable the "user activity log" in any of your theme regions.

-- CUSTOM TEMPLATE --
* Override user-activity-log template
  - Copy file user-activity-log-tpl.html.twig to your theme folder.
  - Copy file list-all-user-activity-item.html.twig to your theme folder.
  - Clear caches.
