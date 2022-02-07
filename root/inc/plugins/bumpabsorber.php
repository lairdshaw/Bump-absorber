<?php

/*
 * Bump Absorber, a plugin for MyBB 1.8.x.
 * Copyright (C) 2021-2022 Laird Shaw.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

if (defined('IN_ADMINCP')) {
	$plugins->add_hook('admin_tools_recount_rebuild_thread_counters', 'bumpabsorber_hookin__admin_tools_recount_rebuild_thread_counters');
} else {
	$plugins->add_hook('showthread_end'                    , 'bumpabsorber_hookin__showthread_end'                            );
	$plugins->add_hook('newthread_end'                     , 'bumpabsorber_hookin__newthread_end'                             );
	$plugins->add_hook('datahandler_post_insert_thread_end', 'bumpabsorber_hookin__datahandler_post_insert_thread_end'        );
	$plugins->add_hook('datahandler_post_insert_post'      , 'bumpabsorber_hookin__datahandler_post_insert_post'              );
	$plugins->add_hook('datahandler_post_insert_post_end'  , 'bumpabsorber_hookin__datahandler_post_insert_or_update_post_end');
	$plugins->add_hook('datahandler_post_update_end'       , 'bumpabsorber_hookin__datahandler_post_insert_or_update_post_end');
	$plugins->add_hook('moderation_start'                  , 'bumpabsorber_hookin__moderation_start'                          );
	$plugins->add_hook('class_moderation_open_threads'     , 'bumpabsorber_hookin__class_moderation_open_threads'             );
	$plugins->add_hook('editpost_end'                      , 'bumpabsorber_hookin__editpost_end'                              );
}

const c_ba_patches = array(
	array(
		'file' => 'inc/plugins/dvz_stream/streams/posts.php',
		'might_not_exist' => true,
		'from' => "    if (in_array('thread', \dvzStream\getCsvSettingValues('group_events_by'))) {",
		'to'   => "    if (in_array('thread', \dvzStream\getCsvSettingValues('group_events_by'))) {
/*Begin BmpAbs patch*/
        if (\$mybb->settings['bumpabsorber_forums'] == -1) {
            \$baWhere = 'p.dateline = t.lastpost';
        } else if (trim(\$mybb->settings['bumpabsorber_forums']) == '') {
            \$baWhere = 'p2.pid IS NULL';
        } else {
            \$baWhere = '(t.fid NOT IN ('.\$mybb->settings['bumpabsorber_forums'].') AND p2.pid IS NULL OR t.fid IN ('.\$mybb->settings['bumpabsorber_forums'].') AND p.dateline = t.lastpost)';
        }
