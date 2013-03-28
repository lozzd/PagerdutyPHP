# Pagerduty PHP

### A PHP library of handy functions utilising the Pagerduty API.


## What's included?

* **pagerduty.php** - The file which has all the functions that can be used. These include printing who is currently on call, who will be on call, retrieving recent incidents. 
* **pagerdutycron.php** - A script designed to run from cron that will either ping irccat or send an email (or both) when the person on call in your rotations changes. 
* **timeago.php** - A support function provided by alex at nyoc dot net. 

## How do I use it? 

You need to enter some basic configuration information into pagerduty.php before you can use it, relating to your login and your schedules. 

After you've done this, you are free to include pagerduty.php and use the functions in the file. Keep reading for info on other files.

## Using with irccat

### Printing who is on call now
If you already use the IRC bot [irccat](https://github.com/RJ/irccat) you can ask irccat who is on call for a given rotation by including it in your command handler like so:

     case 'opsoncall':
        include_once("pagerduty.php");
        printPersonOnCallAt("ops", $allargs);
        break;

This will allow anyone to query the Pagerduty schedule to find out who is on call now (no args) or at a specfied time (passed through string-to-time, so vaguely intelligent). 

**For example:**

      <laurie> ?opsoncall
      <irccat> Bob Robertson is on call from 2 days ago until 4 days from now
      <laurie> ?opsoncall next week
      <irccat> Dave Davidson is on call from 4 days from now until 11 days from now

### Printing next N people on call
You can print the next few people on call using the following:

    case 'listoncall':
        include_once("pagerduty.php");
        printUpcomingInTeam($toks[0], $toks[1]);
        break;

**For example** 

      <laurie> ?listoncall ops 2
      <irccat> Upcoming 2 weeks for team schedule ops:
      <irccat>    Rob Robertson is on call from 2 days ago until 4 days from now
      <irccat>    Dave Davidson is on call from 4 days from now until 11 days from now

## pagerdutycron.php

Setup is fairly simple; there are a few configuration options at the top of the file relating to whether you want to send an email, or send a message to irccat, or both, and where to send those messages. 
After you've done that, run it by hand once to ensure everything works as planned, and then set it up in cron to run as often as you please (for example, 1 minute). 

When it detects a change in rotation, it will perform your selected action to alert the relevant parties. 

For example, in IRC:

      <irccat> On call person for ops is now Dave Davidson until 7 days from now

If you have selected email, the email will contain the new person on call, the actual date/time they're on call until (and the relative time), a list of incidents in the past week, and the next 4 weeks of schedule information for that team. 
