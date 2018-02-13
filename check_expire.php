<?php

// Provided AS IS under the GNU license located at: http://www.gnu.org/licenses/gpl.txt

// To use the script.
// Set the variables below, then execute the script with the users OU as an argument.
// Example: /path/to/scriptdir/check_expire.php -o "CN=People, DC=Domain, DC=org"

// After setting the options below, be sure to update the two email templates included
// to include your company logo and instructions for the end-user.

// Some variables will need to be set first.

// Full path to this script.
$scriptPath     = "/usr/local/pwdexpire/";
// A regular user to bind to AD.  Use the upn format.
$ldapupn        = "ldap_bind@sub.domain.tld";
// That users password
$ldappass       = "password";
// To make a connection to the domain controller over SSL use ldaps:// instead of ldap://
$ldaphost       = "ldap://dc.sub.domain.tld/";
//  How many days out to start warning the user.
$warndays       = "15";
// From email header  on end-user notifications.
$useremailheader = "MIME-Version: 1.0" . "\r\n";
$useremailheader .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$useremailheader .= "From: IT Support <support@sub.domain.tld>" . "\r\n";

// Email alias for administrators.  This email will get a listing of the users that are expiring.
$adminemailto   = "admin@sub.domain.tld";
// From email header  on admin notifications.
$adminemailheader = "MIME-Version: 1.0" . "\r\n";
$adminemailheader .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$adminemailheader .= "From: IT Support <support@sub.domain.tld>" . "\r\n";

// Debugging Options
// 1 is Enabled, 0 is Disabled
// When debug is enabled, no emails will be sent to the users.
$debug = "0";

// End Options - Begin Workflow

// Default variables
$listforadmin   = "<tr><th><u>Username</u></th><th><u>Account Status</u></th><th><u>Password Status</u></th><th><u>Password Expired Time (in days)</u></th><th><u>Password Expired Time</u></th><th><u>PWM Response set Status</u>\r\n\t<br /></th></tr>";
$filter         = "(&(objectCategory=Person)(objectClass=User))";
$attrib         = array("sn", "givenname", "cn", "sAMAccountName", "msDS-UserPasswordExpiryTimeComputed", "mail", "pwmresponseset", "useraccountcontrol");

// Setup Logging to file
$file = "/var/log/ad-password-check-expire.log";
$fh = fopen($file, 'a') or die("can't open log file");

// logtime function with microseconds and change from assumed php.ini configured New_York timezone to UTC
function logtime()
{
    $utctz = new DateTimeZone('UTC');
    $t = microtime(true);
    $micro = sprintf("%06d", ($t - floor($t)) * 1000000);
    $d = new DateTime(date('Y-m-d H:i:s.'.$micro, $t), new DateTimeZone('America/New_York'));
    $d->setTimezone(new DateTimeZone('UTC'));
    $logtimestamp = $d->format("Y-m-d H:i:s.u");
    return $logtimestamp;
}

// Get current time
$now    = time();
$currentdatehuman = date("m-d-Y", "$now");
$currenttimehuman = date("m-d-Y H:i:s e", "$now");
$currentdayofweek = date("w", "$now");

/*
AD date values.  Offset is approximate 10millionths of a second from
1/1/1601 to 1/1/1970 (Epoch).  MS stores the time as number of 100 nanoseconds
since 1/1/1601.  Since we get epoch from now(), we need to add the difference.
*/
$offset         = 116444736000000000;
$oneday         = 864000000000;
$daystowarn     = $oneday * $warndays;

//Set current date in large int as AD does
$dateasadint    = ($now * 10000000) + $offset;

// Set search value for todays date plus warning time.
$warndatethresh = $dateasadint + $daystowarn;

echo "Current Date: $currentdatehuman\n";
echo "Now in Epoch: $now \n";
fwrite($fh, logtime() . " - -----\n");
$thisscript = realpath($_SERVER['SCRIPT_FILENAME']);
fwrite($fh, logtime() . " - Logging for execution of: $thisscript\n");
fwrite($fh, logtime() . " - Script Execution Date/Time: $currenttimehuman\n");
echo "Using number days to warn: $warndays\n";
fwrite($fh, logtime() . " - Using number days to warn: $warndays\n");


