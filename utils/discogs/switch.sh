service pgbouncer stop
psql -U postgres -c "DROP DATABASE discogs2"
psql -U postgres -c "ALTER DATABASE discogs RENAME TO discogs2"
psql -U postgres -c "ALTER DATABASE discogs1 RENAME TO discogs"
psql -U postgres -c "ALTER DATABASE discogs2 RENAME TO discogs1"
service pgbouncer start
