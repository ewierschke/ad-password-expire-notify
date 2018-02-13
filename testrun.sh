#!/bin/bash
#--enable debug--
sed -i.baktest "0,/\$debug.*/ s/\$debug.*/\$debug = \"1\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
#--backup and adjust admin_email
cp /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.tmp
sed -i.baktest "s|_ENVIRNAME_|New Instance Launch|g" /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.bak
cp /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.bak /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl
#--create execution from cron job file
cut -b 17- /etc/cron.d/ad-password-expire-notify > /usr/local/bin/scriptrun
#--execute
cat /usr/local/bin/scriptrun | bash
if [[ $? -ne 0 ]]
then
    exit 1
fi
#--revert admin email
cp /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl.tmp /usr/local/bin/ad-password-expire-notify/admin_email_inlined.tpl
#--disable debug for normal operation in cron job
sed -i.bakrevert "0,/\$debug = \"1\".*/ s/\$debug = \"1\".*/\$debug = \"0\";/" /usr/local/bin/ad-password-expire-notify/check_expire.php
