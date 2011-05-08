<?php
ini_set("memory_limit","120M");
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

$result = pg_query($dbconn, 'SELECT * FROM submissions') or die('Query failed: ' . pg_last_error());
printf("<center><h3>Recent additions:</h3>");
while(true == ($record = pg_fetch_array($result)))
{
	$path = phpCTDB::ctdbid2path($record['discid'], sprintf('%08x',$record['ctdbid']));
  $ctdb = new phpCTDB($path);
  $ctdb->ParseToc();
  $record1 = $ctdb->ctdb2pg($record['discid']);
	$result1 = pg_query_params($dbconn, 'UPDATE submissions SET fulltoc=$1 WHERE id=$2', array($ctdb->fulltoc, $record['id']));
  if (pg_affected_rows($result1) != 1) echo 'Not found!!<br>';
	pg_free_result($result1);
	echo $ctdb->fulltoc . '<br>';
  unset($ctdb);
}
pg_free_result($result);
printf("<h1>OK</h1>");
printf("</center>");
?>
</body>
</html>
