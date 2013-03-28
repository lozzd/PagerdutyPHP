<?php

# Feel free to tweak this to your preferred timezone or let your OS take care of it
date_default_timezone_set('America/New_York');
include_once('timeago.php');

# The base URL... Everyone has their own subdomain, set yours here. 
$baseurl = "https://yourcompany.pagerduty.com/api/v1/";

# Define your teams list here: A friendly name, the schedule ID and the service ID.
#
# Each schedule has its own ID. Get yours by visting the Pagerduty website. 
# When you look at your schedule, the URL will have something like: 
# http://yourcompany.pagerduty.com/schedule/rotations/AB1ABCD
# The last part of this URL is the schedule ID. 
# Add it in this array with a reasonable name to identify it. 
# You also need to put the service ID in too to get additional details about
# indicidents that occured in the last week
$teams = array(
    'ops' => array(
        'schedule' => 'PSYAA5A',
        'service' => 'PKAAA8A',
    ),
    'team-a' => array(
        'schedule' => 'PBBBBBB',
        'service' => 'PBBBBBB',
    ),
);

# Your Pagerduty username is required for basic auth for access to the API.
# I suggest creating a new account just for this. 
$username = "automationuser@yourcompany.com";
$password = "yourpassword";

function apiCall($path, $parameters) {
    global $baseurl, $username, $password;

    $context = stream_context_create(array(
        'http' => array(
            'header'  => "Authorization: Basic " . base64_encode("$username:$password")
        )
    ));

    $params = null;
    foreach ($parameters as $key => $value) {
        if (isset($params)) {
            $params .= '&';
        } else {
            $params = '?';
        }
        $params .= sprintf('%s=%s', $key, $value);
    }
    return file_get_contents($baseurl . $path . $params, false, $context);
}

function getId($type, $team) {
    global $teams;
    $teaminfo = array_key_exists($team, $teams) ? $teams[$team] : null;
    if(!isset($teaminfo)) {
        echo "Cannot find that team. Define in pagerduty.php\n";
        die();
    }
    return $teaminfo[$type];

}

function whoIsOnCall($team, $time = null) {

    $until = $since = date('c', isset($time) ? $time : time());
    $parameters = array(
        'since' => $since,
        'until' => $until,
        'overflow' => 'true',
    );

    $json = apiCall(sprintf('/schedules/%s/entries', getId('schedule', $team)), $parameters);

    if (false === ($scheddata = json_decode($json))) {
        echo "There was an error with the data from PagerDuty, please try again later.\n";
        return false;
    }

    if ($scheddata->entries['0']->user->name == "") {
        echo "No data from Pagerduty for that date, sorry.\n";
        return false;
    }

    $oncalldetails = array();
    $oncalldetails['person'] = $scheddata->entries['0']->user->name;
    $oncalldetails['email'] = $scheddata->entries['0']->user->email;
    $oncalldetails['start'] = strtotime($scheddata->entries['0']->start);
    $oncalldetails['end'] = strtotime($scheddata->entries['0']->end);

    return $oncalldetails;

}

function incidentsInTimePeriod($team, $since = null, $until = null) {

    $since = isset($since) ? $since : (time() - (7 * 24 * 60 * 60));

    $parameters = array(
        'since' => date('c', $since),
        'service' => getId('service', $team),
    );

    $json = apiCall('/incidents', $parameters);

    if (false === ($incidentdata = json_decode($json))) {
        echo 'There was an error with the data from PagerDuty, please try again later.\n';
        return false;
    }

    return $incidentdata;
}

function printPersonOnCallNow($team) {
    if (empty($team)) {
        $team = "ops";
    }
    if($results = whoIsOnCall($team)) {
        echo $results['person'] . " is on call until " . date("d-m-Y H:i:s", $results['end']);
    }
}

function printPersonOnCallAt($team, $time) {
    $time = empty($time) ? null : strtotime($time);
    if($results = whoIsOnCall($team, $time)) {
        echo $results['person'] . " is on call from " . timeago($results['start']) . " until " . timeago($results['end']);
    }
}

function getLastWeeksIncidentReport($team) {
    $report = '';
    if ($results = incidentsInTimePeriod($team)) {
        $report .= "Last Weeks Incident Report\n\n";
        if ($results->total <= 0) {
            $report .= "\tNo incidents in the past week.\n\n";

        }
        foreach ($results->incidents as $incident) {
            $report .= sprintf("\tUser: %s\n", isset($incident->last_status_change_by) ? $incident->last_status_change_by->name : 'n/a');
            $report .= sprintf("\tCreated: %s\n", $incident->created_on);
            $report .= sprintf("\t%s: %s\n", ucwords($incident->status), $incident->last_status_change_on);
            $report .= sprintf("\tSummary: %s\n", $incident->trigger_summary_data->subject);
            $report .= sprintf("\t%s\n", $incident->trigger_details_html_url);
            $report .= "\n";
        }
    }
    return $report;
}

function printLastWeeksIncidentReport($team) {
    echo getLastWeeksIncidentReport($team);
}

function whoIsUpcomingInTeam($team, $weeks) {

    $until = date('c', strtotime("+{$weeks} weeks"));
    $since = date('c');
    $parameters = array(
        'since' => $since,
        'until' => $until,
        'overflow' => 'true',
    );

    $json = apiCall(sprintf('/schedules/%s/entries', getId('schedule', $team)), $parameters);

    if (false === ($scheddata = json_decode($json))) {
        echo "There was an error with the data from PagerDuty, please try again later.\n";
        return false;
    }

    $oncalldetails = array();

    foreach ($scheddata->entries as $entry) {
        $oncalldetails[] = array(
            'person' => $entry->user->name,
            'email' => $entry->user->email,
            'start' => strtotime($entry->start),
            'end' => strtotime($entry->end),
        );
    }

    return $oncalldetails;

}

function getUpcomingInTeam($team, $weeks) {
    if (is_null($team) || is_null($weeks)) {
        $output = "You need to give me a team and a number of weeks";
    } else {
        if($results = whoIsUpcomingInTeam($team, $weeks)) {
            $output = "Upcoming {$weeks} weeks for team schedule {$team}: \n";
            foreach ($results as $result) {
                $output .= "\t" . $result['person'] . " is on call from " . timeago($result['start']) . " until " . timeago($result['end']) . "\n";
            }
        }
    }
    return $output;
}

function printUpcomingInTeam($team, $weeks) {
    echo getUpcomingInTeam($team, $weeks);
}

?>
