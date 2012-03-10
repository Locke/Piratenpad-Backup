<?php

/**
 * Implementation of hook_meu()
 */
function pp_piratenpad_menu() {
  $items = array();

  $items['admin/settings/pp_piratenpad'] = array(
    'title' => 'Piratenpads',
    'description' => 'Piratenpads module settings',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('pp_piratenpad_admin_form'),
    'access arguments' => array('administer site configuration'),
    'file' => 'pp_piratenpad.admin.inc'
  );

  return $items;
}


/**
* Display help and module information.
* @param path
*   Which path of the site we're displaying help.
* @param arg
*   Array that holds the current path as would be returned from arg() function.
* @return
*   help text for the path.
*/
function pp_piratenpad_help($path, $arg) {
  $output = '';
  switch ($path) {
    case "admin/help#pp_piratenpad":
      $output = '<p>'. t("Zeigt den Block für alle aktuellen Piratenpads").'</p>';
      break;
  }
  return $output;
} // function pp_piratenpad_help


/**
* Valid permissions for this module
* @return array An array of valid permissions for the pp_piratenpad module
*/

function pp_piratenpad_perm() {
  return array('access pp_piratenpad content');
} // function pp_piratenpad_perm()


/**
* Generate HTML for the pp_piratenpad block
* @param op the operation from the URL
* @param delta offset
* @returns block HTML
*/
function pp_piratenpad_block($op='list', $delta=0) {
	// listing of blocks, such as on the admin/block page
		if ($op == "list") {
			$block[0]["info"] = t('Aktuelle Piratenpads');
			return $block;
		} else if ($op == 'view') {
			// our block content
				$max = variable_get('pp_piratenpad_max', 5);
				$sort_alpha = variable_get('pp_piratenpad_sort_alpha', TRUE);
				$sort_last_edited = variable_get('pp_piratenpad_sort_last_edited', FALSE);

				$sql = "SELECT * FROM {pp_piratenpad}";
				if ($sort_alpha && $sort_last_edited) {
					$sql .= " ORDER BY `last_edited` DESC, `name`";
				} else if ($sort_alpha) {
					$sql .= " ORDER BY `name`";
				} else if ($sort_last_edited) {
					$sql .= " ORDER BY `last_edited` DESC";
				}
				$sql .= " LIMIT 0, ".$max;

				$results = db_query($sql);

				$block_content	= '<ul class="menu">';
				while ($pad = db_fetch_array($results)) {
					$block_content .= "<li><a href='".$pad["url"]."'>".$pad["name"]."</a></li>";
				}
				$block_content = trim($block_content);
				$block_content = nl2br($block_content);

				$block_content .= "</ul><div style='width: 100%; text-align: right;'><a href='/piratenpad' title='Mehr'>&raquo; Mehr</a></div>";

			// set up the block
				$block['subject'] = t('Aktuelle Piratenpads');
				$block['content'] = $block_content;
				return $block;
		}
}

/**
*
*/
function pp_piratenpad_cron() {
	$url = variable_get('pp_piratenpad_url', NULL);
	$email = variable_get('pp_piratenpad_email', NULL);
	$password = variable_get('pp_piratenpad_password', NULL);

	$vars = array(
		"url" => $url,
		"email" => $email,
		"password" => $password,
	);

	if (!empty($url) && !empty($email) && !empty($password)) {
		// 1. get the piratepad team cookies
			$_url = "http://".$url;
			$_headers = array();
			$_data = array();

			$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
			#print_r($response);
			#die();
			$_team_cookie = $response->headers['Set-Cookie'];
			preg_match_all("/(E\w=\w*;)/", $_team_cookie, $regs);
			#print_r($regs);
			$_team_cookie = "";
			foreach($regs[1] as $reg) {
				$_team_cookie .= $reg." ";
			}
			$_cookie = trim($_team_cookie);
			#print_r($_cookie);
			$_headers = array('Cookie' => $_cookie);
			$_url = $response->headers['Location'];

		// 2. get the piratepad cookie
			$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
			#print_r($response);
			#die();
			$_cookie = $response->headers['Set-Cookie'];
			preg_match_all("/(E\w=\w*;)/", $_cookie, $regs);
			#print_r($regs);
			$_cookie = trim($_team_cookie);
			foreach($regs[1] as $reg) {
				$_cookie .= " ".$reg;
			}
			$_cookie = trim($_cookie);
			#print_r($_cookie);
			$_headers = array('Cookie' => $_cookie);
			$_url = $response->headers['Location'];

		// 3. get the 1st redirect to the login screen
			$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
			#print_r($response);
			#die();
			$_url = $response->headers['Location'];

		// 4. get the 2nd redirect to the login screen
			$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
			#print_r($response);
			#die();
			$_url = $response->headers['Location'];

		// 5. get the login screen
			$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
			#print_r($response);
			#die();

		// 6. do the login
			$_headers['Content-Type'] = "application/x-www-form-urlencoded";
			$_url = "http://".$url."/ep/account/sign-in?cont=".urlencode("http://".$url."/ep/padlist/all-pads");
			$_data = array(
				"email" => $email,
				"password" => $password,
			);
			#print_r($_data);
			$response = drupal_http_request($_url, $_headers, 'POST', http_build_query($_data, '', '&'), 0);
			#print_r($response);
			#die();
			$_url = $response->headers['Location'];

		// 7. get the result page
			unset($_headers['Content-Type']);
			$_data = array();
			$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
			#print_r($response);

			$pads = array();

			if (strpos($response->data, "padlock.gif") === false) {
				// no padlock
					preg_match_all("/<td\s*class=\"title first\">(.*)?</", $response->data, $regs);
			} else {
				// padlock found
					preg_match_all("/<td\s*class=\"title\">(.*)?</", $response->data, $regs);
			}
			#print_r($regs);
			#die();

			foreach($regs[1] as $id => $reg) {
				preg_match_all("/(\/.*)?\"/", $reg, $regs2);
				#print_r($regs2);
				$pads[$id] = array();
				$pads[$id]["name"] = strip_tags($reg);
				$pads[$id]["url"] = "http://".$url.$regs2[1][1];
			}

			preg_match_all("/<td\s*class=\"lastEditedDate\">(.*)?</", $response->data, $regs);
			#print_r($regs);
			foreach($regs[1] as $id => $reg) {
				$edited = strip_tags($reg);
				$edited = str_replace("ago", "", $edited);
				$edited = trim($edited);
				$edited = "- ".$edited;
				$pads[$id]["last_edited"] = strtotime($edited);
			}

			preg_match_all("/<td\s*class=\"editors\">(.*)?</", $response->data, $regs);
			#print_r($regs);
			foreach($regs[1] as $id => $reg) {
				$pads[$id]["editor"] = strip_tags($reg);
			}
			#print_r($pads);
			#die();

		// 8. sign out
			$_url = "http://".$url."/ep/account/sign-out";
			$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
			#print_r($response);
			#die();

		// 9. check pads public status
			foreach($pads as $id => $pad) {
				#print_r($pad);
				$response = drupal_http_request($pad["url"], $_headers, 'GET', http_build_query($_data), 0);
				#print_r($response->code);
				if ($response->code != 200)
					unset($pads[$id]);
			}
			#print_r($pads);
			#die();

		// 10. store to database
			db_query("TRUNCATE TABLE {pp_piratenpad}");
			foreach($pads as $id => $pad) {
				drupal_write_record("pp_piratenpad", $pad);
			}
	} else {
		watchdog('pp_piratenpad', t("Cron run failed: Missing variables"), $vars, WATCHDOG_ERROR);
	}
}

?>
