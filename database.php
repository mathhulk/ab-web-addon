<?php
session_start(); //Sessions data is saved for accounts.
ob_start(); //Static content loads first.
//This is required for account data to be saved.

$con = mysqli_connect("host","user","password","database");
//Enter your MYSQL details here.

$info = array(
	'title'=>'AdvancedBan Web Addon', //This will be displayed in the title, main jumbotron, and navigation bar. (string)
	'description'=>'A simple, but sleek, web addon for AdvancedBan.', //This will be displayed under the title on all pages. (string)
	'theme'=>'yeti', //This is the name of the theme you wish to load. You can find a list of compatible themes at http://bootswatch.com/. (string)
	'table'=>'PunishmentHistory', //The table of your MYSQL database for which punishments are saved. (string)
	'base'=>'www.example.com/bans', //DO NOT INCLUDE A TRAILING SLASH. The URL at which ab-web-addon is located. (string)
	'ip-bans'=>true, //Whether punishments that reveal the IP address of players will be shown. (boolean)
	'admin'=>array(
		'accounts'=>array('test') //The list of users that can log in to the dashboard. These must be active accounts from https://theartex.net. (array) (string)
		)
	);

if (mysqli_connect_errno()) {
	die('Failed to connect to database.'); //Restrict access to any page if no connection is established.
}

//Set up a default structure for monitoring pages.
$page = array('max'=>10, 'min'=>0, 'number'=>1, 'posts'=>0, 'count'=>0); 

//Set up a structure for monitoring pages based on user input.
if(isset($_GET['p']) && is_numeric($_GET['p'])) {
	$page = array('max'=>$_GET['p']*10,'min'=>($_GET['p'] - 1)*10,'number'=>$_GET['p'],'posts'=>0,'count'=>0); 
}

//List the types of punishments.
$types = array('all','ban','temp_ban','mute','temp_mute','warning','temp_warning','kick'); 
if($info['ip-bans'] == true) {
	$types[] = 'ip_ban';
}

// translatable lables for punishments.
//					punishment type => translated text to be displayed on page
$typeLabels = array("all" => "All", 
					"ban" => "Bans",
					"temp_ban" => "Temp Bans",
					"mute" => "Mutes",
					"temp_mute" => "Temp Mutes",
					"warning" => "Warnings",
					"temp_warning" => "Temp Warnings",
					"kick" => "Kicks",
					"ip_ban" => "IP Bans");

//-----------------------------------------------------------------------------------
// (!) The following portion of the database.php file does not require changes. (!)
//-----------------------------------------------------------------------------------

//Use the developer API from theartex.net for user authentication checks.
if(isset($_SESSION['id'])) {
	$json = json_decode(httpPost("https://www.theartex.net/cloud/api/", array('sec'=>'validate', 'id'=>$_SESSION['id'], 'token'=>$_SESSION['token'])), true);
	if(isset($json['status']) && $json['status'] == "success") {
		if(in_array($json['data']['username'], $info['admin']['accounts'])) {
			$_SESSION['id'] = $json['data']['id'];
			$_SESSION['username'] = $json['data']['username'];
			$_SESSION['role'] = $json['data']['role'];
			$_SESSION['email'] = $json['data']['email'];
			if(!empty($json['data']['gravatar'])) {
				$_SESSION['gravatar'] = $json['data']['gravatar'];
			}
			$_SESSION['key'] = $json['data']['key'];
			if(!empty($json['data']['page'])) {
				$_SESSION['page'] = $json['data']['page'];
			}
			if(!empty($json['data']['last_seen'])) {
				$_SESSION['last_seen'] = $json['data']['last_seen'];
			}
			if(isset($_SESSION['remember']) && $_SESSION['remember'] == "true") {
				setcookie("id", base64_encode($_SESSION['id']), time() + (86400 * 30), "/");
				setcookie("token", base64_encode($_SESSION['token']), time() + (86400 * 30), "/");
			}
			httpPost("https://www.theartex.net/cloud/api/", array('sec'=>'session', 'page'=>$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], 'ip'=>$_SERVER['REMOTE_ADDR'], 'key'=>$_SESSION['key']));
		} else {
			setcookie("id", "", time() - (86400 * 30), "/");
			setcookie("token", "", time() - (86400 * 30), "/");
			session_destroy();
		}
	} else {
		setcookie("id", "", time() - (86400 * 30), "/");
		setcookie("token", "", time() - (86400 * 30), "/");
		session_destroy();
	}
} elseif(isset($_COOKIE['id'])) {
	$json = json_decode(httpPost("https://www.theartex.net/cloud/api/", array('sec'=>'validate', 'id'=>base64_decode($_COOKIE['id']), 'token'=>base64_decode($_COOKIE['token']))), true);
	if(isset($json['status']) && $json['status'] == "success") {
		if(in_array($json['data']['username'], $info['admin']['accounts'])) {
			$_SESSION['id'] = $json['data']['id'];
			$_SESSION['username'] = $json['data']['username'];
			$_SESSION['role'] = $json['data']['role'];
			$_SESSION['email'] = $json['data']['email'];
			if(!empty($json['data']['gravatar'])) {
				$_SESSION['gravatar'] = $json['data']['gravatar'];
			}
			$_SESSION['key'] = $json['data']['key'];
			$_SESSION['token'] = base64_decode($_COOKIE['token']);
			if(!empty($json['data']['page'])) {
				$_SESSION['page'] = $json['data']['page'];
			}
			if(!empty($json['data']['last_seen'])) {
				$_SESSION['last_seen'] = $json['data']['last_seen'];
			}
			$_SESSION['remember'] == "true";
			setcookie("id", base64_encode($_SESSION['id']), time() + (86400 * 30), "/");
			setcookie("token", base64_encode(base64_decode($_COOKIE['token'])), time() + (86400 * 30), "/");
			httpPost("https://www.theartex.net/cloud/api/", array('sec'=>'session', 'page'=>$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], 'ip'=>$_SERVER['REMOTE_ADDR'], 'key'=>$_SESSION['key']));
		} else {
			setcookie("id", "", time() - (86400 * 30), "/");
			setcookie("token", "", time() - (86400 * 30), "/");
			session_destroy();
		}
	} else {
		setcookie("id", "", time() - (86400 * 30), "/");
		setcookie("token", "", time() - (86400 * 30), "/");
		session_destroy();
	}
}

//Use an external API to display times relative to the user's timezone.
if(!isset($_SESSION['time_zone'])) {
	$_SESSION['time_zone'] = "America/Los_Angeles";
	$tz_api = json_decode(file_get_contents('http://freegeoip.net/json/'.$_SERVER['REMOTE_ADDR']), true);
	if(isset($tz_api['time_zone']) && in_array($tz_api['time_zone'], timezone_identifiers_list())) {
		$_SESSION['time_zone'] = $tz_api['time_zone'];
	}
}

//A simple function to easily use the developer API.
function httpPost($url,$params) {
    $ch = curl_init();  
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true); 
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));    
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
	if(curl_error($ch)) {
		die('An error occurred while contacting the application API:' . curl_error($ch));
	}
    curl_close($ch);
	return $output;
}