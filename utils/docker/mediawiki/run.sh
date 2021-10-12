docker run --name ctwiki -d --network ct --restart always \
  -v ctwikiimages:/var/www/html/images \
  -v ctwikiblacklist:/var/www/blacklist \
  -v /var/run/postgresql:/var/run/postgresql \
  -v /opt/db.cue.tools/utils/docker/mediawiki/LocalSettings.php:/var/www/html/LocalSettings.php \
  ctwiki
