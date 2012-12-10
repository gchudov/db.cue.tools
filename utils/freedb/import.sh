dbname=freedb1
dbuser=freedb_user
psql -U postgres -c "DROP DATABASE $dbname"
psql -U postgres -c "CREATE DATABASE $dbname"
psql -U postgres -c "ALTER DATABASE $dbname OWNER TO $dbuser"
#psql -U postgres -d $dbname -f /usr/share/pgsql/contrib/cube.sql
psql -U postgres -d $dbname -c "CREATE EXTENSION cube"
psql -U $dbuser -d $dbname -f $(dirname $0)/create_tables.sql
for table in artist_names genre_names entries tracks ; do
  s3cmd --no-progress get s3://private.cuetools.net/freedb/`date +%Y%m`01/freedb_"$table".sql.bz2 - | nice -n 19 bunzip2 | psql -U $dbuser -d $dbname
done
psql -U $dbuser -d $dbname -f $(dirname $0)/create_keys.sql
psql -U postgres -d $dbname -c "VACUUM"
