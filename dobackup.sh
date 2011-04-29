tar --directory=/var/www/html --create --file="backups/ctdbdata-`date +%Y-%m-%d`.tar" --listed-incremental=backups/ctdbdata.snar parity parity2
