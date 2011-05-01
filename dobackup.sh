cd /var/www/html
tar --gzip --create --file="backups/ctdbdata-`date +%Y-%m-%d`.tgz" --listed-incremental=backups/ctdbdata.snar parity parity2
