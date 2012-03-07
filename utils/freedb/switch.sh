service pgbouncer stop
psql -U postgres -c "DROP DATABASE freedb2"
psql -U postgres -c "ALTER DATABASE freedb RENAME TO freedb2"
psql -U postgres -c "ALTER DATABASE freedb1 RENAME TO freedb"
service pgbouncer start
