<?php
/**
*
* @package phpBB3
* @version $Id: convert.php ForumsFaciles $
* @copyright (c) 2016 ForumsFaciles ( forumsfaciles.fr )
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/boost2phpbb/constants.' . $phpEx);

// Nous dÃ©marrons la session
$user->session_begin();
$auth->acl($user->data);
$user->setup('mods/boost2phpbb/convert');

// Quelques paramÃ¨tres
$mode					= request_var('mode', '', true);
$page					= request_var('page', 0);

switch ($mode)
{
	case 'prepare_users_table':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_USERS_TABLE_PAGE_TITLE'];
		
		// Nous supprimons toute table de prÃ©-migration des utilisateurs existante
		$sql = 'DROP TABLE IF EXISTS ' . CONVERT_USERS_TABLE;
		$db->sql_query($sql);
		
		// Nous crÃ©ons la table de prÃ©-migration des utilisateurs
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
		
		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['PREPARE_USERS_TABLE_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['PREPARE_USERS_TABLE_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['PREPARE_USERS_TABLE_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=prepare_users_convert'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);
	break;

	case 'prepare_users_convert':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_USERS_CONVERT_PAGE_TITLE'];

		// Nous sÃ©lectionnons le plus grand user_id de la table phpbb_users
		$sql = 'SELECT MAX(user_id) as max_phpbb_user_id
			FROM ' . USERS_TABLE;
		$result = $db->sql_query($sql);
		$max_phpbb_user_id = (int) $db->sql_fetchfield('max_phpbb_user_id');
		$db->sql_freeresult($result);

		// Nous comptons le nombre d'utilisateurs
		$sql = 'SELECT COUNT(user_id) AS num_users
			FROM ' . BOOST_USERS;
		$result = $db->sql_query($sql);
		$num_users = (int) $db->sql_fetchfield('num_users');
		$db->sql_freeresult($result);

		// Combien d'utilisateurs Ã  la fois
		$nb_users_per_page = SCRIPT_MAX_USERS_LOOPS; // Modifiable via includes/boost2phpbb/constants.php

		// Nombre de pages nÃ©cessaires
		$nb_pages = ceil($num_users/$nb_users_per_page);

		// Sur quelle page nous trouvons-nous
		if (!$page)
		{
			// Aucun numÃ©ro de page dÃ©fini... nous dÃ©marrons par la premiÃ¨re page
			$current_page = 1;
		}
		else
		{
			// Un numÃ©ro de page a Ã©tÃ© trouvÃ©... nous dÃ©marrons de celui-ci
			$current_page = $page;

			// Et si le numÃ©ro de page est supÃ©rieur au nombre de pages maximal, nous dÃ©marrons par la derniÃ¨re page
			if($current_page > $nb_pages)
			{
				$current_page = $nb_pages;
			}
		}

		// A partir de quel enregistrement allons-nous dÃ©marrer
		$first_entry = ($current_page - 1) * $nb_users_per_page;

		// Nous sÃ©lectionnons tous les membres phpBoost
		$sql = 'SELECT user_id, login, level, user_mail, user_show_mail, user_timezone, timestamp, user_msg, last_connect 
			FROM ' . BOOST_USERS . '
			LIMIT ' . $first_entry . ', ' . $nb_users_per_page;
		$result = $db->sql_query($sql);
		
		while ($row = $db->sql_fetchrow($result))
		{
			// Nous remplissons la table provisoire des utilisateurs : ancien et nouvel ID
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

		for($i=1; $i<=$nb_pages; $i++)
		{
			//On va faire notre condition
			if($i==$current_page) //Si il s'agit de la page actuelle...
			{
				$next = $i + 1;
				$nb_users = ($current_page == $nb_pages) ? $num_users : $nb_users_per_page * $current_page;
				$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['PREPARE_USERS_CONVERT_SUCCESS_TITLE'] : $user->lang['PREPARE_USERS_CONVERT_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('PREPARE_USERS_CONVERT_SUCCESS_CONTENT', (int) $nb_users, (int) $num_users) : $user->lang('PREPARE_USERS_CONVERT_PROCESS_CONTENT', (int) $nb_users, (int) $num_users),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['PREPARE_USERS_CONVERT_NEXT_TITLE'] : $user->lang['PREPARE_USERS_CONVERT_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
					'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=import_users_first_step'),
				));

				// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous invitons Ã  passer Ã  la suite.
				$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=import_users_first_step' : 'mode=prepare_users_convert&amp;page=' . $next);
				// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous ne redirigeons pas
				// $wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
				($current_page == $nb_pages) ? '' : meta_refresh(SCRIPT_WAITING_TIME_FOR_LOOPS, $redirect_url);
			}
		}		
	break;
	
	case 'import_users_first_step':
		// Titre de notre page
		$page_title = $user->lang['USERS_IMPORT_PAGE_TITLE'];

		// First we delete our existant founder from our phpBB fresh installation
		$sql = 'DELETE FROM ' . USERS_TABLE . '
			WHERE user_type <> ' . USER_IGNORE;
		$db->sql_query($sql);

		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['IMPORT_USERS_FIRST_STEP_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['IMPORT_USERS_FIRST_STEP_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['IMPORT_USERS_FIRST_STEP_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=import_users_second_step'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);		
	break;
	
	case 'import_users_second_step':
		// Titre de notre page
		$page_title = $user->lang['USERS_IMPORT_PAGE_TITLE'];

		// Select our default phpBB group_id
		$default_group_name =  'REGISTERED';
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $db->sql_escape($default_group_name) . "'
			AND group_type = " . GROUP_SPECIAL;
		$result = $db->sql_query($sql);
		$default_group_id = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// Select our admin phpBB group_id
		$admin_group_name =  'ADMINISTRATORS';
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $db->sql_escape($admin_group_name) . "'
			AND group_type = " . GROUP_SPECIAL;
		$result = $db->sql_query($sql);
		$admin_group_id = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// Select our mod phpBB group_id
		$mod_group_name =  'GLOBAL_MODERATORS';
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $db->sql_escape($mod_group_name) . "'
			AND group_type = " . GROUP_SPECIAL;
		$result = $db->sql_query($sql);
		$mod_group_id = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// Nous comptons le nombre d'utilisateurs
		$sql = 'SELECT COUNT(convert_user_id) AS num_users
			FROM ' . CONVERT_USERS_TABLE;
		$result = $db->sql_query($sql);
		$num_users = (int) $db->sql_fetchfield('num_users');
		$db->sql_freeresult($result);

		// Combien d'utilisateurs Ã  la fois
		$nb_users_per_page = SCRIPT_MAX_USERS_LOOPS;

		// Nombre de pages nÃ©cessaires
		$nb_pages = ceil($num_users/$nb_users_per_page);

		// Sur quelle page nous trouvons-nous
		if (!$page)
		{
			// Aucun numÃ©ro de page dÃ©fini... nous dÃ©marrons par la premiÃ¨re page
			$current_page = 1;
		}
		else
		{
			// Un numÃ©ro de page a Ã©tÃ© trouvÃ©... nous dÃ©marrons de celui-ci
			$current_page = $page;

			// Et si le numÃ©ro de page est supÃ©rieur au nombre de pages maximal, nous dÃ©marrons par la derniÃ¨re page
			if($current_page > $nb_pages)
			{
				$current_page = $nb_pages;
			}
		}

		// A partir de quel enregistrement allons-nous dÃ©marrer
		$first_entry = ($current_page - 1) * $nb_users_per_page;

		// Select our phpBoost members (exluded our founder)
		$sql = 'SELECT convert_user_id, convert_user_new_id, convert_user_login, convert_user_level, convert_user_mail, convert_user_show_mail, convert_user_timezone, convert_timestamp, convert_user_msg, convert_last_connect, convert_is_founder
			FROM ' . CONVERT_USERS_TABLE . '
			LIMIT ' . $first_entry . ', ' . $nb_users_per_page;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
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
				'user_id'					=> $row['convert_user_new_id'],
				'user_type'             	=> ($row['convert_user_id'] == SCRIPT_FOUNDER) ? USER_FOUNDER : USER_NORMAL,
				'group_id'              	=> ($row['convert_user_level'] == BOOST_USER_ADMIN) ? $admin_group_id['group_id'] : (($row['convert_user_level'] == BOOST_USER_MODO) ? $mod_group_id['group_id'] : $default_group_id['group_id']),
				'user_regdate'             	=> $row['convert_timestamp'],
				'username'              	=> $row['convert_user_login'],
				'username_clean'            => utf8_clean_string($row['convert_user_login']),
				'user_password'             => phpbb_hash($pass),
				'user_email'              	=> $row['convert_user_mail'],
				'user_lastvisit'			=> $row['convert_last_connect'],
				'user_posts'				=> $row['convert_user_msg'],
				'user_lang'					=> 'fr',
				'user_allow_viewemail'		=> $row['convert_user_show_mail'],
			);
			$user_id = user_add($user_row);

			// No registration
			if (!$user_id)
			{
				// Some template vars
				$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'	=> $user->lang['IMPORT_USERS_SECOND_STEP_ERROR_TITLE'],
					'PAGE_CONTENT'			=> $user->lang['IMPORT_USERS_SECOND_STEP_ERROR_CONTENT'],
					'PAGE_NEXT_TITLE'		=> $user->lang['IMPORT_USERS_SECOND_STEP_ERROR_NEXT_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> true,
					'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=import_users_first_step'),
				));				
			}
			else
			{
				$user->setup(array('common', 'ucp')); // Some language files necessary...

				// Email template...
				$message = array();
				$message[] = $user->lang['ACCOUNT_ADDED'];
				$email_template = ($row['convert_user_level'] == BOOST_USER_ADMIN) ? 'boost2phpbb/phpboost2phpbb_admin_welcome' : (($row['convert_user_level'] == BOOST_USER_MODO) ? 'boost2phpbb/phpboost2phpbb_modo_welcome' : 'boost2phpbb/phpboost2phpbb_user_welcome');

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
			}		
		}
		$db->sql_freeresult($result);

		for($i=1; $i<=$nb_pages; $i++)
		{
			//On va faire notre condition
			if($i==$current_page) //Si il s'agit de la page actuelle...
			{
				$next = $i + 1;
				$nb_users = ($current_page == $nb_pages) ? $num_users : $nb_users_per_page * $current_page;
				$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['IMPORT_USERS_SECOND_STEP_SUCCESS_TITLE'] : $user->lang['IMPORT_USERS_SECOND_STEP_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('IMPORT_USERS_SECOND_STEP_SUCCESS_CONTENT', (int) $nb_users, (int) $num_users) : $user->lang('IMPORT_USERS_SECOND_STEP_PROCESS_CONTENT', (int) $nb_users, (int) $num_users),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['IMPORT_USERS_SECOND_STEP_NEXT_TITLE'] : $user->lang['IMPORT_USERS_SECOND_STEP_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
					'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=import_users_third_step'),
				));

				// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
				$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=import_users_third_step' : 'mode=import_users_second_step&amp;page=' . $next);
				// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
				$wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
				($current_page == $nb_pages) ? '' : meta_refresh(SCRIPT_WAITING_TIME_FOR_LOOPS, $redirect_url);
			}
		}
	break;
	
	case 'import_users_third_step':
		// Titre de notre page
		$page_title = $user->lang['USERS_IMPORT_PAGE_TITLE'];

		// Nous vidons la table phpbb_user_groups
		$sql = 'DELETE FROM ' . USER_GROUP_TABLE;
		$db->sql_query($sql);

		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['IMPORT_USERS_THIRD_STEP_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['IMPORT_USERS_THIRD_STEP_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['IMPORT_USERS_THIRD_STEP_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=import_users_fourth_step'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);
	break;

	case 'import_users_fourth_step':
		// Titre de notre page
		$page_title = $user->lang['USERS_IMPORT_PAGE_TITLE'];

		// Nous comptons le nombre d'utilisateurs
		$sql = 'SELECT COUNT(user_id) AS num_users
			FROM ' . USERS_TABLE;
		$result = $db->sql_query($sql);
		$num_users = (int) $db->sql_fetchfield('num_users');
		$db->sql_freeresult($result);

		// Combien d'utilisateurs Ã  la fois
		$nb_users_per_page = SCRIPT_MAX_USERS_LOOPS;

		// Nombre de pages nÃ©cessaires
		$nb_pages = ceil($num_users/$nb_users_per_page);

		// Sur quelle page nous trouvons-nous
		if (!$page)
		{
			// Aucun numÃ©ro de page dÃ©fini... nous dÃ©marrons par la premiÃ¨re page
			$current_page = 1;
		}
		else
		{
			// Un numÃ©ro de page a Ã©tÃ© trouvÃ©... nous dÃ©marrons de celui-ci
			$current_page = $page;

			// Et si le numÃ©ro de page est supÃ©rieur au nombre de pages maximal, nous dÃ©marrons par la derniÃ¨re page
			if($current_page > $nb_pages)
			{
				$current_page = $nb_pages;
			}
		}

		// A partir de quel enregistrement allons-nous dÃ©marrer
		$first_entry = ($current_page - 1) * $nb_users_per_page;

		// Select our phpBoost members (exluded our founder)
		$sql = 'SELECT user_id, group_id
			FROM ' . USERS_TABLE . '
			LIMIT ' . $first_entry . ', ' . $nb_users_per_page;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			// Nous remplissons la table provisoire des utilisateurs : ancien et nouvel ID
			$sql = 'INSERT INTO ' . USER_GROUP_TABLE . ' ' . $db->sql_build_array('INSERT', array(
				'group_id'			=> $row['group_id'],
				'user_id'			=> $row['user_id'],
				'group_leader'		=> 0,
				'user_pending'		=> 0,
			));
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);

		for($i=1; $i<=$nb_pages; $i++)
		{
			//On va faire notre condition
			if($i==$current_page) //Si il s'agit de la page actuelle...
			{
				$next = $i + 1;
				$nb_users = ($current_page == $nb_pages) ? $num_users : $nb_users_per_page * $current_page;
				$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['IMPORT_USERS_FOURTH_STEP_SUCCESS_TITLE'] : $user->lang['IMPORT_USERS_FOURTH_STEP_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('IMPORT_USERS_FOURTH_STEP_SUCCESS_CONTENT', (int) $nb_users, (int) $num_users) : $user->lang('IMPORT_USERS_FOURTH_STEP_PROCESS_CONTENT', (int) $nb_users, (int) $num_users),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['IMPORT_USERS_FOURTH_STEP_NEXT_TITLE'] : $user->lang['IMPORT_USERS_FOURTH_STEP_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
					'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=import_users_fifth_step'),
				));

				// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
				$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=import_users_fifth_step' : 'mode=import_users_fourth_step&amp;page=' . $next);
				// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
				$wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
				($current_page == $nb_pages) ? '' : meta_refresh(SCRIPT_WAITING_TIME_FOR_LOOPS, $redirect_url);
			}
		}		
	break;
	
	case 'import_users_fifth_step':
		// Titre de notre page
		$page_title = $user->lang['USERS_IMPORT_PAGE_TITLE'];

		// Select our default phpBB group_id
		$default_group_name =  'REGISTERED';
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $db->sql_escape($default_group_name) . "'
			AND group_type = " . GROUP_SPECIAL;
		$result = $db->sql_query($sql);
		$default_group_id = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// Nous vidons la table phpbb_user_groups
		$sql = 'DELETE FROM ' . USER_GROUP_TABLE . "
			WHERE group_id = " . $default_group_id['group_id'];
		$db->sql_query($sql);

		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['IMPORT_USERS_FIFTH_STEP_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['IMPORT_USERS_FIFTH_STEP_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['IMPORT_USERS_FIFTH_STEP_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=import_users_sixth_step'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);
	break;
	
	case 'import_users_sixth_step':
		// Titre de notre page
		$page_title = $user->lang['USERS_IMPORT_PAGE_TITLE'];

		// Nous comptons le nombre d'utilisateurs
		$sql = 'SELECT COUNT(user_id) AS num_users
			FROM ' . USERS_TABLE . '
			WHERE user_type <> ' . USER_IGNORE;
		$result = $db->sql_query($sql);
		$num_users = (int) $db->sql_fetchfield('num_users');
		$db->sql_freeresult($result);

		// Select our default phpBB group_id
		$default_group_name =  'REGISTERED';
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $db->sql_escape($default_group_name) . "'
			AND group_type = " . GROUP_SPECIAL;
		$result = $db->sql_query($sql);
		$default_group_id = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		// Combien d'utilisateurs Ã  la fois
		$nb_users_per_page = SCRIPT_MAX_USERS_LOOPS;

		// Nombre de pages nÃ©cessaires
		$nb_pages = ceil($num_users/$nb_users_per_page);

		// Sur quelle page nous trouvons-nous
		if (!$page)
		{
			// Aucun numÃ©ro de page dÃ©fini... nous dÃ©marrons par la premiÃ¨re page
			$current_page = 1;
		}
		else
		{
			// Un numÃ©ro de page a Ã©tÃ© trouvÃ©... nous dÃ©marrons de celui-ci
			$current_page = $page;

			// Et si le numÃ©ro de page est supÃ©rieur au nombre de pages maximal, nous dÃ©marrons par la derniÃ¨re page
			if($current_page > $nb_pages)
			{
				$current_page = $nb_pages;
			}
		}

		// A partir de quel enregistrement allons-nous dÃ©marrer
		$first_entry = ($current_page - 1) * $nb_users_per_page;

		// Select our phpBoost members (exluded our founder)
		$sql = 'SELECT user_id, group_id, user_type
			FROM ' . USERS_TABLE . '
			WHERE user_type <> ' . USER_IGNORE . '
			LIMIT ' . $first_entry . ', ' . $nb_users_per_page;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			// Nous remplissons la table provisoire des utilisateurs : ancien et nouvel ID
			$sql = 'INSERT INTO ' . USER_GROUP_TABLE . ' ' . $db->sql_build_array('INSERT', array(
				'group_id'			=> $default_group_id['group_id'],
				'user_id'			=> $row['user_id'],
				'group_leader'		=> 0,
				'user_pending'		=> 0,
			));
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);

		for($i=1; $i<=$nb_pages; $i++)
		{
			//On va faire notre condition
			if($i==$current_page) //Si il s'agit de la page actuelle...
			{
				$next = $i + 1;
				$nb_users = ($current_page == $nb_pages) ? $num_users : $nb_users_per_page * $current_page;
				$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['IMPORT_USERS_SIXTH_STEP_SUCCESS_TITLE'] : $user->lang['IMPORT_USERS_SIXTH_STEP_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('IMPORT_USERS_SIXTH_STEP_SUCCESS_CONTENT', (int) $nb_users, (int) $num_users) : $user->lang('IMPORT_USERS_SIXTH_STEP_PROCESS_CONTENT', (int) $nb_users, (int) $num_users),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['IMPORT_USERS_SIXTH_STEP_NEXT_TITLE'] : $user->lang['IMPORT_USERS_SIXTH_STEP_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
					'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=import_groups'),
				));

				// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
				$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=import_groups' : 'mode=import_users_sixth_step&amp;page=' . $next);
				// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
				$wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
				($current_page == $nb_pages) ? '' : meta_refresh(SCRIPT_WAITING_TIME_FOR_LOOPS, $redirect_url);
			}
		}	
	break;
	
	case 'prepare_groups_table':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_GROUPS_TABLE_PAGE_TITLE'];
		
		// Nous supprimons toute table de prÃ©-migration des utilisateurs existante
		$sql = 'DROP TABLE IF EXISTS ' . CONVERT_GROUPS_TABLE;
		$db->sql_query($sql);
		
		// Nous crÃ©ons la table de prÃ©-migration des utilisateurs
		$sql = 'CREATE TABLE IF NOT EXISTS ' . CONVERT_GROUPS_TABLE . ' (
			convert_group_id mediumint(8) unsigned NOT NULL,
			convert_group_new_id mediumint(8) unsigned NOT NULL,
			convert_group_name varchar(255) COLLATE utf8_bin NOT NULL DEFAULT "",
			convert_group_img varchar(255) COLLATE utf8_bin NOT NULL DEFAULT "",
			convert_group_color varchar(255) COLLATE utf8_bin NOT NULL DEFAULT "",
			convert_group_auth varchar(255) COLLATE utf8_bin NOT NULL DEFAULT "",
			convert_group_members_id varchar(255) COLLATE utf8_bin NOT NULL DEFAULT "",
			PRIMARY KEY (convert_group_id)
		)';
		$db->sql_query($sql);
		
		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['PREPARE_GROUPS_TABLE_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['PREPARE_GROUPS_TABLE_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['PREPARE_GROUPS_TABLE_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=prepare_groups_convert'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);
	break;
	
	case 'prepare_groups_convert':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_GROUPS_CONVERT_PAGE_TITLE'];

			// Nous sÃ©lectionnons le plus grand user_id de la table phpbb_groups
			$sql = 'SELECT MAX(group_id) as max_phpbb_group_id
			FROM ' . GROUPS_TABLE;
			$result = $db->sql_query($sql);
			$max_phpbb_group_id = (int) $db->sql_fetchfield('max_phpbb_group_id');
			$db->sql_freeresult($result);

			// Nous comptons le nombre de groupes
			$sql = 'SELECT COUNT(id) AS num_groups
				FROM ' . BOOST_GROUPS;
			$result = $db->sql_query($sql);
			$num_groups = (int) $db->sql_fetchfield('num_groups');
			$db->sql_freeresult($result);
			
			// Combien d'utilisateurs Ã  la fois
			$nb_groups_per_page = SCRIPT_MAX_GROUPS_LOOPS;
			
			// Nombre de pages nÃ©cessaires
			$nb_pages = ceil($num_groups/$nb_groups_per_page);
			
			// Sur quelle page nous trouvons-nous
			if (!$page)
			{
				// Aucun numÃ©ro de page dÃ©fini... nous dÃ©marrons par la premiÃ¨re page
				$current_page = 1;
			}
			else
			{
				// Un numÃ©ro de page a Ã©tÃ© trouvÃ©... nous dÃ©marrons de celui-ci
				$current_page = $page;
				
				// Et si le numÃ©ro de page est supÃ©rieur au nombre de pages maximal, nous dÃ©marrons par la derniÃ¨re page
				if($current_page > $nb_pages)
				{
					$current_page = $nb_pages;
				}
			}
			
			// A partir de quel enregistrement allons-nous dÃ©marrer
			$first_entry = ($current_page - 1) * $nb_groups_per_page;

		// Nous sÃ©lectionnons tous les groupes phpBoost
		$sql = 'SELECT id, name, img, color, auth, members 
		FROM ' . BOOST_GROUPS . '
		LIMIT ' . $first_entry . ', ' . $nb_groups_per_page;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			// Nous remplissons la table provisoire des groupes : ancien et nouvel ID
			$sql = 'INSERT INTO ' . CONVERT_GROUPS_TABLE . ' ' . $db->sql_build_array('INSERT', array(
			'convert_group_id'				=> $row['id'],
			'convert_group_new_id'			=> $row['id'] + $max_phpbb_group_id,
			'convert_group_name'			=> $row['name'],
			'convert_group_img'				=> $row['img'],
			'convert_group_color'			=> $row['color'],
			'convert_group_auth'			=> $row['auth'],		
			'convert_group_members_id'		=> $row['members'],
			));
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);
		
		for($i=1; $i<=$nb_pages; $i++)
		{
			 //On va faire notre condition
			 if($i==$current_page) //Si il s'agit de la page actuelle...
			 {
				$next = $i + 1;
				$nb_groups = ($current_page == $nb_pages) ? $num_groups : $nb_groups_per_page * $current_page;
				$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['PREPARE_GROUPS_CONVERT_SUCCESS_TITLE'] : $user->lang['PREPARE_GROUPS_CONVERT_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('PREPARE_GROUPS_CONVERT_SUCCESS_CONTENT', (int) $nb_groups, (int) $num_groups) : $user->lang('PREPARE_GROUPS_CONVERT_PROCESS_CONTENT', (int) $nb_groups, (int) $num_groups),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['PREPARE_GROUPS_CONVERT_NEXT_TITLE'] : $user->lang['PREPARE_GROUPS_CONVERT_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
				'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=import_groups'),
				));
		
				// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
				$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=import_groups' : 'mode=prepare_groups_convert&amp;page=' . $next);
				// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
				$wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
				($current_page == $nb_pages) ? '' : meta_refresh(SCRIPT_WAITING_TIME_FOR_LOOPS, $redirect_url);
			 }
		}			
	break;

	case 'import_groups':
		$page_title = 'Importation des groupes';
		
	// In case we come back to this step... we delete all entries in phpBB users_copy_table except founder
	$sql = 'DELETE FROM ' . GROUPS_TABLE . '
		WHERE group_type != ' . GROUP_SPECIAL;
	$db->sql_query($sql);

	// Let's show a list of all groups from phpboost board
	$sql = 'SELECT convert_group_id, convert_group_new_id, convert_group_name, convert_group_img, convert_group_color, convert_group_auth, convert_group_members_id
		FROM ' . CONVERT_GROUPS_TABLE;
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		// Group vars
		$group_row = array(
			'group_id'				=> $row['convert_group_new_id'],
			'group_type'			=> (int) GROUP_OPEN,
			'group_founder_manage'	=> 0,
			'group_skip_auth'		=> 0,
			'group_name'			=> (string) $row['convert_group_name'],
			'group_desc'			=> (string) 'Group from Boost',
			'group_desc_bitfield'	=> '',
			'group_desc_options'	=> 7,
			'group_desc_uid'		=> '',
			'group_display'			=> 0,
			'group_avatar	'		=> '',
			'group_avatar_type'		=> 0,
			'group_avatar_width'	=> 0,
			'group_avatar_height'	=> 0,
			'group_rank'			=> 0,
			'group_colour'			=> $row['convert_group_color'],
			'group_sig_chars'		=> 0,
			'group_receive_pm'		=> 0,
			'group_message_limit'	=> 0,
			'group_max_recipients'	=> 5,
			'group_legend'			=> 0,	
		);
		
		$sql = 'INSERT INTO ' . GROUPS_TABLE . ' ' . $db->sql_build_array('INSERT', $group_row);
		$db->sql_query($sql);		
	}
	$db->sql_freeresult($result);
	
	$template->assign_vars(array(
				'PAGE_CONTENT_TITLE'		=> 'Import des groupes',
				'PAGE_CONTENT'		=> 'Import des groupes rÃ©ussi, toutes les fÃ©licitations de la part de notre groupe !',
				
				'PAGE_NEXT_TITLE'	=> 'PrÃ©parer la page des groupes de utilisateurs',
				'PAGE_NEXT_URL_SHOW'	=> true,
				'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=prepare_users_groups_table'),
				));
	break;
	
	case 'prepare_users_groups_table':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_USERS_GROUPS_TABLE_PAGE_TITLE'];
		
		// Nous supprimons toute table de prÃ©-migration des utilisateurs existante
		$sql = 'DROP TABLE IF EXISTS ' . CONVERT_USERS_GROUPS_TABLE;
		$db->sql_query($sql);

		// Nous crÃ©ons la table de prÃ©-migration des groupes d'utilisateurs
		$sql = 'CREATE TABLE IF NOT EXISTS ' . CONVERT_USERS_GROUPS_TABLE . ' (
		convert_user_group_id mediumint(8) unsigned NOT NULL,
		convert_user_group_old_user_id mediumint(8) unsigned NOT NULL,
		convert_user_group_new_user_id mediumint(8) unsigned NOT NULL
		)';
		$db->sql_query($sql);

		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['PREPARE_USERS_GROUPS_TABLE_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['PREPARE_USERS_GROUPS_TABLE_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['PREPARE_USERS_GROUPS_TABLE_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=prepare_users_groups_convert'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);		
	break;
	
	case 'prepare_users_groups_convert':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_USERS_GROUPS_CONVERT_PAGE_TITLE'];	
		
			// Nous sÃ©lectionnons le plus grand user_id de la table phpbb_groups
			$sql = 'SELECT COUNT(convert_group_id) as num_convert_group_id
			FROM ' . CONVERT_GROUPS_TABLE;
			$result = $db->sql_query($sql);
			$num_convert_group_id = (int) $db->sql_fetchfield('num_convert_group_id');
			$db->sql_freeresult($result);	

			// Combien d'utilisateurs Ã  la fois
			$nb_convert_groups_per_page = SCRIPT_MAX_USERS_GROUPS_LOOPS;
			
			// Nombre de pages nÃ©cessaires
			$nb_pages = ceil($num_convert_group_id/$nb_convert_groups_per_page);
			
			// Sur quelle page nous trouvons-nous
			if (!$page)
			{
				// Aucun numÃ©ro de page dÃ©fini... nous dÃ©marrons par la premiÃ¨re page
				$current_page = 1;
			}
			else
			{
				// Un numÃ©ro de page a Ã©tÃ© trouvÃ©... nous dÃ©marrons de celui-ci
				$current_page = $page;
				
				// Et si le numÃ©ro de page est supÃ©rieur au nombre de pages maximal, nous dÃ©marrons par la derniÃ¨re page
				if($current_page > $nb_pages)
				{
					$current_page = $nb_pages;
				}
			}
			
			// A partir de quel enregistrement allons-nous dÃ©marrer
			$first_entry = ($current_page - 1) * $nb_convert_groups_per_page;

			// Let's show a list of all groups from phpboost board
		$sql = 'SELECT convert_group_id, convert_group_new_id, convert_group_members_id
		FROM ' . CONVERT_GROUPS_TABLE . '
		LIMIT ' . $first_entry . ', ' . $nb_convert_groups_per_page;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$members = explode('|', $row['convert_group_members_id']);

			foreach ($members as $user_id)
			{
				if ($user_id)
				{
					// Group vars
					$group_users_row = array(
					'convert_user_group_id'			=> $row['convert_group_new_id'],
					'convert_user_group_old_user_id'			=> $user_id,
					'convert_user_group_new_user_id'		=> 0,
					);

					$sql = 'INSERT INTO ' . CONVERT_USERS_GROUPS_TABLE . ' ' . $db->sql_build_array('INSERT', $group_users_row);
					$db->sql_query($sql);
				}
			}
		}
		$db->sql_freeresult($result);

		for($i=1; $i<=$nb_pages; $i++)
		{
			 //On va faire notre condition
			 if($i==$current_page) //Si il s'agit de la page actuelle...
			 {
				$next = $i + 1;
				$nb_groups = ($current_page == $nb_pages) ? $num_convert_group_id : $nb_convert_groups_per_page * $current_page;
				$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['PREPARE_GROUPS_CONVERT_SUCCESS_TITLE'] : $user->lang['PREPARE_GROUPS_CONVERT_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('PREPARE_GROUPS_CONVERT_SUCCESS_CONTENT', (int) $nb_groups, (int) $num_convert_group_id) : $user->lang('PREPARE_GROUPS_CONVERT_PROCESS_CONTENT', (int) $nb_groups, (int) $num_convert_group_id),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['PREPARE_GROUPS_CONVERT_NEXT_TITLE'] : $user->lang['PREPARE_GROUPS_CONVERT_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
				'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=sync_users_groups'),
				));
		
				// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
				$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=sync_users_groups' : 'mode=prepare_users_groups_convert&amp;page=' . $next);
				// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
				$wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
				meta_refresh($wait_for_next, $redirect_url);
			 }
		}
	break;
	
	case 'sync_users_groups':
		// Titre de notre page
		$page_title = $user->lang['USERS_GROUPS_SYNC_PAGE_TITLE'];

		$sql = 'SELECT cugt.convert_user_group_old_user_id, cvt.convert_user_id, cvt.convert_user_new_id
		FROM ' . CONVERT_USERS_GROUPS_TABLE . ' cugt
		LEFT JOIN ' . CONVERT_USERS_TABLE . ' cvt
		ON (cugt.convert_user_group_old_user_id = cvt.convert_user_id)';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$sql = 'UPDATE ' . CONVERT_USERS_GROUPS_TABLE . ' SET convert_user_group_new_user_id = ' . $row['convert_user_new_id'] . ' WHERE convert_user_group_old_user_id = ' . $row['convert_user_id'];
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);

		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'		=> $user->lang['SYNC_USERS_GROUPS_TABLE_TITLE'],
			'PAGE_CONTENT'				=> $user->lang['SYNC_USERS_GROUPS_TABLE_CONTENT'],
			
			'PAGE_NEXT_TITLE'	=> $user->lang['SYNC_USERS_GROUPS_TABLE_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=import_users_groups'),
		));		
	break;
	
	case 'import_users_groups':
		// Titre de notre page
		$page_title = $user->lang['USERS_GROUPS_IMPORT_PAGE_TITLE'];

		// Let's show a list of all groups from phpboost board
		$sql = 'SELECT convert_user_group_id, convert_user_group_old_user_id, convert_user_group_new_user_id
			FROM ' . CONVERT_USERS_GROUPS_TABLE;
		$result = $db->sql_query($sql);
		
		while ($row = $db->sql_fetchrow($result))
		{
			// Group vars
			$group_users_row = array(
				'group_id'			=> $row['convert_user_group_id'],
				'user_id'			=> $row['convert_user_group_new_user_id'],
				'group_leader'		=> 0,
				'user_pending'		=> 0,
			);
			
			$sql = 'INSERT INTO ' . USER_GROUP_TABLE . ' ' . $db->sql_build_array('INSERT', $group_users_row);
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);
		
	$template->assign_vars(array(
				'PAGE_CONTENT_TITLE'		=> $user->lang['USERS_GROUPS_IMPORT_TITLE'],
				'PAGE_CONTENT'		=> $user->lang['USERS_GROUPS_IMPORT_CONTENT'],
				
				'PAGE_NEXT_TITLE'	=> $user->lang['USERS_GROUPS_IMPORT_NEXT_TITLE'],
				'PAGE_NEXT_URL_SHOW'	=> true,
				'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=import_cats'),
				));		
	break;
	
	case 'import_cats':
		// Titre de notre page
		$page_title = $user->lang['IMPORT_CATS_MAIN_TITLE'];

		// First we delete our existant phpBB categories and forums
		$sql = 'DELETE FROM ' . FORUMS_TABLE;
		$db->sql_query($sql);
				
		// Select our phpboost forums
		$sql = 'SELECT id, id_left, id_right, level, name, subname, nbr_topic, nbr_msg, status, url 
			FROM ' . BOOST_FORUMS;
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

				//'forum_posts'	=> $row['nbr_msg'], // tornade to phpbb 3.0.14
				'forum_posts_approved'	=> $row['nbr_msg'], // tornade to phpbb 3.1.7-PL1
				//'forum_topics'	=> $row['nbr_topic'], // tornade to phpbb 3.0.14
				'forum_topics_approved'	=> $row['nbr_topic'], // tornade to phpbb 3.1.7-PL1
				'forum_link'			=> $row['url'],
				'forum_rules'			=> '',
				'display_subforum_list'	=> ($row['level'] > 1) ? 0 : 1,
				'display_on_index'	=> ($row['level'] > 2) ? 0 : 1,
			);

			// We insert all datas
			$sql = 'INSERT INTO ' . FORUMS_TABLE . ' ' . $db->sql_build_array('INSERT', $forum_data_sql);
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);

		// Select our phpboost forums
		$sql = 'SELECT MAX(level) as max_level
			FROM ' . BOOST_FORUMS;
		$result = $db->sql_query($sql);
		$max_level = (int) $db->sql_fetchfield('max_level');

		for ($x = 0; $x <= $max_level; $x++)
		{
			$sql = 'SELECT id, level, id_left, id_right
				FROM ' . BOOST_FORUMS . '
				WHERE level = ' . $x;
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$sql2 = 'SELECT id, level, id_left, id_right
					FROM ' . BOOST_FORUMS . '
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

		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'		=> $user->lang['IMPORT_CATS_TITLE'],
			'PAGE_CONTENT'		=> $user->lang['MIGRATION_CATS_CONTENT'],

			'PAGE_NEXT_TITLE'	=> $user->lang['IMPORT_CATS_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=prepare_topics_table'),
		));		
	break;

	case 'prepare_topics_table':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_TOPICS_TABLE_PAGE_TITLE'];
		
		// Nous supprimons toute table de prÃ©-migration des utilisateurs existante
		$sql = 'DROP TABLE IF EXISTS ' . CONVERT_TOPICS_TABLE;
		$db->sql_query($sql);

		// Nous crÃ©ons la table de prÃ©-migration des groupes d'utilisateurs
		$sql = 'CREATE TABLE IF NOT EXISTS ' . CONVERT_TOPICS_TABLE . ' (
			convert_topic_id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			convert_forum_id mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_topic_title varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT "",
			convert_topic_poster mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_topic_poster_new mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_topic_replies_real mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_topic_views mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_topic_last_poster_id mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_topic_last_poster_id_new mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_topic_last_post_id mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_topic_last_post_time int(11) unsigned NOT NULL DEFAULT "0",
			convert_topic_first_post_id mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_topic_type tinyint(3) NOT NULL DEFAULT "0",
			convert_topic_status tinyint(3) NOT NULL DEFAULT "0",
			convert_topic_approved tinyint(1) unsigned NOT NULL DEFAULT "1",
			PRIMARY KEY (convert_topic_id)
		)';
		$db->sql_query($sql);

		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['PREPARE_TOPICS_TABLE_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['PREPARE_TOPICS_TABLE_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['PREPARE_TOPICS_TABLE_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=prepare_topics'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);		
	break;
	
	case 'prepare_topics':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_TOPICS_CONVERT_PAGE_TITLE'];
		
		// How many topics ?
		$sql = 'SELECT COUNT(id) AS num_topics
			FROM ' . BOOST_TOPICS;
		$result = $db->sql_query($sql);
		$num_topics = (int) $db->sql_fetchfield('num_topics');
		$db->sql_freeresult($result);

		// How many topics by loop ?
		$nb_topics_per_page = SCRIPT_MAX_TOPICS_LOOPS;

		// Calculate how many pages are necessary
		$nb_pages = ceil($num_topics/$nb_topics_per_page);

		if (($page && empty($page)) || !$page)
		{
			// We have no page value so... let's start with first page
			$current_page = 1;
		}
		else
		{
			$current_page = $page;

			// Our current_page is bigger than our total of pages...
			if($current_page > $nb_pages)
			{
				$current_page = $nb_pages;
			}
		}

		// Which entry do we have ?
		$first_entry = ($current_page - 1) * $nb_topics_per_page;

		// Let's show a list of all topics from phpboost board
		$sql = 'SELECT id, idcat, title, user_id, nbr_msg, nbr_views, last_user_id, last_msg_id, last_timestamp, first_msg_id, type, status, aprob
			FROM ' . BOOST_TOPICS . '
			LIMIT ' . $first_entry . ', ' . $nb_topics_per_page;;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			// Group vars
			$convert_topics_row = array(
				'convert_topic_id'					=> $row['id'],
				'convert_forum_id'					=> $row['idcat'],
				'convert_topic_title'				=> $row['title'],
				'convert_topic_poster'				=> $row['user_id'],
				'convert_topic_poster_new'			=> 0, // new field
				'convert_topic_replies_real'		=> $row['nbr_msg'],
				'convert_topic_views'				=> $row['nbr_views'],
				'convert_topic_last_poster_id'		=> $row['last_user_id'],
				'convert_topic_last_poster_id_new'	=> 0, // new field
				'convert_topic_last_post_id'		=> $row['last_msg_id'],
				'convert_topic_last_post_time'		=> $row['last_timestamp'],
				'convert_topic_first_post_id'		=> $row['first_msg_id'],
				'convert_topic_type'				=> ($row['type'] == BOOST_TOPIC_TYPE_ANNOUNCE) ? POST_ANNOUNCE : (($row['type'] == BOOST_TOPIC_TYPE_STICKY) ? POST_STICKY : POST_NORMAL), // 0 = normal // 1 = Ã©pinglÃ© // 2 = annonce
				'convert_topic_status'				=> (!$row['status'] == BOOST_TOPIC_STATUS_LOCKED) ? ITEM_LOCKED : BOOST_TOPIC_STATUS_UNLOCKED, // 0 = verrouillÃ© // 1 = dÃ©verrouiller
				'convert_topic_approved'			=> 1,
			);

			$sql = 'INSERT INTO ' . CONVERT_TOPICS_TABLE . ' ' . $db->sql_build_array('INSERT', $convert_topics_row);
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);

		for($i=1; $i<=$nb_pages; $i++)
		{
			//On va faire notre condition
			if($i==$current_page) //Si il s'agit de la page actuelle...
			{
			$next = $i + 1;
			$nb_topics = ($current_page == $nb_pages) ? $num_topics : $nb_topics_per_page * $current_page;
			$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['PREPARE_TOPICS_SUCCESS_TITLE'] : $user->lang['PREPARE_TOPICS_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('PREPARE_TOPICS_SUCCESS_CONTENT', (int) $nb_topics, (int) $num_topics) : $user->lang('PREPARE_TOPICS_PROCESS_CONTENT', (int) $nb_topics, (int) $num_topics),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['PREPARE_TOPICS_NEXT_TITLE'] : $user->lang['PREPARE_TOPICS_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
				'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=sync_topics'),
			));

			// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
			$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=sync_topics' : 'mode=prepare_topics&amp;page=' . $next);
			// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
			$wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
			meta_refresh($wait_for_next, $redirect_url);
			}
		}	
	break;
	
	case 'sync_topics':
		// Titre de notre page
		$page_title = $user->lang['TOPICS_SYNC_PAGE_TITLE'];

		//PremiÃ¨re synchronisation des auteurs dans les sujets
		$sql = 'SELECT ctt.convert_topic_poster, cut.convert_user_id, cut.convert_user_new_id
		FROM ' . CONVERT_TOPICS_TABLE . ' ctt
		LEFT JOIN ' . CONVERT_USERS_TABLE . ' cut
		ON (ctt.convert_topic_poster = cut.convert_user_id)';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
		$sql = 'UPDATE ' . CONVERT_TOPICS_TABLE . ' SET convert_topic_poster_new = ' . $row['convert_user_new_id'] . ' WHERE convert_topic_poster = ' . $row['convert_user_id'];
		$db->sql_query($sql);
		}
		$db->sql_freeresult($result);

		// Seconde synchronisation des auteurs dans les sujets

		$sql = 'SELECT ctt.convert_topic_last_poster_id, cut.convert_user_id, cut.convert_user_new_id
		FROM ' . CONVERT_TOPICS_TABLE . ' ctt
		LEFT JOIN ' . CONVERT_USERS_TABLE . ' cut
		ON (ctt.convert_topic_last_poster_id = cut.convert_user_id)';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
		$sql = 'UPDATE ' . CONVERT_TOPICS_TABLE . ' SET convert_topic_last_poster_id_new = ' . $row['convert_user_new_id'] . ' WHERE convert_topic_last_poster_id = ' . $row['convert_user_id'];
		$db->sql_query($sql);
		}
		$db->sql_freeresult($result);
		
		$template->assign_vars(array(
		'PAGE_CONTENT_TITLE'		=> $user->lang['SYNC_TOPICS_TABLE_TITLE'],
		'PAGE_CONTENT'				=> $user->lang['SYNC_TOPICS_TABLE_CONTENT'],
		
		'PAGE_NEXT_TITLE'	=> $user->lang['SYNC_TOPICS_TABLE_NEXT_TITLE'],
		'PAGE_NEXT_URL_SHOW'	=> true,
		'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=prepare_topics_import'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		$redirect_url = append_sid("convert.$phpEx", 'mode=prepare_topics_import');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);
	break;

	case 'prepare_topics_import':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_TOPICS_IMPORT_PAGE_TITLE'];
		
		// Nous supprimons toute table de prÃ©-migration des utilisateurs existante
		$sql = 'DELETE FROM ' . TOPICS_TABLE;
		$db->sql_query($sql);

		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['PREPARE_TOPICS_IMPORT_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['PREPARE_TOPICS_IMPORT_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['PREPARE_TOPICS_IMPORT_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=import_topics'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);		
	break;
	
	case 'import_topics':
		$page_title = $user->lang['TOPICS_IMPORT_PAGE_TITLE'];
			// How many topics ?
			$sql = 'SELECT COUNT(convert_topic_id) AS num_topics
				FROM ' . CONVERT_TOPICS_TABLE;
			$result = $db->sql_query($sql);
			$num_topics = (int) $db->sql_fetchfield('num_topics');
			$db->sql_freeresult($result);
			
			// How many topics by loop ?
			$nb_topics_per_page = SCRIPT_MAX_TOPICS_LOOPS;
			
			// Calculate how many pages are necessary
			$nb_pages = ceil($num_topics/$nb_topics_per_page);
			
			if (!$page)
			{
				// We have no page value so... let's start with first page
				$current_page = 1;
			}
			else
			{
				$current_page = $page;
				
				// Our current_page is bigger than our total of pages...
				if($current_page > $nb_pages)
				{
					$current_page = $nb_pages;
				}
			}
			
			// Which entry do we have ?
			$first_entry = ($current_page - 1) * $nb_topics_per_page;
			
			// La requÃªte sql pour rÃ©cupÃ©rer les messages de la page actuelle.
			$sql = 'SELECT ctt.convert_topic_id, ctt.convert_forum_id, ctt.convert_topic_title, ctt.convert_topic_poster_new, ctt.convert_topic_replies_real, ctt.convert_topic_views, ctt.convert_topic_last_poster_id_new, ctt.convert_topic_last_post_id, ctt.convert_topic_last_post_time, ctt.convert_topic_first_post_id, ctt.convert_topic_type, ctt.convert_topic_status, ctt.convert_topic_approved, cpt.convert_post_id, cpt.convert_topic_id, cpt.convert_poster_id_new, cpt.convert_contents, cpt.convert_timestamp, cpt.convert_timestamp_edit, cpt.convert_post_edit_user, cpt.convert_post_edit_user_new, cpt.convert_user_ip
			FROM ' . CONVERT_TOPICS_TABLE . ' ctt
			LEFT JOIN ' . CONVERT_POSTS_TABLE . ' cpt
			ON (ctt.convert_topic_id = cpt.convert_topic_id)
			WHERE cpt.convert_post_id = ctt.convert_topic_first_post_id
			ORDER BY ctt.convert_topic_id ASC
			LIMIT ' . $first_entry . ', ' . $nb_topics_per_page;
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$sql_username = 'SELECT *
					FROM ' . USERS_TABLE . '
					WHERE user_id = ' . $row['convert_topic_poster_new'];
				$result_username = $db->sql_query($sql_username);
				$row_username = $db->sql_fetchrow($result_username);
				$db->sql_freeresult($result_username);
				$user->data = array_merge($user->data, $row_username);
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
				$msg  = $row['convert_contents'];
				//$phpboost_bbcodes = array('<strong>', '</strong>', '<em>', '</em>');
				//$phpb_bbcodes   = array('[b]', '[/b]', '[i]', '[/i]');
				//$msg = preg_replace('#\<strong>(.+)<\/strong>#iUs', '[b]$1[/b]', $msg);
				//$msg = preg_replace('#\<em>(.+)<\/em>#iUs', '[i]$1[/i]', $msg);
				//$msg = preg_replace('#\<span style="text-decoration: underline;">(.+)<\/span>#iUs', '[u]$1[/u]', $msg);
				//$msg = preg_replace('#\<br \/>#iUs',"\n", $msg);
				//$msg = preg_replace('#\"\n\n"#iUs',"\n", $msg);
 
				//$msg = str_replace($phpboost_bbcodes, $phpb_bbcodes, $msg);
				
				// Some post parameters
				$poll = $uid = $bitfield = $options = '';
				generate_text_for_storage($row['convert_topic_title'], $uid, $bitfield, $options, false, false, false);
				generate_text_for_storage($msg, $uid, $bitfield, $options, true, true, true);				

				// Some vars to insert into DB
				$data = array(
					'topic_id'			=> $row['convert_topic_id'],
					'post_id'			=> $row['convert_topic_first_post_id'],
					'forum_id'  		=> $row['convert_forum_id'],
					'icon_id'  			=> false,
					'poster_id'			=> $row['convert_topic_poster_new'],
					'enable_bbcode' 	=> true,
					'enable_smilies'	=> true,
					'enable_urls'  		=> true,
					'enable_sig'  		=> true,
					'message'  			=> $msg,
					'message_md5'   	=> md5($msg),
					'bbcode_bitfield'   => $bitfield,
					'bbcode_uid'  		=> $uid,
					'post_edit_locked'  => 0,
					'topic_title'  		=> $row['convert_topic_title'],
					'notify_set'  		=> false,
					'notify' 			=> true,
					'post_time'   		=> $row['convert_timestamp'],
					'forum_name'  		=> '',
					'enable_indexing'   => true,
					'topic_approved'	=> 1,
					'post_approved'		=> 1,							
				);

					
				// Submitting our post
				//$post_reply = ($select_post_type == 1) ? 'post' : 'reply';
				$post_reply = 'post';
				//echo $data['topic_id'];
				submit_post($post_reply, $row['convert_topic_title'], $row_username['username'], POST_NORMAL, $poll, $data);
		}
		$db->sql_freeresult($result);
		
		for($i=1; $i<=$nb_pages; $i++)
		{
			 //On va faire notre condition
			 if($i==$current_page) //Si il s'agit de la page actuelle...
			 {
				$next = $i + 1;
				$nb_topics = ($current_page == $nb_pages) ? $num_topics : $nb_topics_per_page * $current_page;
				$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['PREPARE_TOPICS_IMPORT_SUCCESS_TITLE'] : $user->lang['PREPARE_TOPICS_IMPORT_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('PREPARE_TOPICS_IMPORT_SUCCESS_CONTENT', (int) $nb_topics, (int) $num_topics) : $user->lang('PREPARE_TOPICS_IMPORT_PROCESS_CONTENT', (int) $nb_topics, (int) $num_topics),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['PREPARE_GROUPS_CONVERT_NEXT_TITLE'] : $user->lang['PREPARE_TOPICS_IMPORT_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
				'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=prepare_posts_table'),
				));
		
				// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
				$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=prepare_posts_table' : 'mode=import_topics&amp;page=' . $next);
				// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
				$wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
				($current_page == $nb_pages) ? '' : meta_refresh(SCRIPT_WAITING_TIME_FOR_LOOPS, $redirect_url);
			 }
		}
break;

	case 'prepare_posts_table':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_POSTS_TABLE_PAGE_TITLE'];

		// Nous supprimons toute table de prÃ©-migration des utilisateurs existante
		$sql = 'DROP TABLE IF EXISTS ' . CONVERT_POSTS_TABLE;
		$db->sql_query($sql);

		// Nous crÃ©ons la table de prÃ©-migration des groupes d'utilisateurs
		$sql = 'CREATE TABLE IF NOT EXISTS ' . CONVERT_POSTS_TABLE . ' (
			convert_post_id mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			convert_topic_id mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_poster_id mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_poster_id_new mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_contents mediumtext COLLATE utf8_bin NOT NULL,
			convert_timestamp int(11) unsigned NOT NULL DEFAULT "0",
			convert_timestamp_edit int(11) unsigned NOT NULL DEFAULT "0",
			convert_post_edit_user mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_post_edit_user_new mediumint(8) unsigned NOT NULL DEFAULT "0",
			convert_user_ip varchar(40) COLLATE utf8_bin NOT NULL DEFAULT "",
			PRIMARY KEY (convert_post_id)
		)';
		$db->sql_query($sql);

		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['PREPARE_POSTS_TABLE_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['PREPARE_POSTS_TABLE_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['PREPARE_POSTS_TABLE_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=prepare_posts'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);		
	break;
	
	case 'prepare_posts':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_POSTS_CONVERT_PAGE_TITLE'];
		
			// How many posts ?
			$sql = 'SELECT COUNT(id) AS num_posts
				FROM ' . BOOST_POSTS;
			$result = $db->sql_query($sql);
			$num_posts = (int) $db->sql_fetchfield('num_posts');
			$db->sql_freeresult($result);
			
			// How many posts by loop ?
			$nb_posts_per_page = 25;
			
			// Calculate how many pages are necessary
			$nb_pages = ceil($num_posts/$nb_posts_per_page);
			
			if (($page && empty($page)) || !$page)
			{
				// We have no page value so... let's start with first page
				$current_page = 1;
			}
			else
			{
				$current_page = $page;
				
				// Our current_page is bigger than our total of pages...
				if($current_page > $nb_pages)
				{
					$current_page = $nb_pages;
				}
			}
			
			// Which entry do we have ?
			$first_entry = ($current_page - 1) * $nb_posts_per_page;

			// Let's show a list of all topics from phpboost board
		$sql = 'SELECT id, idtopic, user_id, contents, timestamp, timestamp_edit, user_id_edit, user_ip
		FROM ' . BOOST_POSTS . '
		LIMIT ' . $first_entry . ', ' . $nb_posts_per_page;;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			// Group vars
			$convert_posts_row = array(
			'convert_post_id'					=> $row['id'],
			'convert_topic_id'					=> $row['idtopic'],
			'convert_poster_id'					=> $row['user_id'],
			'convert_poster_id_new'				=> 0, // new field
			'convert_contents'					=> $row['contents'],
			'convert_timestamp'					=> $row['timestamp'],
			'convert_timestamp_edit'			=> $row['timestamp_edit'],
			'convert_post_edit_user'			=> $row['user_id_edit'],
			'convert_post_edit_user_new'		=> 0, // new field
			'convert_user_ip'					=> $row['user_ip'],
			);
		
			$sql = 'INSERT INTO ' . CONVERT_POSTS_TABLE . ' ' . $db->sql_build_array('INSERT', $convert_posts_row);
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);

		for($i=1; $i<=$nb_pages; $i++)
		{
			 //On va faire notre condition
			 if($i==$current_page) //Si il s'agit de la page actuelle...
			 {
				$next = $i + 1;
			$nb_posts = ($current_page == $nb_pages) ? $num_posts : $nb_posts_per_page * $current_page;
			$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['PREPARE_POSTS_SUCCESS_TITLE'] : $user->lang['PREPARE_POSTS_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('PREPARE_POSTS_SUCCESS_CONTENT', (int) $nb_posts, (int) $num_posts) : $user->lang('PREPARE_POSTS_PROCESS_CONTENT', (int) $nb_posts, (int) $num_posts),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['PREPARE_POSTS_NEXT_TITLE'] : $user->lang['PREPARE_POSTS_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
				'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=sync_posts'),
				));
		
				// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
				$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=sync_posts' : 'mode=prepare_posts&amp;page=' . $next);
				// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
				$wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
				($current_page == $nb_pages) ? '' : meta_refresh(SCRIPT_WAITING_TIME_FOR_LOOPS, $redirect_url);
			 }
		}
	break;
	
	case 'sync_posts':
		// Titre de notre page
		$page_title = $user->lang['POSTS_SYNC_PAGE_TITLE'];

			// PremiÃ¨re synchronisation des auteurs dans les messages
		$sql = 'SELECT cpt.convert_poster_id, cut.convert_user_id, cut.convert_user_new_id
		FROM ' . CONVERT_POSTS_TABLE . ' cpt
		LEFT JOIN ' . CONVERT_USERS_TABLE . ' cut
		ON (cpt.convert_poster_id = cut.convert_user_id)';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
		$sql = 'UPDATE ' . CONVERT_POSTS_TABLE . ' SET convert_poster_id_new = ' . $row['convert_user_new_id'] . ' WHERE convert_poster_id = ' . $row['convert_user_id'];
		$db->sql_query($sql);
		}
		$db->sql_freeresult($result);
		
		// Seconde synchronisation des auteurs dans les messages
		$sql = 'SELECT cpt.convert_post_edit_user, cut.convert_user_id, cut.convert_user_new_id
		FROM ' . CONVERT_POSTS_TABLE . ' cpt
		LEFT JOIN ' . CONVERT_USERS_TABLE . ' cut
		ON (cpt.convert_post_edit_user = cut.convert_user_id)';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			if ($row['convert_post_edit_user'] != 0)
			{
			$sql = 'UPDATE ' . CONVERT_POSTS_TABLE . ' SET convert_post_edit_user_new = ' . $row['convert_user_new_id'] . ' WHERE convert_post_edit_user = ' . $row['convert_user_id'];
			$db->sql_query($sql);
			}
		}
		$db->sql_freeresult($result);
		
		$template->assign_vars(array(
		'PAGE_CONTENT_TITLE'		=> $user->lang['SYNC_POSTS_TABLE_TITLE'],
		'PAGE_CONTENT'				=> $user->lang['SYNC_POSTS_TABLE_CONTENT'],
		
		'PAGE_NEXT_TITLE'	=> $user->lang['SYNC_POSTS_TABLE_NEXT_TITLE'],
		'PAGE_NEXT_URL_SHOW'	=> true,
		'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=prepare_posts_import'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		$redirect_url = append_sid("convert.$phpEx", 'mode=prepare_posts_import');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);	
	break;
	
	case 'prepare_posts_import':
		// Titre de notre page
		$page_title = $user->lang['PREPARE_POSTS_IMPORT_PAGE_TITLE'];

		// Nous supprimons toute table de prÃ©-migration des utilisateurs existante
		$sql = 'DELETE FROM ' . POSTS_TABLE;
		$db->sql_query($sql);
		
		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['PREPARE_POSTS_IMPORT_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['PREPARE_POSTS_IMPORT_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['PREPARE_POSTS_IMPORT_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=import_posts'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);
	break;
	
	case 'import_posts':
		$page_title = $user->lang['POSTS_IMPORT_PAGE_TITLE'];

			// How many topics ?
			$sql = 'SELECT COUNT(convert_post_id) AS num_posts
				FROM ' . CONVERT_POSTS_TABLE;
			$result = $db->sql_query($sql);
			$num_posts = (int) $db->sql_fetchfield('num_posts');
			$db->sql_freeresult($result);
			
			// How many topics by loop ?
			$nb_posts_per_page = 100;
			
			// Calculate how many pages are necessary
			$nb_pages = ceil($num_posts/$nb_posts_per_page);
			
			if (!$page)
			{
				// We have no page value so... let's start with first page
				$current_page = 1;
			}
			else
			{
				$current_page = $page;
				
				// Our current_page is bigger than our total of pages...
				if($current_page > $nb_pages)
				{
					$current_page = $nb_pages;
				}
			}
			
			// Which entry do we have ?
			$first_entry = ($current_page - 1) * $nb_posts_per_page;
			
			// La requÃªte sql pour rÃ©cupÃ©rer les messages de la page actuelle.
			$sql = 'SELECT ctt.convert_topic_id, ctt.convert_forum_id, ctt.convert_topic_title, ctt.convert_topic_poster_new, ctt.convert_topic_replies_real, ctt.convert_topic_views, ctt.convert_topic_last_poster_id_new, ctt.convert_topic_last_post_id, ctt.convert_topic_last_post_time, ctt.convert_topic_first_post_id, ctt.convert_topic_type, ctt.convert_topic_status, ctt.convert_topic_approved, cpt.convert_post_id, cpt.convert_topic_id, cpt.convert_poster_id_new, cpt.convert_contents, cpt.convert_timestamp, cpt.convert_timestamp_edit, cpt.convert_post_edit_user, cpt.convert_post_edit_user_new, cpt.convert_user_ip
			FROM ' . CONVERT_TOPICS_TABLE . ' ctt
			LEFT JOIN ' . CONVERT_POSTS_TABLE . ' cpt
			ON (ctt.convert_topic_id = cpt.convert_topic_id)
			WHERE cpt.convert_post_id != ctt.convert_topic_first_post_id
			ORDER BY ctt.convert_topic_id ASC
			LIMIT ' . $first_entry . ', ' . $nb_posts_per_page;
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$sql_username = 'SELECT *
					FROM ' . USERS_TABLE . '
					WHERE user_id = ' . $row['convert_topic_poster_new'];
				$result_username = $db->sql_query($sql_username);
				$row_username = $db->sql_fetchrow($result_username);
				$db->sql_freeresult($result_username);
				$user->data = array_merge($user->data, $row_username);
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
				$msg  = $row['convert_contents'];
				//$phpboost_bbcodes = array('<strong>', '</strong>', '<em>', '</em>');
				//$phpb_bbcodes   = array('[b]', '[/b]', '[i]', '[/i]');
				//$msg = preg_replace('#\<strong>(.+)<\/strong>#iUs', '[b]$1[/b]', $msg);
				//$msg = preg_replace('#\<em>(.+)<\/em>#iUs', '[i]$1[/i]', $msg);
				//$msg = preg_replace('#\<span style="text-decoration: underline;">(.+)<\/span>#iUs', '[u]$1[/u]', $msg);
				//$msg = preg_replace('#\<br \/>#iUs',"\n", $msg);
				//$msg = preg_replace('#\"\n\n"#iUs',"\n", $msg);
 
				//$msg = str_replace($phpboost_bbcodes, $phpb_bbcodes, $msg);
				
				// Some post parameters
				$poll = $uid = $bitfield = $options = '';
				generate_text_for_storage($row['convert_topic_title'], $uid, $bitfield, $options, false, false, false);
				generate_text_for_storage($msg, $uid, $bitfield, $options, true, true, true);				

				// Some vars to insert into DB
				$data = array(
					'topic_id'			=> $row['convert_topic_id'],
					'post_id'			=> $row['convert_topic_first_post_id'],
					'forum_id'  		=> $row['convert_forum_id'],
					'icon_id'  			=> false,
					'poster_id'			=> $row['convert_topic_poster_new'],
					'enable_bbcode' 	=> true,
					'enable_smilies'	=> true,
					'enable_urls'  		=> true,
					'enable_sig'  		=> true,
					'message'  			=> $msg,
					'message_md5'   	=> md5($msg),
					'bbcode_bitfield'   => $bitfield,
					'bbcode_uid'  		=> $uid,
					'post_edit_locked'  => 0,
					'topic_title'  		=> $row['convert_topic_title'],
					'notify_set'  		=> false,
					'notify' 			=> true,
					'post_time'   		=> $row['convert_timestamp'],
					'forum_name'  		=> '',
					'enable_indexing'   => true,
					'topic_approved'	=> 1,
					'post_approved'		=> 1,							
				);

					
				// Submitting our post
				//$post_reply = ($select_post_type == 1) ? 'post' : 'reply';
				$post_reply = 'reply';
				//echo $data['topic_id'];
				submit_post($post_reply, $row['convert_topic_title'], $row_username['username'], POST_NORMAL, $poll, $data);
		}
		$db->sql_freeresult($result);
		
		for($i=1; $i<=$nb_pages; $i++)
		{
			 //On va faire notre condition
			 if($i==$current_page) //Si il s'agit de la page actuelle...
			 {
				$next = $i + 1;
				$nb_posts = ($current_page == $nb_pages) ? $num_posts : $nb_posts_per_page * $current_page;
				$template->assign_vars(array(
					'PAGE_CONTENT_TITLE'		=> ($current_page == $nb_pages) ? $user->lang['PREPARE_POSTS_IMPORT_SUCCESS_TITLE'] : $user->lang['PREPARE_POSTS_IMPORT_PROCESS_TITLE'],
					'PAGE_CONTENT'		=> ($current_page == $nb_pages) ? $user->lang('PREPARE_POSTS_IMPORT_SUCCESS_CONTENT', (int) $nb_posts, (int) $num_posts) : $user->lang('PREPARE_POSTS_IMPORT_PROCESS_CONTENT', (int) $nb_posts, (int) $num_posts),
					'PAGE_NEXT_TITLE'	=> ($current_page == $nb_pages) ? $user->lang['PREPARE_GROUPS_CONVERT_NEXT_TITLE'] : $user->lang['PREPARE_POSTS_IMPORT_CONTINUE_TITLE'],
					'PAGE_NEXT_URL_SHOW'	=> ($current_page == $nb_pages) ? true : false,
				'PAGE_NEXT_URL'		=> append_sid("convert.$phpEx", 'mode=end'),
				));
		
				// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
				$redirect_url = append_sid("convert.$phpEx", ($current_page == $nb_pages) ? 'mode=end' : 'mode=import_posts&amp;page=' . $next);
				// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
				$wait_for_next = ($current_page == $nb_pages) ? SCRIPT_WAITING_TIME_FOR_STEPS : SCRIPT_WAITING_TIME_FOR_LOOPS;
				($current_page == $nb_pages) ? '' : meta_refresh(SCRIPT_WAITING_TIME_FOR_LOOPS, $redirect_url);
			 }
		}		
	break;

	case 'end':
		$page_title = $user->lang['END_PAGE_TITLE'];
		
		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['END_MIGRATION_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['END_MIGRATION_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['END_MIGRATION_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("../index.$phpEx"),
		));
	break;
		
	default:
		// Titre de notre page
		$page_title = $user->lang['DEFAULT_PAGE_TITLE'];

		// Nous affichons un message d'information en fin d'Ã©tape
		$template->assign_vars(array(
			'PAGE_CONTENT_TITLE'	=> $user->lang['PREPARE_MIGRATION_TITLE'],
			'PAGE_CONTENT'			=> $user->lang['PREPARE_MIGRATION_CONTENT'],
			'PAGE_NEXT_TITLE'		=> $user->lang['PREPARE_MIGRATION_NEXT_TITLE'],
			'PAGE_NEXT_URL_SHOW'	=> true,
			'PAGE_NEXT_URL'			=> append_sid("convert.$phpEx", 'mode=prepare_users_table'),
		));

		// Nous redirigeons automatiquement s'il reste des pages Ã  traiter. Sinon, nous passons Ã  la suite.
		//$redirect_url = append_sid("index.$phpEx", 'mode=prepare_users_table');
		// Temps d'attente entre chaque page... S'il s'agit de la derniÃ¨re, nous prolongeons ce temps
		//meta_refresh(SCRIPT_WAITING_TIME_FOR_STEPS, $redirect_url);		
	break;
}

// Output page
page_header($page_title);

$template->set_filenames(array(
'body' => 'boost2phpbb/index_body.html')
);

page_footer();

?>
