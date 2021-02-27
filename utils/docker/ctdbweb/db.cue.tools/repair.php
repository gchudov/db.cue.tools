<?php

$dbconn = pg_connect("dbname=ctdb user=ctdb_user host=pgbouncer port=6432")
	or die('Could not connect: ' . pg_last_error());

$id = $_GET['id'];

$result = pg_query_params($dbconn, "SELECT * FROM submissions2 WHERE id=$1 AND hasparity", array($id))
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
header("Location: " . sprintf("http://p.cuetools.net/%d", $id));
