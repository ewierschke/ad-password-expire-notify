<?php

// Provided AS IS under the GNU license located at: http://www.gnu.org/licenses/gpl.txt

// To use the script.  
// Set the variables below, then execute the script with the users OU as an argument.
// Example: /path/to/scriptdir/check_expire.php -ou "CN=People, DC=Domain, DC=org"

// After setting the options below, be sure to update the two email templates included
// to include your company logo and instructions for the end-user.

// Some variables will need to be set first.

// Full path to this script.
$scriptPath 	= "/usr/local/pwdexpire/";
// A regular user to bind to AD.  Use the upn format.
$ldapupn		= "ldap_bind@sub.domain.tld";
// That users password
$ldappass 		= "password";
// To make a connection to the domain controller over SSL use ldaps:// instead of ldap://
$ldaphost 		= "ldap://dc.sub.domain.tld/";
//  How many days out to start warning the user.
$warndays		= "15";
$disabledays	= "60";
// From email header  on end-user notifications.  
$useremailheader = "MIME-Version: 1.0" . "\r\n";
$useremailheader .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$useremailheader .= "From: IT Support <support@sub.domain.tld>" . "\r\n";

// Email alias for administrators.  This email will get a listing of the users that are expiring.
$adminemailto 		= "admin@sub.domain.tld";
// From email header  on admin notifications.  
$adminemailheader = "MIME-Version: 1.0" . "\r\n";
$adminemailheader .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$adminemailheader .= "From: IT Support <support@sub.domain.tld>" . "\r\n";

// Debugging Options
// 1 is Enabled, 0 is Disabled
// When debug is enabled, no emails will be sent to the users.
$debug			= "0";


// End Options - Begin Workflow

// Default variables
$listforadmin 	= "<tr><th><u>Username</u></th><th><u>Account Status</u></th><th><u>Password Status</u></th><th><u>Password Expired Time (in days)</u></th><th><u>Password Expired Time</u></th><th><u>PWM Response set Status</u>\r\n\t<br /></th></tr>";
$filter			= "(&(objectCategory=Person)(objectClass=User))";
$attrib			= array("sn", "givenname", "cn", "sAMAccountName", "msDS-UserPasswordExpiryTimeComputed", "mail", "pwmresponseset", "useraccountcontrol");

//Check that the proper command line arguments have been passed to the script.
$argumentOU = getopt("o:");

if ($argumentOU) {
    echo("Checking for expired passwords in OU: {$argumentOU['o']}\n");
	$dn = $argumentOU['o'];
}else{ 
	echo("You must specify an LDAP OU in the arguments passed to this script.  Example: /path/to/scriptdir/scriptname.php -o \"CN=Users, DC=Domain, DC=org\" ");
    exit;
}

// Get current time
$now	= time();
$currentdatehuman = date("m-d-Y", "$now");
$currentdayofweek = date("w", "$now");

/*
AD date values.  Offset is approximate 10millionths of a second from 
1/1/1601 to 1/1/1970 (Epoch).  MS stores the time as number of 100 nanoseconds
since 1/1/1601.  Since we get epoch from now(), we need to add the difference.
*/
$offset		= 116444736000000000;
$oneday		= 864000000000;
$daystowarn	= $oneday * $warndays;
$disabledaystowarn = $oneday * ($disabledays - $warndays);
$disableindays = $oneday * $disabledays;

//Set current date in large int as AD does
$dateasadint	= ($now * 10000000) + $offset;

// Set search value for todays date plus warning time.
$warndatethresh	= $dateasadint + $daystowarn;
$disablewarndatethresh	= $dateasadint + $disabledaystowarn;

echo "Current Date: $currentdatehuman\n";
echo "Now in Epoch: $now \n";
echo "Using number days to warn: $warndays\n";

// Connect to LDAP
echo "Beginning LDAP search...\n";
$ldapconn = ldap_connect($ldaphost)
         or die("Could not connect to {$ldaphost}.\n");

