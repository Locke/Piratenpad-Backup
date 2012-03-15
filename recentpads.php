<?php

include("bootstrap.inc");
include("common.inc");

include("config.inc");

/**
* parse html
*/
function parse($html, $url){
	$pads = array();
	
	/*if (strpos($response->data, "padlock.gif") === false) {
		// no padlock
		echo "no padlock/n";
		preg_match_all("/<td\s*class=\"title first\">(.*)?</", $response->data, $regs);
	} else {
		// padlock found
		echo "padlock found/n";
		//preg_match_all("/<td\s*class=\"title\">(.*)?</", $response->data, $regs);
	}*/
	
	preg_match_all("/<td\s*class=\"title first\">(.*)?</", $html, $regs);
	#print_r($regs);
	#die();
	
	foreach($regs[1] as $id => $reg) {
		preg_match_all("/(\/.*?)?\"/", $reg, $regs2);
		#print_r($regs2);
		$pads[$id] = array();
		$pads[$id]["name"] = strip_tags($reg);
		$pads[$id]["url"] = $url.$regs2[1][1];
	}
	
	preg_match_all("/<td\s*class=\"lastEditedDate\">(.*)?</", $html, $regs);
	#print_r($regs);
	foreach($regs[1] as $id => $reg) {
		$edited = strip_tags($reg);
		$edited = str_replace("ago", "", $edited);
		$edited = trim($edited);
		$edited = "- ".$edited;
		$pads[$id]["last_edited"] = strtotime($edited);
	}
	
	preg_match_all("/<td\s*class=\"editors\">(.*)?</", $html, $regs);
	#print_r($regs);
	foreach($regs[1] as $id => $reg) {
		$pads[$id]["editor"] = strip_tags($reg);
	}
	#print_r($pads);
	#die();

	return $pads;
}

/**
* fetch pads
*/
function fetch_recent_pads($url, $email, $password, $check_public, $filter_time) {
	global $base, $exportpre, $filename, $exportpost;

	$vars = array(
		"url" => $url."/",
		"email" => $email,
		"password" => $password,
	);
	
	// 1. get the piratepad team cookies
	echo("1. get the piratepad team cookies\n");
	$_url = $url."/";
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
	echo("2. get the piratepad cookie\n");
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
	echo("3. get the 1st redirect to the login screen\n");
	$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
	#print_r($response);
	#die();
	$_url = $response->headers['Location'];
	
	// 4. get the 2nd redirect to the login screen
	echo("4. get the 2nd redirect to the login screen\n");
	$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
	#print_r($response);
	#die();
	$_url = $response->headers['Location'];
	
	// 5. get the login screen
	echo("5. get the login screen\n");
	$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
	#print_r($response);
	#die();
	
	// 6. do the login
	echo("6. do the login\n");
	$_headers['Content-Type'] = "application/x-www-form-urlencoded";
	$_url = $url."/ep/account/sign-in?cont=".urlencode($url."/ep/padlist/all-pads");
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
	echo("7. get the result page\n");
	unset($_headers['Content-Type']);
	$_data = array();
	$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
	#print_r($response);
	
	$pads = parse($response->data, $url);
	
	// clear old pads
	echo("clear old pads\n");
	if($filter_time > 0){
		foreach($pads as $id => $pad) {
			#print_r($pad);
			if ($pad["last_edited"] < $filter_time)
				unset($pads[$id]);
		}
	}

	// sign out if backup only public pads
	if($check_public){
		// 8. sign out
		echo("8. sign out\n");
		$_url = $url."/ep/account/sign-out";
		$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
		#print_r($response);
		#die();
	}
	#print_r($pads);
	#die();

	$olddir = getcwd();
	$dir = dirname(__FILE__);
	chdir($dir . '/backups');

	
	/* download pads */
	echo("download pads\n");
	foreach($pads as $id => $pad) {
		#print_r($pad);

		$filename = str_replace($url."/", "", $pad["url"]);

		$_url = $base . $exportpre . $filename . $exportpost;

		$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
		#print_r($response->code);
		
		if($response->code == 200){
			echo("fetched " . $filename . "\n");
			$file = fopen($filename, "w");
			fwrite($file, $response->data);
			fclose($file);

			exec("git add \"".$filename."\"");
		}
		else{
			echo("ignored " . $filename . "\n");
		}
	}

	echo("commit\n");
	exec("git commit -am \"pads updated: $(git status)\" > /dev/null");
	echo("commited\n");

	chdir($olddir);

	if(!$check_public){
		// 8. sign out
		echo("8. sign out\n");
		$_url = $url."/ep/account/sign-out";
		$response = drupal_http_request($_url, $_headers, 'GET', http_build_query($_data), 0);
		#print_r($response);
		#die();
	}
}

//error_reporting(E_ALL ^ E_NOTICE);

if($interval > 0)
	$time = time() - $interval;
else
	$time = 0;

fetch_recent_pads($base, $email, $password, $check_public, $time);

?>
