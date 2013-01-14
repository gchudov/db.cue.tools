dbdump="/tmp/$(basename $0).$$.tar.bz2"
wget -q http://ftp.musicbrainz.org/pub/musicbrainz/data/fullexport/`wget http://ftp.musicbrainz.org/pub/musicbrainz/data/fullexport/LATEST -O - -q`/mbdump-cdstubs.tar.bz2 -O $dbdump || exit $?
psql -U musicbrainz -d musicbrainz  -c "DELETE FROM cdtoc_raw;"
psql -U musicbrainz -d musicbrainz  -c "DELETE FROM release_raw;"
psql -U musicbrainz -d musicbrainz  -c "DELETE FROM track_raw;"
/root/mbslave/mbslave-import.py $dbdump
rm $dbdump
