#!/bin/sh
gunzip -c | sed -e 's/[\x01-\x08|\x0B|\x0C|\x0E-\x1F]//g' -e 's/33 â[^,}]* RPM/33 ⅓ RPM/g' | php `dirname $0`/discogs.php | gzip > discogs_enums_sql.gz
