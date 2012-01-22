dbname=discogs1
dbuser=postgres
psql -U postgres -c "DROP DATABASE $dbname"
psql -U postgres -c "CREATE DATABASE $dbname"
psql -U postgres -c "ALTER DATABASE $dbname OWNER TO $dbuser"
gunzip -c discogs_enums_sql.gz | psql -U $dbuser -d $dbname
psql -U $dbuser -d $dbname -f $(dirname $0)/create_tables.sql
for table in artist_credit_name artist_credit artist_name video label release releases_formats releases_images releases_videos releases_labels toc track track_title ; do 
  gunzip -c discogs_"$table"_sql.gz | psql -U $dbuser -d $dbname
done
psql -U $dbuser -d $dbname -f $(dirname $0)/create_keys.sql
psql -U postgres -d $dbname -f /usr/share/pgsql/contrib/cube.sql
psql -U $dbuser -d $dbname -f $(dirname $0)/create_cube.sql
pg_dump -Fc -U postgres $dbname > discogs.bin
