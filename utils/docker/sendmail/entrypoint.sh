#!/bin/bash

set -euo pipefail
IP=`hostname -I`

touch /var/log/mail.log
service rsyslog restart

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

#cd /etc/mail && make access.db
cd /etc/mail && make

# run in foreground
# sendmail -bD
service sendmail restart

# keep running
tail -f /var/log/mail.log
