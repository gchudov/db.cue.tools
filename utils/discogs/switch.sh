dbcont=postgres96
psql="docker exec -i $dbcont psql"
docker stop pgbouncer
$psql -U postgres -c "DROP DATABASE discogs2"
$psql -U postgres -c "ALTER DATABASE discogs RENAME TO discogs2"
$psql -U postgres -c "ALTER DATABASE discogs1 RENAME TO discogs"
$psql -U postgres -c "ALTER DATABASE discogs2 RENAME TO discogs1"
docker start pgbouncer
