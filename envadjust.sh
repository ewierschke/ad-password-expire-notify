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
sed -i.bak1 "s|_ENVIRNAME_|${ENVIRNAME}|g" /usr/local/bin/ad-password-expire-notify/user_email_inlined.tpl
sed -i.bak "s|_ENVIRNAME_|${ENVIRNAME}|g" /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl
sed -i.bak2 "s|_PWMURL_|${PWMURL}|g" /usr/local/bin/ad-password-expire-notify/user_email_inlined.tpl
sed -i.bak3 "s|_OSTURL_|${OSTURL}|g" /usr/local/bin/ad-password-expire-notify/user_email_inlined.tpl
sed -i.bak4 "s|_OSTEMAIL_|${OSTEMAIL}|g" /usr/local/bin/ad-password-expire-notify/user_email_inlined.tpl

#--php adjust--
sed -i.bak1 "s|;date.timezone =|date.timezone = America/New_York|" /etc/php.ini
sed -i.bak2 "s|sendmail_path = .*|sendmail_path = /usr/sbin/sendmail -t -i -f \"${EMAILFROM}\"|" /etc/php.ini

#--check_expire adjust--
sed -i.bak1 '0,/\$scriptPath.*/ s/\$scriptPath.*/\$scriptPath = "\/usr\/local\/bin\/ad-password-expire-notify\/";/' /usr/local/bin/ad-password-expire-notify/check_expire.php
sed -i.bak2 "0,/\$ldapupn.*/ s/\$ldapupn.*/\$ldapupn = \"${SVCACCTUPN}\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
sed -i.bak3 "0,/\$ldappass.*/ s/\$ldappass.*/\$ldappass = \"${SVCACCTPASS}\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
##todo-need to control for $ or other res chars in password
sed -i.bak4 "0,/\$ldaphost.*/ s/\$ldaphost.*/\$ldaphost = \"${LDAPHOSTNAME}\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
sed -i.bak5 "0,/\$adminemailto.*/ s/\$adminemailto.*/\$adminemailto = \"${ADMINEMAILTO}\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
sed -i.bak6 "0,/\$useremailheader \.= \"From.*/ s/\$useremailheader \.= \"From.*/\$useremailheader \.= \"From: Password Expiration Warning <${EMAILFROM}>\" . \"\\\r\\\n\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
sed -i.bak7 "0,/\$adminemailheader \.= \"From.*/ s/\$adminemailheader \.= \"From.*/\$adminemailheader \.= \"From: Password Expiration Warning <${EMAILFROM}>\" . \"\\\r\\\n\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
