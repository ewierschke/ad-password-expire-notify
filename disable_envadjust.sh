#!/bin/bash
#--pull variables from files--
export ENVIRNAME=$(cat /usr/local/bin/envirname)
export PWMURL=$(cat /usr/local/bin/pwmurl)
export OSTURL=$(cat /usr/local/bin/osturl)
export OSTEMAIL=$(cat /usr/local/bin/ostemail)
export EMAILFROM=$(cat /usr/local/bin/emailfrom)
export SVCACCTUPN=$(cat /usr/local/bin/svcacctupn)
export SVCACCTPASS=$(cat /usr/local/bin/svcacctpass)
export LDAPHOSTNAME=$(cat /usr/local/bin/ldaphostname)
export ADMINEMAILTO=$(cat /usr/local/bin/adminemailto)
export OUPATH=$(cat /usr/local/bin/oupath)

#--envir adjust--
sed -i.env1bak "s|_ENVIRNAME_|${ENVIRNAME}|g" /usr/local/bin/ad-password-expire-notify/disable_user_email_inlined.tpl
sed -i.env2bak "s|_ENVIRNAME_|${ENVIRNAME}|g" /usr/local/bin/ad-password-expire-notify/disable_admin_email_inlined.tpl
#sed -i.pwmurlbak "s|_PWMURL_|${PWMURL}|g" /usr/local/bin/ad-password-expire-notify/user_email_inlined.tpl
sed -i.ostbak "s|_OSTURL_|${OSTURL}|g" /usr/local/bin/ad-password-expire-notify/user_email_inlined.tpl
sed -i.ostemailbak "s|_OSTEMAIL_|${OSTEMAIL}|g" /usr/local/bin/ad-password-expire-notify/user_email_inlined.tpl
chmod 600 /usr/local/bin/ad-password-expire-notify/*email_inlined.tpl*

#--php adjust--
sed -i.tzbak "s|;date.timezone =|date.timezone = America/New_York|" /etc/php.ini
sed -i.mailfrombak "s|sendmail_path = .*|sendmail_path = /usr/sbin/sendmail -t -i -f \"${EMAILFROM}\"|" /etc/php.ini
chmod 600 /etc/php.ini.*

#--check_disable adjust--
sed -i.pathbak '0,/\$scriptPath.*/ s/\$scriptPath.*/\$scriptPath = "\/usr\/local\/bin\/ad-password-expire-notify\/";/' /usr/local/bin/ad-password-expire-notify/check_disable.php
sed -i.upnbak "0,/\$ldapupn.*/ s/\$ldapupn.*/\$ldapupn = \"${SVCACCTUPN}\";/" /usr/local/bin/ad-password-expire-notify/check_disable.php
sed -i.passbak "0,/\$ldappass.*/ s/\$ldappass.*/\$ldappass = \"${SVCACCTPASS}\";/" /usr/local/bin/ad-password-expire-notify/check_disable.php
##todo-need to control for $ or other res chars in password
sed -i.hostbak "0,/\$ldaphost.*/ s/\$ldaphost.*/\$ldaphost = \"${LDAPHOSTNAME}\";/" /usr/local/bin/ad-password-expire-notify/check_disable.php
sed -i.mailtobak "0,/\$adminemailto.*/ s/\$adminemailto.*/\$adminemailto = \"${ADMINEMAILTO}\";/" /usr/local/bin/ad-password-expire-notify/check_disable.php
sed -i.usrmailfrombak "0,/\$useremailheader \.= \"From.*/ s/\$useremailheader \.= \"From.*/\$useremailheader \.= \"From: Account Disable Warning <${EMAILFROM}>\" . \"\\\r\\\n\";/" /usr/local/bin/ad-password-expire-notify/check_disable.php
sed -i.admmailfrombak "0,/\$adminemailheader \.= \"From.*/ s/\$adminemailheader \.= \"From.*/\$adminemailheader \.= \"From: Account Disable Warning <${EMAILFROM}>\" . \"\\\r\\\n\";/" /usr/local/bin/ad-password-expire-notify/check_disable.php
##
sed -i.pathbak '0,/\$scriptPath.*/ s/\$scriptPath.*/\$scriptPath = "\/usr\/local\/bin\/ad-password-expire-notify\/";/' /usr/local/bin/ad-password-expire-notify/disable_inactive.php
sed -i.upnbak "0,/\$ldapupn.*/ s/\$ldapupn.*/\$ldapupn = \"${SVCACCTUPN}\";/" /usr/local/bin/ad-password-expire-notify/disable_inactive.php
sed -i.passbak "0,/\$ldappass.*/ s/\$ldappass.*/\$ldappass = \"${SVCACCTPASS}\";/" /usr/local/bin/ad-password-expire-notify/disable_inactive.php
##todo-need to control for $ or other res chars in password
sed -i.hostbak "0,/\$ldaphost.*/ s/\$ldaphost.*/\$ldaphost = \"${LDAPHOSTNAME}\";/" /usr/local/bin/ad-password-expire-notify/disable_inactive.php
sed -i.mailtobak "0,/\$adminemailto.*/ s/\$adminemailto.*/\$adminemailto = \"${ADMINEMAILTO}\";/" /usr/local/bin/ad-password-expire-notify/disable_inactive.php
sed -i.usrmailfrombak "0,/\$useremailheader \.= \"From.*/ s/\$useremailheader \.= \"From.*/\$useremailheader \.= \"From: Account Disable Warning <${EMAILFROM}>\" . \"\\\r\\\n\";/" /usr/local/bin/ad-password-expire-notify/disable_inactive.php
sed -i.admmailfrombak "0,/\$adminemailheader \.= \"From.*/ s/\$adminemailheader \.= \"From.*/\$adminemailheader \.= \"From: Account Disable Warning <${EMAILFROM}>\" . \"\\\r\\\n\";/" /usr/local/bin/ad-password-expire-notify/disable_inactive.php
chmod 600 /usr/local/bin/ad-password-expire-notify/check_disable.php*