//Check that the proper command line arguments have been passed to the script.
$argumentOU = getopt("o:");

if ($argumentOU) {
    echo("Checking for expired passwords in OU: \"{$argumentOU['o']}\"\n");
    fwrite($fh, logtime() . " - Checking for expired passwords in OU: \"{$argumentOU['o']}\"\n");
    $dn = $argumentOU['o'];
} else {
    echo("You must specify an LDAP OU in the arguments passed to this script.  Example: /path/to/scriptdir/scriptname.php -o \"CN=Users, DC=Domain, DC=org\" ");
    fwrite($fh, logtime() . " - You must specify an LDAP OU in the arguments passed to this script.  Example: /path/to/scriptdir/scriptname.php -o \"CN=Users, DC=Domain, DC=org\" ");
    fclose($fh);
    exit(1);
}

// Connect to LDAP
echo "Beginning LDAP search...\n";
$ldapconn = ldap_connect($ldaphost)
         or die("Could not connect to LDAP host: \"{$ldaphost}\".\n");

if ($ldapconn) {
    echo "Attempting LDAP bind.\n";
    fwrite($fh, logtime() . " - Attempting LDAP bind via UPN: \"{$ldapupn}\" to LDAP host: \"{$ldaphost}\".\n");

    // Bind to LDAP.
    $ldapbind = ldap_bind($ldapconn, $ldapupn, $ldappass);

    // Verify LDAP connected.
    if ($ldapbind) {
        echo "LDAP bind successful.\n";
        fwrite($fh, logtime() . " - LDAP bind successful.\n");
    } else {
        echo "LDAP bind failed.\n";
        fwrite($fh, logtime() . " - LDAP bind failed.\n");
        fclose($fh);
        exit(1);
    }
}

// Search LDAP using filter, get the entries, and set count.
$search = ldap_search($ldapconn, $dn, $filter, $attrib, 0, 0)
or die("Could not search LDAP server.\n");

$dsarray = ldap_get_entries($ldapconn, $search);
$count = $dsarray["count"];
$adminlistcount = 0;
$emailcount = 0;

echo "$count User Objects found.\n";
fwrite($fh, logtime() . " - $count User Objects found in \"{$argumentOU['o']}\"\n");

if ($debug=="1") {
    fwrite($fh, logtime() . " - Debugging Enabled, no user warning emails will be sent\n");
}
fwrite($fh, logtime() . " - -----\n");

