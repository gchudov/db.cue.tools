<?php 
mb_internal_encoding("UTF-8");
require_once( 'phpctdb/ctdb.php' );
$mbid = @$_GET['mbid'];
header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*60*24) . ' GMT'); 
echo phpCTDB::musicbrainz2json(phpCTDB::mblookup($mbid));
?>
