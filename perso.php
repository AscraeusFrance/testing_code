<?php
/**
*
* @package phpBB3
* @version $Id: template.php Raimon $
* @copyright (c) 2008 Raimon ( http://www.phpBBservice.nl )
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = 'tornade-to-olympus/';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('mods/phpboost2phpbb/common');

// Some vars
$mode					= request_var('mode', '', true);
$option					= request_var('option', '', true);
$founder				= request_var('founder', 0);

switch ($mode)
{
	case 'users':
		if (!$option)
		{
			// How many admins ?
			$sql = 'SELECT COUNT(user_id) AS num_admins, level
				FROM ' . TORNADE_USERS . '
				WHERE level = 2';
			$result = $db->sql_query($sql);
			$num_admins = (int) $db->sql_fetchfield('num_admins');
			$db->sql_freeresult($result);
			
			// How many mods ?
			$sql = 'SELECT COUNT(user_id) AS num_mods, level
				FROM ' . TORNADE_USERS . '
				WHERE level = 1';
			$result = $db->sql_query($sql);
			$num_mods = (int) $db->sql_fetchfield('num_mods');
			$db->sql_freeresult($result);
			
			// How many members ?
			$sql = 'SELECT COUNT(user_id) AS num_members, level
				FROM ' . TORNADE_USERS . '
				WHERE level = 0';
			$result = $db->sql_query($sql);
			$num_members = (int) $db->sql_fetchfield('num_members');
			$db->sql_freeresult($result);

			$template->assign_vars(array(
				'DEFAULT_USERS_PAGE'				=> true,
				'DEFAULT_USERS_PAGE_TITLE'			=> $user->lang['DEFAULT_USERS_PAGE_TITLE'],
				'DEFAULT_USERS_PAGE_CONTENT'		=> $user->lang['DEFAULT_USERS_PAGE_CONTENT'],
				'DEFAULT_USERS_COUNT_SECTION_TITLE'	=> $user->lang['DEFAULT_USERS_COUNT_SECTION_TITLE'],
				
				'DEFAULT_USERS_PAGE_ADMINS'			=> $user->lang('DEFAULT_USERS_PAGE_ADMINS', (int) $num_admins),
				'DEFAULT_USERS_PAGE_MODS'			=> $user->lang('DEFAULT_USERS_PAGE_MODS', (int) $num_mods),
				'DEFAULT_USERS_PAGE_USERS'			=> $user->lang('DEFAULT_USERS_PAGE_USERS', (int) $num_members),
				'DEFAULT_USERS_PAGE_NEXT_TEXT'		=> $user->lang['DEFAULT_USERS_PAGE_NEXT_TEXT'],
				'DEFAULT_USERS_PAGE_NEXT_LINK'		=> append_sid("tornade-to-olympus.$phpEx", 'mode=users&amp;option=founder'),
			));
		}
		
		if ($option == 'founder')
		{
			if (!$founder)
			{
				// Let's show a list of all admins from phpboost board
				$sql = 'SELECT user_id, login, level, user_groups, user_lang, user_theme, user_mail, user_show_mail, user_timezone, timestamp, user_msg, last_connect 
					FROM ' . TORNADE_USERS . '
					WHERE level = ' . USER_ADMIN;
				$result = $db->sql_query($sql);
				
				$range = 0;
				while ($row = $db->sql_fetchrow($result))
				{
					$range = $range+1;
					$template->assign_block_vars('admins_list', array(
						'USER_ID'			=> $row['user_id'],
						'USER_LOGIN'		=> $row['login'],
						'USER_LEVEL'		=> ($row['level'] == 2) ? 'Administrateur' : (($row['level'] == 1) ? 'Moderateur' : 'Utilisateur'),
						'USER_POSTS'		=> $row['user_msg'],
						'USER_GROUPS'		=> (!$row['user_groups']) ? 'Aucun' : $row['user_groups'],
						'USER_LANG'			=> $row['user_lang'],
						'USER_THEME'		=> $row['user_theme'],
						'USER_MAIL'			=> $row['user_mail'],
						'USER_SHOW_MAIL'	=> ($row['user_show_mail'] == 1) ? 'Oui' : 'Non',
						'USER_TIMEZONE'		=> $row['user_timezone'],
						'USER_TIMESTAMP'	=> $user->format_date($row['timestamp']),
						'USER_LAST_CONNECT'	=> (!$row['last_connect']) ? 'N/A' : $user->format_date($row['last_connect']),
						'USER_CHECKED'		=> ($row['user_id'] == $founder) ? ' checked="checked"' : '',
						'BG_LIST'			=> ($range%2==0) ? 'bg1' : 'bg2',
					));	
				}
				$db->sql_freeresult($result);

				// Some template vars
				$template->assign_vars(array(
					'FOUNDER_SELECT_PAGE'					=> true,
					'FOUNDER_SELECT_PAGE_TITLE'				=> $user->lang['FOUNDER_SELECT_PAGE_TITLE'],
					'FOUNDER_SELECT_PAGE_CONTENT'			=> $user->lang['FOUNDER_SELECT_PAGE_CONTENT'],
					'FOUNDER_SELECT_PAGE_SECTION_TITLE'		=> $user->lang['FOUNDER_SELECT_PAGE_SECTION_TITLE'],
					'FOUNDER_SELECT_PAGE_SECTION_CONTENT'	=> $user->lang['FOUNDER_SELECT_PAGE_SECTION_CONTENT'],
				));				
			}
			else
			{
				// First we delete our existant founder from our phpBB fresh installation
				$sql = 'DELETE FROM ' . USERS_TABLE . '
					WHERE user_type <> ' . USER_IGNORE . ' AND user_id > 1';
				$db->sql_query($sql);

				// Now we create our users_copy_table --- previous/new ids
				$sql = 'CREATE TABLE IF NOT EXISTS ' . USERS_COPY_TABLE . ' (
				user_id mediumint(8) unsigned NOT NULL,
				user_previous_id mediumint(8) unsigned NOT NULL
				)';
				$db->sql_query($sql);

				// Then we truncate our phpbb_users_copy table
				$sql = 'TRUNCATE TABLE ' . USERS_COPY_TABLE;
				$db->sql_query($sql);

				// Select our founder from our phpboost members table
				$sql = 'SELECT user_id, login, level, user_mail, user_show_mail, user_timezone, timestamp, user_msg, last_connect 
					FROM ' . TORNADE_USERS . '
					WHERE user_id = ' . $founder;
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					// First we add an entry to register new/former user_id
					$sql = 'INSERT INTO ' . USERS_COPY_TABLE . ' ' . $db->sql_build_array('INSERT', array(
						'user_id'			=> 2,
						'user_previous_id'	=> $row['user_id'],
					));
					$db->sql_query($sql);

					// Including our function to register our member...
					if (!function_exists('user_add'))
					{
						include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
					}

					// User password... sent with our welcome message sent by email
						$string		= str_shuffle('abcdefghjkmnpqrstuvwxyz123456789ABCDEFGHJKMNPQRSTUVWXYZ');
						$string2	= substr( $string , 0 , 3 ); // prendre les 10 1ers caractÃ¨res.
						$string3	= str_shuffle('abcdefghjkmnpqrstuvwxyz123456789ABCDEFGHJKMNPQRSTUVWXYZ');
						$string4	= substr( $string3 , 0 , 5 ); // prendre les 10 1ers caractÃ¨res
						$string5	= str_shuffle('abcdefghjkmnpqrstuvwxyz123456789ABCDEFGHJKMNPQRSTUVWXYZ');
						$string6	= substr( $string5 , 0 , 5 ); // prendre les 10 1ers caractÃ¨res.
						
						$pass		= $string2 . $string4 . $string6;

					// User vars...
					$user_row = array(
						'user_id'						=> 2,
						'user_type'             		=> USER_FOUNDER,
						'group_id'              		=> 5,
						'user_regdate'             		=> $row['timestamp'],
						'username'              		=> $row['login'],
						'username_clean'              	=> utf8_clean_string($row['login']),
						'user_password'              	=> phpbb_hash($pass),
						'user_email'              		=> $row['user_mail'],
						'user_lastvisit'				=> $row['last_connect'],
						'user_posts'					=> $row['user_msg'],
						'user_lang'						=> 'fr',
						'user_allow_viewemail'			=> $row['user_show_mail'],
						
					);
					$user_id = user_add($user_row);

					// No registration
					if (!$user_id)
					{
						// Some template vars
						$template->assign_vars(array(
							'FOUNDER_PAGE_ERROR'	=> true,
							'FOUNDER_PAGE_ERROR_TITLE'			=> $user->lang['FOUNDER_PAGE_ERROR_TITLE'],
							'FOUNDER_PAGE_ERROR_CONTENT'		=> $user->lang['FOUNDER_PAGE_ERROR_CONTENT'],
							'FOUNDER_PAGE_ERROR_NEXT_TEXT'		=> $user->lang['FOUNDER_PAGE_ERROR_NEXT_TEXT'],
							'FOUNDER_PAGE_ERROR_NEXT_LINK'		=> append_sid("tornade-to-olympus.$phpEx", 'mode=users&amp;option=founder'),
						));				
					}
					// Registration --- Send an email
					else
					{
						$user->setup(array('common', 'ucp')); // Some language files necessary...
						
						// Email template...
						$message = array();
						$message[] = $user->lang['ACCOUNT_ADDED'];
						$email_template = 'phpboost2phpbb_founder_welcome';
								
						if ($config['email_enable'])
						{
							if (!class_exists('messenger'))
							{
								include($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
							}

						$messenger = new messenger(false);

						$messenger->template($email_template, $user_row['user_lang']);

						$messenger->to($user_row['user_email'], $user_row['username']);

						$messenger->headers('X-AntiAbuse: Board servername - ' . $config['server_name']);
						$messenger->headers('X-AntiAbuse: User_id - ' . $user_id);
						$messenger->headers('X-AntiAbuse: Username - ' . $user_row['username']);
						$messenger->headers('X-AntiAbuse: User IP - ' . $user->ip);

						$messenger->assign_vars(array(
							'WELCOME_MSG'	=> htmlspecialchars_decode(sprintf($user->lang['WELCOME_SUBJECT'], $config['sitename'])),
							'USERNAME'		=> htmlspecialchars_decode($user_row['username']),
							'PASSWORD'		=> htmlspecialchars_decode($pass),
						));

						$messenger->send(NOTIFY_EMAIL);
						}

					// Success --- include our founder name into our success message
					$sql = 'SELECT user_id, username 
						FROM ' . USERS_TABLE . '
						WHERE user_id = 2';
					$result = $db->sql_query($sql);
					$founder_name = $db->sql_fetchfield('username');
					$db->sql_freeresult($result);
			//echo $founder_name;
					// Some template vars
					$template->assign_vars(array(
						'FOUNDER_PAGE_SUCCESS'	=> true,
						'FOUNDER_PAGE_SUCCESS_TITLE'			=> $user->lang['FOUNDER_PAGE_SUCCESS_TITLE'],
						'FOUNDER_PAGE_SUCCESS_CONTENT'			=> $user->lang('FOUNDER_PAGE_SUCCESS_CONTENT', $founder_name),
						'FOUNDER_PAGE_SUCCESS_NEXT_TEXT'		=> $user->lang['FOUNDER_PAGE_SUCCESS_NEXT_TEXT'],
						'FOUNDER_PAGE_SUCCESS_NEXT_LINK'		=> append_sid("tornade-to-olympus.$phpEx", 'mode=users&amp;option=all'),
					));	
					}					
				}
				$db->sql_freeresult($result);		
			}
		}
		
		if ($option == 'all')
		{
			// In case we come back to this step... we delete all entries in phpBB users_table except founder and bots
			$sql = 'DELETE FROM ' . USERS_TABLE . '
				WHERE user_type <> ' . USER_IGNORE . ' AND user_id > 2';
			$db->sql_query($sql);

			// Remembering our choosen founder
			$sql = 'SELECT user_id, user_previous_id 
				FROM ' . USERS_COPY_TABLE . '
				LIMIT 1';
			$result = $db->sql_query($sql);
			$founder_id = (int) $db->sql_fetchfield('user_previous_id');
			//$founder_id = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			// In case we come back to this step... we delete all entries in phpBB users_copy_table except founder
			$sql = 'DELETE FROM ' . USERS_COPY_TABLE . '
				WHERE user_previous_id != ' . $founder_id;
			$db->sql_query($sql);
			
			echo 'Previous founder : ' . $founder_id . '<br />';

			// Select our max phpBB user_id
			$sql = 'SELECT MAX(user_id) as max_phpbb_users
				FROM ' . USERS_TABLE;
			$result = $db->sql_query($sql);
			$max_id = (int) $db->sql_fetchfield('max_phpbb_users');
			//$max_id = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			// Select our default phpBB group_id
			$group_name =  'REGISTERED';
			$sql = 'SELECT group_id
				FROM ' . GROUPS_TABLE . "
				WHERE group_name = '" . $db->sql_escape($group_name) . "'
					AND group_type = " . GROUP_SPECIAL;
			$result = $db->sql_query($sql);
			$default_id = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			// Select our phpBoost members (exluded our founder)
			$sql2 = 'SELECT user_id, login, level, user_mail, user_show_mail, user_timezone, timestamp, user_msg, last_connect 
				FROM ' . TORNADE_USERS . '
				WHERE user_id != ' . $founder_id;
			$result2 = $db->sql_query($sql2);
			//echo '<br /><br />' . $sql . '<br /><br />';
			while ($row2 = $db->sql_fetchrow($result2))
			{
				$sql = 'INSERT INTO ' . USERS_COPY_TABLE . ' ' . $db->sql_build_array('INSERT', array(
					'user_id'			=> $row2['user_id'] + $max_id, //$new,
					'user_previous_id'	=> $row2['user_id'],
				));
				$db->sql_query($sql);

				// Including our function to register our member...
				if (!function_exists('user_add'))
				{
					include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
				}
				
				// User password... sent with our welcome message sent by email
				$string		= str_shuffle('abcdefghjkmnpqrstuvwxyz123456789ABCDEFGHJKMNPQRSTUVWXYZ');
				$string2	= substr( $string , 0 , 3 ); // prendre les 10 1ers caractÃ¨res.
				$string3	= str_shuffle('abcdefghjkmnpqrstuvwxyz123456789ABCDEFGHJKMNPQRSTUVWXYZ');
				$string4	= substr( $string3 , 0 , 5 ); // prendre les 10 1ers caractÃ¨res
				$string5	= str_shuffle('abcdefghjkmnpqrstuvwxyz123456789ABCDEFGHJKMNPQRSTUVWXYZ');
				$string6	= substr( $string5 , 0 , 5 ); // prendre les 10 1ers caractÃ¨res.
				
				$pass		= $string2 . $string4 . $string6;
				
					// User vars...
					$user_row = array(
						'user_id'					=> $row2['user_id'] + $max_id,
						'user_type'             	=> USER_NORMAL,
						'group_id'              	=> $default_id['group_id'],
						'user_regdate'             	=> $row2['timestamp'],
						'username'              	=> $row2['login'],
						'username_clean'            => utf8_clean_string($row2['login']),
						'user_password'             => phpbb_hash($pass),
						'user_email'              	=> $row2['user_mail'],
						'user_lastvisit'			=> $row2['last_connect'],
						'user_posts'				=> $row2['user_msg'],
						'user_lang'					=> 'fr',
						'user_allow_viewemail'		=> $row2['user_show_mail'],
						
					);
					$user_id = user_add($user_row);
					
					// No registration
					if (!$user_id)
					{
						// Some template vars
						$template->assign_vars(array(
							'USERS_PAGE_ERROR'	=> true,
							'USERS_PAGE_ERROR_TITLE'		=> $user->lang['USERS_PAGE_ERROR_TITLE'],
							'USERS_PAGE_ERROR_CONTENT'		=> $user->lang['USERS_PAGE_ERROR_CONTENT'],
							'USERS_PAGE_ERROR_NEXT_TEXT'	=> $user->lang['USERS_PAGE_ERROR_NEXT_TEXT'],
							'USERS_PAGE_ERROR_NEXT_LINK'	=> append_sid("tornade-to-olympus.$phpEx", 'mode=users&amp;option=all'),
						));				
					}
					else
					{
						$user->setup(array('common', 'ucp')); // Some language files necessary...
						
						// Email template...
						$message = array();
						$message[] = $user->lang['ACCOUNT_ADDED'];
						$email_template = ($row2['level'] == 2) ? 'phpboost2phpbb_admin_welcome' : (($row2['level'] == 1) ? 'phpboost2phpbb_modo_welcome' : 'phpboost2phpbb_user_welcome');
								
						if ($config['email_enable'])
						{
							if (!class_exists('messenger'))
							{
								include($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
							}

						$messenger = new messenger(false);

						$messenger->template($email_template, $user_row['user_lang']);

						$messenger->to($user_row['user_email'], $user_row['username']);

						$messenger->headers('X-AntiAbuse: Board servername - ' . $config['server_name']);
						$messenger->headers('X-AntiAbuse: User_id - ' . $user_id);
						$messenger->headers('X-AntiAbuse: Username - ' . $user_row['username']);
						$messenger->headers('X-AntiAbuse: User IP - ' . $user->ip);

						$messenger->assign_vars(array(
							'WELCOME_MSG'	=> htmlspecialchars_decode(sprintf($user->lang['WELCOME_SUBJECT'], $config['sitename'])),
							'USERNAME'		=> htmlspecialchars_decode($user_row['username']),
							'PASSWORD'		=> htmlspecialchars_decode($pass),
						));

						$messenger->send(NOTIFY_EMAIL);
						}
						
						// How many users coming from phpBoost ?
						$sql = 'SELECT COUNT(user_id) as count_phpboost_id
							FROM ' . TORNADE_USERS;
						$result = $db->sql_query($sql);
						$count_phpboost_id= (int) $db->sql_fetchfield('count_phpboost_id');
						//$count_phpboost_id = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);
						
						// How many users merged into phpBB ?
						$sql = 'SELECT COUNT(user_id) as count_phpbb_id
							FROM ' . USERS_TABLE . '
							WHERE user_type <> ' . USER_IGNORE;
						$result = $db->sql_query($sql);
						$count_phpbb_id= (int) $db->sql_fetchfield('count_phpbb_id');
						//$count_phpbb_id = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);						
						
						
						//$user->lang('USERS_PAGE_SUCCESS_CONTENT', (int) $num_members)
						// Some template vars
						$template->assign_vars(array(
							'USERS_PAGE_SUCCESS'	=> true,
							'USERS_PAGE_SUCCESS_TITLE'			=> $user->lang['USERS_PAGE_SUCCESS_TITLE'],
							'USERS_PAGE_SUCCESS_CONTENT'		=> $user->lang('USERS_PAGE_SUCCESS_CONTENT', (int) $count_phpbb_id, (int) $count_phpboost_id),
							'USERS_PAGE_SUCCESS_NEXT_TEXT'		=> $user->lang['USERS_PAGE_SUCCESS_NEXT_TEXT'],
							'USERS_PAGE_SUCCESS_NEXT_LINK'		=> append_sid("tornade-to-olympus.$phpEx", 'mode=forums'),
						));	
					}
			}
			$db->sql_freeresult($result);
		}
	break;

	case 'forums':
		if (!$option)
		{
			
/* Testing unserealize start */

			// Select our default phpBB group_id
			//$group_name =  'REGISTERED';
			$sql = 'SELECT id, auth
				FROM ' . TORNADE_FORUMS . '
				WHERE id = 1';
			$result = $db->sql_query($sql);
			$foo_auth = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			//$datas_foo = unserialize($foo_auth[0]);
			print_r(unserialize($foo_auth['auth'])) . '<br />';
