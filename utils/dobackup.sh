pg_dump -Fc ctdb -U ctdb_user > /tmp/ctdb.bin
s3cmd put /tmp/ctdb.bin "s3://private.cuetools.net/backups/ctdbdump-`date +%s-%Y-%m-%d`.bin"
rm /tmp/ctdb.bin
#tar --gzip --create --file="backups/ctdbdata-`date +%s-%Y-%m-%d`.tgz" --listed-incremental=backups/ctdbdata.snar parity
