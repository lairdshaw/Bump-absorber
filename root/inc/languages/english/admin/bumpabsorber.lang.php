<?php

$l['bmp_name'] = 'Bump Absorber';
$l['bmp_desc'] = 'Inhibits thread bumps (by new posts) in stipulated forums: only replies by the thread\'s author bump the thread, and only after the expiry of a stipulated bump interval (since the last bumping post, counting the first post as a bumping post). Replies by other members never bump threads in the stipulated forums.';

$l['bmp_settings_title'] = 'Bump Absorber Settings';
$l['bmp_settings_desc' ] = 'Settings for the Bump Absorber plugin.';

$l['bmp_setting_forums_title'] = 'Enabled forums';
$l['bmp_setting_forums_desc' ] = 'Select the forums for which bump absorption should be enabled.';

$l['bmp_setting_bumpinterval_title'] = 'Bump interval (in hours)';
$l['bmp_setting_bumpinterval_desc' ] = 'Set the number of hours that must elapse since the last bumping post to a thread in the enabled forums before a new reply by the thread\'s author will bump that thread.';

$l['bmp_setting_showreallastpost_title'] = 'Show real last post in forum display and search results?';
$l['bmp_setting_showreallastpost_desc' ] = 'This toggle affects which post\'s author and date-time to show in the "Last Post" column of a forum listing page in a forum enabled in the setting above, and for search result listings showing threads from a forum enabled in the setting above. Choose "No" to show the details of the last bumping post. Choose "Yes" to show the details of the actual last post even if it didn\'t bump the thread. (Note: this does not affect thread order when ordering by Last Post; regardless of the value of this setting, threads will be ordered by date of last bump).';

$l['bmp_all_patched'] = 'All necessary patches have automatically been applied to the following file(s) (where they actually exist): {1}. To auto-revert them, uninstall this plugin.';
$l['bmp_unwritable' ] = 'The following file(s) is/are not writable by your web server, and patches could not be auto-applied to it/them: {1}. Please grant your web server write permissions on that/those file(s). ';
$l['bmp_fpcfalse'   ] = 'Whilst the following files(s) seem(s) to be writable by your web server, a return of false was obtained when trying to save it/them: {1}. Please ensure that your web server can write to that/those file(s). ';
$l['bmp_unpatchable'] = 'Whilst the following file(s) is/are writable by your web server, not all of the patch(es) auto-applied to them succeeded: {1}. Please check that all of the "from" fields of the patch(es) for that/those file(s) has a match in the file(s), and adjust as necessary. ';