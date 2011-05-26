<?php 
mb_internal_encoding("UTF-8");
require_once( 'phpctdb/ctdb.php' );
$mbid = @$_GET['mbid'];
$mbmeta = phpCTDB::mblookup($mbid);
if (!$mbmeta) { header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found"); die(); } 
$json_releases = phpCTDB::musicbrainz2json($mbmeta);
echo $json_releases
?>
