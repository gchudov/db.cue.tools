docker stop pgbouncer
docker exec -it postgres psql -U postgres -c "DROP DATABASE freedb2"
docker exec -it postgres psql -U postgres -c "ALTER DATABASE freedb RENAME TO freedb2"
docker exec -it postgres psql -U postgres -c "ALTER DATABASE freedb1 RENAME TO freedb"
docker start pgbouncer
