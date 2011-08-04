psql -U postgres -c "DROP DATABASE freedb1"
psql -U postgres -c "CREATE DATABASE freedb1"
psql -U postgres -c "ALTER DATABASE freedb1 OWNER TO freedb_user"
psql -U postgres -d freedb1 -f /usr/share/pgsql/contrib/cube.sql
psql -U freedb_user -d freedb1 -f create_tables.sql
bzcat /tmp/freedb_*sql.bz2 | psql -U freedb_user -d freedb1
psql -U freedb_user -d freedb1 -f create_keys.sql
psql -U postgres -d freedb1 -c "VACUUM"
