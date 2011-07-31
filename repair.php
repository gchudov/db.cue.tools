<?php

#require_once( 'phpctdb/ctdb.php' );

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
	or die('Could not connect: ' . pg_last_error());

$id = $_GET['id'];

//echo $tocid . ':' . $crc32 . ':' . $trackoffsets;

$result = pg_query_params($dbconn, "SELECT s3, tocid, crc32 FROM submissions2 WHERE id=$1 AND hasparity", array($id))
	or die('Query failed: ' . pg_last_error());
if (pg_num_rows($result) < 1) 
{
  ob_clean();
  header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
  header("Status: 404 Not Found");
  die('Not Found');
}
if (pg_num_rows($result) > 1) die('not unique');
$record = pg_fetch_array($result);
pg_free_result($result);

ob_clean();
header($_SERVER["SERVER_PROTOCOL"]." 301 Moved permanently");
header("Location: " . sprintf("http://p.cuetools.net/%s%08x", str_replace('.','%2B',$record['tocid']), $record['crc32']));
