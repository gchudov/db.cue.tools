dbname=discogs1
dbuser=discogs
dbdump="/tmp/$(basename $0).$$.tmp"
s3cmd --no-progress get s3://private.cuetools.net/discogs/`date +%Y%m`01/discogs.bin $dbdump
psql -U postgres -c "DROP DATABASE $dbname"
psql -U postgres -c "CREATE DATABASE $dbname OWNER $dbuser"
pg_restore -d $dbname -U discogs --no-owner $dbdump
psql -U postgres -d $dbname -c "VACUUM"
rm $dbdump
