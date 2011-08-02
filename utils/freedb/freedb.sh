#nice -n 19 tar vxjOf *.gz 2>&1 | ./freedb | psql -q -U freedb_user -d freedb
