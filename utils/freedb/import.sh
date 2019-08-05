dbname=freedb1
dbmaster=postgres1
dbuser=freedb_user
dbhost=localhost
dbport=6544
psql -U postgres -h $dbhost -p $dbport -d $dbmaster -c "DROP DATABASE $dbname"
psql -U postgres -h $dbhost -p $dbport -d $dbmaster -c "CREATE DATABASE $dbname OWNER $dbuser"
psql -U postgres -h $dbhost -p $dbport -d $dbname -c "CREATE EXTENSION cube"
psql -U $dbuser -h $dbhost -p $dbport -d $dbname -f $(dirname $0)/create_tables.sql
for table in artist_names genre_names entries tracks ; do
  s3cmd --no-progress get s3://private.cuetools.net/freedb/`date +%Y%m`01/freedb_"$table".sql.bz2 - | nice -n 19 bunzip2 | psql -U $dbuser -h $dbhost -p $dbport -d $dbname
done
psql -U $dbuser -h $dbhost -p $dbport -d $dbname -f $(dirname $0)/create_keys.sql
psql -U postgres -h $dbhost -p $dbport -d $dbname -c "VACUUM"
