<?php

// Disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
	die('Direct access to this file is not allowed.');
}

if (!defined('IN_ADMINCP')) {
	$plugins->add_hook('showthread_end'                    , 'bumpabsorber_hookin__showthread_end'                    );
	$plugins->add_hook('newthread_end'                     , 'bumpabsorber_hookin__newthread_end'                     );
	$plugins->add_hook('datahandler_post_insert_thread_end', 'bumpabsorber_hookin__datahandler_post_insert_thread_end');
	$plugins->add_hook('datahandler_post_insert_post_end'  , 'bumpabsorber_hookin__datahandler_post_insert_post_end'  );
	$plugins->add_hook('moderation_start'                  , 'bumpabsorber_hookin__moderation_start'                  );
	$plugins->add_hook('datahandler_post_validate_post'    , 'bumpabsorber_hooking__datahandler_post_validate_post'   );
}

const c_ba_patches = array(
	array(
		'file' => 'inc/plugins/dvz_stream/streams/posts.php',
		'from' => "    if (in_array('thread', \dvzStream\getCsvSettingValues('group_events_by'))) {",
		'to'   => "    if (in_array('thread', \dvzStream\getCsvSettingValues('group_events_by'))) {
        if (\$mybb->settings['bumpabsorber_forums'] == -1) {
            \$baWhere = 'p.dateline = t.lastpost';
        } else if (trim(\$mybb->settings['bumpabsorber_forums']) == '') {
            \$baWhere = 'p2.pid IS NULL';
        } else {
            \$baWhere = '(t.fid NOT IN ('.\$mybb->settings['bumpabsorber_forums'].') AND p2.pid IS NULL OR t.fid IN ('.\$mybb->settings['bumpabsorber_forums'].') AND p.dateline = t.lastpost)';
        }",
	),
	array(
		'file' => 'inc/plugins/dvz_stream/streams/posts.php',
		'from' => '                " . $queryWhere . " AND p2.pid IS NULL',
		'to'   => '                " . $queryWhere . " AND (" . $baWhere . ")',
	),
	array(
		'file' => 'inc/plugins/dvz_stream/streams/posts.php',
		'from' => '                " . $queryWhere . "
            ORDER BY p.pid DESC',
		'to'   => '                " . $queryWhere . " AND p.dateline <= t.lastpost
            ORDER BY p.pid DESC'
	),
	array(
		'file' => 'xmlhttp.php',
		'from' => "		if(\$thread['closed'] == 1)",
		'to'   => "		if(\$thread['closed'] == 1 && !(function_exists('ba_can_edit_thread') && ba_can_edit_thread(\$thread, \$mybb->user['uid'])))"
	),
	array(
		'file' => 'editpost.php',
		'from' => "	if(!is_moderator(\$fid, \"caneditposts\"))
	{
		if(\$thread['closed'] == 1)",
		'to'   => "	if(!is_moderator(\$fid, \"caneditposts\"))
	{
		if(\$thread['closed'] == 1 && !(function_exists('ba_can_edit_thread') && ba_can_edit_thread(\$thread, \$mybb->user['uid'])))"
	),
	array(
		'file' => 'inc/functions_post.php',
		'from' => "\$thread['closed'] != 1 && ",
		'to'   => "(\$thread['closed'] != 1 || function_exists('ba_can_edit_thread') && ba_can_edit_thread(\$thread, \$mybb->user['uid'])) && ",
	),
	array(
		'file' => 'inc/datahandlers/post.php',
		'from' => "					\$modoptions_update['closed'] = \$closed = 0;",
		'to'   => "					\$modoptions_update['closed'] = \$closed = 0;
					\$modoptions_update['ba_closed_by_author'] = 0;",
	),
);

function bumpabsorber_info() {
	global $lang, $plugins_cache;

	$lang->load('bumpabsorber');

	$info = array(
		'name'          => $lang->bmp_name,
		'description'   => $lang->bmp_desc,
		'author'        => 'Laird Shaw',
		'authorsite'    => 'https://creativeandcritical.net/',
		'version'       => '0.0.4',
		'codename'      => 'bumpabsorber',
		'compatibility' => '18*'
	);

	if (empty($plugins_cache) || !is_array($plugins_cache)) {
		$plugins_cache = $cache->read('plugins');
	}
	$active_plugins = $plugins_cache['active'];
	$list_items = '';
	if ($active_plugins && $active_plugins['bumpabsorber']) {
		$unwritable_files = ba_realise_missing_patches();
		if ($unwritable_files) {
			$info['description'] .= '<ul><li style="list-style-image: url(styles/default/images/icons/warning.png)"><span style="color: red;">'.$lang->sprintf($lang->bmp_unwritable, implode($lang->comma, $unwritable_files)).'</span></li></ul>'.PHP_EOL;
		} else {
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
			'value'       => '-1',
		),
		'bumpabsorber_bumpintervalhrs' => array(
			'title'       => $lang->bmp_setting_bumpinterval_title,
			'description' => $lang->bmp_setting_bumpinterval_desc,
			'optionscode' => "numeric\nmin=1",
			'value'       => '1'
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

// Store the lastpost data for the thread/forum in a global variable if
// necessary, so that we can restore it when the post datahandler updates it.
function bumpabsorber_hooking__datahandler_post_validate_post($postHandler) {
	global $g_ba_last_arr, $mybb, $db, $plugins;

	if (($thread = get_thread($postHandler->data['tid']))
	    &&
	    ($mybb->settings['bumpabsorber_forums'] == -1
	     ||
	     in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_forums']))
	    )
	    &&
	    !ba_can_bump_thread($thread)
	   ) {
		$query = $db->simple_select('forums', '*', "fid={$thread['fid']}");
		$forum = $db->fetch_array($query);
		$g_ba_last_arr = array(
			'thread_lastpost'        => $thread['lastpost'       ],
			'thread_lastposter'      => $thread['lastposter'     ],
			'thread_lastposteruid'   => $thread['lastposteruid'  ],
			'forum_lastpost'         => $forum ['lastpost'       ],
			'forum_lastposter'       => $forum ['lastposter'     ],
			'forum_lastposteruid'    => $forum ['lastposteruid'  ],
			'forum_lastposttid'      => $forum ['lastposttid'    ],
			'forum_lastpostsubject'  => $forum ['lastpostsubject'],
		);
	}
}

// Show the "Close Thread" checkbox when starting a thread in a forum applicable
// to this plugin.
function bumpabsorber_hookin__newthread_end() {
	global $modoptions, $bgcolor, $stickoption, $closeoption, $mybb, $templates, $lang, $fid;

	if (($mybb->settings['bumpabsorber_forums'] == -1
	     ||
	     in_array($fid, explode(',', $mybb->settings['bumpabsorber_forums']))
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
		eval("\$closeoption .= \"".$templates->get("newreply_modoptions_close")."\";");
		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
	}
}

// Show the "Close Thread" checkbox in the quick reply box when viewing a thread
// if the current user is the thread's author in a forum applicable to this
// plugin, but only if the thread is not already closed - we don't permit a
// thread author to open the thread because s/he might not have been the one to
// close it in the first place - it might have been done by a mod, and tracking
// whether or not the thread's author *did* close it involves more effort than
// we initially wanted to invest in this plugin.
//
// The check for a closed thread is probably unnecessary though given that the
// Quick Reply box, which includes that "Close Thread" checkbox, is suppressed
// for ordinary members when the thread is closed anyway.
function bumpabsorber_hookin__showthread_end() {
	global $mybb, $templates, $lang, $theme, $moderation_notice, $tid, $reply_subject, $posthash, $last_pid, $page, $collapsedthead, $collapsedimg, $expaltext, $collapsed, $trow, $option_signature, $closeoption, $captcha, $thread, $quickreply;

	if (!empty($quickreply)
	    &&
	    ($mybb->settings['bumpabsorber_forums'] == -1
	     ||
	     in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_forums']))
	    )
	    &&
	    $mybb->user['uid'] == $thread['uid']
	    &&
	    $thread['closed'] != '1'
	    &&
	    (!$ismod || !is_moderator($thread['fid'], 'canopenclosethreads'))
	   ) {
		if (!isset($closeoption)) {
			$closeoption = '';
		}
		$closelinkch = '';

		eval("\$closeoption .= \"".$templates->get("showthread_quickreply_options_close")."\";");
		eval("\$quickreply = \"".$templates->get("showthread_quickreply")."\";");
	}
}

// Process the "Close Thread" checkbox on thread creation in a forum applicable
// to this plugin by closing the thread, but only if this would not have already
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
	    ($mybb->settings['bumpabsorber_forums'] == -1
	     ||
	     in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_forums']))
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
// forum applicable to this plugin, by closing the thread, but only if this
// would not have already occurred in the data handler, which it would have if
// the thread's author is a moderator with the right to open and close threads.
// Do not, however, process an unchecked checkbox, i.e., do not allow thread
// authors to open their closed threads, for the reasons explained above
// bumpabsorber_hookin__showthread_end().
function bumpabsorber_hookin__datahandler_post_insert_post_end($postHandler) {
	global $mybb, $db, $lang, $thread, $ismod, $closed;

	$post = $postHandler->data;

	if ($mybb->settings['bumpabsorber_forums'] == -1
	    ||
	    in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_forums']))
	   ) {
		if ($mybb->user['uid'] == $thread['uid']
		    &&
		    !$post['savedraft']
		    &&
		    (!$ismod
		     ||
		     !is_moderator($post['fid'], 'canopenclosethreads', $post['uid'])
		    )
		   ) {
			$lang->load('moderation');

			$modlogdata['fid'] = $thread['fid'];
			$modlogdata['tid'] = $thread['tid'];
			$modoptions = $post['modoptions'];

			if (!empty($modoptions['closethread']) && $thread['closed'] != 1) {
				log_moderator_action($modlogdata, $lang->thread_closed);
				$db->update_query('threads', array('closed' => 1, 'ba_closed_by_author' => 1), "tid='{$thread['tid']}'");
				$postHandler->return_values['closed'] = 1;
			} else if (empty($modoptions['closethread']) && $thread['closed'] == 1) {
				// We don't allow threads to be opened by their
				// authors for the reason explained above
				// bumpabsorber_hookin__newthread_end()
// 				log_moderator_action($modlogdata, $lang->thread_opened);
// 				$db->update_query('threads', array('closed' => 0), "tid='{$thread['tid']}'");
// 				$postHandler->return_values['closed'] = 0;
			}
		}
		if (!ba_can_bump_thread($thread)) {
			global $g_ba_last_arr;

			$update_array = array(
				'lastpost'      => $g_ba_last_arr['thread_lastpost'     ],
				'lastposter'    => $g_ba_last_arr['thread_lastposter'   ],
				'lastposteruid' => $g_ba_last_arr['thread_lastposteruid'],
			);
			$db->update_query('threads', $update_array, "tid='{$thread['tid']}'");

			$update_array = array(
				'lastpost'        => $g_ba_last_arr['forum_lastpost'       ],
				'lastposter'      => $g_ba_last_arr['forum_lastposter'     ],
				'lastposteruid'   => $g_ba_last_arr['forum_lastposteruid'  ],
				'lastposttid'     => $g_ba_last_arr['forum_lastposttid'    ],
				'lastpostsubject' => $g_ba_last_arr['forum_lastpostsubject'],
			);
			$db->update_query('forums', $update_array, "fid='{$thread['fid']}'");
		}
	}
}

// Checks whether the user with uid $uid can bump the thread $thread.
// Assumes that the thread is in a forum enabled for this plugin.
function ba_can_bump_thread($thread, $uid = false) {
	global $mybb, $db;

	static $cached_ret = -1;

	if ($cached_ret === -1) {
		$can_bump = false;
		if ($uid === false) {
			$uid = $mybb->user['uid'];
		}
		if ($uid == $thread['uid']) {
			$query = $db->simple_select('posts', 'MAX(dateline) AS lastposted', 'tid='.$thread['tid'].' AND uid='.$uid);
			$lastposted = $db->fetch_field($query, 'lastposted');
			$elapsed = TIME_NOW - $lastposted;
			$required_wait = $mybb->settings['bumpabsorber_bumpintervalhrs'] * 3600;
			if (!$lastposted || $elapsed >= $required_wait) {
				$can_bump = true;
			}
		}
		$cached_ret = $can_bump;
	} else	$can_bump = $cached_ret;

	return $can_bump;
}

// Not yet used via the user interface, but works if the appropriate URL is
// entered directly.
// e.g., https://your.forum/moderation.php?action=ba_close_own_thread&tid=[tid]
function bumpabsorber_hookin__moderation_start() {
	global $mybb, $lang;

	if ($mybb->get_input('action') == 'ba_close_own_thread') {
		$lang->load('moderation');
		$lang->load('bumpabsorber');

		$tid = $mybb->get_input('tid', MyBB::INPUT_INT);

		if (!$tid || !($thread = get_thread($tid))) {
			error($lang->error_invalidthread, $lang->error);
		}
		$fid = $thread['fid'];
		if (!($mybb->settings['bumpabsorber_forums'] == -1
		      ||
		      in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_forums']))
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

		if ($thread['closed'] == 1) {
			// Do not allow thread authors to open their closed
			// threads for the reason stated above
			// bumpabsorber_hookin__showthread_end()
			error($lang->bmp_err_thread_already_closed);
// 			$openclose = $lang->opened;
// 			$redirect = $lang->redirect_openthread;
// 			$moderation->open_threads($tid);
		} else {
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
	$unwritable_files = array();
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
			ba_replace_in_file(MYBB_ROOT.$entry['file'], $from, $to);
		}
	}

	return array_unique($unwritable_files);
}

function ba_replace_in_file($file, $from, $to) {
	$contents = file_get_contents($file);
	$contents_new = str_replace($from, $to, $contents);
	file_put_contents($file, $contents_new);
}

function ba_get_missing_patch_ids() {
	$ret = array();
	foreach (c_ba_patches as $idx => $entry) {
		if (!file_exists(MYBB_ROOT.$entry['file'])) {
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

	return $thread['ba_closed_by_author'] == 1 && $uid == $thread['uid'] && ($mybb->settings['bumpabsorber_forums'] == -1 || in_array($thread['fid'], explode(',', $mybb->settings['bumpabsorber_forums'])));
}
