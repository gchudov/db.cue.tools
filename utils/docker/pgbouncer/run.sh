docker build . --tag pgbouncer:latest
docker run -d --name pgbouncer --network ct --restart always \
 -v /var/run/postgresql:/var/run/postgresql \
 -v /opt/db.cue.tools/utils/docker/pgbouncer/userlist.txt:/etc/pgbouncer/userlist.txt \
 -e 'DATABASES=ctwiki = host=postgres96,freedb = host=postgres96,discogs = host=postgres96,musicbrainz = host=postgres96,ctdb = host=postgres96' \
 -e 'PGBOUNCER_AUTH_TYPE=trust' \
 -e 'PGBOUNCER_AUTH_FILE= /etc/pgbouncer/userlist.txt' \
 -e 'PGBOUNCER_UNIX_SOCKET_DIR=/var/run/postgresql' \
 pgbouncer:latest
# -e 'PGBOUNCER_LISTEN_PORT=6544'