if ($ldapconn) {
   
   echo "Attempting LDAP bind.\n";
 
   // Bind to LDAP.
   $ldapbind = ldap_bind($ldapconn, $ldapupn, $ldappass);

   // Verify LDAP connected.
   if ($ldapbind) {
       echo "LDAP bind successful.\n";
   } else {
       echo "LDAP bind failed.\n";
       exit(1);
   }

}

// Search LDAP using filter, get the entries, and set count.
$search = ldap_search($ldapconn, $dn, $filter, $attrib, 0, 0)
or die ("Could not search LDAP server.\n");

$dsarray = ldap_get_entries($ldapconn, $search);
$count = $dsarray["count"];
$adminlistcount = 0;

echo "$count Entries found.\n";

for($i = 0; $i < $count; $i++) {
	// Converts large int from AD to epoch then to human readable format
	$timeepoch = ($dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] - 116444736000000000) / 10000000;
	$disabletimeepoch = (($dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] + $disableindays) - 116444736000000000) / 10000000;
	$timetemp = split( "[.]" ,$timeepoch, 2);
	$disabletimetemp = split( "[.]" ,$disabletimeepoch, 2);
	$timehumanuser = date("m-d-Y", "$timetemp[0]");
	$disabletimehumanuser = date("m-d-Y", "$disabletimetemp[0]");
	$timehuman = date("m-d-Y H:i:s e", "$timetemp[0]");
	$disabletimehuman = date("m-d-Y H:i:s e", "$disabletimetemp[0]");
	$doesnot = " ";
	$notdisabled = " ";
	$userdisablewarn = $dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] + $disabledaystowarn;
	$userdisable = $dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] + $disableindays;
	$usermustreset = "51840000000000";
	echo "Name: {$dsarray[$i]['cn'][0]} \t\t ExpireDate: $timehuman \t\t DisableDate: $disabletimehuman \t{$dsarray[$i]['dn']}\n";
	if (!isset($dsarray[$i]['pwmresponseset'][0])) {
		echo "PWM Response Set is not stored\n";
		$doesnot = "<strong>NOT</strong>";
	}
	if (($dsarray[$i]['useraccountcontrol'][0]) == 512) {
		echo "Account is not disabled\n";
		$notdisabled = "<strong> NOT</strong>";
	}

			// Check to see if password expiration is greater than disable date and not set to require reset, then disable.
		if($debug=="0") {
			if ($userdisable > $usermustreset && $userdisable < $dateasadint && $dsarray[$i]['useraccountcontrol'][0] == 512) {
				$new['useraccountcontrol'][0] = 514;
				$new['description'][0] = "Account disabled by script due to inactivity on $currentdatehuman";
				ldap_modify($ldapconn, $dsarray[$i]['dn'], $new);
				$from=date_create(date('Y-m-d'));
				$to=date_create(date("Y-m-d", "$timetemp[0]"));
				$diff = date_diff($to,$from);
				$numdays = $diff->format('%a');
				$timefrom = "$numdays days ago";
				$listforadmin .= "<tr><td>{$dsarray[$i]['samaccountname'][0]}</td><td>was disabled,</td><td>password expired</td><td>$timefrom</td><td>at $timehuman and,</td><td>does $doesnot have PWM password responses stored.\r\n\t<br /></td></tr>";
				$adminlistcount = $adminlistcount + 1;
			}
		}

			// Check to see if today is greater than or equal to disabledays minus warndays (warn period) and less than or equal to the disabledays (disable period) and not disabled.
//			if ($dateasadint >= $userdisablewarn && $dateasadint <= $userdisable && $dsarray[$i]['useraccountcontrol'][0] == 512) {
//				$from=date_create(date('Y-m-d'));
//				$to=date_create(date("Y-m-d", "$disabletimetemp[0]"));
//				$diff = date_diff($to,$from);
//				$numdays = $diff->format('%a');
//				$disablefrom=date_create(date('Y-m-d'));
//				$disableto=date_create(date("Y-m-d", "$timetemp[0]"));
//				$disablediff = date_diff($disableto,$disablefrom);
//				$disablenumdays = $disablediff->format('%a');
//				$timefrom = "$disablenumdays days ago";
//				$timetillforuser = "in $numdays days on $disabletimehumanuser";

