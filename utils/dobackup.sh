backup_path="/opt/ctdb/tmp/backup"
s3_path="`date +%Y-%m-%d-%s`"
mkdir -p $backup_path
pg_dump -Fc ctdb -U postgres > $backup_path/ctdb.bin
pg_dump -Fc ctwiki -U postgres > $backup_path/ctwiki.bin
tar --gzip --create --file="$backup_path/ctdb.tgz" /opt/ctdb/www/ctdbweb --exclude="/opt/ctdb/www/ctdbweb/parity/*"
docker run --rm --volumes-from ctwiki -v $backup_path:/backup ubuntu bash -c "cd /var/www/html/images && tar cf /backup/ctwiki-images.tar ."
mkdir /mnt/backups.cuetools.net/$s3_path
s3cmd --no-progress --rr put $backup_path/ctdb.bin $backup_path/wiki.bin $backup_path/ctdb.tgz $backup_path/wiki.tgz s3://backups.cuetools.net/$s3_path/
rm $backup_path/ctdb.bin $backup_path/ctwiki.bin $backup_path/ctdb.tgz $backup_path/ctwiki-images.tar
