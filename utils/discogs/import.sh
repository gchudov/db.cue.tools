dbname=discogs1
dbuser=discogs
psql -U postgres -c "DROP DATABASE $dbname"
psql -U postgres -c "CREATE DATABASE $dbname OWNER $dbuser"
pg_restore -d $dbname -U postgres --no-owner "/mnt/private.cuetools.net/discogs/$(date +%Y%m)01/discogs.bin"
for table in artist_credit_name artist_credit artist_name video label release releases_formats releases_images releases_identifiers releases_videos releases_labels toc track track_title ; do
  psql -U postgres -d $dbname  -c "ALTER TABLE public.$table SET (autovacuum_enabled = FALSE, toast.autovacuum_enabled = FALSE)"
  psql -U postgres -d $dbname  -c "GRANT ALL ON public.$table TO $dbuser"
done
psql -U postgres -d $dbname -c "VACUUM"
psql -U postgres -d $dbname -c "ANALYZE"