/*End BmpAbs patch*/",
	),
	array(
		'file' => 'inc/plugins/dvz_stream/streams/posts.php',
		'might_not_exist' => true,
		'from' => '                " . $queryWhere . " AND p2.pid IS NULL',
		'to'   => '                " . $queryWhere . /*Remainder of line (after open quote) is a BmpAbs patch*/" AND (" . $baWhere . ")',
	),
	array(
		'file' => 'inc/plugins/dvz_stream/streams/posts.php',
		'might_not_exist' => true,
		'from' => '                " . $queryWhere . "
            ORDER BY p.pid DESC',
		'to'   => '                " . $queryWhere . /*Remainder of line (after open quote) is a BmpAbs patch*/" AND p.dateline <= t.lastpost
            ORDER BY p.pid DESC'
	),
	array(
		'file' => 'xmlhttp.php',
		'from' => "		if(\$thread['closed'] == 1)",
		'to'   => "		if(\$thread['closed'] == 1/*Begin BmpAbs patch*/ && !(function_exists('ba_can_edit_thread') && ba_can_edit_thread(\$thread, \$mybb->user['uid']))/*End BmpAbs patch*/)"
	),
	array(
		'file' => 'editpost.php',
		'from' => "			error(\$lang->redirect_threadclosed);",
		'to'   => "/*Begin BmpAbs patch*/
			if (!(function_exists('ba_can_edit_thread') && ba_can_edit_thread(\$thread, \$mybb->user['uid']))) {
/*End BmpAbs patch (other than additional tab on next line) */
				error(\$lang->redirect_threadclosed);
/*Begin BmpAbs patch*/
			}
/*End BmpAbs patch*/",
	),
	array(
		'file' => 'inc/functions_post.php',
		'from' => "\$thread['closed'] != 1 && ",
		'to'   => "/*Begin BmpAbs patch*/(/*End BmpAbs patch*/\$thread['closed'] != 1/*Begin BmpAbs patch*/ || function_exists('ba_can_edit_thread') && ba_can_edit_thread(\$thread, \$mybb->user['uid']))/*End BmpAbs patch*/ && ",
	),
	array(
		'file' => 'newreply.php',
		'from' => "		error(\$lang->redirect_threadclosed);",
		'to'   => "/*Begin BmpAbs patch*/
		if (!(function_exists('ba_can_edit_thread') && ba_can_edit_thread(\$thread, \$mybb->user['uid']))) {
/*End BmpAbs patch (other than additional tab on next line) */
			error(\$lang->redirect_threadclosed);
/*Begin BmpAbs patch*/
		}
/*End BmpAbs patch*/"
	),
	array(
		'file' => 'showthread.php',
		'from' => "	\$quickreply = '';
	if(\$forumpermissions['canpostreplys'] != 0 && \$mybb->user['suspendposting'] != 1 && (\$thread['closed'] != 1",
		'to'   => "	\$quickreply = '';
	if(\$forumpermissions['canpostreplys'] != 0 && \$mybb->user['suspendposting'] != 1 && (\$thread['closed'] != 1/*Begin BmpAbs patch*/ || function_exists('ba_can_edit_thread') && ba_can_edit_thread(\$thread, \$mybb->user['uid'])/*End BmpAbs patch*/",
	),
	array(
		'file' => 'inc/functions.php',
		'from' => "	\$lastpost['username'] = \$db->escape_string(\$lastpost['username']);
	\$firstpost['username'] = \$db->escape_string(\$firstpost['username']);

	\$update_array = array(",
		'to'   => "	\$lastpost['username'] = \$db->escape_string(\$lastpost['username']);
	\$firstpost['username'] = \$db->escape_string(\$firstpost['username']);

/*Begin BmpAbs patch*/
	if (function_exists('ba_can_bump_thread') && !ba_can_bump_thread(\$thread)) {
		\$lastpost['username'] = \$thread['lastposter'];
		\$lastpost['uid'] = \$thread['lastposteruid'];
		\$lastpost['dateline'] = \$thread['lastpost'];
	}
/*End BmpAbs patch*/

	\$update_array = array(",
	),
	array(
		'file' => 'inc/functions.php',
		'from' => "	\$lastpost['username'] = \$db->escape_string(\$lastpost['username']);

	\$update_array = array(",
		'to'   => "	\$lastpost['username'] = \$db->escape_string(\$lastpost['username']);

/*Begin BmpAbs patch*/
	if (function_exists('ba_can_bump_thread') && !ba_can_bump_thread(\$thread = get_thread(\$tid))) {
		\$lastpost['username'] = \$thread['lastposter'];
		\$lastpost['uid'] = \$thread['lastposteruid'];
		\$lastpost['dateline'] = \$thread['lastpost'];
	}
/*End BmpAbs patch*/

	\$update_array = array(",
	),
);

function bumpabsorber_info() {
	global $lang, $plugins_cache, $cache;

	$lang->load('bumpabsorber');

	$info = array(
		'name'          => $lang->bmp_name,
		'description'   => $lang->bmp_desc,
		'author'        => 'Laird Shaw',
		'authorsite'    => 'https://creativeandcritical.net/',
		'version'       => '1.0.0',
		'codename'      => 'bumpabsorber',
		'compatibility' => '18*'
	);

	if (empty($plugins_cache) || !is_array($plugins_cache)) {
		$plugins_cache = $cache->read('plugins');
	}
	$active_plugins = $plugins_cache['active'];
	$list_items = '';
	if ($active_plugins && $active_plugins['bumpabsorber']) {
		list($unwritable_files, $fpcfalse_files, $failedpatch_files) = ba_realise_missing_patches();
		if ($unwritable_files) {
			$info['description'] .= '<ul><li style="list-style-image: url(styles/default/images/icons/warning.png)"><span style="color: red;">'.$lang->sprintf($lang->bmp_unwritable, implode($lang->comma, $unwritable_files)).'</span></li></ul>'.PHP_EOL;
		}
		if ($fpcfalse_files) {
			$info['description'] .= '<ul><li style="list-style-image: url(styles/default/images/icons/warning.png)"><span style="color: red;">'.$lang->sprintf($lang->bmp_fpcfalse, implode($lang->comma, $fpcfalse_files)).'</span></li></ul>'.PHP_EOL;
		}
		if ($failedpatch_files) {
			$info['description'] .= '<ul><li style="list-style-image: url(styles/default/images/icons/warning.png)"><span style="color: red;">'.$lang->sprintf($lang->bmp_unpatchable, implode($lang->comma, $failedpatch_files)).'</span></li></ul>'.PHP_EOL;
		}
		if (!$unwritable_files && !$fpcfalse_files && !$failedpatch_files) {
			$patched_files = array();
			foreach (c_ba_patches as $patch) {
				if (!in_array($patch['file'], $patched_files)) {
					$patched_files[] = $patch['file'];
				}
			}
			$info['description'] .= '<ul><li style="list-style-image: url(styles/default/images/icons/success.png)"><span style="color: green;">'.$lang->sprintf($lang->bmp_all_patched, implode($lang->comma, $patched_files)).'</span></li></ul>'.PHP_EOL;
		}
	}

	return $info;
}

function bumpabsorber_install() {
	global $db, $lang;

	$res = $db->query('SELECT MAX(disporder) as max_disporder FROM '.TABLE_PREFIX.'settinggroups');
	$disporder = intval($db->fetch_field($res, 'max_disporder')) + 1;

	// Insert the plugin's settings group into the database.
	$setting_group = array(
		'name'         => 'bumpabsorber_settings',
		'title'        => $db->escape_string($lang->bmp_settings_title),
		'description'  => $db->escape_string($lang->bmp_settings_desc),
		'disporder'    => $disporder,
		'isdefault'    => 0
	);
	$db->insert_query('settinggroups', $setting_group);
	$gid = $db->insert_id();

	// Now insert each of its settings values into the database...
	$settings = array(
		'bumpabsorber_forums' => array(
			'title'       => $lang->bmp_setting_forums_title,
			'description' => $lang->bmp_setting_forums_desc,
			'optionscode' => 'forumselect',
			'value'       => '',
		),
		'bumpabsorber_bumpintervalhrs' => array(
			'title'       => $lang->bmp_setting_bumpinterval_title,
			'description' => $lang->bmp_setting_bumpinterval_desc,
			'optionscode' => "numeric\nmin=1",
			'value'       => '1'
		),
		'bumpabsorber_opclosable_forums' => array(
			'title'       => $lang->bmp_setting_opclosable_forums_title,
			'description' => $lang->bmp_setting_opclosable_forums_desc,
			'optionscode' => 'forumselect',
			'value'       => '',
		),
	);

	$disporder = 1;
	foreach ($settings as $name => $setting) {
		$insert_settings = array(
			'name'        => $db->escape_string($name),
			'title'       => $db->escape_string($setting['title']),
			'description' => $db->escape_string($setting['description']),
			'optionscode' => $db->escape_string($setting['optionscode']),
			'value'       => $db->escape_string($setting['value']),
			'disporder'   => $disporder,
			'gid'         => $gid,
			'isdefault'   => 0
		);
		$db->insert_query('settings', $insert_settings);
		$disporder++;
	}

	rebuild_settings();

	if (!$db->field_exists('ba_closed_by_author', 'threads')) {
		$db->add_column('threads', 'ba_closed_by_author', 'tinyint(1) NOT NULL DEFAULT 0');
	}
}

function bumpabsorber_uninstall() {
	global $db;

	$rebuild_settings = false;
	$query = $db->simple_select('settinggroups', 'gid', "name = 'bumpabsorber_settings'");
	while (($gid = $db->fetch_field($query, 'gid'))) {
		$db->delete_query('settinggroups', "gid='{$gid}'");
		$db->delete_query('settings', "gid='{$gid}'");
		$rebuild_settings = true;
	}
	if ($rebuild_settings) rebuild_settings();

	ba_revert_patches();

	if ($db->field_exists('ba_closed_by_author', 'threads')) {
		$db->drop_column('threads', 'ba_closed_by_author');
	}
}

function bumpabsorber_is_installed() {
	global $db;

	$query = $db->simple_select('settinggroups', 'gid', "name = 'bumpabsorber_settings'");

	return $db->fetch_field($query, 'gid') ? true : false;
}

function bumpabsorber_activate() {
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('editpost', '(\\{\\$postoptions\\})', '{$postoptions}
{$modoptions}'
	);
}

function bumpabsorber_deactivate() {
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('editpost', '(\\r?\\n\\{\\$modoptions\\})', '', 0);
}

/**
 * Signal via a global variable to ba_can_bump_thread() - which will be called
 * by one of the above patches to inc/functions.php - that the user is inserting
 * a post in an existing thread (potentially one under this plugin's remit).
 */
function bumpabsorber_hookin__datahandler_post_insert_post($postHandler) {
	global $g_ba_post_is_being_inserted;

	$g_ba_post_is_being_inserted = true;
}

function bumpabsorber_hookin__admin_tools_recount_rebuild_thread_counters($postHandler) {
	global $g_ba_thread_counters_are_being_rebuilt;

	$g_ba_thread_counters_are_being_rebuilt = true;
}

// Show the "Close Thread" checkbox when starting a thread in a forum stipulated
// in this plugin's settings.
function bumpabsorber_hookin__newthread_end() {
	global $modoptions, $bgcolor, $stickoption, $closeoption, $mybb, $templates, $lang, $fid;

	if (($mybb->settings['bumpabsorber_opclosable_forums'] == -1
	     ||
	     in_array($fid, explode(',', $mybb->settings['bumpabsorber_opclosable_forums']))
	    )
	    &&
	    (!is_moderator($fid)
	     ||
	     !is_moderator($fid, 'canopenclosethreads')
	    )
	   ) {
		if (!isset($closeoption)) {
			$closeoption = '';
		}
		if (!isset($modoptions)) {
			$modoptions = '';
		}
		if (!empty($mybb->input['previewpost'])) {
			$modopts = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);
			$closecheck = !empty($modopts['closethread']) ? 'checked="checked"' : '';
		}
		eval('$closeoption .= "'.$templates->get('newreply_modoptions_close').'";');
		eval('$modoptions = "'.$templates->get('newreply_modoptions').'";');
	}
}

// Show the "Close Thread" checkbox in the quick reply box when viewing a thread
// if the current user is the thread's author in a forum stipulated in this
// plugin's settings.
function bumpabsorber_hookin__showthread_end() {
	global $mybb, $templates, $lang, $theme, $moderation_notice, $tid, $reply_subject, $posthash, $last_pid, $page, $collapsedthead, $collapsedimg, $expaltext, $collapsed, $trow, $option_signature, $closeoption, $captcha, $thread, $quickreply;

	if (!empty($quickreply)
	    &&
	    ($mybb->settings['bumpabsorber_opclosable_forums'] == -1
	     ||
	     in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_opclosable_forums']))
	    )
	    &&
	    $mybb->user['uid'] == $thread['uid']
	    &&
	    ($thread['closed'] != 1 || $thread['ba_closed_by_author'] == 1)
	    &&
	    !is_moderator($thread['fid'], 'canopenclosethreads')
	   ) {
		if (!isset($closeoption)) {
			$closeoption = '';
		}
		$closelinkch = $thread['closed'] ? ' checked="checked"' : '';

		eval('$closeoption .= "'.$templates->get('showthread_quickreply_options_close').'";');
		eval('$quickreply = "'.$templates->get('showthread_quickreply').'";');
	}
}

// Process the "Close Thread" checkbox on thread creation in a forum stipulated in
// this plugin's settings by closing the thread, but only if this would not have already
// occurred in the data handler, which it would have if the thread's author is
// a moderator with the right to open and close threads.
function bumpabsorber_hookin__datahandler_post_insert_thread_end($postHandler) {
	global $mybb, $db, $lang;

	$thread = $postHandler->data;

	if (!$thread['savedraft']
	    &&
	    (!is_moderator($thread['fid'], '', $thread['uid'])
	     ||
	     !is_moderator($thread['fid'], 'canopenclosethreads', $thread['uid'])
	    )
	    &&
	    !empty($thread['modoptions']['closethread'])
	    &&
	    ($mybb->settings['bumpabsorber_opclosable_forums'] == -1
	     ||
	     in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_opclosable_forums']))
	    )
	   ) {
		$lang->load('moderation');

		$modlogdata['fid'] = $thread['fid'];
		$modlogdata['tid'] = $postHandler->tid;
		log_moderator_action($modlogdata, $lang->thread_closed);
		$db->update_query('threads', array('closed' => 1, 'ba_closed_by_author' => 1), "tid='{$postHandler->tid}'");
	}
}

// Process the "Close Thread" checkbox, on reply to a thread by its author in a
// forum stipulated in this plugin's settings, by closing the thread, but only if this
// would not have already occurred in the data handler, which it would have if
// the thread's author is a moderator with the right to open and close threads.
function bumpabsorber_hookin__datahandler_post_insert_or_update_post_end($postHandler) {
	global $mybb, $db, $lang;

	$post = $postHandler->data;
	$thread = get_thread($post['tid']);

	if ($mybb->settings['bumpabsorber_opclosable_forums'] == -1
	    ||
	    in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_opclosable_forums']))
	   ) {
		if ($mybb->user['uid'] == $thread['uid']
		    &&
		    !$post['savedraft']
		    &&
		    !is_moderator($post['fid'], 'canopenclosethreads', $post['uid'])
		   ) {
			$lang->load('datahandler_post');

			$modlogdata['fid'] = $thread['fid'];
			$modlogdata['tid'] = $thread['tid'];
			$modoptions = !empty($post['modoptions']) ? $post['modoptions'] : $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);

			if (!empty($modoptions['closethread']) && $thread['closed'] != 1) {
				log_moderator_action($modlogdata, $lang->thread_closed);
				$db->update_query('threads', array('closed' => 1, 'ba_closed_by_author' => 1), "tid='{$thread['tid']}'");
				$postHandler->return_values['closed'] = 1;
			} else if (empty($modoptions['closethread']) && $thread['closed'] == 1 && $thread['ba_closed_by_author'] == 1) {
				log_moderator_action($modlogdata, $lang->thread_opened);
				$db->update_query('threads', array('closed' => 0, 'ba_closed_by_author' => 0), "tid='{$thread['tid']}'");
				$postHandler->return_values['closed'] = 0;
			}
		}
	}

	if (!$post['savedraft'] && isset($post['modoptions']) && empty($modoptions['closethread']) && $thread['closed'] == 1 && is_moderator($post['fid'], 'canopenclosethreads', $post['uid'])) {
		$db->update_query('threads', array('ba_closed_by_author' => 0), "tid = {$thread['tid']}");
	}
}

// Checks whether the user with uid $uid can bump the thread $thread.
function ba_can_bump_thread($thread, $uid = false) {
	global $mybb, $db, $g_ba_post_is_being_inserted, $g_ba_thread_counters_are_being_rebuilt;

	static $cached_ret = -1;

	if ($cached_ret === -1) {
		if ($uid === false) {
			$uid = $mybb->user['uid'];
		}
		$time_since_last_bump = TIME_NOW - $thread['lastpost'];
		$required_wait = $mybb->settings['bumpabsorber_bumpintervalhrs'] * 3600;
		/* Can not bump if:
		 * we are in a ba forum
		 * AND
		 * ((a new post is being inserted
		 *   AND
		 *   (the poster is not the OP
		 *    OR
		 *    the bump interval has not expired
		 *   )
		 *  )
		 *  OR
		 *  (a new post is NOT being inserted
		 *   AND
		 *   we are NOT rebuilding the thread counters via Recount & Rebuild
		 *  )
		 * )
		 */
		$can_bump =
		  !(
		    ($mybb->settings['bumpabsorber_forums'] == -1
		     ||
		     in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_forums']))
		    )
		    &&
		    (
		     (!empty($g_ba_post_is_being_inserted)
		      &&
		      ($uid != $thread['uid']
		       ||
		       $time_since_last_bump < $required_wait
		      )
		     )
		     ||
		     (empty($g_ba_post_is_being_inserted)
		      &&
		      empty($g_ba_thread_counters_are_being_rebuilt)
		     )
		    )
		   );
		$cached_ret = $can_bump;
	} else	$can_bump = $cached_ret;

	return $can_bump;
}

// Not yet used via the user interface, but works if the appropriate URL is
// entered directly.
// e.g., https://your.forum/moderation.php?action=ba_toggle_own_thread_closed&tid=[tid]
function bumpabsorber_hookin__moderation_start() {
	global $mybb, $lang, $db;

	if ($mybb->get_input('action') == 'ba_toggle_own_thread_closed') {
		$lang->load('moderation');
		$lang->load('bumpabsorber');

		$tid = $mybb->get_input('tid', MyBB::INPUT_INT);

		if (!$tid || !($thread = get_thread($tid))) {
			error($lang->error_invalidthread, $lang->error);
		}
		$fid = $thread['fid'];
		if (!($mybb->settings['bumpabsorber_opclosable_forums'] == -1
		      ||
		      in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_opclosable_forums']))
		     )
		   ) {
			error($lang->bmp_err_no_close_right_in_forum, $lang->error);
		}
		if ($mybb->user['uid'] != $thread['uid']) {
			error($lang->bmp_err_no_close_right_not_author, $lang->error);
		}

		$modlogdata['tid'] = $tid;
		$modlogdata['fid'] = $fid;

		$moderation = new Moderation;
		if ($thread['visible'] == -1) {
			error($lang->error_thread_deleted, $lang->error);
		}

		if ($thread['closed'] == 1 && $thread['ba_closed_by_author'] == 1) {
			$openclose = $lang->opened;
			$redirect = $lang->redirect_openthread;
			$moderation->open_threads($tid);
		} else if ($thread['closed'] == 0) {
			$openclose = $lang->closed;
			$redirect = $lang->redirect_closethread;
			$db->update_query('threads', array('closed' => 1, 'ba_closed_by_author' => 1), "tid='{$tid}'");
		}
		$lang->mod_process = $lang->sprintf($lang->mod_process, $openclose);

		log_moderator_action($modlogdata, $lang->mod_process);

		moderation_redirect(get_thread_link($thread['tid']), $redirect);
	}
}

function ba_revert_patches() {
	$ids = array_keys(c_ba_patches);
	return ba_realise_or_revert_patches($ids, true);
}

function ba_realise_missing_patches() {
	$ids = ba_get_missing_patch_ids();
	return ba_realise_or_revert_patches($ids, false);
}

function ba_realise_or_revert_patches($ids, $revert = false) {
	$unwritable_files  = array();
	$fpcfalse_files    = array();
	$failedpatch_files = array();
	foreach ($ids as $id) {
		if (!file_exists(MYBB_ROOT.$entry['file'])) {
			continue;
		}
		$entry = c_ba_patches[$id];
		if (!is_writable(MYBB_ROOT.$entry['file'])) {
			if (!in_array(MYBB_ROOT.$entry['file'], $unwritable_files)) {
				$unwritable_files[] = $entry['file'];
			}
		} else {
			$from = $entry[$revert ? 'to'   : 'from'];
			$to   = $entry[$revert ? 'from' : 'to'  ];
			$res = ba_replace_in_file(MYBB_ROOT.$entry['file'], $from, $to);
			if ($res === false) {
				$fpcfalse_files[] = $entry['file'];
			} else if ($res === -1) {
				$failedpatch_files[] = $entry['file'];
			}
		}
	}

	return array(array_unique($unwritable_files), array_unique($fpcfalse_files), array_unique($failedpatch_files));
}

// Returns:
// true if the patch succeeded.
// false if the patch failed due to file_put_contents() returning false
// -1 if the patch seemed to succeed but was not present in the file upon checking for it
function ba_replace_in_file($file, $from, $to) {
	$contents = file_get_contents($file);
	$contents_new = str_replace($from, $to, $contents);
	if (file_put_contents($file, $contents_new) === false) {
		return false;
	}
	$contents_after = file_get_contents($file);

	return strpos($contents_after, $to) !== false ? true : -1;
}

function ba_get_missing_patch_ids() {
	$ret = array();
	foreach (c_ba_patches as $idx => $entry) {
		if (!empty($entry['might_not_exist']) && !file_exists(MYBB_ROOT.$entry['file'])) {
			continue;
		}
		$contents = file_get_contents(MYBB_ROOT.$entry['file']);
		if (strpos($contents, $entry['to']) === false) {
			$ret[] = $idx;
		}
	}

	return $ret;
}

function ba_can_edit_thread($thread, $uid = -1) {
	global $mybb;

	if ($uid == -1) {
		$uid = $mybb->user['uid'];
	}

	return $thread['ba_closed_by_author'] == 1 && $uid == $thread['uid'] && ($mybb->settings['bumpabsorber_opclosable_forums'] == -1 || in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_opclosable_forums'])));
}

function bumpabsorber_hookin__class_moderation_open_threads($tids) {
	global $db;

	$tid_list = implode(',', $tids);
	$db->update_query('threads', array('ba_closed_by_author' => 0), "tid IN ($tid_list)");
}

function bumpabsorber_hookin__editpost_end() {
	global $mybb, $lang, $templates, $thread, $modoptions, $fid, $bgcolor, $bgcolor2;

	$modoptions = '';
	if (($thread['closed'] == 0
	     ||
	     $thread['ba_closed_by_author'] == 1
	    )
	    &&
	    $mybb->user['uid'] == $thread['uid']
	    &&
	    ($mybb->settings['bumpabsorber_opclosable_forums'] == -1
	     ||
	     in_array($fid, explode(',', $mybb->settings['bumpabsorber_opclosable_forums']))
	    )
	   ) {
		$lang->load('newthread');

		if (isset($mybb->input['previewpost'])) {
			$modoptions = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);
			if (isset($modoptions['closethread']) && $modoptions['closethread'] == 1) {
				$closecheck = ' checked="checked"';
			} else	$closecheck = '';
		} else {
			$closecheck = $thread['closed'] ? ' checked="checked"' : '';
		}

		eval('$closeoption = "'.$templates->get('newreply_modoptions_close').'";');
		$stickoption = '';
		eval('$modoptions = "'.$templates->get('newreply_modoptions').'";');
		$bgcolor = 'trow1';
		$bgcolor2 = 'trow2';
	}
}
