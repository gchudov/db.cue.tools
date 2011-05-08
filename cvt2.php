<?php
include 'logo_start.php'; 
require_once( 'phpctdb/ctdb.php' );

$result = pg_query('SELECT * FROM submissions2')
	or die('Query failed: ' . pg_last_error());

printf("<center><h3>Recent additions:</h3>");
while(true == ($record = pg_fetch_array($result)))
{
	$tocid = phpCTDB::toc2tocid($record);
	$result2 = pg_query_params($dbconn, "UPDATE submissions2 SET tocid=$1 WHERE id=$2", array($tocid,  $record['id']));
	pg_free_result($result2);
}
pg_free_result($result);
printf("</center>");
?>
</body>
</html>