/* Testing unserealize start */
			
			// Some template vars
			$template->assign_vars(array(
				'DEFAULT_FORUMS_PAGE'			=> true,
				'DEFAULT_FORUMS_PAGE_TITLE'		=> $user->lang['DEFAULT_FORUMS_PAGE_TITLE'],
				'DEFAULT_FORUMS_PAGE_CONTENT'	=> $user->lang['DEFAULT_FORUMS_PAGE_CONTENT'],
				'DEFAULT_FORUMS_PAGE_NEXT_TEXT'	=> $user->lang['DEFAULT_FORUMS_PAGE_NEXT_TEXT'],
				'DEFAULT_FORUMS_PAGE_NEXT_LINK'	=> append_sid("tornade-to-olympus.$phpEx", 'mode=forums&amp;option=copy'),
			));				
		}

		if ($option == 'copy')
		{
			// First we delete our existant phpBB categories and forums
			$sql = 'DELETE FROM ' . FORUMS_TABLE;
			$db->sql_query($sql);

			// Select our phpboost forums
			$sql = 'SELECT id, id_left, id_right, level, name, subname, nbr_topic, nbr_msg, status, url 
				FROM ' . TORNADE_FORUMS;
			$result = $db->sql_query($sql);

			$range = 0;
			while ($row = $db->sql_fetchrow($result))
			{
				// First we collect our datas
				$forum_data_sql = array(
						'forum_id'				=> $row['id'],
						'left_id'				=> $row['id_left'],
						'right_id'				=> $row['id_right'],
						'parent_id'				=> 0,
						'forum_parents'			=> '',
						'forum_type'			=> (!empty($row['url'])) ? FORUM_LINK : (($row['level'] == 0) ? FORUM_CAT : FORUM_POST),
						'forum_name'			=> $row['name'],
						'forum_desc'			=> $row['subname'],
						'forum_status'			=> ($row['status'] == 0) ? ITEM_LOCKED : ITEM_UNLOCKED,
						
						'forum_posts'	=> $row['nbr_msg'],
						'forum_topics'	=> $row['nbr_topic'],
						'forum_link'			=> $row['url'],
						'forum_rules'			=> '',
				);
			
				// We insert all datas
				$sql = 'INSERT INTO ' . FORUMS_TABLE . ' ' . $db->sql_build_array('INSERT', $forum_data_sql);
				$db->sql_query($sql);
				
			}
			$db->sql_freeresult($result);
			
	// Success
			$template->assign_vars(array(
				'SUCCESS_FORUMS_PAGE'			=> true,
				'SUCCESS_FORUMS_PAGE_TITLE'		=> $user->lang['SUCCESS_FORUMS_PAGE_TITLE'],
				'SUCCESS_FORUMS_PAGE_CONTENT'	=> $user->lang['SUCCESS_FORUMS_PAGE_CONTENT'],
				'SUCCESS_FORUMS_PAGE_NEXT_TEXT'	=> $user->lang['SUCCESS_FORUMS_PAGE_NEXT_TEXT'],
				'SUCCESS_FORUMS_PAGE_NEXT_LINK'	=> append_sid("tornade-to-olympus.$phpEx", 'mode=forums&amp;option=order'),
			));	
		}
		
		if ($option == 'order')
		{
			// Select our phpboost forums
			$sql = 'SELECT MAX(level) as max_level
			FROM ' . TORNADE_FORUMS;
			$result = $db->sql_query($sql);
			$max_level = (int) $db->sql_fetchfield('max_level');

			for ($x = 0; $x <= $max_level; $x++)
			{
				$sql = 'SELECT id, level, id_left, id_right
				FROM ' . TORNADE_FORUMS . '
				WHERE level = ' . $x;
				$result = $db->sql_query($sql);

				while ($row = $db->sql_fetchrow($result))
				{
					$sql2 = 'SELECT id, level, id_left, id_right
					FROM ' . TORNADE_FORUMS . '
					WHERE id_left > ' . $row['id_left'] . ' AND id_right < ' . $row['id_right'] . ' AND level <> ' . $row['level'];
					$result2 = $db->sql_query($sql2);

					while ($row2 = $db->sql_fetchrow($result2))
					{
						$sql = 'UPDATE ' . FORUMS_TABLE . ' SET parent_id = ' . $row['id'] . ' WHERE forum_id = ' . $row2['id'];
						$db->sql_query($sql);	
					}	
					$db->sql_freeresult($result2);

				}
				$db->sql_freeresult($result);	
			
			} 
			$db->sql_freeresult($result);
		
			// Ordering Success
			$template->assign_vars(array(
				'ORDERING_SUCCESS_FORUMS_PAGE'			=> true,
				'ORDERING_FORUMS_PAGE_TITLE'		=> $user->lang['ORDERING_FORUMS_PAGE_TITLE'],
				'ORDERING_SUCCESS_FORUMS_PAGE_CONTENT'	=> $user->lang['ORDERING_SUCCESS_FORUMS_PAGE_CONTENT'],
				'ORDERING_SUCCESS_FORUMS_PAGE_NEXT_TEXT'	=> $user->lang['ORDERING_SUCCESS_FORUMS_PAGE_NEXT_TEXT'],
				'ORDERING_SUCCESS_FORUMS_PAGE_NEXT_LINK'	=> append_sid("tornade-to-olympus.$phpEx", 'mode=topics'),
			));			
		}
		
		if ($option == 'parents')
		{
			// Select our phpboost forums
			$sql_parents = 'SELECT forum_id, parent_id, left_id, right_id, forum_parents 
				FROM ' . FORUMS_TABLE;
			$result_parents = $db->sql_query($sql_parents);

			$range = 0;
			while ($row_parents = $db->sql_fetchrow($result_parents))
			{
				$forum_parents = array();

				if ($row_parents['parent_id'] > 0)
				{
					if ($row_parents['forum_parents'] == '')
					{
						$sql = 'SELECT forum_id, forum_name, forum_type
							FROM ' . FORUMS_TABLE . '
							WHERE left_id < ' . $row_parents['left_id'] . '
								AND right_id > ' . $row_parents['right_id'] . '
							ORDER BY left_id ASC';
						$result = $db->sql_query($sql);

						while ($row = $db->sql_fetchrow($result))
						{
							$forum_parents[$row['forum_id']] = array($row['forum_name'], (int) $row['forum_type']);
						}
						$db->sql_freeresult($result);

						$row_parents['forum_parents'] = serialize($forum_parents);

						$sql = 'UPDATE ' . FORUMS_TABLE . "
							SET forum_parents = '" . $db->sql_escape($row_parents['forum_parents']) . "'
							WHERE parent_id = " . $row_parents['parent_id'];
						$db->sql_query($sql);
					}
					else
					{
						$forum_parents = unserialize($row_parents['forum_parents']);
					}

				}
			}	
		}
	break;

	case 'topics':
		if (!$option)
		{
			// How many topics ?
			$sql = 'SELECT COUNT(id) AS num_topics
				FROM ' . TORNADE_TOPICS;
			$result = $db->sql_query($sql);
			$num_topics = (int) $db->sql_fetchfield('num_topics');
			$db->sql_freeresult($result);
			
			// Default page
			$template->assign_vars(array(
				'DEFAULT_TOPICS_PAGE'			=> true,
				'DEFAULT_TOPICS_PAGE_TITLE'		=> $user->lang['DEFAULT_TOPICS_PAGE_TITLE'],
				'DEFAULT_TOPICS_PAGE_CONTENT'	=> $user->lang('DEFAULT_TOPICS_PAGE_CONTENT', (int) $num_topics),
				'DEFAULT_TOPICS_PAGE_NEXT_TEXT'	=> $user->lang['DEFAULT_TOPICS_PAGE_NEXT_TEXT'],
				'DEFAULT_TOPICS_PAGE_NEXT_LINK'	=> append_sid("tornade-to-olympus.$phpEx", 'mode=topics&amp;option=migration'),
			));						
		}
		
		if ($option == 'migration')
		{
			// First we reset auto_increment into phpBB topics table
			$sql = 'ALTER TABLE ' . TOPICS_TABLE . ' AUTO_INCREMENT=1';
			$db->sql_query($sql);
			
			// Then we truncate our phpBB topics table
			$sql = 'TRUNCATE TABLE ' . TOPICS_TABLE;
			$db->sql_query($sql);
			
			// First we reset auto_increment into phpBB posts table
			$sql = 'ALTER TABLE ' . POSTS_TABLE . ' AUTO_INCREMENT=1';
			$db->sql_query($sql);
			
			// Then we truncate our phpBB posts table
			$sql = 'TRUNCATE TABLE ' . POSTS_TABLE;
			$db->sql_query($sql);
			
			$sql = 'SELECT msg.id, msg.idtopic, msg.user_id, msg.contents, msg.timestamp, msg.timestamp_edit, msg.user_id_edit, msg.user_ip, topic.id, topic.idcat, topic.title, topic.user_id, topic.nbr_msg, topic.nbr_views, topic.last_user_id, topic.last_msg_id, topic.last_timestamp, topic.first_msg_id, topic.type, topic.status, topic.aprob
			FROM ' . TORNADE_TOPICS . ' topic
			LEFT JOIN ' . TORNADE_POSTS . ' msg
			ON (msg.idtopic = topic.id)
			WHERE msg.id = topic.first_msg_id';
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{					
				$sql2 = 'SELECT *
					FROM ' . USERS_TABLE . '
					WHERE user_id = ' . $row['user_id'];
				$result2 = $db->sql_query($sql2);
				$row2 = $db->sql_fetchrow($result2);
				$db->sql_freeresult($result2);
				$user->data = array_merge($user->data, $row2);
				$auth->acl($user->data);
				$user->ip = '0.0.0.0 ';
				
				// Let's include some important files
				if (!function_exists('submit_post'))
				{
					include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
					include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
					include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
				}

				// Let's take care about phpBoost BBCodes...
				$msg  = $row['contents'];
				//$phpboost_bbcodes = array('<strong>', '</strong>', '<em>', '</em>');
				//$phpb_bbcodes   = array('[b]', '[/b]', '[i]', '[/i]');
				$msg = preg_replace('#\<strong>(.+)<\/strong>#iUs', '[b]$1[/b]', $msg);
				$msg = preg_replace('#\<em>(.+)<\/em>#iUs', '[i]$1[/i]', $msg);
				$msg = preg_replace('#\<span style="text-decoration: underline;">(.+)<\/span>#iUs', '[u]$1[/u]', $msg);
				$msg = preg_replace('#\<br \/>#iUs',"\n", $msg);
				$msg = preg_replace('#\"\n\n"#iUs',"\n", $msg);
 
				//$msg = str_replace($phpboost_bbcodes, $phpb_bbcodes, $msg);
				
				// Some post parameters
				$poll = $uid = $bitfield = $options = '';
				generate_text_for_storage($row['title'], $uid, $bitfield, $options, false, false, false);
				generate_text_for_storage($msg, $uid, $bitfield, $options, true, true, true);

				// Some vars to insert into DB
				$data = array(
					'topic_id'			=> $row['idtopic'],
					'post_id'			=> $row['first_msg_id'],
					'forum_id'  		=> $row['idcat'],
					'icon_id'  			=> false,
					'poster_id'			=> $row['user_id'],
					'enable_bbcode' 	=> true,
					'enable_smilies'	=> true,
					'enable_urls'  		=> true,
					'enable_sig'  		=> true,
					'message'  			=> $msg,
					'message_md5'   	=> md5($msg),
					'bbcode_bitfield'   => $bitfield,
					'bbcode_uid'  		=> $uid,
					'post_edit_locked'  => 0,
					'topic_title'  		=> $row['title'],
					'notify_set'  		=> false,
					'notify' 			=> true,
					'post_time'   		=> $row['timestamp'],
					'forum_name'  		=> '',
					'enable_indexing'   => true,
					'topic_approved'	=> 1,
					'post_approved'		=> 1,							
				);
					
				// Submitting our post
				//$post_reply = ($select_post_type == 1) ? 'post' : 'reply';
				$post_reply = 'post';
				//echo $data['topic_id'];
				submit_post($post_reply, $row['title'], $row2['username'], POST_NORMAL, $poll, $data);
				
				// We need to approve posts
				$sql = 'UPDATE ' . POSTS_TABLE . ' SET post_approved = 1';
				$db->sql_query($sql);
				
				// We need to approve topics
				$sql = 'UPDATE ' . TOPICS_TABLE . ' SET topic_approved = 1';
				$db->sql_query($sql);
				
				// We need to set correct timestamp to posts
				$sql = 'UPDATE ' . POSTS_TABLE . ' SET post_time = ' . $row['timestamp'] . ' WHERE post_id = ' . $row['first_msg_id'];
				$db->sql_query($sql);
				
				// We need to set correct poster to posts
				$sql = 'UPDATE ' . POSTS_TABLE . ' SET poster_id = (SELECT user_id FROM ' . USERS_COPY_TABLE . ' WHERE user_previous_id = ' . $row['user_id'] . ')';
				$db->sql_query($sql);

				// We include necessary files to synchronise all
				if (!function_exists('sync'))
				{
					include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
				}
				// We synchronise all topics and forums
					sync('topic', 'topic_id', $data['topic_id'], true);
					sync('forum', 'forum_id', $data['forum_id'], true);
				}	
				$db->sql_freeresult($result);
		}
	break;
	
	default:			
		$template->assign_vars(array(
			'DEFAULT_PAGE_MODE'			=> true,
			'DEFAULT_PAGE_TITLE'		=> $user->lang['SCRIPT_PAGE_TITLE'],
			'DEFAULT_PAGE_CONTENT'		=> $user->lang['DEFAULT_PAGE_CONTENT'],
			'DEFAULT_PAGE_LIST'			=> $user->lang['DEFAULT_PAGE_LIST'],
			'DEFAULT_PAGE_NEXT_TEXT'	=> $user->lang['DEFAULT_PAGE_NEXT_TEXT'],
			'DEFAULT_PAGE_NEXT_LINK'	=> append_sid("tornade-to-olympus.$phpEx", 'mode=users'),
		));	
	break;
}
// Output page
page_header($user->lang['SCRIPT_PAGE_TITLE']);



$template->set_filenames(array(
	'body' => 'website/tornade-to-olympus_body.html')
);

page_footer();

?>
