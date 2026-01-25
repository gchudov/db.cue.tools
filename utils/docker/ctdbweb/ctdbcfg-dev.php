<?php
// Development configuration - read-only mode for safety
$ctdbcfg_musicbrainz_db = "dbname=musicbrainz user=musicbrainz host=pgbouncer port=6432";
$ctdbcfg_discogs_db = "dbname=discogs user=discogs host=pgbouncer port=6432";
$ctdbcfg_freedb_db = "dbname=freedb user=freedb_user host=pgbouncer port=6432";
$ctdbcfg_s3 = "https://s3.cue.tools";
$ctdbcfg_s3_id = "4";
$ctdbcfg_readonly = true;  // CRITICAL: Prevents dev from modifying production data
?>
