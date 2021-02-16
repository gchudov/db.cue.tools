dbname=discogs1
dbuser=discogs
dbmaster=postgres
dbcont=postgres96
psql="docker exec -i $dbcont psql"
pg_restore="docker exec -i $dbcont pg_restore"
$psql -U postgres -d $dbmaster -c "DROP DATABASE $dbname"
$psql -U postgres -d $dbmaster -c "CREATE ROLE $dbuser LOGIN"
$psql -U postgres -d $dbmaster -c "CREATE DATABASE $dbname OWNER $dbuser"
$psql -U postgres -d $dbname -c "CREATE EXTENSION cube"
aws s3 cp --quiet "s3://private.cuetools.net/discogs/$(date +%Y%m)01/discogs.bin" - | $pg_restore -U postgres -d $dbname --no-owner
$psql -U postgres -d $dbname -c "GRANT ALL ON ALL TABLES IN SCHEMA public TO $dbuser"
#for table in artist_credit_name artist_credit artist_name video label release releases_formats releases_images releases_identifiers releases_videos releases_labels toc track track_title ; do
#  $psql -U postgres -d $dbname -c "ALTER TABLE public.$table SET (autovacuum_enabled = FALSE, toast.autovacuum_enabled = FALSE)"
#done
$psql -U postgres -d $dbname -c "VACUUM"
$psql -U postgres -d $dbname -c "ANALYZE"
