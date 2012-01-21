dbname=discogs1
dbuser=discogs
psql -U postgres -c "DROP DATABASE $dbname"
psql -U postgres -c "CREATE DATABASE $dbname"
psql -U postgres -c "ALTER DATABASE $dbname OWNER TO $dbuser"
s3cmd --no-progress get s3://private.cuetools.net/discogs/`date +%Y%m`01/discogs_enums_sql.gz - | gunzip | psql -U $dbuser -d $dbname
psql -U $dbuser -d $dbname -f $(dirname $0)/create_tables.sql
for table in artist_credit_name artist_credit artist_name image label release releases_formats releases_images releases_labels toc track track_title ; do 
  s3cmd --no-progress get s3://private.cuetools.net/discogs/`date +%Y%m`01/discogs_"$table"_sql.gz - | gunzip | psql -U $dbuser -d $dbname
done
psql -U $dbuser -d $dbname -f $(dirname $0)/create_keys.sql
psql -U postgres -d $dbname -f /usr/share/pgsql/contrib/cube.sql
psql -U $dbuser -d $dbname -f $(dirname $0)/create_cube.sql
psql -U postgres -d $dbname -c "VACUUM"
