#!/bin/sh
gunzip -c | sed 's/[\x01-\x08|\x0B|\x0C|\x0E-\x1F]//g' | php `dirname $0`/discogs.php | gzip > discogs_enums_sql.gz
(cat discogs/create_tables.sql ; gunzip -c discogs_*_sql.gz ; cat discogs/create_keys.sql) | bzip2 -1
