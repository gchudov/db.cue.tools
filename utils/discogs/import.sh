dbname=discogs1
dbuser=discogs
dbdump="/tmp/$(basename $0).$$.tmp"
s3cmd --no-progress get s3://private.cuetools.net/discogs/`date +%Y%m`01/discogs.bin $dbdump
psql -U postgres -c "DROP DATABASE $dbname"
psql -U postgres -c "CREATE DATABASE $dbname OWNER $dbuser"
pg_restore -d $dbname -U postgres --no-owner $dbdump
for table in artist_credit_name artist_credit artist_name video label release releases_formats releases_images releases_videos releases_labels toc track track_title ; do
  psql -U postgres -d $dbname  -c "GRANT ALL ON public.$table TO $dbuser"
done
psql -U postgres -d $dbname -c "VACUUM"
rm $dbdump
