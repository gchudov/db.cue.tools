<?php

require_once( 'phpctdb/ctdb.php' );

$dbconn = pg_connect("dbname=ctdb user=ctdb_user port=6543")
	or die('Could not connect: ' . pg_last_error());

$id = $_GET['id'];

//echo $tocid . ':' . $crc32 . ':' . $trackoffsets;

$result = pg_query_params($dbconn, "SELECT parfile FROM submissions2 WHERE id=$1", array($id))
	or die('Query failed: ' . pg_last_error());
if (pg_num_rows($result) < 1) die('not found');
if (pg_num_rows($result) > 1) die('not unique');
$record = pg_fetch_array($result);
pg_free_result($result);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename='.basename($record['parfile']));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($record['parfile']));
ob_clean();
flush();
readfile($record['parfile']);
