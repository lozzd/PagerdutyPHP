<?php

# Feel free to tweak this to your preferred timezone or let your OS take care of it
date_default_timezone_set('America/New_York');
include_once('timeago.php');

# The base URL... Everyone has their own subdomain, set yours here. 
$baseurl = "https://yourcompany.pagerduty.com/api/v1/schedules/";

# Each schedule has its own ID. Get yours by visting the Pagerduty website. 
# When you look at your schedule, the URL will have something like: 
# http://yourcompany.pagerduty.com/schedule/rotations/AB1ABCD
# The last part of this URL is the schedule ID. 
# Add it in this array with a reasonable name to identify it. 
$schedules = array ('ops' => 'AB1ABCD', 'dev' => 'CD1CDEFG');

# Your Pagerduty username is required for basic auth for access to the API.
# I suggest creating a new account just for this. 
$username = "automationuser@yourcompany.com";
$password = "yourpassword";



function whoIsOnCall($schedule, $time = "") {
	global $baseurl, $schedules, $username, $password;
	if($time == "") $time = time();

	if(!$scheduleid = array_key_exists($schedule, $schedules)) {
		echo "Cannot find that schedule. Define in pagerduty.php";
		die();
	}

	$context = stream_context_create(array(
	    'http' => array(
	        'header'  => "Authorization: Basic " . base64_encode("$username:$password")
	    )
	));

	$time = date("c", $time);
	$json = file_get_contents($baseurl . $schedules[$schedule] . "/entries?since={$time}&until={$time}&overflow=true", false, $context); 

        if (false == ($scheddata = json_decode($json))) {
		echo "There was an error with the data from PagerDuty, please try again later.";
		return false;
	}

	if ($scheddata->entries['0']->user->name == "") {
		echo "No data from Pagerduty for that date, sorry.";
		return false;
	}

	$oncalldetails = array();
	$oncalldetails['person'] = $scheddata->entries['0']->user->name;
	$oncalldetails['start'] = strtotime($scheddata->entries['0']->start);
	$oncalldetails['end'] = strtotime($scheddata->entries['0']->end);

	return $oncalldetails;

}

function printPersonOnCallNow($team) {
	if($results = whoIsOnCall($team)) {
		echo $results['person'] . " is on call until " . date("d-m-Y H:i:s", $results['end']);
	}
}

function printPersonOnCallAt($team, $time) {
	$time = strtotime($time);
	if($results = whoIsOnCall($team, $time)) { 
		echo $results['person'] . " is on call from " . timeago($results['start']) . " until " . timeago($results['end']);
	}
}

?>
