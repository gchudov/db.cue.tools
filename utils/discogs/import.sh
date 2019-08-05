dbname=discogs1
dbuser=discogs
dbmaster=postgres1
dbhost=localhost
dbport=6544
psql -U postgres -d $dbmaster -h $dbhost -p $dbport -c "DROP DATABASE $dbname"
#psql -U postgres -d $dbmaster -h $dbhost -p $dbport -c "CREATE ROLE $dbuser LOGIN"
psql -U postgres -d $dbmaster -h $dbhost -p $dbport -c "CREATE DATABASE $dbname OWNER $dbuser"
s3cmd --no-progress get "s3://private.cuetools.net/discogs/$(date +%Y%m)01/discogs.bin" "/opt/ctdb/tmp/discogs.bin"
pg_restore -U postgres -d $dbname -h $dbhost -p $dbport --no-owner "/opt/ctdb/tmp/discogs.bin"
rm "/opt/ctdb/tmp/discogs.bin"
for table in artist_credit_name artist_credit artist_name video label release releases_formats releases_images releases_identifiers releases_videos releases_labels toc track track_title ; do
  psql -U postgres -d $dbname -h $dbhost -p $dbport -c "ALTER TABLE public.$table SET (autovacuum_enabled = FALSE, toast.autovacuum_enabled = FALSE)"
  psql -U postgres -d $dbname -h $dbhost -p $dbport -c "GRANT ALL ON public.$table TO $dbuser"
done
psql -U postgres -d $dbname -h $dbhost -p $dbport -c "VACUUM"
psql -U postgres -d $dbname -h $dbhost -p $dbport -c "ANALYZE"