//				$listforadmin .= "<tr><td>{$dsarray[$i]['samaccountname'][0]}</td><td>is$notdisabled disabled,</td><td>password expired</td><td>$timefrom</td><td>at $disabletimehuman and,</td><td>does $doesnot have PWM password responses stored.\r\n\t<br /></td></tr>";
//				$adminlistcount = $adminlistcount + 1;

//				print "WARNING! Account will be disabled.\n";
//				echo "Sending email to {$dsarray[$i]['cn'][0]} at address {$dsarray[$i]['mail'][0]} \n";

//				//If debug is enabled, then send all emails to admin
//				if($debug=="0") {
//					//If pwmresponseset is not set replace pwmstatus with user text to setup pwmresponseset
//					$noreply = "null";
//					$pwmstatus = " ";
//					if($doesnot == "<strong>NOT</strong>") {
//						$pwmstatus = "You have NOT setup your password responses in PWM to ease password recovery.  Please do so now.";
//					}
					//If mail is defined in LDAP use mail, if not send to admin email.
//					if($dsarray[$i]['mail'][0] && $dsarray[$i]['useraccountcontrol'][0] == 512)  {
//						$userto = "{$dsarray[$i]['mail'][0]}";
//					} elseif ($dsarray[$i]['useraccountcontrol'][0] == 514) {
//						$userto = $noreply;
//					} else { 
//						$userto = $adminemailto;
//					}

//					$usersubject = "Account for {$dsarray[$i]['samaccountname'][0]} will be disabled soon!";

					// Warning Email
					// Get the email from a template in the same directory as this script.
//					if(file_exists($scriptPath . "disable_user_email_inlined.tpl")) {
//						$userbody = file_get_contents($scriptPath . "disable_user_email_inlined.tpl");
//						$userbody = str_replace("__DISPLAYNAME__", $dsarray[$i]['givenname'][0], $userbody);
//						$userbody = str_replace("__SAMACCOUNTNAME__", $dsarray[$i]['samaccountname'][0], $userbody);
//						$userbody = str_replace("__EXPIRETIME__", $timetillforuser, $userbody);
//						$userbody = str_replace("__PWMRESPONSESETSTATUS__", $pwmstatus, $userbody);
//					}

						// Send the email to the user.
//							if (mail($userto, $usersubject, $userbody, $useremailheader)) {
//								echo("User email successfully sent.\n");
//							} else {
//								echo("User email delivery failed.\n");
//							}
//					unset($pwmstatus);
					//End If Debug
//					}
			//End check for expiration within warning time limit.
//			}

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
	
//End for loop for each entry in LDAP.
}

//Send email of users to admin.
if (($listforadmin && $debug == "1") || ($listforadmin && $currentdayofweek == 1)) {
	$adminsubject = "List of Accounts Disabled by script";
	if(file_exists($scriptPath . "admin_email_inlined.tpl")) {
				$adminbody = file_get_contents($scriptPath . "admin_email_inlined.tpl");
				$adminbody = str_replace("__CURRENTDATE__", $currentdatehuman, $adminbody);
				$adminbody = str_replace("__USERLIST__", $listforadmin, $adminbody);
				$adminbody =  str_replace("__USEROU__", $argumentOU['o'], $adminbody);
				$adminbody =  str_replace("__USERCOUNT__", $adminlistcount, $adminbody);
				}

		if (mail($adminemailto, $adminsubject, $adminbody, $adminemailheader)) {
                       echo("Admin email successfully sent.\n");
                       } else {
                       echo("Admin email delivery failed.\n");
                 }
}

//  Unbind and Disconnect from Server
$unbind = ldap_unbind($ldapconn);

if ($unbind) {
	echo "LDAP successfully unbound.\n";
} else {
	echo "LDAP not unbound.\n";
}

?>
