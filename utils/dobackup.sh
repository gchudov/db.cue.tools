backup_path="/tmp/backup"
s3_path="`date +%Y-%m-%d-%s`"
mkdir $backup_path
pg_dump -Fc ctdb -U postgres > $backup_path/ctdb.bin
pg_dump -Fc wikidb -U postgres > $backup_path/wiki.bin
tar --gzip --create --file="$backup_path/ctdb.tgz" /var/www/ctdbweb --exclude="/var/www/ctdbweb/parity/*"
tar --gzip --create --file="$backup_path/wiki.tgz" /var/www/w
mkdir /mnt/backups.cuetools.net/$s3_path
s3cmd --no-progress --rr put $backup_path/ctdb.bin $backup_path/wiki.bin $backup_path/ctdb.tgz $backup_path/wiki.tgz s3://backups.cuetools.net/$s3_path/
rm $backup_path/ctdb.bin $backup_path/wiki.bin $backup_path/ctdb.tgz $backup_path/wiki.tgz
