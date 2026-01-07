backup_path="/var/tmp/backup"
s3_path="`date +%Y-%m-%d-%s`"
mkdir -p $backup_path
docker exec postgres16 pg_dump -Z 3 -Fc ctdb -U postgres > $backup_path/ctdb.bin
docker exec postgres16 pg_dump -Z 3 -Fc ctwiki -U postgres > $backup_path/ctwiki.bin
#tar --gzip --create --file="$backup_path/ctdb.tgz" /opt/ctdb/www/ctdbweb --exclude="/opt/db.cue.tools/parity/*"
docker run --rm --volumes-from ctwiki -v $backup_path:/backup ubuntu bash -c "cd /var/www/html/images && tar cf /backup/ctwiki-images.tar ."
aws s3 sync --quiet --storage-class REDUCED_REDUNDANCY $backup_path/ s3://backups.cuetools.net/$s3_path/
echo -n "$s3_path" > $backup_path/LATEST
aws s3 cp --quiet $backup_path/LATEST s3://backups.cuetools.net/
rm -rf $backup_path/*
