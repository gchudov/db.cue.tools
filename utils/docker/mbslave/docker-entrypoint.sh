#!/usr/bin/env bash
set -Eeo pipefail
# TODO swap to -Eeuo pipefail above (after handling all potentially-unset variables)

# usage: file_env VAR [DEFAULT]
#    ie: file_env 'XYZ_DB_PASSWORD' 'example'
# (will allow for "$XYZ_DB_PASSWORD_FILE" to fill in the value of
#  "$XYZ_DB_PASSWORD" from a file, especially for Docker's secrets feature)
file_env() {
	local var="$1"
	local fileVar="${var}_FILE"
	local def="${2:-}"
	if [ "${!var:-}" ] && [ "${!fileVar:-}" ]; then
		echo >&2 "error: both $var and $fileVar are set (but are exclusive)"
		exit 1
	fi
	local val="$def"
	if [ "${!var:-}" ]; then
		val="${!var}"
	elif [ "${!fileVar:-}" ]; then
		val="$(< "${!fileVar}")"
	fi
	export "$var"="$val"
	unset "$fileVar"
}

if [ "${1:0:1}" = '-' ]; then
	set -- postgres "$@"
fi

cd /usr/local/lib/python2.7/dist-packages/mbdata

if [ "$( psql -h postgres12 -U postgres -tAc "SELECT 1 FROM pg_database WHERE datname='musicbrainz'" )" = '1' ]
then
    exec "$@"
fi

echo "Database does not exist"

psql -h postgres12 -U postgres postgres -c 'DROP ROLE IF EXISTS musicbrainz;'
psql -h postgres12 -U postgres postgres -c 'CREATE ROLE musicbrainz LOGIN;'
psql -h postgres12 -U postgres postgres -c 'CREATE DATABASE musicbrainz OWNER musicbrainz TEMPLATE "template0" LC_COLLATE "C" LC_CTYPE "C" ENCODING "UTF-8";'
psql -h postgres12 -U postgres musicbrainz -c 'CREATE EXTENSION cube;'
psql -h postgres12 -U postgres musicbrainz -c 'CREATE EXTENSION earthdistance;'

#: <<'END'

echo 'CREATE SCHEMA musicbrainz;' | mbslave psql -S
echo 'CREATE SCHEMA cover_art_archive;' | mbslave psql -S
#echo 'CREATE SCHEMA event_art_archive;' | mbslave psql -S

mbslave remap-schema <sql/CreateTables.sql | mbslave psql
mbslave remap-schema <sql/caa/CreateTables.sql | mbslave psql

mbdumps=/var/lib/mbdumps

if [ -e "$mbdumps/mbdump.tar.bz2" ]; then
    echo -e "${ylbold}\nFound the dump files.${endColor}"
else
    LATEST="$(wget -O - http://ftp.musicbrainz.org/pub/musicbrainz/data/fullexport/LATEST)"

    pushd $mbdumps
    wget -q http://ftp.musicbrainz.org/pub/musicbrainz/data/fullexport/$LATEST/mbdump-cdstubs.tar.bz2
    wget -q http://ftp.musicbrainz.org/pub/musicbrainz/data/fullexport/$LATEST/mbdump-cover-art-archive.tar.bz2
    wget -q http://ftp.musicbrainz.org/pub/musicbrainz/data/fullexport/$LATEST/mbdump-derived.tar.bz2
#    wget -q http://ftp.musicbrainz.org/pub/musicbrainz/data/fullexport/$LATEST/mbdump-editor.tar.bz2
#    wget -q http://ftp.musicbrainz.org/pub/musicbrainz/data/fullexport/$LATEST/mbdump-event-art-archive.tar.bz2
    wget -q http://ftp.musicbrainz.org/pub/musicbrainz/data/fullexport/$LATEST/mbdump.tar.bz2
    popd
fi

mbslave import $mbdumps/mbdump.tar.bz2 $mbdumps/mbdump-cdstubs.tar.bz2 \
  $mbdumps/mbdump-derived.tar.bz2 $mbdumps/mbdump-cover-art-archive.tar.bz2

rm $mbdumps/mbdump*.bz2

mbslave remap-schema <sql/CreatePrimaryKeys.sql | mbslave psql
mbslave remap-schema <sql/caa/CreatePrimaryKeys.sql | mbslave psql

mbslave remap-schema <sql/CreateIndexes.sql | grep -v musicbrainz_collate | mbslave psql
mbslave remap-schema <sql/CreateSlaveIndexes.sql | mbslave psql
mbslave remap-schema <sql/caa/CreateIndexes.sql | mbslave psql

mbslave remap-schema <sql/CreateViews.sql | mbslave psql
mbslave remap-schema <sql/CreateFunctions.sql | mbslave psql

echo 'VACUUM ANALYZE;' | mbslave psql

exec "$@"
