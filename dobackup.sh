cd /var/www/ctdbweb
/usr/pgsql-9.0/bin/pg_dump -Fc ctdb -U ctdb_user > "backups/ctdbdump-`date +%s-%Y-%m-%d`.bin"
tar --gzip --create --file="backups/ctdbdata-`date +%s-%Y-%m-%d`.tgz" --listed-incremental=backups/ctdbdata.snar parity
