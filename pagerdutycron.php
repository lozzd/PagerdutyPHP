#!/usr/bin/php
<?

# A script designed to be run from cron at an interval (e.g. 1 minute) and then alerts you when changes
# to the on call rotation happen. For example, be emailed when a new person comes on call. 

# Include the libs
include('pagerduty.php');
include_once('timeago.php');

if (!isset($argv[1])) {
	echo "Must be run with an argument for which schedule you want to monitor, e.g. pagerdutycron.php ops\n";
	die();
}


$schedule = $argv[1];

# CONFIG
#
# ** Remember to set up the base options in pagerduty.php ** 
#
# Choose your options here:
#
# $emailalert = This controls whether to send an email on change in the on call schedule
$emailalert = true;

# $emailrecipient = An array mapping of schedule names to email addresses. Leave blank to not email this schedule
$emailrecipient = array('ops' => "ops@yourcompany.com", 'dev' => "");

# $irccatalert = This controls whether to send a message to IRC on change in the on call schedule
$irccatalert = false;

# $ircchannel = An array mapping of schedules to irc channels. Leave blank to not irccat announce this schedule
$ircchannel = array('ops' => '#ops', 'dev' => '#dev');

# $irccathost = Hostname of the irccat server
$irccathost = "127.0.0.1";

# $schedulefile = Where you want to store the temporary state information for the schedule. Must be writeable!
$schedulefile = "/tmp/pagerduty-{$schedule}";


# END CONFIG
#
# Edit things under here for additional control. 
#

logline("Starting run to check Pagerduty schedule {$schedule} for changes");

if(!is_readable($schedulefile)) { # If the schedule file isn't readable, it probably never existed. Populate it.
	logline("Schedule file is unreadable... Attempting to initial populate");
	if (!is_writable($schedulefile)) {
		if($results = whoIsOnCall($schedule)) {
			$fh = fopen($schedulefile, "w");
			fwrite($fh, $results['person']);
			logline("Wrote initial data to schedule file. Person on call is currently {$results['person']}");
			fclose($fh);
		} else {
			logline("Pagerduty call failed; I cannot proceed. Try again later.");
			die();
		}
	} else {
		logline("Your schedule file is unwriteable. Please choose a different path.");
		die();
	}
}

unset($results);
logline("Checking schedule for updates...");

# Basic checking of the file... Don't want any errors.
if (!is_readable($schedulefile)) {
        logline("Schedule file is no longer readable, bailing.");
        die();
}
if (!is_writable($schedulefile)) {
        logline("Schedule file is not writeable, bailing. ");
        die();
}

if($results = whoIsOnCall($schedule)) {
        logline("Person on call is now {$results['person']}");
        $fh = fopen($schedulefile, "r");
        $lastoncall = fgets($fh);
        fclose($fh);
        if ($lastoncall == $results['person']) { # No change in on call rotation
            logline("Person on call last == person on call now ({$lastoncall} == {$results['person']}), nothing to do");
        } else { # A change has occured! Alert the relevant places and write the new person to our temp file
            logline("Person on call has changed! Was {$lastoncall} is now {$results['person']}.");
            if ($irccatalert) {
                irccat("On call person for {$schedule} is now {$results['person']} until " . timeago($results['end']));
                irccat("Thanks to {$lastoncall}, you are now free. Remember to tip your on call guy/gal!");
            }

            if ($emailalert) {
                $emailmessage = "Hi! \n\nThis is an automated email to let you know that the on call person for schedule {$schedule} has changed.\n\n";
                $emailmessage .= "The on call person for {$schedule} is now {$results['person']} until " . date("r", $results['end']) . " (" . timeago($results['end']) . ")\n\n";
                $emailmessage .= "Thanks to {$lastoncall} who is now relieved from duty. \n\n";
                $emailmessage .= "Thank you!\nPagerduty email bot";
                email($emailmessage);
            }
            logline("Writing new on call person to log file...");
            $fh = fopen($schedulefile, "w");
            fwrite($fh, $results['person']);
            fclose($fh);
        }
} else {
        logline("Pagerduty call failed, trying again later.");
}
logline("Run completed");




function logline($message) {
        echo date(DATE_RFC822) . " " . $message . "\n";
}

function irccat($message) {
    global $ircchannel, $schedule, $irccathost;
    if ($ircchannel[$schedule] == "") {
        logline("Bailing on IRC, no channel set for this schedule");
        return false;
    }
    echo "IRC MESSAGE: {$message} \n";
    $irccat = fsockopen($irccathost, 12345);
    if ($irccat) {
    	fwrite($irccat, $ircchannel[$schedule] . " " .  $message);
    	fclose($irccat);
    }
}

function email($message) {
    global $emailrecipient, $schedule; 
    if ($emailrecipient[$schedule] == "") {
        logline("Bailing on sending email, no recipient set for this schedule");
        return false;
    }
    $emailsubject = "New on-call person for {$schedule}";
    if (mail($emailrecipient[$schedule], $emailsubject, $message)) {
        logline("Email sent successfully");
        return true;
    } else {
        logline("Email sending failed");
        return false;
    }

}

?>
