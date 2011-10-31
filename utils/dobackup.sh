backup_path="/mnt/backups.cuetools.net/`date +%Y-%m-%d-%s`"
mkdir $backup_path
pg_dump -Fc ctdb -U postgres > $backup_path/ctdb.bin
pg_dump -Fc wikidb -U postgres > $backup_path/wiki.bin
tar --gzip --create --file="$backup_path/ctdb.tgz" /var/www/ctdbweb --exclude="/var/www/ctdbweb/parity/*"
tar --gzip --create --file="$backup_path/wiki.tgz" /var/www/w/images /var/www/w/extensions /var/www/w/skins /var/www/w/LocalSettings.php
