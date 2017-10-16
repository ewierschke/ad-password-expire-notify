# Active Directory Password Expiration Notifcations
This php script can be used to send users daily email notifications warning them
when their password is about to expire.  

### Setup
Modify the variables in the beginning of the script.
* scriptPath: Full path to the PHP script parent directory.
* ldapupn: AD userprincipal name used to bind to AD.
* ldappass: AD userprincipal name password.
* ldaphost: AD domain controller.
* warndays: Number of days to start warning the user.
* useremailheader: Email header information for end-user notifications.
* adminemailto: Admin email address to receive summary of notifications.
* adminemailheader: Email header information for admin notifications.



Edit .tpl files to adjust email format for notifications.

### Running
Execute php script with the -o flag to specifiy which OU to search for users 
with expiring passwords

Edit the file user_email_inlined.tpl to replace \_ENVIRNAME\_, \_PWMURL\_, 
\_OSTURL\_, and \_OSTEMAIL\_ with environment specific entries

When running from CentOS, 
* Postfix configuration is assumed to be working, 
* php and php-ldap packages should be installed; `yum -y install php php-ldap`
* Edit /etc/php.ini to adjust the appropriate date.timezone = value 
(https://secure.php.net/manual/en/timezones.php)
as well as potential to include sendmail arguments for 
-F (for friendly/full name) and -f (for address of the from person)
```bash
sed -i 's|;date.timezone =|date.timezone = America/New_York|' /etc/php.ini
sed -i 's|sendmail_path = .*|sendmail_path = /usr/sbin/sendmail -t -i -f \"sendfrom@example.com\"|' /etc/php.ini
```

Example: `php /path/to/script/check_expire.php -o "CN=Users, DC=example, DC=com"`

