dbname=freedb1
dbmaster=postgres
dbuser=freedb_user
dbcont=postgres96
latest=`aws s3 cp --quiet s3://private.cuetools.net/freedb/LATEST -`
psql="docker exec -i $dbcont psql"
$psql -U postgres -d $dbmaster -c "DROP DATABASE $dbname"
$psql -U postgres -d $dbmaster -c "CREATE ROLE $dbuser LOGIN"
$psql -U postgres -d $dbmaster -c "CREATE DATABASE $dbname OWNER $dbuser"
$psql -U postgres -d $dbname -c "CREATE EXTENSION cube"
cat $(dirname $0)/create_tables.sql | $psql -U $dbuser -d $dbname
for table in artist_names genre_names entries tracks ; do
  aws s3 cp --quiet "s3://private.cuetools.net/freedb/$latest/freedb_"$table".sql.bz2" - | nice -n 19 bunzip2 | $psql -U $dbuser -d $dbname
done
cat $(dirname $0)/create_keys.sql | $psql -U $dbuser -d $dbname
$psql -U postgres -d $dbname -c "VACUUM"
