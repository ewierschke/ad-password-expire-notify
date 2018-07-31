#!/bin/bash
#--enable debug--
/usr/bin/sed -i.baktest "0,/\$debug.*/ s/\$debug.*/\$debug = \"1\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
#--backup and adjust admin_email
/usr/bin/cp /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.tmptestrun
/usr/bin/sed -i.baktest "s|_ENVIRNAME_|New Instance Launch|g" /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.env2bak
/usr/bin/cp /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.env2bak /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl
#--create execution from cron job file
/usr/bin/cut -b 17- /etc/cron.d/ad-password-expire-notify > /usr/local/bin/scriptrun
#--execute
/usr/bin/cat /usr/local/bin/scriptrun | bash
if [[ $? -ne 0 ]]
then
    exit 1
fi
#--revert admin email
/usr/bin/cp /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.tmptestrun /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl
/usr/bin/cp /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.env2bak.baktest /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.env2bak
#--disable debug for normal operation in cron job
/usr/bin/sed -i.bakrevert "0,/\$debug = \"1\".*/ s/\$debug = \"1\".*/\$debug = \"0\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
/usr/bin/chmod 600 /usr/local/bin/ad-password-expire-notify/check_expire.php*
