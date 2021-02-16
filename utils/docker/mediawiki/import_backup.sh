latest=`aws s3 cp --quiet s3://backups.cuetools.net/LATEST -`
aws s3 cp --quiet "s3://backups.cuetools.net/$latest/ctwiki-images.tar" /tmp/
docker run --rm --volumes-from ctwiki -v /tmp/ctwiki-images.tar:/backup/ctwiki-images.tar ubuntu bash -c "cd /var/www/html/images && tar xf /backup/ctwiki-images.tar ."
dbname=ctwiki
dbuser=ctwiki
dbmaster=postgres
dbcont=postgres96
psql="docker exec -i $dbcont psql"
pg_restore="docker exec -i $dbcont pg_restore"
$psql -U postgres -d $dbmaster -c "DROP DATABASE $dbname"
$psql -U postgres -d $dbmaster -c "CREATE ROLE $dbuser LOGIN"
$psql -U postgres -d $dbmaster -c "CREATE DATABASE $dbname OWNER $dbuser"
aws s3 cp --quiet "s3://backups.cuetools.net/$latest/ctwiki.bin" - | $pg_restore -U postgres -d $dbname --no-owner
$psql -U postgres -d $dbname -c "GRANT ALL ON SCHEMA mediawiki TO $dbuser"
$psql -U postgres -d $dbname -c "GRANT ALL ON ALL TABLES IN SCHEMA mediawiki TO $dbuser"
$psql -U postgres -d $dbname -c "VACUUM"
$psql -U postgres -d $dbname -c "ANALYZE"
rm "/tmp/ctwiki-images.tar"
