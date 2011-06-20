<?php 
mb_internal_encoding("UTF-8");
require_once( 'phpctdb/ctdb.php' );
$toc_s = $_GET['toc'] or die('argument missing: toc');
$do_mbz = @$_GET['mbz'];
$do_fdb = @$_GET['fdb'];
$toc = phpCTDB::toc_s2toc($toc_s);
$meta_mbz = $do_mbz ? phpCTDB::mblookup(phpCTDB::toc2mbid($toc)) : array();
if (count($meta_mbz) > 0 && $do_fdb == 1) $do_fdb = 0;
$meta_fdb = $do_fdb ? phpCTDB::freedblookup($toc_s) : array();
if (count($meta_fdb) == 0 && $do_fdb == 1)
$meta_fdb = phpCTDB::freedblookup($toc_s, 300);
$meta = array_merge($meta_mbz, $meta_fdb);
$body = phpCTDB::musicbrainz2json($meta);
$etag = md5($body);
//header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*60*24) . ' GMT'); 
header("Expires:  " . gmdate('D, d M Y H:i:s', time() + 60*5) . ' GMT'); 
header("ETag:  " . $etag); 
if (@$_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
  header($_SERVER["SERVER_PROTOCOL"]." 304 Not Modified");
  exit;
}
echo $body;
