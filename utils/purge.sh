#!/bin/bash
ENTRIES=`psql -U ctdb_user -d ctdb -q -f \`dirname "$BASH_SOURCE"\`/purge.sql`
for ID in $ENTRIES ; do 
  echo $ID
  psql -U ctdb_user -d ctdb -q << EOF
    DELETE FROM submissions2 WHERE id=$ID;
EOF
#    s3cmd del "s3://p.cuetools.net/$ID"
done
