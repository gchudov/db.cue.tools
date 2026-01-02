FROM debian:bookworm-slim
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		sendmail sendmail-cf m4 sasl2-bin libsasl2-modules rsyslog openssl patch \
		vim less man \
	; \
	rm -rf /var/lib/apt/lists/*; \
	# Disable imklog module (can't read /proc/kmsg in container)
	sed -i '/imklog/s/^/#/' /etc/rsyslog.conf

RUN sed -i "s|START=no|START=yes|" /etc/default/saslauthd \
	&& service saslauthd restart

#	&& ln -sf /dev/stdout /var/log/mail.log
#	&& ln -sf /dev/stderr /var/log/mail.err
#        && update-rc.d rsyslog defaults

ENV HOSTNAME sendmail
#ENV HOSTNAME mail.cue.tools
ENV SUBNET 172.18

# https://docs.aws.amazon.com/ses/latest/dg/send-email-sendmail.html
# AuthInfo:email-smtp.us-west-2.amazonaws.com "U:root" "I:smtpUsername" "P:smtpPassword" "M:PLAIN"
RUN cp /etc/mail/access /etc/mail/access.bak
RUN cp /etc/mail/sendmail.mc /etc/mail/sendmail.mc.bak
# ADD authinfo /etc/mail/authinfo
# RUN makemap hash /etc/mail/authinfo.db < /etc/mail/authinfo
ADD access /etc/mail/access
RUN makemap hash /etc/mail/access.db < /etc/mail/access

# Add the following lines to the /etc/mail/sendmail.mc file before any MAILER() definitions.
ADD sendmail.mc.diff /etc/mail/sendmail.mc.diff
RUN patch -p0 < /etc/mail/sendmail.mc.diff
# Tell  sendmail to listen on local network (on local network interface/IP)
#RUN sed -i "s|Addr=127.0.0.1|Addr=`hostname -I`|" /etc/mail/sendmail.mc

ADD entrypoint.sh /entrypoint.sh
RUN chmod u+x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
