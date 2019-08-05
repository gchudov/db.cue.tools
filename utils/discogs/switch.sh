docker stop pgbouncer
docker exec -it postgres psql -U postgres -c "DROP DATABASE discogs2"
docker exec -it postgres psql -U postgres -c "ALTER DATABASE discogs RENAME TO discogs2"
docker exec -it postgres psql -U postgres -c "ALTER DATABASE discogs1 RENAME TO discogs"
docker exec -it postgres psql -U postgres -c "ALTER DATABASE discogs2 RENAME TO discogs1"
docker start pgbouncer
