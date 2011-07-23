#!/bin/sh
gunzip -c | sed -e 's/[\x01-\x08|\x0B|\x0C|\x0E-\x1F]//g' -e 's/33 â[^,}]* RPM/33 ⅓ RPM/g' | php `dirname $0`/discogs.php | gzip > discogs_enums_sql.gz
(cat `dirname $0`/create_tables.sql ; gunzip -c discogs_*_sql.gz ; cat `dirname $0`/create_keys.sql) | bzip2 -2
