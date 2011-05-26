<?php 
mb_internal_encoding("UTF-8");
require_once( 'phpctdb/ctdb.php' );
$mbid = @$_GET['mbid'];
$mbmeta = phpCTDB::mblookup($mbid);
if (!$mbmeta) { 
  header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found"); 
  die(); 
}
header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*60*24) . ' GMT'); 
$json_releases = phpCTDB::musicbrainz2json($mbmeta);
echo $json_releases
?>
