docker run --name ctdbweb -d --network ct --restart always \
  -v ctparity:/var/www/html/parity \
  -v /var/run/postgresql:/var/run/postgresql \
  -v /opt/db.cue.tools/utils/docker/ctdbweb/ctdbcfg.php:/var/www/html/ctdbcfg.php \
  ctdbweb
