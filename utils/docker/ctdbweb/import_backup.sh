dbname=ctdb
dbuser=ctdb_user
dbschema=public
dbmaster=postgres
dbcont=postgres16
psql="docker exec -i $dbcont psql"
pg_restore="docker exec -i $dbcont pg_restore"
$psql -U postgres -d $dbmaster -c "DROP DATABASE $dbname"
$psql -U postgres -d $dbmaster -c "CREATE ROLE $dbuser LOGIN"
$psql -U postgres -d $dbmaster -c "CREATE DATABASE $dbname OWNER $dbuser"
latest=`aws s3 cp --quiet s3://backups.cuetools.net/LATEST -`
aws s3 cp --quiet "s3://backups.cuetools.net/$latest/ctdb.bin" - | $pg_restore -U postgres -d $dbname --no-owner
$psql -U postgres -d $dbname -c "GRANT ALL ON SCHEMA $dbschema TO $dbuser"
$psql -U postgres -d $dbname -c "GRANT ALL ON ALL TABLES IN SCHEMA $dbschema TO $dbuser"
$psql -U postgres -d $dbname -c "GRANT ALL ON ALL SEQUENCES IN SCHEMA $dbschema TO $dbuser"
$psql -U postgres -d $dbname -c "VACUUM"
$psql -U postgres -d $dbname -c "ANALYZE"
