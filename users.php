switch ($mode)
{
	case 'initialize':
		if (!$step)
		{
			$template->assign_vars(array(
				'UNKNOWN_STEP'					=> true,
				'UNKNOWN_STEP_TITLE'			=> $user->lang['UNKNOWN_STEP_TITLE'],
				'UNKNOWN_STEP_CONTENT'			=> $user->lang['UNKNOWN_STEP_CONTENT'],
				'UNKNOWN_STEP_NEXT_LINK_TITLE'	=> $user->lang['UNKNOWN_STEP_NEXT_LINK_TITLE'],
				'UNKNOWN_STEP_NEXT_LINK_URL'	=> append_sid("index.$phpEx"),
			));			
		}
		
		if ($step = 'users')
		{
			if ($validate != 'confirm')
			{
				// How many administrators ?
					$sql = 'SELECT COUNT(user_id) AS num_admins, level
					FROM ' . BOOST_USERS . '
					WHERE level = ' . BOOST_USER_ADMIN;
					$result = $db->sql_query($sql);
					$num_admins = (int) $db->sql_fetchfield('num_admins');
					$db->sql_freeresult($result);

				// How many moderators ?
					$sql = 'SELECT COUNT(user_id) AS num_mods, level
					FROM ' . BOOST_USERS . '
					WHERE level = ' . BOOST_USER_MODO;
					$result = $db->sql_query($sql);
					$num_mods = (int) $db->sql_fetchfield('num_mods');
					$db->sql_freeresult($result);

				// How many "simple" members ?
					$sql = 'SELECT COUNT(user_id) AS num_members, level
					FROM ' . BOOST_USERS . '
					WHERE level = ' . BOOST_USER_NORMAL;
					$result = $db->sql_query($sql);
					$num_members = (int) $db->sql_fetchfield('num_members');
					$db->sql_freeresult($result);

					$template->assign_vars(array(
						'USERS_NO_VALIDATE'					=> true,
						'USERS_NO_VALIDATE_TITLE'			=> $user->lang['USERS_NO_VALIDATE_TITLE'],
						'USERS_NO_VALIDATE_CONTENT'			=> $user->lang['USERS_NO_VALIDATE_CONTENT'],
						'USERS_NO_VALIDATE_STATS_TITLE'			=> $user->lang['USERS_NO_VALIDATE_STATS_TITLE'],

						'USERS_NO_VALIDATE_COUNT_ADMINS'	=> $user->lang('COUNTING_ADMINS', (int) $num_admins),
						'USERS_NO_VALIDATE_COUNT_MODS'		=> $user->lang('COUNTING_MODS', (int) $num_mods),
						'USERS_NO_VALIDATE_COUNT_USERS'		=> $user->lang('COUNTING_USERS', (int) $num_members),
						'USERS_NO_VALIDATE_NEXT_LINK_TITLE'	=> $user->lang['USERS_NO_VALIDATE_NEXT_LINK_TITLE'],
						'USERS_NO_VALIDATE_NEXT_LINK_URL'	=> append_sid("index.$phpEx", 'mode=initialize&amp;step=users&amp;validate=confirm'),
					));		
			}
			else
			{
				// Nous supprimons notre table de conversion pour les membres
					$sql = 'DROP TABLE IF EXISTS ' . CONVERT_USERS_TABLE;
					$db->sql_query($sql);
					
				// Nous créons notre table de conversion pour les membres
					$sql = 'CREATE TABLE IF NOT EXISTS ' . CONVERT_USERS_TABLE . ' (
					convert_user_id mediumint(8) unsigned NOT NULL,
					convert_user_new_id mediumint(8) unsigned NOT NULL,
					convert_user_login varchar(255) COLLATE utf8_bin NOT NULL DEFAULT "",
					convert_user_level tinyint(1) NOT NULL DEFAULT "0",
					convert_user_mail varchar(100) COLLATE utf8_bin NOT NULL DEFAULT "",
					convert_user_show_mail tinyint(1) unsigned NOT NULL DEFAULT "1",
					convert_user_timezone tinyint(4) unsigned NOT NULL DEFAULT "1",
					convert_timestamp int(11) unsigned NOT NULL DEFAULT "0",
					convert_user_msg mediumint(8) unsigned NOT NULL DEFAULT "0",
					convert_last_connect int(11) unsigned NOT NULL DEFAULT "0",
					convert_is_founder tinyint(1) NOT NULL DEFAULT "0",
					PRIMARY KEY (convert_user_id)
					)';
					$db->sql_query($sql);
	
				// Nous sélectionnons le plus grand user_id de la table phpbb_users
					$sql = 'SELECT MAX(user_id) as max_phpbb_user_id
					FROM ' . USERS_TABLE
					;
					$result = $db->sql_query($sql);
					$max_phpbb_user_id = (int) $db->sql_fetchfield('max_phpbb_user_id');
					$db->sql_freeresult($result);

				// Nous sélectionnons tous les membres phpBoost
					$sql = 'SELECT user_id, login, level, user_mail, user_show_mail, user_timezone, timestamp, user_msg, last_connect 
					FROM ' . BOOST_USERS
					;
					$result = $db->sql_query($sql);

					while ($row = $db->sql_fetchrow($result))
					{
						// First we add an entry to register new/former user_id
							$sql = 'INSERT INTO ' . CONVERT_USERS_TABLE . ' ' . $db->sql_build_array('INSERT', array(
							'convert_user_id'			=> $row['user_id'],
							'convert_user_new_id'		=> ($row['user_id'] == SCRIPT_FOUNDER) ? 2 : ($row['user_id'] + $max_phpbb_user_id),
							'convert_user_login'		=> $row['login'],
							'convert_user_level'		=> $row['level'],
							'convert_user_mail'			=> $row['user_mail'],
							'convert_user_show_mail'	=> $row['user_show_mail'],
							'convert_user_timezone'		=> $row['user_timezone'],
							'convert_timestamp'			=> $row['timestamp'],
							'convert_user_msg'			=> $row['user_msg'],
							'convert_last_connect'		=> $row['last_connect'],
							'convert_is_founder'		=> ($row['user_id'] == SCRIPT_FOUNDER) ? 1 : 0,
							));
							$db->sql_query($sql);						
					}
					$db->sql_freeresult($result);

					$template->assign_vars(array(
					'CONVERT_USERS_TABLE'					=> true,
					'CONVERT_USERS_TABLE_TITLE'				=> $user->lang['CONVERT_USERS_TABLE_TITLE'],
					'CONVERT_USERS_TABLE_CONTENT'			=> $user->lang['CONVERT_USERS_TABLE_CONTENT'],
					'CONVERT_USERS_TABLE_NEXT_LINK_TITLE'	=> $user->lang['CONVERT_USERS_TABLE_NEXT_LINK_TITLE'],
					'CONVERT_USERS_TABLE_NEXT_LINK_URL'		=> append_sid("index.$phpEx", 'mode=initialize&amp;step=groups'),
					));
			}
		}

		if ($step == 'groups')
		{
			
		}
		
		if ($step == 'users_groups')
		{
			
		}
		
		if ($step == 'cats')
		{
			
		}
		
		if ($step == 'topics')
		{
			
		}
		
		if ($step == 'posts')
		{
			
		}
	break;

	default:
		$template->assign_vars(array(
			'INDEX_PAGE'					=> true,
			'INDEX_PAGE_TITLE'				=> $user->lang['INDEX_PAGE_TITLE'],
			'INDEX_PAGE_CONTENT'			=> $user->lang['INDEX_PAGE_CONTENT'],
			'INDEX_PAGE_NEXT_LINK_TITLE'	=> $user->lang['INDEX_PAGE_NEXT_LINK_TITLE'],
			'INDEX_PAGE_NEXT_LINK_URL'		=> append_sid("index.$phpEx", 'mode=initialize&amp;step=users'),
		));
	break;
}
