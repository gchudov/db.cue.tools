backup_path="s3://private.cuetools.net/backups/`date +%Y-%m-%d-%s`/"
pg_dump -Fc ctdb -U ctdb_user > /tmp/ctdb.bin
s3cmd put --rr --no-progress /tmp/ctdb.bin $backup_path
rm /tmp/ctdb.bin
tar --gzip --create --file="/tmp/ctdb.tgz" /var/www/ctdbweb --exclude="/var/www/ctdbweb/parity/*"
s3cmd put --rr --no-progress /tmp/ctdb.tgz $backup_path
rm /tmp/ctdb.tgz
pg_dump -Fc wikidb -U postgres > /tmp/wiki.bin
s3cmd put --rr --no-progress /tmp/wiki.bin $backup_path
rm /tmp/wiki.bin
tar --gzip --create --file="/tmp/wiki.tgz" /var/www/w/images /var/www/w/extensions /var/www/w/skins /var/www/w/LocalSettings.php
s3cmd put --rr --no-progress /tmp/wiki.tgz $backup_path
rm /tmp/wiki.tgz
