docker run -d --name adminer --network ct --restart always \
  -e ADMINER_DEFAULT_SERVER=postgres96 \
  -v /opt/db.cue.tools/utils/docker/adminer/login-otp.php:/var/www/html/plugins-enabled/login-otp.php \
  -v /var/run/postgresql:/var/run/postgresql \
  adminer
