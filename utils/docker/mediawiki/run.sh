docker run --name ctwiki -d --network ct --restart always \
  -v ctwikiimages:/var/www/html/images \
  -v /var/run/postgresql:/var/run/postgresql \
  -v /opt/db.cue.tools/utils/docker/mediawiki/LocalSettings.php:/var/www/html/LocalSettings.php \
  ctwiki
