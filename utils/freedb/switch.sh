dbcont=postgres96
psql="docker exec -i $dbcont psql"
docker stop pgbouncer
$psql -U postgres -c "DROP DATABASE freedb2"
$psql -U postgres -c "ALTER DATABASE freedb RENAME TO freedb2"
$psql -U postgres -c "ALTER DATABASE freedb1 RENAME TO freedb"
$psql -U postgres -c "ALTER DATABASE freedb2 RENAME TO freedb1"
docker start pgbouncer