for ($i = 0; $i < $count; $i++) {
    // Converts large int from AD to epoch then to human readable format
    $timeepoch = ($dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] - 116444736000000000) / 10000000;
    $timetemp = split("[.]", $timeepoch, 2);
    $timehumanuser = date("m-d-Y", "$timetemp[0]");
    $timehuman = date("m-d-Y H:i:s e", "$timetemp[0]");
    $doesnot = " ";
    $notdisabled = " ";
    $logemailstatus = "none";
    echo "Name: {$dsarray[$i]['cn'][0]} \t\t Date: $timehuman \t{$dsarray[$i]['dn']}\n";
    //fwrite($fh, logtime() . " - Name: {$dsarray[$i]['cn'][0]} \t\t Expiration Date: $timehuman \t{$dsarray[$i]['dn']}\n");
    if (!isset($dsarray[$i]['pwmresponseset'][0])) {
        echo "PWM Response Set is not stored\n";
        //fwrite($fh, logtime() . " - PWM Response Set is not stored\n");
        $logpwmresponseset = "NOT Stored";
        $doesnot = "<strong>NOT</strong>";
    } else {
        $logpwmresponseset = "Stored";
    }
    if (($dsarray[$i]['useraccountcontrol'][0]) == 512) {
        echo "Account is not disabled\n";
        //fwrite($fh, logtime() . " - Account is not disabled\n");
        $logacctdisabled = "Enabled";
        $notdisabled = "<strong> NOT</strong>";
    } elseif (($dsarray[$i]['useraccountcontrol'][0]) == 514) {
        $logacctdisabled = "Disabled";
    } elseif (($dsarray[$i]['useraccountcontrol'][0]) == 66048) {
        $logacctdisabled = "Enabled-NoPasswdExpiry";
    } elseif (($dsarray[$i]['useraccountcontrol'][0]) == 66050) {
        $logacctdisabled = "Disabled-NoPasswdExpiry";
    } else {
        $logacctdisabled = "Unknown";
    }

    // Check to see if password has already expired.
    if ($dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] < $dateasadint) {
        $from=date_create(date('Y-m-d'));
        $to=date_create(date("Y-m-d", "$timetemp[0]"));
        $diff = date_diff($to, $from);
        $numdays = $diff->format('%a');
        $timefrom = "$numdays days ago";
        $listforadmin .= "<tr><td>{$dsarray[$i]['samaccountname'][0]}</td><td>is$notdisabled disabled,</td><td>password expired</td><td>$timefrom</td><td>at $timehuman and,</td><td>does $doesnot have PWM password responses stored.\r\n\t<br /></td></tr>";
        $adminlistcount = $adminlistcount + 1;
        $logdays = "-$numdays";
    }

    // Get days until expiration for log.
    if ($dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] > $dateasadint) {
        $from=date_create(date('Y-m-d'));
        $to=date_create(date("Y-m-d", "$timetemp[0]"));
        $diff = date_diff($to, $from);
        $numdays = $diff->format('%a');
        $logdays = "$numdays";
    }

    // Check to see if password expiration is within our warning time limit.
    if ($dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] <= $warndatethresh && $dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] >= $dateasadint) {
        $from=date_create(date('Y-m-d'));
        $to=date_create(date("Y-m-d", "$timetemp[0]"));
        $diff = date_diff($to, $from);
        $numdays = $diff->format('%a');
        $timetill = "in $numdays days";
        $timetillforuser = "in $numdays days on $timehumanuser";

        $listforadmin .= "<tr><td>{$dsarray[$i]['samaccountname'][0]}</td><td>is$notdisabled disabled,</td><td>password expires</td><td>$timetill</td><td>at $timehuman and,</td><td>does $doesnot have PWM password responses stored.\r\n\t<br /></td></tr>";
        $adminlistcount = $adminlistcount + 1;
        echo "WARNING! Password will expire.\n";
        echo "Sending email to {$dsarray[$i]['cn'][0]} at address {$dsarray[$i]['mail'][0]} \n";
        //fwrite($fh, logtime() . " - Sending email to {$dsarray[$i]['cn'][0]} at address {$dsarray[$i]['mail'][0]} \n");

        $logsendwarning = "true";

        //If debug is enabled, then send all emails to admin
        if ($debug=="0") {
            //If pwmresponseset is not set replace pwmstatus with user guidance to setup pwmresponseset
            $noreply = "null";
            $pwmstatus = " ";
            if ($doesnot == "<strong>NOT</strong>") {
                $pwmstatus = "You have NOT setup your password responses in PWM to ease password recovery.  Please do so now.";
            }
            //If mail is set in LDAP send to mail, if account is disabled send to null, if mail not set send to admin email.
            if ($dsarray[$i]['mail'][0] && $dsarray[$i]['useraccountcontrol'][0] == 512) {
                $userto = "{$dsarray[$i]['mail'][0]}";
            } elseif ($dsarray[$i]['useraccountcontrol'][0] == 514) {
                $userto = $noreply;
            } else {
                $userto = $adminemailto;
            }

            $usersubject = "Password for {$dsarray[$i]['samaccountname'][0]} will expire soon!";

            // Warning Email
            // Get the email from a template in the same directory as this script.
            if (file_exists($scriptPath . "user_email_inlined.tpl")) {
                $userbody = file_get_contents($scriptPath . "user_email_inlined.tpl");
                $userbody = str_replace("__DISPLAYNAME__", $dsarray[$i]['givenname'][0], $userbody);
                $userbody = str_replace("__SAMACCOUNTNAME__", $dsarray[$i]['samaccountname'][0], $userbody);
                $userbody = str_replace("__EXPIRETIME__", $timetillforuser, $userbody);
                $userbody = str_replace("__PWMRESPONSESETSTATUS__", $pwmstatus, $userbody);
            }

            // Send the email to the user.
            if (mail($userto, $usersubject, $userbody, $useremailheader)) {
                echo("User email successfully sent.\n");
                //fwrite($fh, logtime() . " - User email successfully sent.\n");
                $emailcount++;
                $logemailstatus = "success";
            } else {
                echo("User email delivery failed.\n");
                //fwrite($fh, logtime() . " - User email delivery failed.\n");
                $logemailstatus = "failed";
            }
            unset($pwmstatus);
            //End If Debug
        } else {
            $logemailstatus = "debugging";
        }
        //End If check for expiration within warning time limit.
    } else {
        $logsendwarning = "false";
    }
    $status = ['passwordexpiration' => $timehuman, 'daystillexpire' => $logdays, 'accountstate' => $logacctdisabled, 'pwmresponsesetstate' => $logpwmresponseset ];
    $action = ['emailaddr' => $dsarray[$i]['mail'][0], 'sendwarning' => $logsendwarning, 'emailsendstatus' => $logemailstatus ];
    $logdata = ['username' => $dsarray[$i]['cn'][0], 'status' => $status, 'action' => $action ];
    $jsonlogdata = json_encode($logdata);
    fwrite($fh, logtime() . " - $jsonlogdata\n");

    //Unset some variables before continuing the loop.
    unset($timeepoch);
    unset($timetemp);
    unset($timehuman);
    unset($userto);
    unset($usersubject);
    unset($userbody);
    unset($doesnot);
    unset($notdisabled);
    unset($timefrom);
    unset($timetill);
    unset($diff);
    unset($logsendwarning);
    unset($logemailstatus);
    unset($logacctdisabled);
    unset($logpwmresponseset);
    unset($logdays);

    //End For loop for each entry in LDAP.
}
fwrite($fh, logtime() . " - -----\n");
//Send email list of users to admin if debug enabled or it is a Monday.
if (($listforadmin && $debug == "1") || ($listforadmin && $currentdayofweek == 1)) {
    $adminsubject = "List of Expired or Expiring Passwords";
    if (file_exists($scriptPath . "admin_email_inlined.tpl")) {
        $adminbody = file_get_contents($scriptPath . "admin_email_inlined.tpl");
        $adminbody = str_replace("__CURRENTDATE__", $currentdatehuman, $adminbody);
        $adminbody = str_replace("__USERLIST__", $listforadmin, $adminbody);
        $adminbody =  str_replace("__USEROU__", $argumentOU['o'], $adminbody);
        $adminbody =  str_replace("__USERCOUNT__", $adminlistcount, $adminbody);
    }

    if (mail($adminemailto, $adminsubject, $adminbody, $adminemailheader)) {
        echo("Admin email successfully sent.\n");
        fwrite($fh, logtime() . " - Admin email successfully sent.\n");
        $emailcount++;
    } else {
        echo("Admin email delivery failed.\n");
        fwrite($fh, logtime() . " - Admin email delivery failed.\n");
    }
} else {
    fwrite($fh, logtime() . " - No Admin email delivery attempted. Not set to debug or not appropriate day of week.\n");
}

//  Unbind and Disconnect from Server
$unbind = ldap_unbind($ldapconn);

if ($unbind) {
    echo "LDAP successfully unbound.\n";
    fwrite($fh, logtime() . " - LDAP successfully unbound.\n");
} else {
    echo "LDAP not unbound.\n";
    fwrite($fh, logtime() . " - LDAP not unbound.\n");
}
fwrite($fh, logtime() . " - $emailcount emails sent.\n");
fclose($fh);
