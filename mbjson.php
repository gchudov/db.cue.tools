<?php 
mb_internal_encoding("UTF-8");
require_once( 'phpctdb/ctdb.php' );
$mbid = @$_GET['mbid'];
$body = phpCTDB::musicbrainz2json(phpCTDB::mblookup($mbid));
$etag = md5($body);
if (@$_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
  header($_SERVER["SERVER_PROTOCOL"]." 304 Not Modified");
  exit;
}
header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*60*24) . ' GMT'); 
header("ETag:  " . $etag); 
echo $body;
?>
