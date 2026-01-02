#!/bin/bash

set -euo pipefail
IP=`hostname -I`

touch /var/log/mail.log
# rsyslogd

# set hostname conf
echo "127.0.0.1 localhost localhost.localdomain" >> /etc/hosts
#echo "127.0.0.1 ${HOSTNAME} localhost localhost.localdomain" >> /etc/hosts
echo "${IP} ${HOSTNAME}" >> /etc/hosts
#echo  "${HOSTNAME}" > /etc/hostname
#echo  "mail.cue.tools" > /etc/hostname

# Tell  sendmail to listen on local network (on local network interface/IP)
# 1. Set local IP instead of loopback
sed -i "s|Addr=127.0.0.1|Addr=${IP}|" /etc/mail/sendmail.mc
sed -i "s|127.0.0.1|${IP}|" /etc/mail/submit.mc
#sed -i "s|127.0.0.1|${IP}|" /etc/mail/access
#RUN makemap hash /etc/mail/access.db < /etc/mail/access

# https://docs.aws.amazon.com/ses/latest/dg/send-email-sendmail.html
# AuthInfo:email-smtp.us-west-2.amazonaws.com "U:root" "I:smtpUsername" "P:smtpPassword" "M:PLAIN"
echo "email-smtp.us-east-1.amazonaws.com \"U:root\" \"I:${AWS_SES_SMTP_USERNAME}\" \"P:${AWS_SES_SMTP_PASSWORD}\" \"M:PLAIN\"" >> /etc/mail/authinfo
/usr/sbin/makemap hash /etc/mail/authinfo.db < /etc/mail/authinfo

#cd /etc/mail && make access.db
cd /etc/mail && make

# run in foreground
# sendmail -bD
/etc/init.d/sendmail start

# keep running
tail -f /var/log/mail.log
